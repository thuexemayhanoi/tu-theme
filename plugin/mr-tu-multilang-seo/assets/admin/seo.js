/**
 * SEO snippet preview (no dependencies).
 * Inside a form, [data-mrtu-source="title"] / [data-mrtu-source="desc"] inputs update the nearest
 * [data-mrtu-snippet] preview; empty values fall back to the data-mrtu-fallback text.
 */
( function () {
	'use strict';

	function trim( value ) {
		return ( value || '' ).replace( /\s+/g, ' ' ).trim();
	}

	function bind( input ) {
		var box = input.closest( '.mrtu-metabox, .mrtu-admin' );
		if ( ! box ) {
			return;
		}
		var source = input.getAttribute( 'data-mrtu-source' );
		var target = box.querySelector( 'title' === source ? '[data-mrtu-snippet-title]' : '[data-mrtu-snippet-desc]' );
		if ( ! target ) {
			return;
		}
		var fallback = target.getAttribute( 'data-mrtu-fallback' ) || '';
		var brand    = input.getAttribute( 'data-mrtu-brand' ) || '';
		function update() {
			var value = trim( input.value );
			if ( '' === value ) {
				target.textContent = fallback;
				return;
			}
			target.textContent = 'title' === source && brand && value.indexOf( trim( brand ) ) === -1 ? value + brand : value;
		}
		input.addEventListener( 'input', update );
		update();
	}

	document.querySelectorAll( '[data-mrtu-source]' ).forEach( bind );
} )();
