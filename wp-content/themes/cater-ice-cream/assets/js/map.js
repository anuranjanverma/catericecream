/**
 * California map enhancements (template-parts/components/ca-map.php). No map library or service.
 *
 * - Pins drop/fade into place once, staggered, when the map first enters the viewport. Skipped
 *   entirely with prefers-reduced-motion or without IntersectionObserver (pins simply show).
 * - Each county (pin + label) becomes a button that opens a compact preview: hover (mouse) and
 *   keyboard focus show it; click, tap, Enter or Space keep it open (keyboard activation moves focus
 *   into the card). Escape, the close button, a tap outside or leaving the county closes it, and
 *   focus returns to the county. The card stays inside the viewport; its photo loads only when shown.
 * - The preview states that the pin is an approximate county reference point, not a service
 *   boundary; coverage is still confirmed for the exact ZIP code.
 */
( function () {
	'use strict';
	var maps = document.querySelectorAll( '[data-cic-map]' );
	if ( ! maps.length ) {
		return;
	}
	var reduce = window.matchMedia( '(prefers-reduced-motion: reduce)' );

	Array.prototype.forEach.call( maps, function ( map ) {
		var points = Array.prototype.slice.call( map.querySelectorAll( '.cic-ca-map__point' ) );
		var card = map.querySelector( '.cic-ca-map__card' );

		/* ---------------- Entrance (once) ---------------- */
		if ( points.length && window.IntersectionObserver && ! reduce.matches ) {
			map.classList.add( 'cic-ca-map--pending' );
			var io = new window.IntersectionObserver( function ( entries ) {
				if ( entries.some( function ( e ) { return e.isIntersecting; } ) ) {
					map.classList.add( 'cic-ca-map--in' );
					io.disconnect();
					// Clear the animation hooks afterwards so nothing keeps running.
					window.setTimeout( function () {
						map.classList.remove( 'cic-ca-map--pending', 'cic-ca-map--in' );
					}, 600 + points.length * 120 );
				}
			}, { threshold: 0.35 } );
			io.observe( map );
		}

		if ( ! card || ! points.length ) {
			return;
		}

		/* ---------------- Preview ---------------- */
		// The card lives at the end of the page so later sections can never paint over it.
		document.body.appendChild( card );
		card.classList.add( 'cic-ca-map__card--floating' );
		var nameEl = card.querySelector( '.cic-ca-map__name' );
		var textEl = card.querySelector( '.cic-ca-map__text' );
		var noteEl = card.querySelector( '.cic-ca-map__note' );
		var linkEl = card.querySelector( '.cic-ca-map__link' );
		var media = card.querySelector( '.cic-ca-map__media' );
		var closeBtn = card.querySelector( '.cic-ca-map__close' );
		var active = null;
		var pinned = false;
		var hideTimer = null;
		var returning = false; // Focus going back to a county after closing must not reopen it.

		points.forEach( function ( p ) {
			p.setAttribute( 'role', 'button' );
			p.setAttribute( 'tabindex', '0' );
			p.setAttribute( 'aria-expanded', 'false' );
			p.setAttribute( 'aria-controls', card.id );
		} );

		var fill = function ( p ) {
			var count = parseInt( p.getAttribute( 'data-count' ), 10 ) || 0;
			nameEl.textContent = p.getAttribute( 'data-name' );
			textEl.textContent = p.getAttribute( 'data-text' ) || '';
			textEl.hidden = ! textEl.textContent;
			noteEl.textContent = count ? card.getAttribute( 'data-note' ).replace( '%d', String( count ) ) : card.getAttribute( 'data-note-none' );
			linkEl.textContent = card.getAttribute( 'data-link' );
			linkEl.href = p.getAttribute( 'data-url' ) || '#';
			linkEl.hidden = ! p.getAttribute( 'data-url' );
			// The approved county photo, requested only now; the box has a fixed ratio (no shift).
			media.textContent = '';
			var src = p.getAttribute( 'data-img' );
			media.hidden = ! src;
			if ( src ) {
				var img = document.createElement( 'img' );
				img.src = src;
				img.alt = '';
				img.width = parseInt( p.getAttribute( 'data-img-w' ), 10 ) || 768;
				img.height = parseInt( p.getAttribute( 'data-img-h' ), 10 ) || 432;
				img.decoding = 'async';
				media.appendChild( img );
			}
		};
		// Next to the pin, inside the viewport (above the pin when there is room, else below).
		var place = function ( p ) {
			var head = p.querySelector( '.cic-ca-map__pin' ) || p;
			var r = head.getBoundingClientRect();
			var edge = 12;
			// Keep clear of the sticky header (its height is published by main.js).
			var header = document.querySelector( '.cic-header.is-sticky' );
			var topEdge = edge + ( header ? Math.max( 0, header.getBoundingClientRect().bottom ) : 0 );
			card.style.left = '0px';
			card.style.top = '0px';
			var w = card.offsetWidth;
			var h = card.offsetHeight;
			var left = r.left + r.width / 2 - w / 2;
			left = Math.min( Math.max( edge, left ), window.innerWidth - edge - w );
			var top = r.top - h - 10;
			if ( top < topEdge ) {
				top = r.bottom + 10;
			}
			if ( top + h > window.innerHeight - edge ) {
				top = Math.max( topEdge, window.innerHeight - edge - h );
			}
			card.style.left = ( left + window.scrollX ) + 'px';
			card.style.top = ( top + window.scrollY ) + 'px';
		};
		var show = function ( p, keep ) {
			window.clearTimeout( hideTimer );
			if ( active && active !== p ) {
				active.setAttribute( 'aria-expanded', 'false' );
				active.classList.remove( 'is-active' );
			}
			if ( active !== p || card.hidden ) {
				fill( p );
			}
			active = p;
			pinned = pinned || !! keep;
			p.classList.add( 'is-active' );
			p.setAttribute( 'aria-expanded', 'true' );
			card.hidden = false;
			place( p );
		};
		var hide = function ( returnFocus ) {
			window.clearTimeout( hideTimer );
			if ( ! active ) {
				return;
			}
			var was = active;
			card.hidden = true;
			pinned = false;
			was.setAttribute( 'aria-expanded', 'false' );
			was.classList.remove( 'is-active' );
			active = null;
			if ( returnFocus ) {
				returning = true;
				was.focus();
				returning = false;
			}
		};
		var softHide = function () {
			if ( pinned ) {
				return;
			}
			window.clearTimeout( hideTimer );
			hideTimer = window.setTimeout( function () {
				if ( ! pinned && ! card.matches( ':hover' ) && ! card.contains( document.activeElement ) && ( ! active || active !== document.activeElement ) ) {
					hide( false );
				}
			}, 200 );
		};

		points.forEach( function ( p ) {
			p.addEventListener( 'pointerenter', function ( e ) {
				if ( 'mouse' === e.pointerType ) {
					show( p, false );
				}
			} );
			p.addEventListener( 'pointerleave', function ( e ) {
				if ( 'mouse' === e.pointerType ) {
					softHide();
				}
			} );
			p.addEventListener( 'focus', function () {
				if ( ! returning ) {
					show( p, false );
				}
			} );
			p.addEventListener( 'blur', softHide );
			p.addEventListener( 'click', function () {
				if ( active === p && pinned ) {
					hide( false );
				} else {
					show( p, true );
				}
			} );
			p.addEventListener( 'keydown', function ( e ) {
				if ( 'Enter' === e.key || ' ' === e.key ) {
					e.preventDefault();
					show( p, true );
					( linkEl.hidden ? closeBtn : linkEl ).focus();
				} else if ( 'Escape' === e.key && active ) {
					e.preventDefault();
					hide( true );
				}
			} );
		} );
		card.addEventListener( 'pointerleave', softHide );
		card.addEventListener( 'focusout', softHide );
		card.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key ) {
				e.preventDefault();
				hide( true );
			} else if ( 'Tab' === e.key ) {
				// Leaving the card by keyboard closes it and continues from its county.
				var items = Array.prototype.filter.call( card.querySelectorAll( 'a[href], button' ), function ( x ) { return ! x.hidden; } );
				var first = items[ 0 ];
				var lastItem = items[ items.length - 1 ];
				if ( e.shiftKey && document.activeElement === first ) {
					e.preventDefault();
					hide( true );
				} else if ( ! e.shiftKey && document.activeElement === lastItem ) {
					hide( true ); // Default Tab then moves on from the county.
				}
			}
		} );
		closeBtn.addEventListener( 'click', function () {
			hide( true );
		} );
		document.addEventListener( 'pointerdown', function ( e ) {
			if ( active && ! card.contains( e.target ) && ! active.contains( e.target ) ) {
				hide( false );
			}
		} );
		window.addEventListener( 'resize', function () {
			if ( active && ! card.hidden ) {
				place( active );
			}
		} );
	} );
}() );
