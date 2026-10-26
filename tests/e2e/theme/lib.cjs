/**
 * Shared helpers for the Mr Tu theme QA scripts (Playwright + Chromium).
 */
'use strict';

const path = require( 'path' );

const BASE = process.env.MRTU_BASE_URL || 'http://localhost:8081';
const ARTIFACTS = path.resolve( __dirname, '../artifacts/theme' );

const VIEWPORTS = [
	{ name: '360', width: 360, height: 780, mobile: true },
	{ name: '390', width: 390, height: 844, mobile: true },
	{ name: '430', width: 430, height: 932, mobile: true },
	{ name: '768', width: 768, height: 1024, mobile: true },
	{ name: '1280', width: 1280, height: 800, mobile: false },
	{ name: '1440', width: 1440, height: 900, mobile: false },
];

const PAGES = [
	{ name: 'home', path: '/' },
	{ name: 'page', path: '/motorbike-rental-hanoi/' },
	{ name: 'single', path: '/first-ride-in-the-old-quarter-a-calm-20-minute-loop/' },
	{ name: 'archive', path: '/blog/' },
	{ name: 'search', path: '/?s=bike' },
	{ name: '404', path: '/this-road-does-not-exist/' },
];

/**
 * New context with colour scheme + stored mode (light|dark) applied before any script runs.
 */
async function newContext( browser, viewport, mode, extra = {} ) {
	const context = await browser.newContext( {
		viewport: { width: viewport.width, height: viewport.height },
		deviceScaleFactor: 1,
		isMobile: !! viewport.mobile,
		hasTouch: !! viewport.mobile,
		colorScheme: mode,
		reducedMotion: extra.reducedMotion || 'no-preference',
	} );
	await context.addInitScript( ( stored ) => {
		try {
			window.localStorage.setItem( 'mrtu-theme', stored );
		} catch ( e ) {}
		// Record the theme attribute at the moment <body> starts (before first paint).
		new MutationObserver( ( records, observer ) => {
			if ( document.body ) {
				window.__mrtuThemeAtBody = document.documentElement.getAttribute( 'data-theme' );
				observer.disconnect();
			}
		} ).observe( document, { childList: true, subtree: true } );
	}, mode );
	return context;
}

/** Scrolls through the page so reveal-on-scroll content is shown, then back to top. */
async function settle( page ) {
	await page.evaluate( async () => {
		const step = Math.max( 200, Math.floor( window.innerHeight * 0.7 ) );
		for ( let y = 0; y < document.documentElement.scrollHeight; y += step ) {
			window.scrollTo( 0, y );
			await new Promise( ( r ) => setTimeout( r, 60 ) );
		}
		window.scrollTo( 0, 0 );
		await new Promise( ( r ) => setTimeout( r, 1300 ) );
		await document.fonts.ready;
	} );
}

module.exports = { BASE, ARTIFACTS, VIEWPORTS, PAGES, newContext, settle };
