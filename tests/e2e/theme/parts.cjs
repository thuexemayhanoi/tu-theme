/**
 * Element screenshots for close design review.
 * Usage: node tests/e2e/theme/parts.cjs <path> <width> <light|dark> "<selector>[,<selector>…]" [prefix]
 */
'use strict';

const fs = require( 'fs' );
const path = require( 'path' );
const { chromium } = require( 'playwright' );
const { BASE, ARTIFACTS, newContext, settle } = require( './lib.cjs' );

( async () => {
	const [ urlPath = '/', w = '390', mode = 'light', selectors = 'main > *', prefix = 'part' ] = process.argv.slice( 2 );
	const dir = path.join( ARTIFACTS, 'parts' );
	fs.mkdirSync( dir, { recursive: true } );
	const browser = await chromium.launch();
	const context = await newContext( browser, { width: +w, height: 900, mobile: +w < 1024 }, mode );
	const page = await context.newPage();
	await page.goto( BASE + urlPath, { waitUntil: 'networkidle' } );
	await settle( page );
	let i = 0;
	for ( const sel of selectors.split( '|' ) ) {
		const handles = await page.$$( sel );
		for ( const h of handles ) {
			const box = await h.boundingBox();
			if ( ! box || box.height < 4 ) {
				continue;
			}
			const file = path.join( dir, `${ prefix }-${ w }-${ mode }-${ String( i++ ).padStart( 2, '0' ) }.png` );
			await h.screenshot( { path: file } );
			console.log( file, Math.round( box.height ) );
		}
	}
	await browser.close();
} )();
