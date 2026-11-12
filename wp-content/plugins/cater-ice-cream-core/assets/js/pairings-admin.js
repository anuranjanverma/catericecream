/**
 * Occasion pairings box: add and remove rows; Media Library photo pickers for new rows.
 */
( function () {
	'use strict';
	var list = document.querySelector( '[data-cic-pairings]' );
	var template = document.querySelector( '[data-cic-pairing-template]' );
	var add = document.querySelector( '[data-cic-pairing-add]' );
	if ( ! list || ! template || ! add ) {
		return;
	}
	var counter = list.children.length + 1000;

	var bindImage = function ( wrap ) {
		var input = wrap.querySelector( 'input[type=hidden]' );
		var img = wrap.querySelector( 'img' );
		var choose = wrap.querySelector( '[data-cic-image-select]' );
		var clear = wrap.querySelector( '[data-cic-image-clear]' );
		if ( wrap.getAttribute( 'data-bound' ) ) {
			return;
		}
		wrap.setAttribute( 'data-bound', '1' );
		choose.addEventListener( 'click', function () {
			var frame = window.wp.media( { title: choose.textContent, library: { type: 'image' }, multiple: false } );
			frame.on( 'select', function () {
				var a = frame.state().get( 'selection' ).first().toJSON();
				input.value = a.id;
				img.src = a.sizes && a.sizes.thumbnail ? a.sizes.thumbnail.url : a.url;
				img.hidden = false;
				clear.hidden = false;
			} );
			frame.open();
		} );
		clear.addEventListener( 'click', function () {
			input.value = '0';
			img.hidden = true;
			clear.hidden = true;
		} );
	};

	add.addEventListener( 'click', function () {
		var html = template.innerHTML.replace( /__INDEX__/g, String( counter++ ) );
		list.insertAdjacentHTML( 'beforeend', html );
		var row = list.lastElementChild;
		row.querySelectorAll( '[data-cic-image]' ).forEach( bindImage );
		var select = row.querySelector( 'select' );
		if ( select ) {
			select.focus();
		}
	} );

	list.addEventListener( 'click', function ( event ) {
		var remove = event.target.closest( '[data-cic-pairing-remove]' );
		if ( remove ) {
			remove.closest( '[data-cic-pairing]' ).remove();
			add.focus();
		}
	} );
}() );
