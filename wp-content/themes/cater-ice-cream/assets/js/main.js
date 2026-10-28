/**
 * Cater Ice Cream frontend: mobile drawer, submenu disclosure buttons and sticky-header offset.
 * Lightweight, dependency-free. Galleries, quote wizard and location search arrive in later phases.
 */
( function () {
	'use strict';

	document.documentElement.classList.add( 'cic-js' );

	var header = document.querySelector( '[data-cic-header]' );
	var drawer = document.querySelector( '[data-cic-drawer]' );
	var opener = document.querySelector( '[data-cic-drawer-open]' );

	/* Submenu toggles: real buttons after each parent link, so submenus never depend on hover. */
	var labelPrefix = document.documentElement.lang && 0 !== document.documentElement.lang.indexOf( 'en' ) ? '' : 'Show submenu for ';
	Array.prototype.forEach.call( document.querySelectorAll( '[data-cic-nav] .menu-item-has-children > a' ), function ( link, index ) {
		var item = link.parentElement;
		var submenu = item.querySelector( '.sub-menu' );
		if ( ! submenu ) {
			return;
		}
		submenu.id = submenu.id || 'cic-submenu-' + index + '-' + Math.random().toString( 36 ).slice( 2, 7 );
		var button = document.createElement( 'button' );
		button.type = 'button';
		button.className = 'cic-submenu-toggle';
		button.setAttribute( 'aria-expanded', 'false' );
		button.setAttribute( 'aria-controls', submenu.id );
		button.setAttribute( 'aria-label', labelPrefix + link.textContent.trim() );
		link.insertAdjacentElement( 'afterend', button );
		button.addEventListener( 'click', function () {
			var open = 'true' !== button.getAttribute( 'aria-expanded' );
			button.setAttribute( 'aria-expanded', String( open ) );
			item.classList.toggle( 'is-open', open );
		} );
		item.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key && item.classList.contains( 'is-open' ) ) {
				event.stopPropagation();
				button.setAttribute( 'aria-expanded', 'false' );
				item.classList.remove( 'is-open' );
				button.focus();
			}
		} );
	} );

	/* Close open desktop submenus when focus or clicks move elsewhere. */
	document.addEventListener( 'click', function ( event ) {
		Array.prototype.forEach.call( document.querySelectorAll( '.cic-nav--primary li.is-open' ), function ( item ) {
			if ( ! item.contains( event.target ) ) {
				item.classList.remove( 'is-open' );
				var button = item.querySelector( '.cic-submenu-toggle' );
				if ( button ) {
					button.setAttribute( 'aria-expanded', 'false' );
				}
			}
		} );
	} );

	/* Drawer: dialog semantics, focus trap, Escape, backdrop and focus return. */
	if ( drawer && opener ) {
		var lastFocus = null;
		var focusables = function () {
			return Array.prototype.filter.call(
				drawer.querySelectorAll( 'a[href], button:not([disabled]), input, select, textarea, [tabindex]:not([tabindex="-1"])' ),
				function ( el ) { return el.offsetParent !== null; }
			);
		};
		var close = function () {
			if ( drawer.hidden ) {
				return;
			}
			drawer.hidden = true;
			opener.setAttribute( 'aria-expanded', 'false' );
			document.body.classList.remove( 'cic-drawer-open' );
			( lastFocus || opener ).focus();
		};
		var open = function () {
			lastFocus = document.activeElement;
			drawer.hidden = false;
			opener.setAttribute( 'aria-expanded', 'true' );
			document.body.classList.add( 'cic-drawer-open' );
			var close_button = drawer.querySelector( '.cic-drawer__close' );
			( close_button || focusables()[ 0 ] ).focus();
		};
		opener.addEventListener( 'click', open );
		Array.prototype.forEach.call( drawer.querySelectorAll( '[data-cic-drawer-close]' ), function ( el ) {
			el.addEventListener( 'click', close );
		} );
		drawer.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key ) {
				close();
				return;
			}
			if ( 'Tab' !== event.key ) {
				return;
			}
			var items = focusables();
			if ( ! items.length ) {
				return;
			}
			var first = items[ 0 ];
			var last = items[ items.length - 1 ];
			if ( event.shiftKey && document.activeElement === first ) {
				event.preventDefault();
				last.focus();
			} else if ( ! event.shiftKey && document.activeElement === last ) {
				event.preventDefault();
				first.focus();
			}
		} );
		/* Following an in-page link closes the drawer so the target is visible. */
		drawer.addEventListener( 'click', function ( event ) {
			var link = event.target.closest( 'a[href]' );
			if ( link && link.hash && link.pathname === window.location.pathname ) {
				lastFocus = null;
				close();
			}
		} );
		window.matchMedia( '(min-width: 1024px)' ).addEventListener( 'change', function ( mq ) {
			if ( mq.matches ) {
				close();
			}
		} );
	}

	/* Fixed mobile "Get a Quote" button (main.css §10d). It is a normal link and works without this
	   script; the script only hides it while the menu drawer or another open dialog (e.g. the date
	   picker) is on screen, or while a form field has focus, so it never sits over the on-screen
	   keyboard or the field being filled in. */
	var cta = document.querySelector( '[data-cic-mobile-cta]' );
	if ( cta ) {
		var FIELD = 'textarea, select, [contenteditable=""], [contenteditable="true"], input:not([type="checkbox"]):not([type="radio"]):not([type="button"]):not([type="submit"]):not([type="reset"]):not([type="image"]):not([type="range"]):not([type="color"]):not([type="file"]):not([type="hidden"])';
		var dialogOpen = function () {
			if ( document.body.classList.contains( 'cic-drawer-open' ) ) {
				return true;
			}
			return Array.prototype.some.call( document.querySelectorAll( 'dialog[open], [aria-modal="true"], [role="dialog"]' ), function ( d ) {
				return ! d.hidden && ! cta.contains( d ) && d.getClientRects().length > 0;
			} );
		};
		var syncCta = function () {
			var active = document.activeElement;
			var typing = !! ( active && active.matches && active.matches( FIELD ) );
			cta.classList.toggle( 'is-hidden', typing || dialogOpen() );
		};
		document.addEventListener( 'focusin', syncCta );
		document.addEventListener( 'focusout', function () {
			window.setTimeout( syncCta, 0 );
		} );
		if ( window.MutationObserver ) {
			new MutationObserver( syncCta ).observe( document.body, { attributes: true, subtree: true, attributeFilter: [ 'hidden', 'open', 'aria-modal' ] } );
		}
		syncCta();
	}

	/* Treat photos and videos: only one video plays at a time (each starts only when pressed). */
	document.addEventListener( 'play', function ( event ) {
		if ( ! event.target.matches || ! event.target.matches( '[data-cic-media-video]' ) ) {
			return;
		}
		Array.prototype.forEach.call( document.querySelectorAll( '[data-cic-media-video]' ), function ( v ) {
			if ( v !== event.target && ! v.paused ) {
				v.pause();
			}
		} );
	}, true );

	/* County directories: accordions on mobile, always open on wider screens. */
	var counties = document.querySelectorAll( '[data-cic-county]' );
	if ( counties.length ) {
		var wide = window.matchMedia( '(min-width: 640px)' );
		var syncCounties = function () {
			Array.prototype.forEach.call( counties, function ( d, i ) {
				d.open = wide.matches || 0 === i;
			} );
		};
		syncCounties();
		wide.addEventListener( 'change', syncCounties );
	}

	/* Sticky header offsets.
	   --cic-header-offset: the full header height (+ admin toolbar). Anchors, scroll padding and
	   focused fields use it, so nothing lands under the header even when it re-expands.
	   --cic-header-visible: the height actually painted right now (smaller while the desktop header
	   is compact). Sticky sub-navigation follows it so it stays snug under the header. */
	if ( header && header.classList.contains( 'is-sticky' ) ) {
		var setOffset = function () {
			var bar = document.getElementById( 'wpadminbar' );
			var barHeight = bar && 'fixed' === window.getComputedStyle( bar ).position ? bar.offsetHeight : 0;
			var top = header.getBoundingClientRect().top;
			var visible = 0;
			Array.prototype.forEach.call( header.children, function ( row ) {
				var r = row.getBoundingClientRect();
				if ( r.height ) {
					visible = Math.max( visible, r.bottom - top );
				}
			} );
			document.documentElement.style.setProperty( '--cic-header-offset', ( header.offsetHeight + barHeight ) + 'px' );
			document.documentElement.style.setProperty( '--cic-header-visible', ( Math.round( visible || header.offsetHeight ) + barHeight ) + 'px' );
		};
		setOffset();
		if ( 'ResizeObserver' in window ) {
			var ro = new ResizeObserver( setOffset );
			ro.observe( header );
			Array.prototype.forEach.call( header.children, function ( row ) {
				ro.observe( row );
			} );
		}
		window.addEventListener( 'resize', setOffset );
	}

	/* Desktop compact header (Header settings → "compact"; only rendered with a sticky header).
	   At the top: the full header. Scrolling down past a small threshold collapses the top row and
	   shortens the main row; scrolling up, or reaching the top, restores it. A direction tolerance
	   ignores tiny movements (no flicker). It stays expanded while a dropdown is open or keyboard
	   focus is inside the header. Desktop breakpoint only: crossing it resets every class and inline
	   variable, so mobile and tablet headers are untouched. Motion lives in CSS (reduced-motion aware). */
	if ( header && header.classList.contains( 'is-sticky' ) && header.hasAttribute( 'data-cic-compact' ) ) {
		var desktop = window.matchMedia( '(min-width: 1024px)' );
		var topRow = header.querySelector( '.cic-header__top' );
		var THRESHOLD = 80; // px from the top before the header may compact.
		var TOLERANCE = 12; // px of travel in one direction before the state changes.
		var compact = false;
		var lastY = Math.max( 0, window.scrollY );
		var travel = 0;
		var ticking = false;
		// Full height = the rows' natural heights (the header box itself is held at this height).
		var measure = function () {
			if ( compact || ! desktop.matches ) {
				return;
			}
			var full = 0;
			Array.prototype.forEach.call( header.children, function ( row ) {
				full += row.offsetHeight;
			} );
			header.style.setProperty( '--cic-header-full', full + 'px' );
			if ( topRow ) {
				header.style.setProperty( '--cic-top-h', topRow.scrollHeight + 'px' );
			}
		};
		var settleTimer = null;
		// Measure once the rows have settled (after a restore, a resize or a breakpoint change).
		// Until then the header has no fixed height, so nothing can be clipped.
		var measureLater = function () {
			window.clearTimeout( settleTimer );
			settleTimer = window.setTimeout( measure, 400 );
		};
		var setCompact = function ( on ) {
			if ( on === compact ) {
				return;
			}
			if ( on && ! header.style.getPropertyValue( '--cic-header-full' ) ) {
				measure();
			}
			compact = on;
			header.classList.toggle( 'is-compact', on );
			if ( on ) {
				window.clearTimeout( settleTimer );
			} else {
				measureLater();
			}
		};
		var keyboardInside = function () {
			var a = document.activeElement;
			return !! a && header.contains( a ) && a.matches( ':focus-visible' );
		};
		var dropdownOpen = function () {
			return Array.prototype.some.call( header.querySelectorAll( '.cic-nav--primary .sub-menu' ), function ( menu ) {
				return 'none' !== window.getComputedStyle( menu ).display;
			} );
		};
		var update = function () {
			ticking = false;
			if ( ! desktop.matches ) {
				return;
			}
			var y = Math.max( 0, window.scrollY );
			var dy = y - lastY;
			lastY = y;
			if ( y <= THRESHOLD ) {
				travel = 0;
				setCompact( false );
				return;
			}
			if ( ! dy ) {
				return;
			}
			if ( ( dy > 0 ) !== ( travel > 0 ) ) {
				travel = 0; // Direction changed: start counting again.
			}
			travel += dy;
			if ( keyboardInside() || dropdownOpen() ) {
				setCompact( false );
			} else if ( travel > TOLERANCE ) {
				setCompact( true );
			} else if ( travel < -TOLERANCE ) {
				setCompact( false );
			}
		};
		var reset = function () {
			compact = false;
			travel = 0;
			lastY = Math.max( 0, window.scrollY );
			header.classList.remove( 'is-compact' );
			header.style.removeProperty( '--cic-header-full' );
			header.style.removeProperty( '--cic-top-h' );
		};
		window.addEventListener( 'scroll', function () {
			if ( ! ticking ) {
				ticking = true;
				window.requestAnimationFrame( update );
			}
		}, { passive: true } );
		// Keyboard focus entering the header (for example Shift+Tab back up) shows the full header.
		header.addEventListener( 'focusin', function () {
			if ( keyboardInside() ) {
				setCompact( false );
			}
		} );
		// Opening a dropdown with its toggle button also restores the full header.
		header.addEventListener( 'click', function ( event ) {
			if ( event.target.closest( '.cic-submenu-toggle' ) ) {
				setCompact( false );
			}
		} );
		desktop.addEventListener( 'change', function () {
			reset();
			measureLater();
		} );
		window.addEventListener( 'resize', function () {
			if ( ! compact ) {
				header.style.removeProperty( '--cic-header-full' );
				measureLater();
			}
		} );
		// Late fonts or wrapping rows: re-measure while expanded.
		if ( 'ResizeObserver' in window ) {
			var rowWatch = new ResizeObserver( function () {
				if ( ! compact ) {
					measureLater();
				}
			} );
			Array.prototype.forEach.call( header.children, function ( row ) {
				rowWatch.observe( row );
			} );
		}
		measure();
	}
}() );
