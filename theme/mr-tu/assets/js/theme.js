/**
 * Mr Tu theme script — vanilla, deferred, progressive enhancement only.
 *
 * - Colour mode (System · Light · Dark) stored in localStorage['mrtu-theme'];
 *   the inline head script already applied it before the first paint.
 * - Mobile menu sheet (<dialog>): focus trap, Esc, backdrop, return focus, scroll lock.
 * - <details data-mrtu-popover> dropdowns: outside click, Esc, one open at a time.
 * - Header elevation, sticky TOC scroll-spy, reveal-on-scroll, in-page anchor offset + focus.
 */
( function () {
	'use strict';

	var doc = document;
	var root = doc.documentElement;
	var KEY = 'mrtu-theme';
	var reduced = window.matchMedia( '(prefers-reduced-motion: reduce)' );

	function all( selector, scope ) {
		return Array.prototype.slice.call( ( scope || doc ).querySelectorAll( selector ) );
	}

	/* Scrollbar width (scroll lock without layout jump; full-bleed blocks). */
	function measureScrollbar() {
		if ( ! root.classList.contains( 'is-locked' ) ) {
			root.style.setProperty( '--sbw', Math.max( 0, window.innerWidth - root.clientWidth ) + 'px' );
		}
	}
	measureScrollbar();
	window.addEventListener( 'resize', measureScrollbar, { passive: true } );

	/* Colour mode ----------------------------------------------------------- */
	function applyMode( mode, persist ) {
		if ( 'light' !== mode && 'dark' !== mode ) {
			mode = 'system';
		}
		if ( 'system' === mode ) {
			root.removeAttribute( 'data-theme' );
		} else {
			root.setAttribute( 'data-theme', mode );
		}
		root.setAttribute( 'data-mode', mode );
		all( 'meta[name="theme-color"]' ).forEach( function ( meta ) {
			var dark = 'dark' === mode || ( 'system' === mode && /dark/.test( meta.media ) );
			meta.content = dark ? '#101114' : '#FFFFFF';
		} );
		all( '[data-mrtu-mode-set]' ).forEach( function ( button ) {
			button.setAttribute( 'aria-pressed', button.getAttribute( 'data-mrtu-mode-set' ) === mode ? 'true' : 'false' );
		} );
		if ( persist ) {
			try {
				localStorage.setItem( KEY, mode );
			} catch ( e ) {}
		}
	}

	applyMode( root.getAttribute( 'data-mode' ), false );

	doc.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '[data-mrtu-mode-set]' );
		if ( ! button ) {
			return;
		}
		var mode = button.getAttribute( 'data-mrtu-mode-set' );
		if ( doc.startViewTransition && ! reduced.matches ) {
			doc.startViewTransition( function () {
				applyMode( mode, true );
			} );
		} else {
			applyMode( mode, true );
		}
	} );

	window.addEventListener( 'storage', function ( event ) {
		if ( KEY === event.key ) {
			applyMode( event.newValue, false );
		}
	} );

	/* Menu sheet ------------------------------------------------------------------ */
	var sheet = doc.querySelector( '[data-mrtu-menu]' );
	var openers = all( '[data-mrtu-menu-open]' );
	var returnFocus = null;

	function focusables( scope ) {
		return all( 'a[href], button:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex="-1"])', scope ).filter( function ( el ) {
			return el.getClientRects().length > 0;
		} );
	}

	function setExpanded( state ) {
		openers.forEach( function ( button ) {
			button.setAttribute( 'aria-expanded', state ? 'true' : 'false' );
		} );
	}

	function afterClose() {
		sheet.classList.remove( 'is-closing' );
		root.classList.remove( 'is-locked' );
		setExpanded( false );
		if ( returnFocus && returnFocus.focus ) {
			returnFocus.focus();
		}
		returnFocus = null;
	}

	function openSheet() {
		if ( ! sheet || sheet.open ) {
			return;
		}
		returnFocus = doc.activeElement;
		measureScrollbar();
		root.classList.add( 'is-locked' );
		if ( 'function' === typeof sheet.showModal ) {
			sheet.showModal();
		} else {
			sheet.setAttribute( 'open', '' );
		}
		setExpanded( true );
		var close = sheet.querySelector( '[data-mrtu-menu-close]' );
		if ( close ) {
			close.focus();
		}
	}

	function closeSheet() {
		if ( ! sheet || ! sheet.open || sheet.classList.contains( 'is-closing' ) ) {
			return;
		}
		var finish = function () {
			if ( 'function' === typeof sheet.close ) {
				sheet.close();
			} else {
				sheet.removeAttribute( 'open' );
				afterClose();
			}
		};
		if ( reduced.matches ) {
			finish();
			return;
		}
		sheet.classList.add( 'is-closing' );
		window.setTimeout( finish, 210 );
	}

	if ( sheet ) {
		openers.forEach( function ( button ) {
			button.addEventListener( 'click', openSheet );
		} );
		sheet.addEventListener( 'close', afterClose );
		sheet.addEventListener( 'cancel', function ( event ) {
			event.preventDefault();
			closeSheet();
		} );
		sheet.addEventListener( 'click', function ( event ) {
			var link = event.target.closest( 'a[href]' );
			if ( event.target === sheet || event.target.closest( '[data-mrtu-menu-close]' ) ) {
				closeSheet();
			} else if ( link && link.hash && link.pathname === location.pathname ) {
				closeSheet();
			}
		} );
		sheet.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key ) {
				event.preventDefault();
				closeSheet();
				return;
			}
			if ( 'Tab' !== event.key ) {
				return;
			}
			var items = focusables( sheet );
			if ( ! items.length ) {
				return;
			}
			var first = items[ 0 ];
			var last = items[ items.length - 1 ];
			if ( event.shiftKey && ( doc.activeElement === first || ! sheet.contains( doc.activeElement ) ) ) {
				event.preventDefault();
				last.focus();
			} else if ( ! event.shiftKey && doc.activeElement === last ) {
				event.preventDefault();
				first.focus();
			}
		} );
		window.matchMedia( '(min-width: 1024px)' ).addEventListener( 'change', function ( event ) {
			if ( event.matches ) {
				closeSheet();
			}
		} );
	}

	/* Dropdowns (<details data-mrtu-popover>) ------------------------------------ */
	var popovers = all( 'details[data-mrtu-popover]' );

	popovers.forEach( function ( details ) {
		details.addEventListener( 'toggle', function () {
			if ( details.open ) {
				popovers.forEach( function ( other ) {
					if ( other !== details ) {
						other.open = false;
					}
				} );
			}
		} );
		details.addEventListener( 'focusout', function ( event ) {
			if ( details.open && event.relatedTarget && ! details.contains( event.relatedTarget ) ) {
				details.open = false;
			}
		} );
	} );

	doc.addEventListener( 'click', function ( event ) {
		popovers.forEach( function ( details ) {
			if ( details.open && ! details.contains( event.target ) ) {
				details.open = false;
			}
		} );
	} );

	doc.addEventListener( 'keydown', function ( event ) {
		if ( 'Escape' !== event.key ) {
			return;
		}
		popovers.forEach( function ( details ) {
			if ( details.open ) {
				details.open = false;
				details.querySelector( 'summary' ).focus();
			}
		} );
	} );

	/* Priority+ navigation: trailing items move into "More" while the bar overflows ---------- */
	var navList = doc.querySelector( '.site-nav__list' );
	var moreItem = navList ? navList.querySelector( '.site-nav__item--more' ) : null;
	var morePanel = moreItem ? moreItem.querySelector( '.site-nav__panel' ) : null;

	if ( navList && moreItem && morePanel ) {
		var moreSummary = moreItem.querySelector( 'summary' );
		var overflowed = [];
		var fitting = false;

		function syncMore() {
			moreItem.hidden = 0 === morePanel.children.length;
			moreSummary.classList.toggle( 'is-current-parent', !! morePanel.querySelector( '.is-current' ) );
		}

		function overflows() {
			return navList.scrollWidth > navList.clientWidth + 1;
		}

		function moveIn() {
			var item = moreItem.previousElementSibling;
			if ( ! item ) {
				return false;
			}
			var link = item.querySelector( ':scope > .site-nav__link' );
			item.setAttribute( 'data-mrtu-width', String( item.getBoundingClientRect().width ) );
			if ( link ) {
				link.classList.remove( 'site-nav__link' );
				link.classList.add( 'site-nav__panel-link' );
			}
			morePanel.insertBefore( item, morePanel.firstChild );
			overflowed.push( item );
			syncMore();
			return true;
		}

		function moveOut() {
			var item = overflowed.pop();
			var link = item.querySelector( ':scope > .site-nav__panel-link' );
			if ( link ) {
				link.classList.remove( 'site-nav__panel-link' );
				link.classList.add( 'site-nav__link' );
			}
			navList.insertBefore( item, moreItem );
			syncMore();
		}

		function fit() {
			if ( fitting || ! navList.clientWidth ) {
				return;
			}
			fitting = true;
			var guard = 40;
			while ( guard-- && overflows() && moveIn() ) {} // eslint-disable-line no-empty
			while ( guard-- && overflowed.length ) {
				var next = overflowed[ overflowed.length - 1 ];
				var need = parseFloat( next.getAttribute( 'data-mrtu-width' ) ) || 0;
				if ( 1 === overflowed.length && 1 === morePanel.children.length ) {
					need -= moreItem.getBoundingClientRect().width; // "More" disappears with its last item.
				}
				if ( navList.scrollWidth + need > navList.clientWidth + 1 ) {
					break;
				}
				moveOut();
				if ( overflows() ) { // rounding: undo and stop.
					moveIn();
					break;
				}
			}
			fitting = false;
		}

		fit();
		if ( doc.fonts && doc.fonts.ready ) {
			doc.fonts.ready.then( fit );
		}
		if ( 'ResizeObserver' in window ) {
			new ResizeObserver( function () {
				window.requestAnimationFrame( fit );
			} ).observe( navList.parentNode );
		} else {
			window.addEventListener( 'resize', fit, { passive: true } );
		}
	}

	/* Scroll: header elevation + TOC scroll-spy ------------------------------------- */
	var header = doc.querySelector( '[data-mrtu-header]' );
	var spyLinks = all( '[data-mrtu-toc-aside] a[href^="#"]' );
	var spyHeads = spyLinks.map( function ( link ) {
		return doc.getElementById( decodeURIComponent( link.hash.slice( 1 ) ) );
	} );
	var ticking = false;

	function onScroll() {
		if ( ticking ) {
			return;
		}
		ticking = true;
		window.requestAnimationFrame( function () {
			ticking = false;
			if ( header ) {
				header.classList.toggle( 'is-scrolled', window.scrollY > 4 );
			}
			if ( spyLinks.length ) {
				var line = window.innerHeight * 0.3;
				var active = -1;
				spyHeads.forEach( function ( head, index ) {
					if ( head && head.getBoundingClientRect().top < line ) {
						active = index;
					}
				} );
				spyLinks.forEach( function ( link, index ) {
					if ( index === active ) {
						link.setAttribute( 'aria-current', 'true' );
					} else {
						link.removeAttribute( 'aria-current' );
					}
				} );
			}
		} );
	}
	window.addEventListener( 'scroll', onScroll, { passive: true } );
	onScroll();

	/* In-page anchors: offset for the sticky header, then move focus to the target. */
	doc.addEventListener( 'click', function ( event ) {
		var link = event.target.closest( 'a[href*="#"]' );
		if ( ! link || event.defaultPrevented || link.pathname !== location.pathname || link.host !== location.host || link.hash.length < 2 ) {
			return;
		}
		var target = doc.getElementById( decodeURIComponent( link.hash.slice( 1 ) ) );
		if ( ! target ) {
			return;
		}
		event.preventDefault();
		var offset = header ? header.getBoundingClientRect().height + 16 : 16;
		window.scrollTo( {
			top: Math.max( 0, target.getBoundingClientRect().top + window.scrollY - offset ),
			behavior: reduced.matches ? 'auto' : 'smooth',
		} );
		if ( history.pushState ) {
			history.pushState( null, '', link.hash );
		}
		if ( ! target.hasAttribute( 'tabindex' ) && ! /^(A|BUTTON|INPUT|SELECT|TEXTAREA)$/.test( target.tagName ) ) {
			target.setAttribute( 'tabindex', '-1' );
		}
		target.focus( { preventScroll: true } );
	} );

	/* Reveal: only elements below the fold at load are hidden, then shown once. ------ */
	if ( doc.body.classList.contains( 'mrtu-reveal' ) && 'IntersectionObserver' in window && ! reduced.matches ) {
		var targets = [];
		all( '.home-content > .mrtu-section, [data-reveal]' ).forEach( function ( el ) {
			if ( el.classList.contains( 'mrtu-section' ) ) {
				Array.prototype.forEach.call( el.children, function ( child, index ) {
					child.style.transitionDelay = Math.min( index, 5 ) * 60 + 'ms';
					targets.push( child );
				} );
			} else {
				targets.push( el );
			}
		} );
		var observer = new IntersectionObserver(
			function ( entries ) {
				entries.forEach( function ( entry ) {
					var el = entry.target;
					if ( entry.isIntersecting ) {
						observer.unobserve( el );
						el.classList.add( 'is-revealed' );
						el.classList.remove( 'is-reveal-pending' );
						// Hand the element back to its own transitions once it has arrived.
						window.setTimeout( function () {
							el.classList.remove( 'is-revealed' );
							el.style.transitionDelay = '';
						}, 1100 );
					}
				} );
			},
			{ threshold: 0.12, rootMargin: '0px 0px -4% 0px' }
		);
		var fold = window.innerHeight;
		// Read every position first, then write classes: interleaving reads and writes forces layout
		// recalculation for each element (Lighthouse "forced reflow").
		var below = targets.filter( function ( el ) {
			return el.getBoundingClientRect().top > fold;
		} );
		below.forEach( function ( el ) {
			el.classList.add( 'is-reveal-pending' );
			observer.observe( el );
		} );
		// Safety net: nothing may stay hidden if the observer never fires (printing, tall render
		// viewports, full-page captures, odd scroll containers). Elements still pending after the
		// grace period, or when printing starts, become visible without animation.
		var revealAll = function () {
			observer.disconnect();
			targets.forEach( function ( el ) {
				el.classList.remove( 'is-reveal-pending', 'is-revealed' );
				el.style.transitionDelay = '';
			} );
		};
		window.setTimeout( revealAll, 3000 );
		window.addEventListener( 'beforeprint', revealAll );
	}
} )();
