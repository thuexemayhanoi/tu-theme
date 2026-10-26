/**
 * Theme settings screen: media pickers for the hero and story photos (wp.media).
 */
( function () {
	'use strict';

	if ( ! window.wp || ! window.wp.media ) {
		return;
	}

	var l10n = window.mrTuSettingsL10n || {};

	document.querySelectorAll( '[data-mr-tu-media]' ).forEach( function ( field ) {
		var input = field.querySelector( '[data-mr-tu-media-input]' );
		var preview = field.querySelector( '[data-mr-tu-media-preview]' );
		var choose = field.querySelector( '[data-mr-tu-media-choose]' );
		var remove = field.querySelector( '[data-mr-tu-media-remove]' );
		var empty = preview.innerHTML;
		var frame = null;

		function render( attachment ) {
			var size = attachment.sizes && ( attachment.sizes.medium || attachment.sizes.full );
			var img = document.createElement( 'img' );
			img.className = 'mr-tu-media__img';
			img.src = size ? size.url : attachment.url;
			img.alt = attachment.alt || '';
			preview.innerHTML = '';
			preview.appendChild( img );
		}

		choose.addEventListener( 'click', function () {
			if ( ! frame ) {
				frame = window.wp.media( {
					title: l10n.title,
					button: { text: l10n.button },
					library: { type: 'image' },
					multiple: false,
				} );
				frame.on( 'select', function () {
					var attachment = frame.state().get( 'selection' ).first().toJSON();
					input.value = attachment.id;
					render( attachment );
					remove.hidden = false;
				} );
			}
			frame.open();
		} );

		remove.addEventListener( 'click', function () {
			input.value = '0';
			preview.innerHTML = empty;
			remove.hidden = true;
			choose.focus();
		} );
	} );
} )();
