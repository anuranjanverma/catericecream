/**
 * Design System and Header/Footer admin behaviour: tabs, override badges, reset buttons,
 * hex/picker sync, live contrast check and Media Library logo picker. No dependencies.
 */
( function () {
	'use strict';

	var $$ = function ( selector, root ) {
		return Array.prototype.slice.call( ( root || document ).querySelectorAll( selector ) );
	};

	var validHex = function ( value ) {
		value = ( value || '' ).trim();
		var short = /^#([0-9a-f]{3})$/i.exec( value );
		if ( short ) {
			value = '#' + short[ 1 ].split( '' ).map( function ( c ) { return c + c; } ).join( '' );
		}
		return /^#[0-9a-f]{6}$/i.test( value ) ? value.toUpperCase() : null;
	};

	/* Tabs (all panels stay visible without JavaScript). */
	var tabs = $$( '[data-cic-tab]' );
	var panels = $$( '[data-cic-panel]' );
	var submit = document.querySelector( '.cic-submit' );
	var show = function ( key ) {
		panels.forEach( function ( panel ) {
			panel.hidden = panel.getAttribute( 'data-cic-panel' ) !== key;
		} );
		tabs.forEach( function ( tab ) {
			var active = tab.getAttribute( 'data-cic-tab' ) === key;
			tab.classList.toggle( 'nav-tab-active', active );
			if ( active ) {
				tab.setAttribute( 'aria-current', 'page' );
			} else {
				tab.removeAttribute( 'aria-current' );
			}
		} );
		if ( submit ) {
			submit.hidden = 'transfer' === key;
		}
	};
	if ( tabs.length ) {
		tabs.forEach( function ( tab ) {
			tab.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				var key = tab.getAttribute( 'data-cic-tab' );
				show( key );
				history.replaceState( null, '', '#' + key );
			} );
		} );
		document.addEventListener( 'click', function ( event ) {
			var link = event.target.closest( 'a[href^="#"]' );
			if ( link && ! link.hasAttribute( 'data-cic-tab' ) ) {
				var key = link.getAttribute( 'href' ).slice( 1 );
				if ( panels.some( function ( p ) { return p.getAttribute( 'data-cic-panel' ) === key; } ) ) {
					event.preventDefault();
					show( key );
				}
			}
		} );
		var initial = window.location.hash.slice( 1 );
		var known = panels.some( function ( p ) { return p.getAttribute( 'data-cic-panel' ) === initial; } );
		show( known ? initial : tabs[ 0 ].getAttribute( 'data-cic-tab' ) );
	}

	/* Row state: badge shows when the row holds a deliberate value. */
	var rowHasValue = function ( row ) {
		return $$( 'input:not([type=color]):not([type=hidden]), select', row ).some( function ( el ) {
			return 'checkbox' === el.type ? el.checked : '' !== el.value;
		} );
	};
	var refresh = function ( row ) {
		row.classList.toggle( 'is-active', rowHasValue( row ) );
	};
	$$( '[data-cic-row]' ).forEach( function ( row ) {
		row.addEventListener( 'input', function () { refresh( row ); } );
		row.addEventListener( 'change', function () { refresh( row ); } );
	} );

	/* Colour choice: show the hex box only for "Custom colour…". */
	$$( '.cic-color-choice' ).forEach( function ( select ) {
		var wrap = select.parentElement.querySelector( '.cic-custom-hex' );
		var sync = function () {
			if ( wrap ) {
				wrap.hidden = 'custom' !== select.value;
			}
		};
		select.addEventListener( 'change', sync );
		sync();
	} );

	/* Hex text box and native picker stay in sync. */
	$$( '.cic-hex' ).forEach( function ( input ) {
		var picker = input.parentElement.querySelector( '.cic-hex-picker' );
		input.addEventListener( 'input', function () {
			var hex = validHex( input.value );
			input.setCustomValidity( '' === input.value || hex ? '' : 'Use a #RRGGBB colour' );
			if ( hex && picker ) {
				picker.value = hex.toLowerCase();
			}
			live();
		} );
		if ( picker ) {
			picker.addEventListener( 'input', function () {
				input.value = picker.value.toUpperCase();
				input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
			} );
		}
	} );

	/* Reset returns the row to inherited/default. */
	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '[data-cic-reset]' );
		if ( ! button ) {
			return;
		}
		var row = button.closest( '[data-cic-row]' );
		$$( 'input:not([type=color]):not([type=hidden]), select', row ).forEach( function ( el ) {
			if ( 'checkbox' === el.type ) {
				el.checked = false;
			} else {
				el.value = '';
			}
			el.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		} );
		$$( '.cic-custom-hex', row ).forEach( function ( wrap ) { wrap.hidden = true; } );
		refresh( row );
		live();
		var first = row.querySelector( 'input:not([type=color]):not([type=hidden]), select' );
		if ( first ) {
			first.focus();
		}
	} );

	/* Live contrast for the main global pairs while editing. */
	var liveBox = document.getElementById( 'cic-live-contrast' );
	var token = function ( key ) {
		var input = document.querySelector( '[data-cic-token="' + key + '"]' );
		if ( ! input ) {
			return null;
		}
		return validHex( input.value ) || input.getAttribute( 'data-default' );
	};
	var luminance = function ( hex ) {
		var rgb = [ 1, 3, 5 ].map( function ( i ) { return parseInt( hex.substr( i, 2 ), 16 ) / 255; } );
		var lin = rgb.map( function ( c ) { return c <= 0.04045 ? c / 12.92 : Math.pow( ( c + 0.055 ) / 1.055, 2.4 ); } );
		return 0.2126 * lin[ 0 ] + 0.7152 * lin[ 1 ] + 0.0722 * lin[ 2 ];
	};
	var ratio = function ( a, b ) {
		var l1 = luminance( a ), l2 = luminance( b );
		return ( Math.max( l1, l2 ) + 0.05 ) / ( Math.min( l1, l2 ) + 0.05 );
	};
	function live() {
		if ( ! liveBox ) {
			return;
		}
		var pairs = [
			[ 'text_primary', 'surface_base', 'Body text on page surface', 4.5 ],
			[ 'text_primary', 'surface_soft', 'Body text on soft surface', 4.5 ],
			[ 'text_muted', 'surface_base', 'Muted text on page surface', 4.5 ],
			[ 'action_primary', 'surface_base', 'Links on page surface', 4.5 ],
			[ 'action_secondary', 'surface_base', 'Secondary action on page surface', 4.5 ],
			[ 'brand_raspberry', 'surface_base', 'Raspberry accent words (large text only)', 3 ],
			[ 'on_action', 'action_primary', 'Button text on primary action', 4.5 ],
			[ 'on_action', 'action_hover', 'Button text on hover colour', 4.5 ]
		];
		var items = pairs.map( function ( p ) {
			var fg = token( p[ 0 ] ), bg = token( p[ 1 ] );
			if ( ! fg || ! bg ) {
				return '';
			}
			var r = ratio( fg, bg );
			var pass = r >= p[ 3 ];
			return '<li class="' + ( pass ? 'is-pass' : 'is-fail' ) + '"><span class="cic-sample" style="color:' + fg + ';background:' + bg + '">Aa</span> ' +
				p[ 2 ] + ': <strong>' + r.toFixed( 2 ) + ':1</strong> ' + ( pass ? '✓' : '— below ' + p[ 3 ] + ':1' ) + '</li>';
		} );
		liveBox.innerHTML = '<h3>Live check (unsaved values)</h3><ul>' + items.join( '' ) + '</ul>';
	}
	live();

	/* Media Library image fields. */
	$$( '[data-cic-image]' ).forEach( function ( wrap ) {
		var input = wrap.querySelector( 'input[type=hidden]' );
		var img = wrap.querySelector( 'img' );
		var choose = wrap.querySelector( '[data-cic-image-select]' );
		var clear = wrap.querySelector( '[data-cic-image-clear]' );
		choose.addEventListener( 'click', function () {
			if ( ! window.wp || ! window.wp.media ) {
				return;
			}
			var frame = window.wp.media( { title: choose.textContent, library: { type: 'image' }, multiple: false } );
			frame.on( 'select', function () {
				var a = frame.state().get( 'selection' ).first().toJSON();
				input.value = a.id;
				img.src = a.sizes && a.sizes.medium ? a.sizes.medium.url : a.url;
				img.hidden = false;
				clear.hidden = false;
			} );
			frame.open();
		} );
		clear.addEventListener( 'click', function () {
			input.value = '0';
			img.hidden = true;
			img.removeAttribute( 'src' );
			clear.hidden = true;
		} );
	} );
}() );
