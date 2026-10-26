/**
 * Interaction QA for the Mr Tu theme: mobile menu dialog (focus trap, Esc, backdrop, return focus,
 * scroll lock), colour mode persistence without flash, language/More popovers, header elevation,
 * skip link. Exits non-zero on failure.
 *
 * Usage: node tests/e2e/theme/interactions.cjs
 */
'use strict';

const fs = require( 'fs' );
const path = require( 'path' );
const { chromium } = require( 'playwright' );
const { BASE, ARTIFACTS, newContext } = require( './lib.cjs' );

const results = [];
function check( name, ok, detail = '' ) {
	results.push( { name, ok: !! ok, detail } );
	console.log( `${ ok ? 'PASS' : 'FAIL' }  ${ name }${ detail ? ' — ' + detail : '' }` );
}

( async () => {
	fs.mkdirSync( ARTIFACTS, { recursive: true } );
	const browser = await chromium.launch();

	/* ---------------- Mobile menu sheet ---------------- */
	for ( const mode of [ 'light', 'dark' ] ) {
		const context = await newContext( browser, { width: 390, height: 844, mobile: true }, mode );
		const page = await context.newPage();
		const errors = [];
		page.on( 'pageerror', ( e ) => errors.push( e.message ) );
		await page.goto( BASE + '/motorbike-rental-hanoi/', { waitUntil: 'networkidle' } );

		const opener = page.locator( '[data-mrtu-menu-open]' );
		await opener.click();
		await page.waitForTimeout( 450 );
		const state = await page.evaluate( () => {
			const d = document.querySelector( '[data-mrtu-menu]' );
			return {
				open: d.open,
				modal: d.matches( ':modal' ),
				ariaModal: d.getAttribute( 'aria-modal' ),
				labelled: !! document.getElementById( d.getAttribute( 'aria-labelledby' ) ),
				focusInside: d.contains( document.activeElement ),
				expanded: document.querySelector( '[data-mrtu-menu-open]' ).getAttribute( 'aria-expanded' ),
				locked: document.documentElement.classList.contains( 'is-locked' ),
				bodyScrollable: getComputedStyle( document.documentElement ).overflow,
			};
		} );
		check( `[${ mode }] menu opens as modal dialog`, state.open && state.modal && state.ariaModal === 'true' && state.labelled, JSON.stringify( state ) );
		check( `[${ mode }] focus moves into the dialog`, state.focusInside );
		check( `[${ mode }] opener reports aria-expanded=true`, state.expanded === 'true' );
		check( `[${ mode }] page scroll is locked`, state.locked && state.bodyScrollable === 'hidden' );
		await page.screenshot( { path: path.join( ARTIFACTS, `menu-sheet-390-${ mode }.png` ) } );

		// Focus trap: Tab and Shift+Tab never leave the dialog.
		let escaped = 0;
		for ( let i = 0; i < 30; i++ ) {
			await page.keyboard.press( i % 7 === 6 ? 'Shift+Tab' : 'Tab' );
			const inside = await page.evaluate( () => document.querySelector( '[data-mrtu-menu]' ).contains( document.activeElement ) );
			if ( ! inside ) {
				escaped++;
			}
		}
		check( `[${ mode }] focus trapped across 30 Tab presses`, escaped === 0, `${ escaped } escapes` );

		await page.keyboard.press( 'Escape' );
		await page.waitForTimeout( 400 );
		const after = await page.evaluate( () => ( {
			open: document.querySelector( '[data-mrtu-menu]' ).open,
			focusOnOpener: document.activeElement === document.querySelector( '[data-mrtu-menu-open]' ),
			locked: document.documentElement.classList.contains( 'is-locked' ),
			expanded: document.querySelector( '[data-mrtu-menu-open]' ).getAttribute( 'aria-expanded' ),
		} ) );
		check( `[${ mode }] Esc closes the dialog`, ! after.open, JSON.stringify( after ) );
		check( `[${ mode }] focus returns to the menu button`, after.focusOnOpener );
		check( `[${ mode }] scroll lock released`, ! after.locked && after.expanded === 'false' );

		// Backdrop click closes.
		await opener.click();
		await page.waitForTimeout( 400 );
		await page.mouse.click( 195, 20 );
		await page.waitForTimeout( 400 );
		check( `[${ mode }] backdrop tap closes the dialog`, ! ( await page.evaluate( () => document.querySelector( '[data-mrtu-menu]' ).open ) ) );
		check( `[${ mode }] no page errors`, errors.length === 0, errors.join( ' | ' ) );
		await context.close();
	}

	/* ---------------- Colour mode persistence (no flash) ---------------- */
	{
		const context = await browser.newContext( { viewport: { width: 1280, height: 800 }, colorScheme: 'light' } );
		await context.addInitScript( () => {
			new MutationObserver( ( records, observer ) => {
				if ( document.body ) {
					window.__mrtuThemeAtBody = document.documentElement.getAttribute( 'data-theme' );
					observer.disconnect();
				}
			} ).observe( document, { childList: true, subtree: true } );
		} );
		const page = await context.newPage();
		await page.goto( BASE + '/', { waitUntil: 'networkidle' } );
		check( 'mode: default is system (no data-theme)', ( await page.evaluate( () => document.documentElement.getAttribute( 'data-theme' ) ) ) === null );

		await page.click( '.site-header [data-mrtu-mode-set="dark"]' );
		await page.waitForTimeout( 300 );
		const pressed = await page.evaluate( () => [ ...document.querySelectorAll( '[data-mrtu-mode-set="dark"]' ) ].every( ( b ) => b.getAttribute( 'aria-pressed' ) === 'true' ) );
		check( 'mode: dark applied and every toggle shows aria-pressed', pressed && ( await page.evaluate( () => document.documentElement.dataset.theme ) ) === 'dark' );
		check( 'mode: stored in localStorage', ( await page.evaluate( () => localStorage.getItem( 'mrtu-theme' ) ) ) === 'dark' );
		check( 'mode: meta theme-color follows', ( await page.evaluate( () => [ ...document.querySelectorAll( 'meta[name="theme-color"]' ) ].every( ( m ) => m.content === '#101114' ) ) ) );

		await page.reload( { waitUntil: 'networkidle' } );
		const atBody = await page.evaluate( () => window.__mrtuThemeAtBody );
		check( 'mode: dark persists across reload, set before first paint', atBody === 'dark', `data-theme at <body> parse = ${ atBody }` );
		const bg = await page.evaluate( () => getComputedStyle( document.body ).backgroundColor );
		check( 'mode: dark background rendered', bg === 'rgb(16, 17, 20)', bg );

		await page.goto( BASE + '/motorbike-rental-hanoi/', { waitUntil: 'networkidle' } );
		check( 'mode: persists across navigation', ( await page.evaluate( () => window.__mrtuThemeAtBody ) ) === 'dark' );

		await page.click( '.site-header [data-mrtu-mode-set="light"]' );
		await page.reload( { waitUntil: 'networkidle' } );
		check( 'mode: light persists (overrides system)', ( await page.evaluate( () => window.__mrtuThemeAtBody ) ) === 'light' );

		await page.click( '.site-header [data-mrtu-mode-set="system"]' );
		await page.reload( { waitUntil: 'networkidle' } );
		check( 'mode: system removes the attribute', ( await page.evaluate( () => window.__mrtuThemeAtBody ) ) === null );

		/* Popovers */
		await page.click( '.site-header .mrtu-lang__toggle' );
		check( 'language popover opens', await page.evaluate( () => document.querySelector( '.site-header .mrtu-lang' ).open ) );
		const langs = await page.evaluate( () => [ ...document.querySelectorAll( '.site-header .mrtu-lang__link' ) ].map( ( a ) => ( { lang: a.querySelector( '.mrtu-lang__name' ).getAttribute( 'lang' ), current: a.getAttribute( 'aria-current' ), fallback: a.hasAttribute( 'data-fallback' ) } ) ) );
		check( 'language list: native names with lang, one current, fallbacks labelled', langs.length === 5 && langs.filter( ( l ) => l.current === 'true' ).length === 1 && langs.every( ( l ) => l.lang ), JSON.stringify( langs ) );
		await page.keyboard.press( 'Escape' );
		const langClosed = await page.evaluate( () => ( { open: document.querySelector( '.site-header .mrtu-lang' ).open, focus: document.activeElement.classList.contains( 'mrtu-lang__toggle' ) } ) );
		check( 'language popover closes on Esc and returns focus', ! langClosed.open && langClosed.focus );

		await page.click( '.site-nav__summary' );
		check( 'More dropdown opens', await page.evaluate( () => document.querySelector( '.site-nav__group' ).open ) );
		await page.mouse.click( 640, 500 );
		check( 'More dropdown closes on outside click', ! ( await page.evaluate( () => document.querySelector( '.site-nav__group' ).open ) ) );

		/* Header elevation */
		await page.evaluate( () => window.scrollTo( 0, 600 ) );
		await page.waitForTimeout( 200 );
		check( 'header elevates on scroll', await page.evaluate( () => document.querySelector( '[data-mrtu-header]' ).classList.contains( 'is-scrolled' ) ) );

		/* Skip link */
		await page.goto( BASE + '/', { waitUntil: 'networkidle' } );
		await page.keyboard.press( 'Tab' );
		await page.waitForTimeout( 350 ); // slide-in transition
		const skip = await page.evaluate( () => {
			const a = document.activeElement;
			const r = a.getBoundingClientRect();
			return { cls: a.className, visible: r.top >= 0 && r.bottom > 0 };
		} );
		check( 'skip link is first tab stop and visible on focus', skip.cls === 'skip-link' && skip.visible, JSON.stringify( skip ) );
		await page.keyboard.press( 'Enter' );
		await page.waitForTimeout( 400 );
		check( 'skip link moves focus to main', await page.evaluate( () => document.activeElement.id === 'main' ) );
		await context.close();
	}

	await browser.close();
	const failed = results.filter( ( r ) => ! r.ok );
	fs.writeFileSync( path.join( ARTIFACTS, 'interactions.json' ), JSON.stringify( results, null, 2 ) );
	console.log( `\n${ results.length - failed.length }/${ results.length } interaction checks passed` );
	process.exit( failed.length ? 1 : 0 );
} )();
