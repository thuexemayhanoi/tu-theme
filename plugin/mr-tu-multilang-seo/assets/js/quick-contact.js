/**
 * Mr Tu Quick Contact dock.
 *
 * - Toggle opens/closes the panel (aria-expanded), Esc and outside click close it; focus moves to the first
 *   item on open and back to the toggle on Esc. ArrowUp/ArrowDown move between items.
 * - .is-compact after scrolling down 240px (label collapses, icon stays) when data-collapse="true".
 * - .is-hidden (+ inert) while a [data-mrtu-qc-avoid] element intersects the viewport, while a text field is
 *   focused (mobile keyboard) and while the assistant is open (mrtu:assistant-opened / mrtu:assistant-closed).
 * - The chat item dispatches document CustomEvent 'mrtu:assistant-open'.
 */
( function () {
	'use strict';

	var root = document.querySelector( '[data-mrtu-qc]' );
	if ( ! root || root.getAttribute( 'data-mrtu-ready' ) ) {
		return;
	}
	var toggle = root.querySelector( '.mrtu-qc__toggle' );
	var panel = root.querySelector( '.mrtu-qc__panel' );
	if ( ! toggle || ! panel ) {
		return;
	}
	root.setAttribute( 'data-mrtu-ready', '1' );

	var isOpen = false;
	var reasons = { avoid: false, typing: false, assistant: false };
	var TEXT_INPUT = /^(?:text|search|email|tel|url|password|number|date|datetime-local|month|time|week)$/;

	function items() {
		return Array.prototype.slice.call( panel.querySelectorAll( '.mrtu-qc__item' ) );
	}

	function open( focusFirst ) {
		if ( isOpen || root.classList.contains( 'is-hidden' ) ) {
			return;
		}
		isOpen = true;
		panel.hidden = false;
		root.classList.add( 'is-open' );
		toggle.setAttribute( 'aria-expanded', 'true' );
		if ( focusFirst ) {
			var list = items();
			if ( list.length ) {
				list[ 0 ].focus();
			}
		}
	}

	function close( returnFocus ) {
		if ( ! isOpen ) {
			return;
		}
		isOpen = false;
		panel.hidden = true;
		root.classList.remove( 'is-open' );
		toggle.setAttribute( 'aria-expanded', 'false' );
		if ( returnFocus ) {
			toggle.focus();
		}
	}

	function update() {
		var hidden = reasons.avoid || reasons.typing || reasons.assistant;
		if ( hidden ) {
			close( false );
		}
		root.classList.toggle( 'is-hidden', hidden );
		if ( hidden ) {
			root.setAttribute( 'inert', '' );
			root.setAttribute( 'aria-hidden', 'true' );
		} else {
			root.removeAttribute( 'inert' );
			root.removeAttribute( 'aria-hidden' );
		}
	}

	function setReason( name, value ) {
		if ( reasons[ name ] !== value ) {
			reasons[ name ] = value;
			update();
		}
	}

	/* Open / close. */
	toggle.addEventListener( 'click', function () {
		if ( isOpen ) {
			close( false );
		} else {
			open( true );
		}
	} );

	document.addEventListener( 'keydown', function ( event ) {
		if ( ! isOpen ) {
			return;
		}
		if ( 'Escape' === event.key || 'Esc' === event.key ) {
			event.preventDefault();
			close( root.contains( document.activeElement ) );
			return;
		}
		if ( 'ArrowDown' === event.key || 'ArrowUp' === event.key ) {
			var list = items();
			var index = list.indexOf( document.activeElement );
			if ( -1 === index ) {
				return;
			}
			event.preventDefault();
			var next = 'ArrowDown' === event.key ? ( index + 1 ) % list.length : ( index - 1 + list.length ) % list.length;
			list[ next ].focus();
		}
	} );

	root.addEventListener( 'focusout', function ( event ) {
		if ( isOpen && event.relatedTarget && ! root.contains( event.relatedTarget ) ) {
			close( false );
		}
	} );

	document.addEventListener( 'click', function ( event ) {
		if ( isOpen && ! root.contains( event.target ) ) {
			close( false );
		}
	} );

	panel.addEventListener( 'click', function ( event ) {
		var target = event.target.closest ? event.target : null;
		if ( ! target ) {
			return;
		}
		if ( target.closest( '[data-mrtu-assistant-open]' ) ) {
			close( false );
			document.dispatchEvent( new CustomEvent( 'mrtu:assistant-open', { detail: { source: 'quick-contact' } } ) );
		} else if ( target.closest( 'a' ) ) {
			close( false );
		}
	} );

	/* Compact after scrolling down 240px. */
	if ( 'true' === root.getAttribute( 'data-collapse' ) ) {
		var ticking = false;
		var onScroll = function () {
			if ( ticking ) {
				return;
			}
			ticking = true;
			window.requestAnimationFrame( function () {
				ticking = false;
				var y = window.scrollY || window.pageYOffset || 0;
				root.classList.toggle( 'is-compact', y > 240 );
			} );
		};
		window.addEventListener( 'scroll', onScroll, { passive: true } );
		onScroll();
	}

	/* Hide while avoid zones (footer contact, forms) are visible. */
	var zones = Array.prototype.slice.call( document.querySelectorAll( '[data-mrtu-qc-avoid]' ) );
	if ( zones.length && 'IntersectionObserver' in window ) {
		var visible = [];
		var observer = new IntersectionObserver( function ( entries ) {
			entries.forEach( function ( entry ) {
				var i = visible.indexOf( entry.target );
				if ( entry.isIntersecting && -1 === i ) {
					visible.push( entry.target );
				} else if ( ! entry.isIntersecting && -1 !== i ) {
					visible.splice( i, 1 );
				}
			} );
			setReason( 'avoid', visible.length > 0 );
		} );
		zones.forEach( function ( zone ) {
			observer.observe( zone );
		} );
	}

	/* Hide while typing (mobile keyboard). */
	function isTyping( el ) {
		if ( ! el || ! el.tagName || root.contains( el ) ) {
			return false;
		}
		if ( el.isContentEditable ) {
			return true;
		}
		if ( 'TEXTAREA' === el.tagName || 'SELECT' === el.tagName ) {
			return true;
		}
		if ( 'INPUT' === el.tagName ) {
			return TEXT_INPUT.test( ( el.getAttribute( 'type' ) || 'text' ).toLowerCase() );
		}
		return false;
	}
	document.addEventListener( 'focusin', function ( event ) {
		setReason( 'typing', isTyping( event.target ) );
	} );
	document.addEventListener( 'focusout', function () {
		window.setTimeout( function () {
			setReason( 'typing', isTyping( document.activeElement ) );
		}, 0 );
	} );

	/* Hide while the assistant panel is open. */
	document.addEventListener( 'mrtu:assistant-opened', function () {
		setReason( 'assistant', true );
	} );
	document.addEventListener( 'mrtu:assistant-closed', function () {
		setReason( 'assistant', false );
	} );
} )();
