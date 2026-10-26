/**
 * One-off screenshot for design iteration.
 * Usage: node tests/e2e/theme/shot.cjs <path> <width> <light|dark> [height] [full|viewport] [out-name]
 */
'use strict';

const fs = require( 'fs' );
const path = require( 'path' );
const { chromium } = require( 'playwright' );
const { BASE, ARTIFACTS, newContext, settle } = require( './lib.cjs' );

( async () => {
	const [ urlPath = '/', w = '390', mode = 'light', h = '844', kind = 'full', name ] = process.argv.slice( 2 );
	fs.mkdirSync( path.join( ARTIFACTS, 'iter' ), { recursive: true } );
	const browser = await chromium.launch();
	const viewport = { width: +w, height: +h, mobile: +w < 1024 };
	const context = await newContext( browser, viewport, mode );
	const page = await context.newPage();
	const errors = [];
	page.on( 'console', ( m ) => m.type() === 'error' && errors.push( m.text() ) );
	page.on( 'pageerror', ( e ) => errors.push( e.message ) );
	await page.goto( BASE + urlPath, { waitUntil: 'networkidle' } );
	await settle( page );
	const file = path.join( ARTIFACTS, 'iter', ( name || `${ urlPath.replace( /\W+/g, '_' ) }-${ w }-${ mode }` ) + '.png' );
	await page.screenshot( { path: file, fullPage: kind === 'full' } );
	const metrics = await page.evaluate( () => {
		const limit = document.documentElement.clientWidth;
		const wide = [];
		document.querySelectorAll( 'body *' ).forEach( ( el ) => {
			const r = el.getBoundingClientRect();
			if ( r.width && r.right > limit + 1 && ! el.closest( '[style*="overflow"], .mrtu-fleet__grid, .card-grid, dialog' ) ) {
				wide.push( ( el.className && el.className.baseVal === undefined ? el.tagName.toLowerCase() + '.' + String( el.className ).split( ' ' ).join( '.' ) : el.tagName ) + ':' + Math.round( r.right ) );
			}
		} );
		return {
			scrollWidth: document.documentElement.scrollWidth,
			innerWidth: window.innerWidth,
			h1: document.querySelectorAll( 'h1' ).length,
			height: document.documentElement.scrollHeight,
			wide: wide.slice( 0, 8 ),
		};
	} );
	console.log( file, JSON.stringify( metrics ), errors.length ? 'ERRORS: ' + errors.join( ' | ' ) : '' );
	await browser.close();
} )();
