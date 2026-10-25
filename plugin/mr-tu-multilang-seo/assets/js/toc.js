/*!
 * Mr Tu — table of contents behaviour (contract §2.9). No dependencies, loaded with `defer`.
 * - Scroll-spy (IntersectionObserver): aria-current="true" on the active link in every [data-mrtu-toc]
 *   and [data-mrtu-toc-aside].
 * - Reading progress: --mrtu-read-progress (0–1, 3 decimals) on <html>, passive scroll + rAF.
 * - Link click: closes the inline TOC below 1024px and moves focus to the heading (tabindex=-1 on demand).
 *   Scrolling stays native, so the theme's CSS `scroll-behavior` (and prefers-reduced-motion) decides how.
 */
( function () {
	'use strict';

	var doc = document;
	var root = doc.documentElement;
	var win = window;
	var SELECTOR = '[data-mrtu-toc] a[href^="#"],[data-mrtu-toc-aside] a[href^="#"]';
	var links = [];
	var heads = [];
	var active;
	var progress = 0;
	var ticking = false;

	function targetOf( link ) {
		var id = link.getAttribute( 'href' ).slice( 1 );
		try {
			id = decodeURIComponent( id );
		} catch ( e ) {}
		return id ? doc.getElementById( id ) : null;
	}

	function setActive( heading ) {
		if ( heading === active ) {
			return;
		}
		active = heading;
		links.forEach( function ( link ) {
			if ( link.mrtuTarget === heading ) {
				link.setAttribute( 'aria-current', 'true' );
			} else {
				link.removeAttribute( 'aria-current' );
			}
		} );
	}

	// Active = last heading above the 30% line; at the very bottom, the last heading in view.
	function spy() {
		var line = win.innerHeight * ( progress > 0.995 ? 1 : 0.3 );
		var current = null;
		for ( var i = 0; i < heads.length && heads[ i ].getBoundingClientRect().top <= line; i++ ) {
			current = heads[ i ];
		}
		setActive( current );
	}

	function measure() {
		var max = root.scrollHeight - win.innerHeight;
		ticking = false;
		progress = max > 0 ? Math.min( 1, Math.max( 0, win.pageYOffset / max ) ) : 0;
		root.style.setProperty( '--mrtu-read-progress', progress.toFixed( 3 ) );
		if ( progress > 0.995 ) {
			spy();
		}
	}

	function onScroll() {
		if ( ! ticking ) {
			ticking = true;
			win.requestAnimationFrame( measure );
		}
	}

	function onClick( event ) {
		var link = event.target.closest ? event.target.closest( SELECTOR ) : null;
		var heading = link && ( link.mrtuTarget || targetOf( link ) );
		var details;
		if ( ! heading || event.defaultPrevented || event.button || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey ) {
			return; // Modified clicks open a new tab/window: leave this page alone.
		}
		details = link.closest( 'details[data-mrtu-toc]' );
		if ( details && win.matchMedia( '(max-width: 1023.98px)' ).matches ) {
			details.open = false;
		}
		if ( ! heading.hasAttribute( 'tabindex' ) ) {
			heading.setAttribute( 'tabindex', '-1' );
		}
		heading.focus( { preventScroll: true } ); // The native hash navigation that follows does the scrolling.
	}

	function init() {
		var observer;
		[].forEach.call( doc.querySelectorAll( SELECTOR ), function ( link ) {
			var heading = targetOf( link );
			if ( heading ) {
				link.mrtuTarget = heading;
				links.push( link );
				if ( heads.indexOf( heading ) < 0 ) {
					heads.push( heading );
				}
			}
		} );
		heads.sort( function ( a, b ) {
			return a.compareDocumentPosition( b ) & 4 ? -1 : 1;
		} );

		measure();
		win.addEventListener( 'scroll', onScroll, { passive: true } );
		win.addEventListener( 'resize', onScroll, { passive: true } );
		doc.addEventListener( 'click', onClick );

		if ( heads.length && 'IntersectionObserver' in win ) {
			observer = new win.IntersectionObserver( spy, { rootMargin: '0px 0px -70% 0px' } );
			heads.forEach( function ( heading ) {
				observer.observe( heading );
			} );
		}
	}

	if ( 'loading' === doc.readyState ) {
		doc.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
