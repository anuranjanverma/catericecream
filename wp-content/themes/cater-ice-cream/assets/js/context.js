/**
 * Quote context helper (BUILD_SPECIFICATION §12). Keeps explicit, non-personal selections
 * (event, treat, location key/ZIP, date, guests) in sessionStorage and carries them to the quote
 * route. Viewing a page never changes the stored context; only explicit actions do (submitting a
 * starter form, following a quote link). The newest explicit value wins per field; other fields are
 * kept. Values are validated here and again on the server (CIC\Quote\Context).
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
			if ( /^\d{5}$/.test( x ) ) {
				return x;
			}
			return x.length <= 60 && /^[A-Za-z][A-Za-z .'\-]{1,58}[A-Za-z.]$/.test( x ) ? slug( x ) : '';
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

	window.cicQuoteContext = { read: read, merge: merge, url: quoteUrl, validators: validators };

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
			window.location.assign( quoteUrl( merge( selection ) ) );
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
			var body = new URLSearchParams();
			body.set( 'location', input.value.trim() );
			window.fetch( form.getAttribute( 'data-endpoint' ), { method: 'POST', body: body, credentials: 'same-origin' } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( data ) {
					result.textContent = data.message || '';
					result.setAttribute( 'data-status', data.status || '' );
					if ( 'outside_area' !== data.status && 'invalid' !== data.status ) {
						var a = document.createElement( 'a' );
						a.href = quoteUrl( Object.assign( {}, read(), { location: input.value.trim() } ) );
						a.className = 'cic-text-link';
						a.textContent = cfg.messages.continue;
						a.addEventListener( 'click', function () { merge( { location: input.value.trim() } ); } );
						result.appendChild( document.createTextNode( ' ' ) );
						result.appendChild( a );
					}
					result.hidden = false;
				} )
				.catch( function () {
					result.textContent = cfg.messages.offline;
					result.hidden = false;
				} );
		} );
	} );
}() );
