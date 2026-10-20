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

	/* Sticky header offset so anchors and focused fields are not hidden underneath it. */
	if ( header && header.classList.contains( 'is-sticky' ) ) {
		var setOffset = function () {
			var bar = document.getElementById( 'wpadminbar' );
			var barHeight = bar && 'fixed' === window.getComputedStyle( bar ).position ? bar.offsetHeight : 0;
			document.documentElement.style.setProperty( '--cic-header-offset', ( header.offsetHeight + barHeight ) + 'px' );
		};
		setOffset();
		if ( 'ResizeObserver' in window ) {
			new ResizeObserver( setOffset ).observe( header );
		}
		window.addEventListener( 'resize', setOffset );
	}
}() );
