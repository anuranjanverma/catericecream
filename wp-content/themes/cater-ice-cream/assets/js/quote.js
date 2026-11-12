/**
 * Progressive enhancement for the shared CF7 enquiry forms (BUILD_SPECIFICATION §12, §13A, §17).
 * Without this script the whole form works on one page and the server validates everything.
 *
 * Quote form (2026-10-23): two steps (Your details & favorites, Your event) with Back, browser
 * history (#step-n), per-step checks mirroring the server (the server stays authoritative), a
 * coverage pre-check before sending that blocks confirmed outside areas and asks for the missing
 * venue city / event ZIP; Other occasion; "Date not set yet"; treats OR "Not sure yet"; phone
 * required when Phone is preferred.
 *
 * Saved progress (CIC\Enquiries\Drafts): nothing is sent while the visitor types Step 1. Continue
 * sends the Step 1 values once; the server creates the draft only if they are valid. After that,
 * changes are autosaved to the same draft (text fields when they lose focus, selections shortly
 * after a change), one request at a time, each a full snapshot with a strictly increasing revision
 * so an older request can never win. No saving messages appear during the steps; only a failure
 * that blocks Continue shows an error with a retry. Access uses an HttpOnly cookie set by the
 * server; the Step 1 client key stays in this page's memory (never in storage or URLs) and goes
 * with the final CF7 submission so the server completes the same inquiry.
 *
 * After the confirmed submission: "Your event at a glance" and optional details (notes, theme,
 * dietary) autosaved to the same inquiry with discreet saving / saved / retry feedback.
 *
 * Both forms: a fresh submission key per request (retries reuse it, so the server stores one
 * enquiry), double-submit protection, network-failure recovery (CF7 otherwise stays "submitting"),
 * an error summary that opens the right step and focuses it (including hidden-field errors), and
 * the confirmation state shown only after the server confirmed one saved enquiry.
 *
 * Analytics: pushed to window.dataLayer only if an approved analytics install defines it, with
 * event keys, treat keys, step numbers, field names and coverage status. Never names, emails,
 * phones, addresses, free text, dietary details or draft keys. quote_submitted fires once per receipt.
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
	// Strictly increasing revision (microseconds since the page's time origin, never repeating).
	var lastRev = 0;
	var nextRev = function () {
		var now = Math.floor( ( window.performance.timeOrigin + window.performance.now() ) * 1000 );
		lastRev = Math.max( lastRev + 1, now );
		return String( lastRev );
	};
	// JSON POST to a draft route. Resolves { status, body }; status 0 = network failure/timeout.
	var post = function ( url, data ) {
		var ctrl = 'AbortController' in window ? new window.AbortController() : null;
		var timer = ctrl ? window.setTimeout( function () { ctrl.abort(); }, 20000 ) : null;
		return window.fetch( url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-CIC-Draft': '1' },
			body: JSON.stringify( data ),
			signal: ctrl ? ctrl.signal : undefined,
		} ).then( function ( r ) {
			return r.json().catch( function () { return {}; } ).then( function ( body ) {
				return { status: r.status, body: body };
			} );
		}, function () {
			return { status: 0, body: {} };
		} ).then( function ( res ) {
			window.clearTimeout( timer );
			return res;
		} );
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
		var draftInput = form.querySelector( '[data-cic-draft-key]' );
		var submitBtn = form.querySelector( '.cic-button--send' );
		var steps = quote ? Array.prototype.slice.call( root.querySelectorAll( '.cic-qf__step' ) ) : [];
		var STEPS = 2;
		var current = 1;
		var busy = false;
		var watchdog = null;
		var draftKey = quote ? newKey() : '';
		var draftReady = false;
		var submitting = false;
		var coverageOk = false;
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
		// Treats: one or more specific treats OR "Not sure yet", never both and never neither. "Not
		// sure yet" is the default only while no valid treat is chosen (a fresh form, or after the
		// last treat is removed); incoming treat preselection and restored choices are kept. This is
		// page state only: nothing is sent until Step 1 Continue.
		var ensureTreatChoice = function () {
			var u = form.querySelector( '[data-cic-unsure]' );
			if ( u && ! u.checked && ! form.querySelector( '[data-cic-treat]:checked' ) ) {
				u.checked = true;
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
			if ( t.matches( '[data-cic-treat],[data-cic-unsure]' ) && ! t.checked ) {
				ensureTreatChoice();
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
				coverageOk = false;
				if ( coverageBox ) {
					coverageBox.hidden = true;
				}
			}
		} );
		form.addEventListener( 'input', function ( e ) {
			if ( e.target.name ) {
				clearError( e.target.name.replace( '[]', '' ) );
			}
			if ( 'location' === e.target.name || 'location_detail' === e.target.name ) {
				coverageOk = false;
			}
		} );

		/* ---------------- Client checks (mirror the server) ---------------- */
		var contactErrors = function () {
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
		};
		var rules = {
			1: function () {
				var errs = contactErrors();
				if ( ! checked( 'treats[]' ).length && ! checked( 'treats_unsure' ).length ) {
					errs.push( { field: 'treats', message: M.treats } );
				}
				return errs;
			},
			2: function () {
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

		/* ---------------- Saved progress (drafts) ---------------- */
		// Snapshot of every field the draft may hold (the server allowlists and limits them again).
		var snapshot = function () {
			var one = function ( name ) {
				var c = checked( name )[ 0 ];
				return c ? c.value : '';
			};
			var flag = function ( name ) {
				var x = el( name );
				return x && x.checked ? '1' : '';
			};
			return {
				your_name: val( 'your_name' ),
				your_email: val( 'your_email' ),
				phone: val( 'phone' ),
				contact_pref: one( 'contact_pref' ),
				treats: checked( 'treats[]' ).map( function ( c ) { return c.value; } ),
				treats_unsure: flag( 'treats_unsure' ),
				event: one( 'event' ),
				event_other: val( 'event_other' ),
				event_date: val( 'event_date' ),
				date_unknown: flag( 'date_unknown' ),
				guests: val( 'guests' ),
				location: val( 'location' ),
				location_detail: val( 'location_detail' ),
				venue: val( 'venue' ),
				serve_time: val( 'serve_time' ),
				serve_duration: val( 'serve_duration' ),
			};
		};
		var lastSent = '';
		var inFlight = false;
		var pending = false;
		var saveTimer = null;
		var failures = 0;
		// One request at a time; changes made meanwhile are sent next, as one newer snapshot.
		var autosave = function () {
			window.clearTimeout( saveTimer );
			if ( ! draftReady || submitting ) {
				return;
			}
			var snap = snapshot();
			var json = JSON.stringify( snap );
			if ( json === lastSent ) {
				return;
			}
			if ( inFlight ) {
				pending = true;
				return;
			}
			inFlight = true;
			post( cfg.save, { key: draftKey, rev: nextRev(), fields: snap } ).then( function ( res ) {
				inFlight = false;
				if ( 200 === res.status && res.body.ok ) {
					lastSent = json;
					failures = 0;
				} else if ( failures < 3 && 403 !== res.status && 410 !== res.status ) {
					// Quiet retry; the final submission carries every value anyway.
					failures++;
					saveTimer = window.setTimeout( autosave, 1500 * failures );
				}
				if ( pending ) {
					pending = false;
					autosave();
				}
			} );
		};
		var schedule = function ( ms ) {
			window.clearTimeout( saveTimer );
			saveTimer = window.setTimeout( autosave, ms );
		};
		if ( quote ) {
			// Text fields: when they lose focus. Selections: shortly after a change.
			root.addEventListener( 'focusout', function ( e ) {
				if ( draftReady && e.target.matches( 'input[type=text],input[type=email],input[type=tel],input[type=number],input[type=time],input[type=hidden],input:not([type])' ) ) {
					schedule( 0 );
				}
			} );
			root.addEventListener( 'change', function ( e ) {
				if ( draftReady && e.target.matches( 'input[type=radio],input[type=checkbox],select,input[type=hidden]' ) ) {
					schedule( 400 );
				}
			} );
			// The date picker writes the hidden canonical value programmatically.
			var dateInput = el( 'event_date' );
			if ( dateInput ) {
				dateInput.addEventListener( 'change', function () {
					if ( draftReady ) {
						schedule( 400 );
					}
				} );
			}
		}
		var failureBox = root.querySelector( '[data-cic-failure]' );
		var showFailure = function ( message, retry ) {
			if ( ! failureBox ) {
				return;
			}
			failureBox.textContent = message + ' ';
			if ( retry ) {
				var b = document.createElement( 'button' );
				b.type = 'button';
				b.className = 'cic-text-link';
				b.textContent = M.retry;
				b.addEventListener( 'click', retry );
				failureBox.appendChild( b );
			}
			failureBox.hidden = false;
		};
		var hideFailure = function () {
			if ( failureBox ) {
				failureBox.hidden = true;
				failureBox.textContent = '';
			}
		};
		// Step 1 "Continue": the only moment a draft can be created. Retries reuse the same key, so
		// a slow or repeated request never creates a second inquiry.
		var continuing = false;
		var saveStep1 = function ( attempt ) {
			var src = '';
			if ( ctx ) {
				var s = ctx.source();
				if ( ! s.first ) {
					ctx.recordSource();
					s = ctx.source();
				}
				src = JSON.stringify( s );
			}
			var hp = form.querySelector( '[name="cic_website"]' );
			var snap = snapshot();
			return post( cfg.draft, { key: draftKey, rev: nextRev(), fields: snap, source: src, cic_ts: tsInput ? tsInput.value : '', cic_website: hp ? hp.value : '' } ).then( function ( res ) {
				if ( 409 === res.status && attempt < 3 ) {
					return new Promise( function ( r ) { window.setTimeout( r, 800 * ( attempt + 1 ) ); } ).then( function () { return saveStep1( attempt + 1 ); } );
				}
				if ( ( 200 === res.status || 201 === res.status ) && res.body.ok ) {
					lastSent = JSON.stringify( snap );
				}
				return res;
			} );
		};

		/* ---------------- Steps ---------------- */
		var progress = root.querySelector( '.cic-qf__progress' );
		var show = function ( n, push ) {
			if ( ! quote ) {
				return;
			}
			// Step 2 needs the saved Step 1 (the server accepted the details).
			current = Math.max( 1, Math.min( STEPS, n ) );
			if ( current > 1 && ! draftReady ) {
				current = 1;
			}
			steps.forEach( function ( s ) {
				s.hidden = +s.getAttribute( 'data-step' ) !== current;
			} );
			if ( progress ) {
				progress.querySelectorAll( 'li' ).forEach( function ( li ) {
					var k = +li.getAttribute( 'data-step' );
					li.classList.toggle( 'is-done', k < current );
					li.classList.toggle( 'is-current', k === current );
					var b = li.querySelector( 'button' );
					var note = li.querySelector( '[data-cic-stepstate]' );
					k === current ? b.setAttribute( 'aria-current', 'step' ) : b.removeAttribute( 'aria-current' );
					// aria-current announces the current step; finished steps say so too.
					if ( note ) {
						note.textContent = k < current ? ' ' + ( M.stepDone || '(completed)' ) : '';
					}
				} );
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
		// Step 1 answers that are explicit choices join the session context (keys only), so a
		// reload shows them again; the event selections live in Step 2.
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
			if ( 1 !== current || continuing ) {
				return;
			}
			hideFailure();
			if ( ! report( rules[ 1 ](), true ) ) {
				return;
			}
			var btn = steps[ 0 ].querySelector( '[data-cic-next]' );
			continuing = true;
			btn.disabled = true;
			btn.setAttribute( 'aria-busy', 'true' );
			saveStep1( 0 ).then( function ( res ) {
				continuing = false;
				btn.disabled = false;
				btn.removeAttribute( 'aria-busy' );
				if ( ( 200 === res.status || 201 === res.status ) && res.body.ok ) {
					draftReady = true;
					showSummary( [] );
					track( 'quote_step_completed', { step: 1 } );
					show( 2, true );
					focusStep();
					return;
				}
				if ( 422 === res.status && res.body.errors ) {
					report( Object.keys( res.body.errors ).map( function ( f ) { return { field: f, message: res.body.errors[ f ] }; } ), true );
					return;
				}
				showFailure( 409 === res.status ? M.continueBusy : M.continueFail, next );
				if ( failureBox ) {
					failureBox.setAttribute( 'tabindex', '-1' );
					failureBox.focus();
				}
			} );
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
					if ( want > current ) {
						next();
					} else {
						show( want, true );
						focusStep();
					}
				}
			} );
			// Enter in a single-line Step 1 field continues instead of submitting.
			root.addEventListener( 'keydown', function ( e ) {
				if ( 'Enter' === e.key && 1 === current && e.target.matches( 'input:not([type=checkbox]):not([type=radio])' ) && ! e.target.getAttribute( 'aria-activedescendant' ) ) {
					e.preventDefault();
					next();
				}
			} );
			window.addEventListener( 'popstate', function ( e ) {
				var n = e.state && e.state.cicStep ? e.state.cicStep : ( /^#step-([12])$/.exec( window.location.hash ) || [ 0, 1 ] )[ 1 ];
				show( +n, false );
			} );

			// Selections from hero search, event pages or this session fill fields that are still empty.
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
					syncPicker( x );
				}
			} );
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
			ensureTreatChoice();
			var hasDetailError = detailWrap && detailWrap.querySelector( '.wpcf7-not-valid' );
			revealDetail( !! ( val( 'location_detail' ) || hasDetailError ) );
			syncOther();
			syncDate();
			syncPhone();
			show( 1, false );
			window.history.replaceState( { cicStep: 1 }, '', window.location.pathname + window.location.search );
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
		var recover = function ( kind ) {
			// Failed request: CF7 would stay "submitting" and refuse a retry. Answers and the
			// submission key are kept, so pressing the button again cannot create a second enquiry
			// (a request that timed out but was saved is answered with the same receipt).
			var message = 'server' === kind && M.server ? M.server : M.network;
			if ( form.wpcf7 ) {
				form.wpcf7.status = 'init';
			}
			form.setAttribute( 'data-status', 'init' );
			form.classList.remove( 'submitting' );
			submitting = false;
			setBusy( false );
			showSummary( [ { field: 'cic_guard', message: message } ] );
			var out = form.querySelector( '.wpcf7-response-output' );
			if ( out ) {
				out.textContent = message;
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
			if ( quote && 1 === current && steps.length ) {
				e.preventDefault();
				e.stopImmediatePropagation();
				next();
				return;
			}
			var errs = quote ? rules[ 1 ]().concat( rules[ 2 ]() ) : rules.contact();
			if ( errs.length ) {
				e.preventDefault();
				e.stopImmediatePropagation();
				report( errs, true );
				return;
			}
			if ( quote && ! coverageOk ) {
				// Coverage pre-check first (blocks confirmed outside areas, asks for clarification).
				e.preventDefault();
				e.stopImmediatePropagation();
				setBusy( true );
				coverageCheck().then( function ( ok ) {
					setBusy( false );
					if ( ok ) {
						coverageOk = true;
						if ( form.requestSubmit ) {
							form.requestSubmit( submitBtn || undefined );
						} else if ( submitBtn ) {
							submitBtn.click();
						}
					}
				} );
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
			if ( draftInput ) {
				draftInput.value = draftReady ? draftKey : '';
			}
			// The final submission carries every value; no autosave may run alongside or after it.
			submitting = true;
			window.clearTimeout( saveTimer );
			showSummary( [] );
			setBusy( true );
			watchdog = window.setTimeout( function () { recover( 'timeout' ); }, 30000 );
		}, true );
		// CF7 leaves its own submit promise unhandled when the feedback request fails: it rejects with
		// the Response (non-2xx status) or { code: 'fetch_error' | 'invalid_json' }. Only those mean this
		// form's request failed; rejections from other scripts on the page are ignored.
		var cf7Failure = function ( reason ) {
			if ( ! reason ) {
				return '';
			}
			if ( window.Response && reason instanceof window.Response ) {
				return /\/contact-form-7\/v1\/contact-forms\/\d+\/feedback(?:[?#]|$)/.test( reason.url || '' ) ? 'server' : '';
			}
			if ( 'invalid_json' === reason.code ) {
				return 'server';
			}
			return 'fetch_error' === reason.code ? 'network' : '';
		};
		window.addEventListener( 'unhandledrejection', function ( e ) {
			var kind = busy ? cf7Failure( e.reason ) : '';
			if ( kind ) {
				e.preventDefault(); // Handled here: shown to the visitor, not an uncaught error.
				recover( kind );
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
			submitting = false;
			coverageOk = false;
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

		/* ---------------- Confirmation ---------------- */
		var fillGlance = function ( g ) {
			var box = received ? received.querySelector( '[data-cic-glance-box]' ) : null;
			if ( ! box || ! g || ! Object.keys( g ).length ) {
				return false;
			}
			box.querySelectorAll( '[data-g]' ).forEach( function ( row ) {
				var v = g[ row.getAttribute( 'data-g' ) ] || '';
				row.querySelector( '[data-g-value]' ).textContent = v;
				row.hidden = ! v;
			} );
			box.hidden = false;
			return true;
		};
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
				var chips = received.querySelector( '[data-cic-chips]' );
				if ( quote && fillGlance( c.glance ) && chips ) {
					chips.hidden = true;
				}
				var more = received.querySelector( '[data-cic-more]' );
				if ( more ) {
					more.hidden = ! ( quote && c.optional );
				}
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
			// Once per receipt (session list), only after the server confirmed the saved inquiry. A
			// replayed receipt counts too: the first response may have been lost (timeout) after saving.
			if ( quote && c.receipt && ! fired( c.receipt ) ) {
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

		/* ---------------- Optional details after submission ---------------- */
		var more = quote && received ? received.querySelector( '[data-cic-more]' ) : null;
		if ( more ) {
			var status = more.querySelector( '[data-cic-more-status]' );
			var retryBtn = more.querySelector( '[data-cic-more-retry]' );
			var moreFields = more.querySelectorAll( '[data-cic-more-field]' );
			var moreTimer = null;
			var moreBusy = false;
			var moreAgain = false;
			var moreSent = '';
			var moreSnap = function () {
				var o = {};
				moreFields.forEach( function ( f ) { o[ f.name ] = f.value; } );
				return o;
			};
			moreSent = JSON.stringify( moreSnap() );
			var say = function ( text, retry ) {
				status.textContent = text;
				retryBtn.hidden = ! retry;
			};
			var saveMore = function () {
				window.clearTimeout( moreTimer );
				var snap = moreSnap();
				var json = JSON.stringify( snap );
				if ( json === moreSent ) {
					return;
				}
				if ( moreBusy ) {
					moreAgain = true;
					return;
				}
				moreBusy = true;
				say( M.optSaving, false );
				post( cfg.save, { key: draftKey, rev: nextRev(), fields: snap } ).then( function ( res ) {
					moreBusy = false;
					if ( 200 === res.status && res.body.ok ) {
						moreSent = json;
						say( M.optSaved, false );
					} else if ( 410 === res.status || 403 === res.status ) {
						say( M.optClosed, false );
						moreFields.forEach( function ( f ) { f.disabled = true; } );
						return;
					} else {
						say( M.optFailed, true );
					}
					if ( moreAgain ) {
						moreAgain = false;
						saveMore();
					}
				} );
			};
			more.addEventListener( 'input', function ( e ) {
				if ( e.target.matches( '[data-cic-more-field]' ) ) {
					window.clearTimeout( moreTimer );
					moreTimer = window.setTimeout( saveMore, 1200 );
				}
			} );
			more.addEventListener( 'focusout', function ( e ) {
				if ( e.target.matches( '[data-cic-more-field]' ) ) {
					saveMore();
				}
			} );
			retryBtn.addEventListener( 'click', saveMore );
		}
	} );
}() );
