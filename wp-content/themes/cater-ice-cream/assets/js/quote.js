/**
 * Progressive enhancement for the shared CF7 enquiry forms (BUILD_SPECIFICATION §12, §13A, §17).
 * Without this script the whole form works on one page and the server validates everything.
 *
 * Quote form: three steps (Your event, Your treats, Review and send) with Back, browser history
 * (#step-n) and "earliest incomplete step" rules; per-step checks mirroring the server (the server
 * stays authoritative); a coverage pre-check that blocks confirmed outside areas and asks for the
 * missing venue city / event ZIP; Other occasion; "Date not set yet"; treats OR "Not sure yet";
 * phone required when Phone is preferred; an editable "at a glance" summary.
 *
 * Both forms: a fresh submission key per request (retries reuse it, so the server stores one
 * enquiry), double-submit protection, network-failure recovery (CF7 otherwise stays "submitting"),
 * an error summary that opens the right step and focuses it (including hidden-field errors), and
 * the confirmation state shown only after the server confirmed one saved enquiry.
 *
 * Analytics: pushed to window.dataLayer only if an approved analytics install defines it, with
 * event keys, treat keys, step numbers, field names and coverage status. Never names, emails,
 * phones, addresses, free text or dietary details. quote_submitted fires once per receipt.
 */
( function () {
	'use strict';
	var cfg = window.cicQuote || { messages: {} };
	var M = cfg.messages;
	var ctx = window.cicQuoteContext;
	var roots = document.querySelectorAll( '[data-cic-form]' );
	if ( ! roots.length ) {
		return;
	}
	document.documentElement.classList.add( 'cic-js' );

	var track = function ( name, params ) {
		var payload = Object.assign( { event: name }, params || {} );
		if ( Array.isArray( window.dataLayer ) ) {
			window.dataLayer.push( payload );
		}
		document.dispatchEvent( new window.CustomEvent( 'cic:analytics', { detail: payload } ) );
	};
	var newKey = function () {
		var bytes = new Uint8Array( 24 );
		window.crypto.getRandomValues( bytes );
		return btoa( String.fromCharCode.apply( null, bytes ) ).replace( /\+/g, '-' ).replace( /\//g, '_' ).replace( /=+$/, '' );
	};
	var fired = function ( receipt ) {
		try {
			var list = JSON.parse( window.sessionStorage.getItem( 'cic_fired' ) ) || [];
			if ( list.indexOf( receipt ) > -1 ) {
				return true;
			}
			list.push( receipt );
			window.sessionStorage.setItem( 'cic_fired', JSON.stringify( list.slice( -20 ) ) );
		} catch ( e ) {}
		return false;
	};
	// User-facing date MM/DD/YYYY, rearranged from the canonical Y-m-d text (no Date object, so no
	// timezone can shift the day).
	var dateLabel = function ( ymd ) {
		var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec( ymd || '' );
		return m ? m[ 2 ] + '/' + m[ 3 ] + '/' + m[ 1 ] : '';
	};
	var syncPicker = function ( input ) {
		if ( input && window.cicDatepicker ) {
			window.cicDatepicker.sync( input );
		}
	};

	Array.prototype.forEach.call( roots, function ( root ) {
		var form = root.closest( 'form' );
		if ( ! form ) {
			return;
		}
		var type = root.getAttribute( 'data-cic-form' );
		var quote = 'quote' === type;
		var area = form.closest( '[data-cic-form-area]' );
		var page = area ? area.parentNode : document;
		var received = page.querySelector( '[data-cic-received]' );
		var summaryBox = root.querySelector( '[data-cic-errors]' );
		var keyInput = form.querySelector( '[data-cic-key]' );
		var tsInput = form.querySelector( '[data-cic-ts]' );
		var sourceInput = form.querySelector( '[data-cic-source]' );
		var submitBtn = form.querySelector( '.cic-button--send' );
		var steps = quote ? Array.prototype.slice.call( root.querySelectorAll( '.cic-qf__step' ) ) : [];
		var current = 1;
		var busy = false;
		var watchdog = null;
		if ( keyInput ) {
			keyInput.value = newKey();
		}
		var el = function ( name ) {
			return form.querySelector( '[name="' + name + '"]' );
		};
		var val = function ( name ) {
			var x = el( name );
			return x ? x.value.trim() : '';
		};
		var checked = function ( name ) {
			return Array.prototype.filter.call( form.querySelectorAll( '[name="' + name + '"]' ), function ( i ) { return i.checked; } );
		};
		var wrapOf = function ( name ) {
			return form.querySelector( '.wpcf7-form-control-wrap[data-name="' + name + '"]' );
		};
		var focusTarget = function ( name ) {
			var w = wrapOf( name );
			return w ? w.querySelector( 'input:not([type=hidden]),select,textarea' ) : null;
		};
		var labelOf = function ( name ) {
			var w = wrapOf( name );
			var field = w ? w.closest( '.cic-field' ) : null;
			var l = field ? field.querySelector( '.cic-field__label' ) : null;
			return l ? l.textContent.replace( /\*/g, '' ).replace( /\(.*?\)/g, '' ).trim() : '';
		};

		/* ---------------- Errors ---------------- */
		var clearError = function ( name ) {
			var w = wrapOf( name );
			if ( ! w ) {
				return;
			}
			Array.prototype.forEach.call( w.querySelectorAll( '.wpcf7-not-valid-tip, .cic-tip' ), function ( t ) { t.remove(); } );
			Array.prototype.forEach.call( w.querySelectorAll( '.wpcf7-form-control' ), function ( c ) {
				c.classList.remove( 'wpcf7-not-valid' );
				c.setAttribute( 'aria-invalid', 'false' );
				var d = ( c.getAttribute( 'aria-describedby' ) || '' ).split( ' ' ).filter( function ( x ) { return x && x.indexOf( '-tip' ) === -1; } );
				d.length ? c.setAttribute( 'aria-describedby', d.join( ' ' ) ) : c.removeAttribute( 'aria-describedby' );
			} );
		};
		var setError = function ( name, message ) {
			var w = wrapOf( name );
			if ( ! w ) {
				return;
			}
			clearError( name );
			var tip = document.createElement( 'span' );
			tip.className = 'wpcf7-not-valid-tip cic-tip';
			tip.id = 'cic-tip-' + name;
			tip.textContent = message;
			w.appendChild( tip );
			Array.prototype.forEach.call( w.querySelectorAll( '.wpcf7-form-control' ), function ( c ) {
				c.classList.add( 'wpcf7-not-valid' );
				c.setAttribute( 'aria-invalid', 'true' );
				c.setAttribute( 'aria-describedby', ( ( c.getAttribute( 'aria-describedby' ) || '' ) + ' ' + tip.id ).trim() );
			} );
		};
		var showSummary = function ( errors ) {
			if ( ! summaryBox ) {
				return;
			}
			summaryBox.textContent = '';
			if ( ! errors.length ) {
				summaryBox.hidden = true;
				return;
			}
			var p = document.createElement( 'p' );
			p.textContent = M.summary;
			var ul = document.createElement( 'ul' );
			errors.forEach( function ( e ) {
				var li = document.createElement( 'li' );
				var target = focusTarget( e.field );
				var text = ( labelOf( e.field ) ? labelOf( e.field ) + ': ' : '' ) + e.message;
				if ( target && target.id ) {
					var a = document.createElement( 'a' );
					a.href = '#' + target.id;
					a.textContent = text;
					a.addEventListener( 'click', function ( ev ) {
						ev.preventDefault();
						openFor( e.field );
						target.focus();
					} );
					li.appendChild( a );
				} else {
					li.textContent = text;
				}
				ul.appendChild( li );
			} );
			summaryBox.appendChild( p );
			summaryBox.appendChild( ul );
			summaryBox.hidden = false;
		};
		var stepOf = function ( name ) {
			var w = wrapOf( name );
			var s = w ? w.closest( '.cic-qf__step' ) : null;
			return s ? +s.getAttribute( 'data-step' ) : current;
		};
		var openFor = function ( name ) {
			if ( quote && 'cic_guard' !== name ) {
				show( stepOf( name ), false );
			}
			if ( 'location_detail' === name ) {
				revealDetail( true );
			}
		};

		/* ---------------- Field behaviour ---------------- */
		var otherWrap = root.querySelector( '[data-cic-other]' );
		var detailWrap = root.querySelector( '[data-cic-detail]' );
		var coverageBox = root.querySelector( '[data-cic-coverage]' );
		var syncOther = function () {
			if ( otherWrap ) {
				var on = checked( 'event' ).some( function ( i ) { return 'other' === i.value; } );
				otherWrap.hidden = ! on;
			}
		};
		var revealDetail = function ( on, kind ) {
			if ( ! detailWrap ) {
				return;
			}
			detailWrap.hidden = ! on;
			if ( kind ) {
				var l = detailWrap.querySelector( '.cic-field__label' );
				if ( l ) {
					l.firstChild.textContent = 'zip' === kind ? M.needZip : M.needCity;
				}
				var input = el( 'location_detail' );
				if ( input ) {
					input.setAttribute( 'inputmode', 'zip' === kind ? 'numeric' : 'text' );
					input.placeholder = 'zip' === kind ? '91101' : 'Pasadena';
				}
			}
		};
		var syncDate = function () {
			var box = el( 'date_unknown' );
			var date = el( 'event_date' );
			if ( box && date ) {
				date.disabled = box.checked;
				if ( box.checked ) {
					date.value = '';
					clearError( 'event_date' );
				}
				syncPicker( date );
			}
		};
		var syncPhone = function () {
			var phone = el( 'phone' );
			if ( ! phone ) {
				return;
			}
			var need = checked( 'contact_pref' ).some( function ( i ) { return 'phone' === i.value; } );
			phone.setAttribute( 'aria-required', need ? 'true' : 'false' );
			var opt = phone.closest( '.cic-field' ).querySelector( '.cic-opt' );
			if ( opt ) {
				opt.hidden = need;
			}
		};
		form.addEventListener( 'change', function ( e ) {
			var t = e.target;
			if ( t.matches( '[data-cic-unsure]' ) && t.checked ) {
				form.querySelectorAll( '[data-cic-treat]' ).forEach( function ( c ) { c.checked = false; } );
			}
			if ( t.matches( '[data-cic-treat]' ) && t.checked ) {
				var u = form.querySelector( '[data-cic-unsure]' );
				if ( u ) {
					u.checked = false;
				}
			}
			if ( t.matches( '[data-cic-treat],[data-cic-unsure]' ) ) {
				clearError( 'treats' );
			}
			if ( 'event' === t.name ) {
				syncOther();
				clearError( 'event' );
			}
			if ( 'date_unknown' === t.name ) {
				syncDate();
			}
			if ( 'contact_pref' === t.name ) {
				syncPhone();
			}
			if ( 'location' === t.name || 'location_detail' === t.name ) {
				if ( coverageBox ) {
					coverageBox.hidden = true;
				}
			}
		} );
		form.addEventListener( 'input', function ( e ) {
			if ( e.target.name ) {
				clearError( e.target.name.replace( '[]', '' ) );
			}
		} );

		/* ---------------- Client checks (mirror the server) ---------------- */
		var rules = {
			1: function () {
				var errs = [];
				if ( ! checked( 'event' ).length ) {
					errs.push( { field: 'event', message: M.event } );
				}
				var unknown = el( 'date_unknown' ) && el( 'date_unknown' ).checked;
				var d = val( 'event_date' );
				if ( ! unknown ) {
					if ( ! d ) {
						errs.push( { field: 'event_date', message: M.date } );
					} else if ( ! ctx || ! ctx.validators.date( d ) ) {
						errs.push( { field: 'event_date', message: M.dateRange } );
					}
				}
				var g = val( 'guests' );
				if ( ! /^\d{1,5}$/.test( g ) || +g < 1 || +g > 10000 ) {
					errs.push( { field: 'guests', message: M.guests } );
				}
				if ( ! ctx || ! ctx.validators.location( val( 'location' ) ) ) {
					errs.push( { field: 'location', message: M.location } );
				}
				return errs;
			},
			2: function () {
				return ( checked( 'treats[]' ).length || checked( 'treats_unsure' ).length ) ? [] : [ { field: 'treats', message: M.treats } ];
			},
			3: function () {
				var errs = [];
				if ( ! val( 'your_name' ) ) {
					errs.push( { field: 'your_name', message: M.required } );
				}
				if ( ! /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test( val( 'your_email' ) ) ) {
					errs.push( { field: 'your_email', message: M.email } );
				}
				var pref = checked( 'contact_pref' )[ 0 ];
				if ( pref && 'phone' === pref.value && val( 'phone' ).replace( /\D/g, '' ).length < 10 ) {
					errs.push( { field: 'phone', message: M.phone } );
				}
				return errs;
			},
			contact: function () {
				var errs = [];
				if ( ! val( 'topic' ) ) {
					errs.push( { field: 'topic', message: M.required } );
				}
				if ( ! val( 'your_name' ) ) {
					errs.push( { field: 'your_name', message: M.required } );
				}
				if ( ! /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test( val( 'your_email' ) ) ) {
					errs.push( { field: 'your_email', message: M.email } );
				}
				if ( ! val( 'message' ) ) {
					errs.push( { field: 'message', message: M.required } );
				}
				return errs;
			}
		};
		var report = function ( errs, focus ) {
			errs.forEach( function ( e ) { setError( e.field, e.message ); } );
			showSummary( errs );
			if ( errs.length ) {
				track( 'quote_validation_error', { form: type, fields: errs.map( function ( e ) { return e.field; } ).join( ',' ) } );
				if ( focus ) {
					openFor( errs[ 0 ].field );
					var t = focusTarget( errs[ 0 ].field );
					( summaryBox && ! summaryBox.hidden ? summaryBox : t ).focus();
				}
			}
			return ! errs.length;
		};

		/* ---------------- Steps ---------------- */
		var progress = root.querySelector( '.cic-qf__progress' );
		var show = function ( n, push ) {
			if ( ! quote ) {
				return;
			}
			current = Math.max( 1, Math.min( 3, n ) );
			steps.forEach( function ( s ) {
				s.hidden = +s.getAttribute( 'data-step' ) !== current;
			} );
			if ( progress ) {
				progress.querySelectorAll( 'li' ).forEach( function ( li ) {
					var k = +li.getAttribute( 'data-step' );
					li.classList.toggle( 'is-done', k < current );
					li.classList.toggle( 'is-current', k === current );
					var b = li.querySelector( 'button' );
					k === current ? b.setAttribute( 'aria-current', 'step' ) : b.removeAttribute( 'aria-current' );
				} );
			}
			if ( 3 === current ) {
				fillGlance();
			}
			if ( push ) {
				window.history.pushState( { cicStep: current }, '', '#step-' + current );
			}
			track( 'quote_step_view', { step: current } );
		};
		var focusStep = function () {
			var t = steps[ current - 1 ] && steps[ current - 1 ].querySelector( '.cic-qf__title' );
			if ( t ) {
				t.setAttribute( 'tabindex', '-1' );
				t.focus();
			}
		};
		var earliestIncomplete = function ( upto ) {
			for ( var i = 1; i < upto; i++ ) {
				if ( rules[ i ]().length ) {
					return i;
				}
			}
			return upto;
		};
		var fillGlance = function () {
			var set = function ( key, text ) {
				var row = root.querySelector( '[data-cic-glance="' + key + '"] [data-cic-glance-value]' );
				if ( row ) {
					row.textContent = text || '—';
				}
			};
			var ev = checked( 'event' )[ 0 ];
			var evText = ev ? ev.closest( 'label' ).querySelector( '.cic-tile__label' ).textContent : '';
			if ( ev && 'other' === ev.value ) {
				evText = val( 'event_other' ) || M.other;
			}
			set( 'event', evText );
			var treats = checked( 'treats[]' ).map( function ( c ) { return c.closest( 'label' ).querySelector( '.cic-treat__label' ).textContent; } );
			set( 'treats', checked( 'treats_unsure' ).length ? M.unsureTip : treats.join( ', ' ) );
			set( 'location', [ val( 'location' ), val( 'location_detail' ) ].filter( Boolean ).join( ' · ' ) );
			set( 'date', el( 'date_unknown' ) && el( 'date_unknown' ).checked ? M.tbc : dateLabel( val( 'event_date' ) ) );
			set( 'guests', val( 'guests' ) ? M.guestsFmt.replace( '%d', val( 'guests' ) ) : '' );
		};
		// Step 1 answers are explicit choices: once validated they join the session context (with the
		// visible location label), so reload and Back/Forward show them again.
		var remember = function ( data ) {
			if ( ! ctx ) {
				return;
			}
			var loc = val( 'location' );
			var key = ( data && data.key && ! /^\d{5}$/.test( ctx.validators.location( loc ) ) && ! val( 'location_detail' ) ) ? data.key : ctx.validators.location( loc );
			var ev = checked( 'event' )[ 0 ];
			var unknown = el( 'date_unknown' ) && el( 'date_unknown' ).checked;
			var sel = { location: key, guests: val( 'guests' ) };
			if ( ev ) {
				sel.event = ev.value;
			}
			if ( ! unknown && val( 'event_date' ) ) {
				sel.date = val( 'event_date' );
			}
			var now = ctx.merge( sel );
			// Keep the address bar in step with the validated choices (keys only), so a reload or
			// Back/Forward shows these selections rather than the original link's.
			try {
				var u = new URL( ctx.url( now ) );
				u.hash = window.location.hash;
				window.history.replaceState( window.history.state, '', u.toString() );
			} catch ( e ) {}
			if ( key ) {
				ctx.setLabel( key, data && data.key === key && data.label && ! /^\d{5}$/.test( key ) ? data.label : loc );
				var input = el( 'location' );
				if ( input ) {
					ctx.markLocation( input, key, loc );
				}
			}
		};
		var coverageCheck = function () {
			var loc = val( 'location' );
			var key = ctx ? ctx.validators.location( loc ) : '';
			var detail = val( 'location_detail' );
			if ( ! ctx || ! key ) {
				return Promise.resolve( true );
			}
			var isZip = /^\d{5}$/.test( key );
			var req = isZip ? ctx.check( loc, detail ) : ( detail ? ctx.check( detail, loc ) : ctx.check( loc ) );
			return req.then( function ( data ) {
				if ( ! data ) {
					return true; // Offline: the server checks again on submission.
				}
				track( 'coverage_result', { coverage_status: data.status } );
				if ( coverageBox ) {
					coverageBox.textContent = data.message + ( data.fixture && window.cicContext && window.cicContext.messages ? ' ' + window.cicContext.messages.fixture : '' );
					coverageBox.setAttribute( 'data-status', data.status );
					coverageBox.hidden = false;
				}
				if ( 'block' === data.action || 'correct' === data.action ) {
					report( [ { field: 'location', message: data.message } ], true );
					return false;
				}
				if ( 'clarify' === data.action && ! detail ) {
					revealDetail( true, isZip ? 'city' : 'zip' );
					report( [ { field: 'location_detail', message: data.message } ], true );
					return false;
				}
				remember( data );
				return true;
			} );
		};
		var next = function () {
			var errs = rules[ current ]();
			if ( ! report( errs, true ) ) {
				return;
			}
			var proceed = function () {
				showSummary( [] );
				track( 'quote_step_completed', { step: current } );
				show( current + 1, true );
				focusStep();
			};
			if ( 1 === current ) {
				var btn = steps[ 0 ].querySelector( '[data-cic-next]' );
				btn.disabled = true;
				coverageCheck().then( function ( ok ) {
					btn.disabled = false;
					if ( ok ) {
						proceed();
					}
				} );
				return;
			}
			proceed();
		};
		if ( quote ) {
			root.classList.add( 'cic-qf--enhanced' );
			root.addEventListener( 'click', function ( e ) {
				var t = e.target.closest( '[data-cic-next],[data-cic-back],[data-cic-goto]' );
				if ( ! t ) {
					return;
				}
				e.preventDefault();
				if ( t.hasAttribute( 'data-cic-next' ) ) {
					next();
				} else if ( t.hasAttribute( 'data-cic-back' ) ) {
					show( current - 1, true );
					focusStep();
				} else {
					var want = +t.getAttribute( 'data-cic-goto' );
					show( want > current ? earliestIncomplete( want ) : want, true );
					focusStep();
				}
			} );
			// Enter in a single-line field moves on instead of submitting from steps 1 and 2.
			root.addEventListener( 'keydown', function ( e ) {
				if ( 'Enter' === e.key && current < 3 && e.target.matches( 'input:not([type=checkbox]):not([type=radio])' ) && ! e.target.getAttribute( 'aria-activedescendant' ) ) {
					e.preventDefault();
					next();
				}
			} );
			window.addEventListener( 'popstate', function ( e ) {
				var n = e.state && e.state.cicStep ? e.state.cicStep : ( /^#step-([123])$/.exec( window.location.hash ) || [ 0, 1 ] )[ 1 ];
				show( earliestIncomplete( +n ), false );
			} );

			// Selections stored in this session fill fields that are still empty.
			var stored = ctx ? ctx.read() : {};
			if ( stored.event && ! checked( 'event' ).length ) {
				var r = form.querySelector( 'input[name="event"][value="' + stored.event + '"]' );
				if ( r ) {
					r.checked = true;
				}
			}
			[ [ 'event_date', 'date' ], [ 'guests', 'guests' ] ].forEach( function ( p ) {
				var x = el( p[ 0 ] );
				if ( x && ! x.value && stored[ p[ 1 ] ] ) {
					x.value = stored[ p[ 1 ] ];
				}
			} );
			// Location: the visible label for the stored key (the server prefill already shows a label).
			var locInput = el( 'location' );
			if ( locInput && stored.location ) {
				if ( ! locInput.value ) {
					ctx.fillLocation( locInput, stored.location );
				} else if ( locInput.value === ctx.labelFor( stored.location ) ) {
					ctx.markLocation( locInput, stored.location, locInput.value );
				}
			}
			if ( stored.treat && ! checked( 'treats[]' ).length && ! checked( 'treats_unsure' ).length ) {
				stored.treat.split( ',' ).forEach( function ( k ) {
					var c = form.querySelector( 'input[name="treats[]"][value="' + k + '"]' );
					if ( c ) {
						c.checked = true;
					}
				} );
			}
			var hasDetailError = detailWrap && detailWrap.querySelector( '.wpcf7-not-valid' );
			revealDetail( !! ( val( 'location_detail' ) || hasDetailError ) );
			syncOther();
			syncDate();
			syncPhone();
			var serverError = root.querySelector( '.wpcf7-not-valid' );
			var hash = /^#step-([123])$/.exec( window.location.hash );
			var start = serverError ? stepOf( serverError.closest( '.wpcf7-form-control-wrap' ).getAttribute( 'data-name' ) ) : earliestIncomplete( hash ? +hash[ 1 ] : 1 );
			show( start, false );
			window.history.replaceState( { cicStep: current }, '', window.location.pathname + window.location.search + ( current > 1 ? '#step-' + current : '' ) );
		}

		/* ---------------- Submission ---------------- */
		var setBusy = function ( on ) {
			busy = on;
			form.setAttribute( 'aria-busy', on ? 'true' : 'false' );
			if ( submitBtn ) {
				submitBtn.disabled = on;
				if ( on ) {
					submitBtn.setAttribute( 'data-label', submitBtn.innerHTML );
					submitBtn.textContent = M.sending;
				} else if ( submitBtn.getAttribute( 'data-label' ) ) {
					submitBtn.innerHTML = submitBtn.getAttribute( 'data-label' );
				}
			}
			window.clearTimeout( watchdog );
		};
		var recover = function () {
			// Network failure: CF7 would stay "submitting" and refuse a retry. Answers and the
			// submission key are kept, so pressing Send again cannot create a second enquiry.
			if ( form.wpcf7 ) {
				form.wpcf7.status = 'init';
			}
			form.setAttribute( 'data-status', 'init' );
			form.classList.remove( 'submitting' );
			setBusy( false );
			showSummary( [ { field: 'cic_guard', message: M.network } ] );
			var out = form.querySelector( '.wpcf7-response-output' );
			if ( out ) {
				out.textContent = M.network;
			}
			if ( summaryBox ) {
				summaryBox.focus();
			}
		};
		document.addEventListener( 'submit', function ( e ) {
			if ( e.target !== form ) {
				return;
			}
			if ( busy ) {
				e.preventDefault();
				e.stopImmediatePropagation();
				return;
			}
			var errs = quote ? rules[ 1 ]().concat( rules[ 2 ](), rules[ 3 ]() ) : rules.contact();
			if ( errs.length ) {
				e.preventDefault();
				e.stopImmediatePropagation();
				report( errs, true );
				return;
			}
			if ( sourceInput && ctx ) {
				var src = ctx.source();
				if ( ! src.first ) {
					ctx.recordSource();
					src = ctx.source();
				}
				sourceInput.value = JSON.stringify( src );
			}
			showSummary( [] );
			setBusy( true );
			watchdog = window.setTimeout( recover, 30000 );
		}, true );
		window.addEventListener( 'unhandledrejection', function () {
			if ( busy ) {
				recover();
			}
		} );
		form.addEventListener( 'wpcf7submit', function ( e ) {
			var r = ( e.detail && e.detail.apiResponse ) || {};
			var c = r.cic || {};
			setBusy( false );
			if ( c.token && tsInput ) {
				tsInput.value = c.token;
			}
			if ( c.accepted ) {
				if ( c.key && keyInput ) {
					keyInput.value = c.key;
				}
				showConfirmation( c );
				return;
			}
			if ( c.pending ) {
				showSummary( [ { field: 'cic_guard', message: M.waiting } ] );
				return;
			}
			var errs = ( r.invalid_fields || [] ).map( function ( f ) { return { field: f.field, message: f.message }; } );
			if ( errs.length ) {
				report( errs, true );
			} else if ( r.message && 'mail_sent' !== r.status ) {
				showSummary( [ { field: 'cic_guard', message: r.message } ] );
				if ( summaryBox ) {
					summaryBox.focus();
				}
			}
		} );

		var showConfirmation = function ( c ) {
			var s = c.summary || {};
			if ( received ) {
				[ [ 'event', s.event ], [ 'location', s.location ], [ 'treats', ( s.treats || [] ).join( ', ' ) ] ].forEach( function ( p ) {
					var li = received.querySelector( '[data-chip="' + p[ 0 ] + '"]' );
					if ( li ) {
						li.querySelector( '[data-chip-value]' ).textContent = p[ 1 ] || '';
						li.hidden = ! p[ 1 ];
					}
				} );
				received.hidden = false;
			}
			if ( area ) {
				area.hidden = true;
			}
			if ( c.receipt ) {
				var u = new URL( window.location.href );
				u.hash = '';
				Array.from( u.searchParams.keys() ).forEach( function ( k ) { u.searchParams.delete( k ); } );
				u.searchParams.set( quote ? 'received' : 'sent', c.receipt );
				window.history.replaceState( {}, '', u.toString() + '#confirmation' );
			}
			if ( quote && c.receipt && ! c.duplicate && ! fired( c.receipt ) ) {
				var keys = s.keys || {};
				track( 'quote_submitted', { event_key: keys.event || '', treat_keys: keys.treat || '', coverage_status: keys.cov || '', template: ( ( ctx && ctx.source().latest ) || {} ).type || '' } );
			}
			if ( quote && ctx ) {
				ctx.clear();
			}
			if ( received ) {
				received.focus();
			}
		};
	} );
}() );
