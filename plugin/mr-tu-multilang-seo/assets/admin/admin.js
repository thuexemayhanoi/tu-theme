/**
 * Mr Tu admin helpers (no dependencies).
 * - Character counters: <input data-mrtu-count="60"> shows "n/60" and flags overflow.
 * - Confirm dangerous actions: <button data-mrtu-confirm="Message">.
 */
( function () {
	'use strict';

	function counter( input ) {
		var max = parseInt( input.getAttribute( 'data-mrtu-count' ), 10 );
		if ( ! max ) {
			return;
		}
		var out = document.createElement( 'span' );
		out.className = 'mrtu-counter';
		out.setAttribute( 'aria-live', 'polite' );
		input.insertAdjacentElement( 'afterend', out );
		function update() {
			var len = Array.from( input.value || '' ).length;
			out.textContent = len + '/' + max;
			out.classList.toggle( 'is-over', len > max );
		}
		input.addEventListener( 'input', update );
		update();
	}

	document.querySelectorAll( '[data-mrtu-count]' ).forEach( counter );

	document.addEventListener( 'click', function ( event ) {
		var el = event.target.closest( '[data-mrtu-confirm]' );
		if ( el && ! window.confirm( el.getAttribute( 'data-mrtu-confirm' ) ) ) {
			event.preventDefault();
		}
	} );
} )();
