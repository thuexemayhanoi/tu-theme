/**
 * Mr Tu price table tabs (WAI-ARIA tabs pattern, automatic activation).
 * Roving tabindex, ArrowLeft/ArrowRight (wrapping), Home/End; toggles .is-active + [hidden] on panels.
 * Without JS every panel is visible and the tab list is hidden by CSS.
 */
( function () {
	'use strict';

	function init( root ) {
		if ( root.getAttribute( 'data-mrtu-ready' ) ) {
			return;
		}
		root.setAttribute( 'data-mrtu-ready', '1' );

		var tabs = Array.prototype.slice.call( root.querySelectorAll( '[role="tab"]' ) );
		if ( ! tabs.length ) {
			return;
		}
		var panels = tabs.map( function ( tab ) {
			return document.getElementById( tab.getAttribute( 'aria-controls' ) );
		} );

		function select( index, focus ) {
			tabs.forEach( function ( tab, i ) {
				var on = i === index;
				tab.setAttribute( 'aria-selected', on ? 'true' : 'false' );
				tab.setAttribute( 'tabindex', on ? '0' : '-1' );
				tab.classList.toggle( 'is-active', on );
				if ( panels[ i ] ) {
					panels[ i ].classList.toggle( 'is-active', on );
					panels[ i ].hidden = ! on;
				}
			} );
			if ( focus ) {
				tabs[ index ].focus();
			}
		}

		var initial = 0;
		tabs.forEach( function ( tab, i ) {
			if ( 'true' === tab.getAttribute( 'aria-selected' ) ) {
				initial = i;
			}
		} );
		select( initial, false );

		tabs.forEach( function ( tab, i ) {
			tab.addEventListener( 'click', function () {
				select( i, true );
			} );
			tab.addEventListener( 'keydown', function ( event ) {
				var last = tabs.length - 1;
				var next = null;
				switch ( event.key ) {
					case 'ArrowRight':
						next = i === last ? 0 : i + 1;
						break;
					case 'ArrowLeft':
						next = 0 === i ? last : i - 1;
						break;
					case 'Home':
						next = 0;
						break;
					case 'End':
						next = last;
						break;
				}
				if ( null !== next ) {
					event.preventDefault();
					select( next, true );
				}
			} );
		} );
	}

	function boot() {
		Array.prototype.forEach.call( document.querySelectorAll( '[data-mrtu-pricing]' ), init );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
