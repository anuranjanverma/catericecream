/**
 * Quote context helper (BUILD_SPECIFICATION §12). Keeps explicit, non-personal selections
 * (event, treat, location key/ZIP, date, guests) in sessionStorage and carries them to the quote
 * route. Viewing a page never changes the stored context; only explicit actions do (submitting a
 * starter form, following a quote link). The newest explicit value wins per field; other fields are
 * kept. Values are validated here and again on the server (CIC\Quote\Context).
 *
 * Location checks use the shared coverage service (POST /cic/v1/coverage): a confirmed
 * outside_area result blocks continuing (other answers are kept); unknown/manual_review ask for
 * the ZIP and venue city; covered confirms geography only, never a date. Location inputs offer a
 * limited, debounced autocomplete (GET /cic/v1/locations) as an accessible combobox.
 */
( function () {
	'use strict';
	var cfg = window.cicContext;
	if ( ! cfg ) {
		return;
	}
	var KEY = 'cic_quote_context';
	var read = function () {
		try {
			return JSON.parse( window.sessionStorage.getItem( KEY ) ) || {};
		} catch ( e ) {
			return {};
		}
	};
	var write = function ( c ) {
		try {
			window.sessionStorage.setItem( KEY, JSON.stringify( c ) );
		} catch ( e ) {}
	};
	var slug = function ( s ) {
		return s.toLowerCase().normalize( 'NFD' ).replace( /[̀-ͯ]/g, '' ).replace( /[^a-z0-9]+/g, '-' ).replace( /^-+|-+$/g, '' );
	};
	var validators = {
		event: function ( x ) {
			return cfg.events.indexOf( x ) > -1 ? x : '';
		},
		treat: function ( x ) {
			return x.split( ',' ).filter( function ( t ) { return cfg.treats.indexOf( t ) > -1; } ).join( ',' );
		},
		location: function ( x ) {
			x = ( x || '' ).trim();
			var zip = /^(\d{5})(?:-\d{4})?$/.exec( x );
			if ( zip ) {
				return zip[ 1 ];
			}
			return x.length <= 60 && /^[A-Za-z][A-Za-z .,'\-]{1,58}[A-Za-z.]$/.test( x ) ? slug( x ) : '';
		},
		date: function ( x ) {
			return /^\d{4}-\d{2}-\d{2}$/.test( x ) && x >= cfg.today && x <= cfg.maxDate ? x : '';
		},
		guests: function ( x ) {
			return /^\d{1,5}$/.test( x ) && +x >= 1 && +x <= 10000 ? String( +x ) : '';
		}
	};
	var merge = function ( selection ) {
		var c = read();
		Object.keys( selection ).forEach( function ( k ) {
			if ( validators[ k ] ) {
				var value = validators[ k ]( String( selection[ k ] ) );
				if ( value ) {
					c[ k ] = value;
				}
			}
		} );
		write( c );
		return c;
	};
	var quoteUrl = function ( c ) {
		var u = new URL( cfg.quoteUrl, window.location.href );
		Object.keys( c ).forEach( function ( k ) {
			if ( validators[ k ] && validators[ k ]( String( c[ k ] ) ) ) {
				u.searchParams.set( k, c[ k ] );
			}
		} );
		return u.toString();
	};
	var isQuote = function ( href ) {
		var u = new URL( href, window.location.href );
		var q = new URL( cfg.quoteUrl, window.location.href );
		return u.origin === q.origin && u.pathname === q.pathname ? u : null;
	};

	// Coverage request (resolves to null on network failure: the server validates again later).
	var check = function ( location, city ) {
		var body = new URLSearchParams();
		body.set( 'location', location );
		if ( city ) {
			body.set( 'city', city );
		}
		return window.fetch( cfg.coverageUrl, { method: 'POST', body: body, credentials: 'same-origin' } )
			.then( function ( r ) { return r.ok ? r.json() : null; } )
			.catch( function () { return null; } );
	};

	// Lead attribution (§17): template type + record key of the page where an explicit quote action
	// happened. First and latest are kept for the session; never personal data.
	var SOURCE = 'cic_quote_source';
	var readSource = function () {
		try {
			return JSON.parse( window.sessionStorage.getItem( SOURCE ) ) || {};
		} catch ( e ) {
			return {};
		}
	};
	var recordSource = function () {
		if ( ! cfg.page || ! cfg.page.type ) {
			return;
		}
		var s = readSource();
		var here = { type: cfg.page.type, key: cfg.page.key || '' };
		if ( ! s.first ) {
			s.first = here;
		}
		s.latest = here;
		try {
			window.sessionStorage.setItem( SOURCE, JSON.stringify( s ) );
		} catch ( e ) {}
	};
	var clear = function () {
		try {
			window.sessionStorage.removeItem( KEY );
			window.sessionStorage.removeItem( SOURCE );
		} catch ( e ) {}
	};

	window.cicQuoteContext = { read: read, merge: merge, url: quoteUrl, validators: validators, check: check, source: readSource, recordSource: recordSource, clear: clear };

	// Arriving on the quote route through an explicit action: its validated parameters join the context.
	if ( isQuote( window.location.href ) ) {
		var arrived = {};
		new URL( window.location.href ).searchParams.forEach( function ( v, k ) { arrived[ k ] = v; } );
		merge( arrived );
	}

	// Explicit quote links: the link's own selection wins; earlier unrelated choices are kept.
	document.addEventListener( 'click', function ( event ) {
		var link = event.target.closest( 'a[href]' );
		var target = link ? isQuote( link.href ) : null;
		if ( ! target ) {
			return;
		}
		var selection = {};
		target.searchParams.forEach( function ( v, k ) { selection[ k ] = v; } );
		recordSource();
		link.href = quoteUrl( merge( selection ) );
	} );

	// Starter forms (hero search and similar).
	Array.prototype.forEach.call( document.querySelectorAll( '[data-cic-quote-form]' ), function ( form ) {
		var stored = read();
		var fields = form.querySelectorAll( '[data-cic-context]' );
		Array.prototype.forEach.call( fields, function ( el ) {
			var k = el.getAttribute( 'data-cic-context' );
			if ( 'date' === k ) {
				el.min = cfg.today;
				el.max = cfg.maxDate;
			}
			if ( el.hasAttribute( 'data-cic-fixed' ) ) {
				return;
			}
			if ( el.hasAttribute( 'data-cic-zip' ) && ! /^\d{5}$/.test( stored[ k ] || '' ) ) {
				return;
			}
			if ( stored[ k ] && ! el.value ) {
				el.value = stored[ k ];
			}
		} );
		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			var selection = {};
			var invalid = [];
			Array.prototype.forEach.call( fields, function ( el ) {
				var k = el.getAttribute( 'data-cic-context' );
				var raw = el.value.trim();
				el.removeAttribute( 'aria-invalid' );
				if ( ! raw ) {
					return;
				}
				if ( validators[ k ]( raw ) ) {
					selection[ k ] = raw;
				} else {
					invalid.push( el );
					el.setAttribute( 'aria-invalid', 'true' );
				}
			} );
			var error = form.querySelector( '.cic-search__error' );
			if ( invalid.length ) {
				if ( error ) {
					error.textContent = cfg.messages.invalid;
					error.hidden = false;
				}
				invalid[ 0 ].focus();
				return;
			}
			if ( error ) {
				error.hidden = true;
			}
			var go = function () {
				recordSource();
				window.location.assign( quoteUrl( merge( selection ) ) );
			};
			if ( ! selection.location || ! cfg.coverageUrl ) {
				go();
				return;
			}
			// Ask the shared coverage service first; only a confirmed outside_area blocks.
			var fixed = form.querySelector( '[data-cic-fixed]' );
			var typed = Array.prototype.filter.call( fields, function ( el ) {
				return 'location' === el.getAttribute( 'data-cic-context' ) && ! el.hasAttribute( 'data-cic-fixed' ) && el.value.trim();
			} )[ 0 ];
			check( selection.location, fixed && typed ? fixed.value : '' ).then( function ( data ) {
				if ( data && 'block' === data.action ) {
					if ( error ) {
						error.textContent = data.message;
						error.hidden = false;
					}
					var target = typed || fixed;
					if ( target && target !== fixed ) {
						target.setAttribute( 'aria-invalid', 'true' );
						target.focus();
					}
					return;
				}
				go();
			} );
		} );
	} );

	// Area check: asks the coverage endpoint; never claims coverage it cannot confirm.
	Array.prototype.forEach.call( document.querySelectorAll( '[data-cic-area-check]' ), function ( form ) {
		var input = form.querySelector( 'input[name=location]' );
		var result = form.querySelector( '.cic-area-check__result' );
		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			var location = validators.location( input.value );
			input.removeAttribute( 'aria-invalid' );
			if ( ! location ) {
				input.setAttribute( 'aria-invalid', 'true' );
				result.textContent = cfg.messages.location;
				result.hidden = false;
				input.focus();
				return;
			}
			// Clear the previous answer while checking, so a stale result is never shown.
			result.textContent = '';
			result.removeAttribute( 'data-status' );
			result.hidden = true;
			form.setAttribute( 'aria-busy', 'true' );
			var body = new URLSearchParams();
			body.set( 'location', input.value.trim() );
			if ( form.getAttribute( 'data-city' ) && /^[\d\s-]+$/.test( input.value.trim() ) ) {
				body.set( 'city', form.getAttribute( 'data-city' ) );
			}
			var link = function ( href, text, onClick ) {
				var a = document.createElement( 'a' );
				a.href = href;
				a.className = 'cic-text-link';
				a.textContent = text;
				if ( onClick ) {
					a.addEventListener( 'click', onClick );
				}
				result.appendChild( document.createTextNode( ' ' ) );
				result.appendChild( a );
			};
			window.fetch( form.getAttribute( 'data-endpoint' ), { method: 'POST', body: body, credentials: 'same-origin' } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( data ) {
					result.textContent = data.message || '';
					result.setAttribute( 'data-status', data.status || '' );
					input.setAttribute( 'aria-invalid', 'invalid' === data.status ? 'true' : 'false' );
					if ( 'covered' === data.status && data.url && data.url.split( '#' )[ 0 ] !== window.location.href.split( /[?#]/ )[ 0 ] ) {
						link( data.url, cfg.messages.cityPage );
					}
					if ( 'outside_area' !== data.status && 'invalid' !== data.status ) {
						var where = form.getAttribute( 'data-city' ) && /^\d{5}$/.test( data.key || '' ) ? data.key : ( data.key || input.value.trim() );
						link( quoteUrl( Object.assign( {}, read(), { location: where } ) ), cfg.messages.continue, function () { recordSource(); merge( { location: where } ); } );
					}
					if ( data.fixture ) {
						var note = document.createElement( 'span' );
						note.className = 'cic-area-check__fixture';
						note.textContent = cfg.messages.fixture;
						result.appendChild( note );
					}
					result.hidden = false;
					form.removeAttribute( 'aria-busy' );
				} )
				.catch( function () {
					result.textContent = cfg.messages.offline;
					result.removeAttribute( 'data-status' );
					result.hidden = false;
					form.removeAttribute( 'aria-busy' );
				} );
		} );
	} );
	document.addEventListener( 'click', function ( event ) {
		var trigger = event.target.closest( '[data-cic-focus]' );
		var target = trigger ? document.querySelector( trigger.getAttribute( 'data-cic-focus' ) ) : null;
		if ( target ) {
			event.preventDefault();
			target.scrollIntoView( { block: 'center', behavior: window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ? 'auto' : 'smooth' } );
			target.focus( { preventScroll: true } );
		}
	} );

	// Location autocomplete (ARIA combobox, limited + debounced; no national list in the browser).
	if ( cfg.locationsUrl ) {
		var inputs = document.querySelectorAll( '[data-cic-autocomplete], [data-cic-quote-form] input[data-cic-context=location]:not([data-cic-fixed]):not([data-cic-zip])' );
		Array.prototype.forEach.call( inputs, function ( input, n ) {
			var host = input.parentNode;
			var list = document.createElement( 'ul' );
			var timer = null;
			var controller = null;
			var items = [];
			var active = -1;
			list.id = ( input.id || 'cic-location' ) + '-options-' + n;
			list.className = 'cic-ac-list';
			list.setAttribute( 'role', 'listbox' );
			list.hidden = true;
			host.classList.add( 'cic-ac-host' );
			host.appendChild( list );
			input.setAttribute( 'role', 'combobox' );
			input.setAttribute( 'aria-autocomplete', 'list' );
			input.setAttribute( 'aria-expanded', 'false' );
			input.setAttribute( 'aria-controls', list.id );
			input.setAttribute( 'autocomplete', 'off' );

			var close = function () {
				list.hidden = true;
				input.setAttribute( 'aria-expanded', 'false' );
				input.removeAttribute( 'aria-activedescendant' );
				active = -1;
			};
			var choose = function ( i ) {
				if ( items[ i ] ) {
					input.value = 'zip' === items[ i ].type ? items[ i ].key : items[ i ].label;
				}
				close();
			};
			var highlight = function ( i ) {
				var options = list.querySelectorAll( '[role=option]' );
				active = ( i + options.length ) % options.length;
				Array.prototype.forEach.call( options, function ( o, j ) {
					o.setAttribute( 'aria-selected', j === active ? 'true' : 'false' );
				} );
				input.setAttribute( 'aria-activedescendant', options[ active ].id );
			};
			var render = function ( found ) {
				items = found;
				list.textContent = '';
				if ( ! found.length ) {
					close();
					return;
				}
				found.forEach( function ( item, i ) {
					var li = document.createElement( 'li' );
					li.id = list.id + '-' + i;
					li.setAttribute( 'role', 'option' );
					li.setAttribute( 'aria-selected', 'false' );
					li.textContent = item.label || item.key;
					li.addEventListener( 'mousedown', function ( e ) {
						e.preventDefault();
						choose( i );
					} );
					list.appendChild( li );
				} );
				list.hidden = false;
				input.setAttribute( 'aria-expanded', 'true' );
			};
			input.addEventListener( 'input', function () {
				var q = input.value.trim();
				window.clearTimeout( timer );
				if ( q.length < 2 || q.length > 60 ) {
					close();
					return;
				}
				timer = window.setTimeout( function () {
					if ( controller ) {
						controller.abort();
					}
					controller = window.AbortController ? new window.AbortController() : null;
					var u = new URL( cfg.locationsUrl, window.location.href );
					u.searchParams.set( 'q', q );
					u.searchParams.set( 'limit', '6' );
					window.fetch( u.toString(), { credentials: 'same-origin', signal: controller ? controller.signal : undefined } )
						.then( function ( r ) { return r.ok ? r.json() : { items: [] }; } )
						.then( function ( data ) { render( data.items || [] ); } )
						.catch( function () {} );
				}, 250 );
			} );
			input.addEventListener( 'keydown', function ( e ) {
				if ( list.hidden ) {
					return;
				}
				if ( 'ArrowDown' === e.key || 'ArrowUp' === e.key ) {
					e.preventDefault();
					highlight( active + ( 'ArrowDown' === e.key ? 1 : -1 ) );
				} else if ( 'Enter' === e.key && active > -1 ) {
					e.preventDefault();
					choose( active );
				} else if ( 'Escape' === e.key ) {
					close();
				}
			} );
			input.addEventListener( 'blur', function () {
				window.setTimeout( close, 120 );
			} );
		} );
	}
}() );
