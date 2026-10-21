/**
 * Accessible date picker for the event-date fields (quote form and every quote starter). Written for
 * this theme (no third-party code). Progressive enhancement: without JavaScript the native date
 * input stays in place and the server validates as before.
 *
 * - The original input keeps its name and the canonical value (Y-m-d, a California calendar date)
 *   and becomes hidden; a visible text input shows MM/DD/YYYY. Display and value are kept separate.
 * - Dates are handled as calendar parts (year, month, day) with UTC arithmetic only, so no timezone
 *   can shift the selected day. "Today" and the latest date come from the server (America/Los_Angeles).
 * - Opens on a click or tap anywhere in the date control (label and icon included), or with Down
 *   arrow / Alt+Down from the field. Typing never opens it; valid manual entry (MM/DD/YYYY, M/D/YYYY
 *   or MMDDYYYY) is accepted when the field is left or Enter is pressed.
 * - Dialog: labelled month/year navigation buttons, a grid with arrow keys, Home/End, PageUp/PageDown
 *   (Shift for years), Enter/Space to choose, Escape to close; focus returns to the field. Dates
 *   outside the allowed range are announced as unavailable for selection. A note states that a date
 *   is not reserved by choosing it.
 * - Programmatic changes (stored context, "Date not set yet") call cicDatepicker.sync( input ).
 */
( function () {
	'use strict';
	var ctx = window.cicContext || {};
	var M = Object.assign( {
		placeholder: 'MM/DD/YYYY',
		hint: 'Format MM/DD/YYYY. Press the Down arrow key to choose from a calendar.',
		dialog: 'Choose a date',
		prevMonth: 'Previous month',
		nextMonth: 'Next month',
		prevYear: 'Previous year',
		nextYear: 'Next year',
		close: 'Close calendar',
		unavailable: 'not available to choose',
		selected: 'selected',
		today: 'today',
		note: 'Choosing a date does not reserve it. Availability is confirmed with your quote.'
	}, ctx.datepicker || {} );
	var MONTHS = [ 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December' ];
	var DAYS = [ 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' ];
	var uid = 0;

	/* ---------------- Calendar-date helpers (no local timezone involved) ---------------- */
	var pad = function ( n ) {
		return ( n < 10 ? '0' : '' ) + n;
	};
	var real = function ( p ) {
		var d = new Date( Date.UTC( p.y, p.m - 1, p.d ) );
		return p.y >= 1000 && d.getUTCFullYear() === p.y && d.getUTCMonth() === p.m - 1 && d.getUTCDate() === p.d;
	};
	var fromYmd = function ( s ) {
		var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec( s || '' );
		var p = m ? { y: +m[ 1 ], m: +m[ 2 ], d: +m[ 3 ] } : null;
		return p && real( p ) ? p : null;
	};
	var fromTyped = function ( s ) {
		var t = ( s || '' ).trim();
		var m = /^(\d{1,2})[\/.\-](\d{1,2})[\/.\-](\d{4})$/.exec( t ) || /^(\d{2})(\d{2})(\d{4})$/.exec( t );
		var p = m ? { y: +m[ 3 ], m: +m[ 1 ], d: +m[ 2 ] } : null;
		return p && real( p ) ? p : null;
	};
	var ymd = function ( p ) {
		return p.y + '-' + pad( p.m ) + '-' + pad( p.d );
	};
	var mdy = function ( p ) {
		return pad( p.m ) + '/' + pad( p.d ) + '/' + p.y;
	};
	var shift = function ( p, days ) {
		var d = new Date( Date.UTC( p.y, p.m - 1, p.d + days ) );
		return { y: d.getUTCFullYear(), m: d.getUTCMonth() + 1, d: d.getUTCDate() };
	};
	var monthsLater = function ( p, n ) {
		var idx = p.y * 12 + ( p.m - 1 ) + n;
		var y = Math.floor( idx / 12 );
		var m = idx - y * 12 + 1;
		var last = new Date( Date.UTC( y, m, 0 ) ).getUTCDate();
		return { y: y, m: m, d: Math.min( p.d, last ) };
	};
	var weekday = function ( p ) {
		return new Date( Date.UTC( p.y, p.m - 1, p.d ) ).getUTCDay();
	};
	var spoken = function ( p ) {
		return DAYS[ weekday( p ) ] + ', ' + MONTHS[ p.m - 1 ] + ' ' + p.d + ', ' + p.y;
	};

	/* ---------------- One field ---------------- */
	var enhance = function ( input ) {
		if ( input.cicVisible || 'date' !== input.type ) {
			return;
		}
		var n = ++uid;
		var min = fromYmd( input.getAttribute( 'min' ) || ctx.today );
		var max = fromYmd( input.getAttribute( 'max' ) || ctx.maxDate );
		var control = input.closest( '.cic-search__field, .cic-qs__control, .cic-field__control, .cic-starter__field, .cic-field' ) || input.parentNode;
		var text = document.createElement( 'input' );
		var hint = document.createElement( 'span' );
		var dialogId = 'cic-dp-' + n;

		// The visible field takes over the id (so existing labels point at it) and the styling.
		text.type = 'text';
		text.className = input.className;
		if ( input.id ) {
			text.id = input.id;
			input.id = input.id + '-value';
		}
		text.placeholder = M.placeholder;
		text.inputMode = 'numeric';
		text.autocomplete = 'off';
		text.maxLength = 10;
		text.setAttribute( 'role', 'combobox' );
		text.setAttribute( 'aria-haspopup', 'dialog' );
		text.setAttribute( 'aria-expanded', 'false' );
		text.setAttribute( 'aria-controls', dialogId );
		[ 'aria-required', 'aria-invalid' ].forEach( function ( a ) {
			if ( input.hasAttribute( a ) ) {
				text.setAttribute( a, input.getAttribute( a ) );
			}
		} );
		hint.className = 'screen-reader-text';
		hint.id = dialogId + '-hint';
		hint.textContent = M.hint;
		text.setAttribute( 'aria-describedby', ( ( input.getAttribute( 'aria-describedby' ) || '' ) + ' ' + hint.id ).trim() );
		input.parentNode.insertBefore( text, input );
		input.parentNode.insertBefore( hint, input );
		input.type = 'hidden';
		input.cicVisible = text;
		control.classList.add( 'cic-dp-host' );

		var dialog = null;
		var grid = null;
		var title = null;
		var view = null; // First day of the shown month.
		var focusDate = null;
		var isOpen = false;

		var setValue = function ( v ) {
			if ( input.value === v ) {
				return;
			}
			input.value = v;
			// Bubbling events let the forms clear errors and react as for any field change.
			input.dispatchEvent( new window.Event( 'input', { bubbles: true } ) );
			input.dispatchEvent( new window.Event( 'change', { bubbles: true } ) );
		};
		var sync = function () {
			var p = fromYmd( input.value );
			text.value = p ? mdy( p ) : ( input.value && ! /^\d{4}-/.test( input.value ) ? input.value : '' );
			text.disabled = input.disabled;
			if ( input.disabled && isOpen ) {
				close( false );
			}
		};
		// Manual entry: a valid date becomes the canonical value; anything else stays visible and is
		// passed on unchanged, so the form's own validation reports it.
		var commit = function () {
			var raw = text.value.trim();
			var p = fromTyped( raw );
			if ( p ) {
				text.value = mdy( p );
				setValue( ymd( p ) );
			} else {
				setValue( raw );
			}
		};
		var allowed = function ( p ) {
			var v = ymd( p );
			return ( ! min || v >= ymd( min ) ) && ( ! max || v <= ymd( max ) );
		};

		var button = function ( cls, label, path ) {
			var b = document.createElement( 'button' );
			b.type = 'button';
			b.className = 'cic-dp__nav ' + cls;
			b.setAttribute( 'aria-label', label );
			b.innerHTML = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="' + path + '"/></svg>';
			return b;
		};
		var build = function () {
			dialog = document.createElement( 'div' );
			dialog.className = 'cic-dp';
			dialog.id = dialogId;
			dialog.hidden = true;
			dialog.setAttribute( 'role', 'dialog' );
			dialog.setAttribute( 'aria-modal', 'false' );
			dialog.setAttribute( 'aria-label', M.dialog );
			var head = document.createElement( 'div' );
			head.className = 'cic-dp__head';
			var prevY = button( 'cic-dp__prev-year', M.prevYear, 'M12 6l-6 6 6 6M18 6l-6 6 6 6' );
			var prevM = button( 'cic-dp__prev', M.prevMonth, 'M15 6l-6 6 6 6' );
			var nextM = button( 'cic-dp__next', M.nextMonth, 'M9 6l6 6-6 6' );
			var nextY = button( 'cic-dp__next-year', M.nextYear, 'M6 6l6 6-6 6M12 6l6 6-6 6' );
			title = document.createElement( 'div' );
			title.className = 'cic-dp__title';
			title.id = dialogId + '-title';
			title.setAttribute( 'aria-live', 'polite' );
			head.appendChild( prevY );
			head.appendChild( prevM );
			head.appendChild( title );
			head.appendChild( nextM );
			head.appendChild( nextY );
			grid = document.createElement( 'table' );
			grid.className = 'cic-dp__grid';
			grid.setAttribute( 'role', 'grid' );
			grid.setAttribute( 'aria-labelledby', title.id );
			var thead = document.createElement( 'thead' );
			var row = document.createElement( 'tr' );
			DAYS.forEach( function ( d ) {
				var th = document.createElement( 'th' );
				th.scope = 'col';
				th.abbr = d;
				th.textContent = d.slice( 0, 2 );
				row.appendChild( th );
			} );
			thead.appendChild( row );
			grid.appendChild( thead );
			grid.appendChild( document.createElement( 'tbody' ) );
			var note = document.createElement( 'p' );
			note.className = 'cic-dp__note';
			note.textContent = M.note;
			var foot = document.createElement( 'div' );
			foot.className = 'cic-dp__foot';
			var closeBtn = document.createElement( 'button' );
			closeBtn.type = 'button';
			closeBtn.className = 'cic-dp__close';
			closeBtn.textContent = M.close;
			foot.appendChild( closeBtn );
			dialog.appendChild( head );
			dialog.appendChild( grid );
			dialog.appendChild( note );
			dialog.appendChild( foot );
			document.body.appendChild( dialog );

			prevY.addEventListener( 'click', function () { move( monthsLater( focusDate, -12 ), true ); } );
			prevM.addEventListener( 'click', function () { move( monthsLater( focusDate, -1 ), true ); } );
			nextM.addEventListener( 'click', function () { move( monthsLater( focusDate, 1 ), true ); } );
			nextY.addEventListener( 'click', function () { move( monthsLater( focusDate, 12 ), true ); } );
			closeBtn.addEventListener( 'click', function () { close( true ); } );
			grid.addEventListener( 'click', function ( e ) {
				var b = e.target.closest( '.cic-dp__day' );
				if ( b ) {
					choose( fromYmd( b.getAttribute( 'data-date' ) ) );
				}
			} );
			grid.addEventListener( 'keydown', gridKeys );
			dialog.addEventListener( 'keydown', function ( e ) {
				if ( 'Escape' === e.key ) {
					e.preventDefault();
					close( true );
				} else if ( 'Tab' === e.key ) {
					// Keep keyboard focus inside the open calendar.
					var items = Array.prototype.filter.call( dialog.querySelectorAll( 'button' ), function ( b ) {
						return ! b.disabled && ( ! b.classList.contains( 'cic-dp__day' ) || '0' === b.getAttribute( 'tabindex' ) );
					} );
					var i = items.indexOf( document.activeElement );
					if ( e.shiftKey && i <= 0 ) {
						e.preventDefault();
						items[ items.length - 1 ].focus();
					} else if ( ! e.shiftKey && i === items.length - 1 ) {
						e.preventDefault();
						items[ 0 ].focus();
					}
				}
			} );
		};

		var render = function () {
			var tbody = grid.querySelector( 'tbody' );
			var selected = fromYmd( input.value );
			var todayP = fromYmd( ctx.today );
			tbody.textContent = '';
			title.textContent = MONTHS[ view.m - 1 ] + ' ' + view.y;
			var first = weekday( view );
			var days = new Date( Date.UTC( view.y, view.m, 0 ) ).getUTCDate();
			var tr = document.createElement( 'tr' );
			for ( var b = 0; b < first; b++ ) {
				tr.appendChild( document.createElement( 'td' ) );
			}
			for ( var d = 1; d <= days; d++ ) {
				var p = { y: view.y, m: view.m, d: d };
				var td = document.createElement( 'td' );
				var btn = document.createElement( 'button' );
				var ok = allowed( p );
				var isSel = selected && ymd( selected ) === ymd( p );
				var label = spoken( p ) + ( isSel ? ', ' + M.selected : '' ) + ( ok ? '' : ', ' + M.unavailable );
				btn.type = 'button';
				btn.className = 'cic-dp__day';
				btn.textContent = String( d );
				btn.setAttribute( 'data-date', ymd( p ) );
				btn.setAttribute( 'aria-label', label );
				btn.setAttribute( 'tabindex', ymd( p ) === ymd( focusDate ) ? '0' : '-1' );
				if ( ! ok ) {
					btn.setAttribute( 'aria-disabled', 'true' );
				}
				if ( todayP && ymd( todayP ) === ymd( p ) ) {
					btn.setAttribute( 'aria-current', 'date' );
				}
				td.setAttribute( 'aria-selected', isSel ? 'true' : 'false' );
				td.appendChild( btn );
				tr.appendChild( td );
				if ( 6 === weekday( p ) ) {
					tbody.appendChild( tr );
					tr = document.createElement( 'tr' );
				}
			}
			if ( tr.children.length ) {
				while ( tr.children.length < 7 ) {
					tr.appendChild( document.createElement( 'td' ) );
				}
				tbody.appendChild( tr );
			}
			var minMonth = min ? min.y * 12 + min.m : -Infinity;
			var maxMonth = max ? max.y * 12 + max.m : Infinity;
			var here = view.y * 12 + view.m;
			dialog.querySelector( '.cic-dp__prev' ).disabled = here <= minMonth;
			dialog.querySelector( '.cic-dp__prev-year' ).disabled = here <= minMonth;
			dialog.querySelector( '.cic-dp__next' ).disabled = here >= maxMonth;
			dialog.querySelector( '.cic-dp__next-year' ).disabled = here >= maxMonth;
		};
		var clamp = function ( p ) {
			if ( min && ymd( p ) < ymd( min ) ) {
				return min;
			}
			if ( max && ymd( p ) > ymd( max ) ) {
				return max;
			}
			return p;
		};
		var focusDay = function () {
			var b = grid.querySelector( '[data-date="' + ymd( focusDate ) + '"]' );
			if ( b ) {
				b.focus();
			}
		};
		// Move the active day (re-rendering when the month changes). Buttons keep focus where they are.
		var move = function ( p, fromNav ) {
			focusDate = clamp( p );
			view = { y: focusDate.y, m: focusDate.m, d: 1 };
			render();
			if ( ! fromNav ) {
				focusDay();
			}
		};
		var gridKeys = function ( e ) {
			var steps = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 };
			var p = null;
			if ( steps[ e.key ] ) {
				p = shift( focusDate, steps[ e.key ] );
			} else if ( 'Home' === e.key ) {
				p = shift( focusDate, -weekday( focusDate ) );
			} else if ( 'End' === e.key ) {
				p = shift( focusDate, 6 - weekday( focusDate ) );
			} else if ( 'PageUp' === e.key || 'PageDown' === e.key ) {
				p = monthsLater( focusDate, ( 'PageUp' === e.key ? -1 : 1 ) * ( e.shiftKey ? 12 : 1 ) );
			} else if ( 'Enter' === e.key || ' ' === e.key ) {
				e.preventDefault();
				var b = e.target.closest( '.cic-dp__day' );
				if ( b ) {
					choose( fromYmd( b.getAttribute( 'data-date' ) ) );
				}
				return;
			}
			if ( p ) {
				e.preventDefault();
				move( p, false );
			}
		};
		var place = function () {
			var r = control.getBoundingClientRect();
			var gap = 6;
			var edge = 12;
			dialog.style.left = '0px';
			dialog.style.top = '0px';
			var w = dialog.offsetWidth;
			var left = Math.min( Math.max( edge, r.left ), Math.max( edge, window.innerWidth - edge - w ) );
			// Always below the field (never over the sticky header); open() scrolls it into view.
			dialog.style.left = ( left + window.scrollX ) + 'px';
			dialog.style.top = ( r.bottom + gap + window.scrollY ) + 'px';
		};
		var open = function ( moveFocus ) {
			if ( text.disabled ) {
				return;
			}
			if ( ! dialog ) {
				build();
			}
			var current = fromYmd( input.value ) || fromTyped( text.value );
			focusDate = clamp( current || fromYmd( ctx.today ) || min || { y: 2026, m: 1, d: 1 } );
			view = { y: focusDate.y, m: focusDate.m, d: 1 };
			render();
			dialog.hidden = false;
			isOpen = true;
			text.setAttribute( 'aria-expanded', 'true' );
			place();
			if ( moveFocus ) {
				focusDay();
			}
			var r = dialog.getBoundingClientRect();
			if ( r.bottom > window.innerHeight || r.top < 0 ) {
				dialog.scrollIntoView( { block: 'nearest', behavior: window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ? 'auto' : 'smooth' } );
			}
		};
		var close = function ( returnFocus ) {
			if ( ! isOpen ) {
				return;
			}
			isOpen = false;
			dialog.hidden = true;
			text.setAttribute( 'aria-expanded', 'false' );
			if ( returnFocus ) {
				text.focus();
			}
		};
		var choose = function ( p ) {
			if ( ! p || ! allowed( p ) ) {
				return;
			}
			text.value = mdy( p );
			setValue( ymd( p ) );
			close( true );
		};

		/* Opening: a click or tap anywhere in the date control. A tap moves focus into the calendar
		   (no on-screen keyboard); a mouse click keeps the caret in the field for typing. */
		var pointerType = '';
		control.addEventListener( 'pointerdown', function ( e ) {
			pointerType = e.pointerType || '';
		} );
		control.addEventListener( 'click', function ( e ) {
			if ( text.disabled || ( dialog && dialog.contains( e.target ) ) ) {
				return;
			}
			if ( e.target !== text ) {
				text.focus();
			}
			if ( ! isOpen ) {
				open( 'touch' === pointerType || 'pen' === pointerType );
			}
			pointerType = '';
		} );
		text.addEventListener( 'keydown', function ( e ) {
			if ( 'ArrowDown' === e.key || ( e.altKey && 'ArrowDown' === e.key ) ) {
				e.preventDefault();
				if ( isOpen ) {
					focusDay();
				} else {
					open( true );
				}
			} else if ( 'Escape' === e.key && isOpen ) {
				e.preventDefault();
				close( false );
			} else if ( 'Enter' === e.key ) {
				commit();
				close( false );
			}
		} );
		// While open, a typed valid date moves the calendar to it (typing alone never opens it).
		text.addEventListener( 'input', function () {
			var p = fromTyped( text.value );
			if ( isOpen && p ) {
				move( p, true );
			}
		} );
		text.addEventListener( 'change', commit );
		document.addEventListener( 'pointerdown', function ( e ) {
			if ( isOpen && ! dialog.contains( e.target ) && ! control.contains( e.target ) ) {
				close( false );
			}
		} );
		window.addEventListener( 'resize', function () {
			if ( isOpen ) {
				place();
			}
		} );
		// "Date not set yet" and similar toggles change the disabled state of the hidden value.
		if ( window.MutationObserver ) {
			new window.MutationObserver( sync ).observe( input, { attributes: true, attributeFilter: [ 'disabled' ] } );
		}
		input.cicPickerSync = sync;
		sync();
	};

	var init = function () {
		Array.prototype.forEach.call( document.querySelectorAll( 'input[type="date"]' ), enhance );
	};
	window.cicDatepicker = {
		sync: function ( input ) {
			if ( input && input.cicPickerSync ) {
				input.cicPickerSync();
			}
		}
	};
	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
