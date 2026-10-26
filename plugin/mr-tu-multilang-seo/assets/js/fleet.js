/**
 * Mr Tu fleet filter chips: aria-pressed toggle buttons that hide non-matching models and
 * announce the visible count in a polite, visually hidden live region.
 */
( function () {
	'use strict';

	function init( root ) {
		if ( root.getAttribute( 'data-mrtu-ready' ) ) {
			return;
		}
		root.setAttribute( 'data-mrtu-ready', '1' );

		var buttons = Array.prototype.slice.call( root.querySelectorAll( '.mrtu-fleet__filter' ) );
		if ( ! buttons.length ) {
			return;
		}
		var items = Array.prototype.slice.call( root.querySelectorAll( '.mrtu-vehicle' ) );
		var live = root.querySelector( '.mrtu-fleet__status' );
		if ( ! live ) {
			live = document.createElement( 'p' );
			live.className = 'mrtu-fleet__status screen-reader-text';
			live.setAttribute( 'aria-live', 'polite' );
			live.setAttribute( 'aria-atomic', 'true' );
			root.insertBefore( live, root.firstChild );
		}
		var template = root.getAttribute( 'data-count-template' ) || '%d';

		function apply( filter, announce ) {
			var count = 0;
			items.forEach( function ( item ) {
				var show = 'all' === filter || item.getAttribute( 'data-category' ) === filter;
				item.hidden = ! show;
				if ( show ) {
					count++;
				}
			} );
			buttons.forEach( function ( button ) {
				button.setAttribute( 'aria-pressed', button.getAttribute( 'data-filter' ) === filter ? 'true' : 'false' );
			} );
			if ( announce ) {
				live.textContent = template.replace( '%d', String( count ) );
			}
		}

		root.addEventListener( 'click', function ( event ) {
			var button = event.target.closest ? event.target.closest( '.mrtu-fleet__filter' ) : null;
			if ( ! button || ! root.contains( button ) ) {
				return;
			}
			apply( button.getAttribute( 'data-filter' ) || 'all', true );
		} );

		apply( 'all', false );
	}

	function boot() {
		Array.prototype.forEach.call( document.querySelectorAll( '[data-mrtu-fleet]' ), init );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
