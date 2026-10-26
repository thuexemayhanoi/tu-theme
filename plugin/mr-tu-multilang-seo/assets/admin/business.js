/**
 * Mr Tu business admin screens (Store information, Prices, Vehicles, Quick Call). No dependencies
 * except wp.media on the Vehicles screen.
 * - Verification badges follow their "verified" toggle live.
 * - E.164 suggestion derived from the displayed phone number.
 * - Media picker for vehicle photos.
 */
( function () {
	'use strict';

	/* Live verification badges: a [data-mrtu-group] holds one [data-mrtu-badge] and one "[verified]" toggle. */
	document.addEventListener( 'change', function ( event ) {
		var input = event.target;
		if ( ! input || 'checkbox' !== input.type || ! /\[verified\]$/.test( input.name || '' ) ) {
			return;
		}
		var group = input.closest( '[data-mrtu-group]' );
		var badge = group ? group.querySelector( '[data-mrtu-badge]' ) : null;
		if ( ! badge ) {
			return;
		}
		badge.classList.toggle( 'mrtu-badge--ok', input.checked );
		badge.classList.toggle( 'mrtu-badge--pending', ! input.checked );
		badge.textContent = badge.getAttribute( input.checked ? 'data-ok' : 'data-pending' );
	} );

	/* E.164 suggestion ("0816 659 199" → "+84816659199"). */
	var display = document.querySelector( '[data-mrtu-phone-display]' );
	var e164 = document.querySelector( '[data-mrtu-phone-e164]' );
	var suggest = document.querySelector( '[data-mrtu-e164-suggest]' );

	function derive( text ) {
		text = ( text || '' ).trim();
		if ( ! text ) {
			return '';
		}
		var plus = '+' === text.charAt( 0 );
		var digits = text.replace( /\D+/g, '' );
		var out = '';
		if ( plus ) {
			out = '+' + digits;
		} else if ( 0 === digits.indexOf( '0084' ) ) {
			out = '+' + digits.slice( 2 );
		} else if ( 0 === digits.indexOf( '84' ) && digits.length >= 11 ) {
			out = '+' + digits;
		} else if ( '0' === digits.charAt( 0 ) ) {
			out = '+84' + digits.slice( 1 );
		}
		return /^\+84\d{9,10}$/.test( out ) ? out : '';
	}

	if ( display && e164 && suggest ) {
		var code = suggest.querySelector( 'code' );
		var refresh = function () {
			var value = derive( display.value );
			var show = '' !== value && value !== e164.value.trim();
			suggest.hidden = ! show;
			if ( show && code ) {
				code.textContent = value;
			}
		};
		display.addEventListener( 'input', refresh );
		e164.addEventListener( 'input', refresh );
		suggest.addEventListener( 'click', function ( event ) {
			if ( event.target.closest( '[data-mrtu-e164-use]' ) ) {
				e164.value = derive( display.value );
				refresh();
				e164.focus();
			}
		} );
		refresh();
	}

	/* Media picker (Vehicles → Photo). */
	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest ? event.target.closest( '[data-mrtu-media]' ) : null;
		if ( ! button ) {
			return;
		}
		var field = button.closest( '[data-mrtu-media-field]' );
		if ( ! field ) {
			return;
		}
		var input = field.querySelector( 'input[type="hidden"]' );
		var preview = field.querySelector( '[data-mrtu-media-preview]' );
		var remove = field.querySelector( '[data-mrtu-media="remove"]' );
		event.preventDefault();

		if ( 'remove' === button.getAttribute( 'data-mrtu-media' ) ) {
			input.value = '0';
			preview.textContent = '';
			if ( remove ) {
				remove.hidden = true;
			}
			return;
		}
		if ( ! window.wp || ! window.wp.media ) {
			return;
		}
		var frame = window.wp.media( {
			title: button.getAttribute( 'data-title' ),
			button: { text: button.getAttribute( 'data-button' ) },
			library: { type: 'image' },
			multiple: false,
		} );
		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();
			var url = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
			var img = document.createElement( 'img' );
			img.src = url;
			img.alt = '';
			input.value = String( attachment.id );
			preview.textContent = '';
			preview.appendChild( img );
			if ( remove ) {
				remove.hidden = false;
			}
		} );
		frame.open();
	} );
} )();
