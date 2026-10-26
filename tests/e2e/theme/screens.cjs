/**
 * Visual + layout QA: every template (home, page, single, archive, search, 404) at six viewports
 * in light and dark. Saves full-page screenshots to tests/e2e/artifacts/theme/screens/ and asserts:
 * no horizontal overflow, no console/page errors, exactly one <h1>, colour mode applied before
 * the first paint. Exits non-zero on failure.
 *
 * Usage: node tests/e2e/theme/screens.cjs [page-name…]
 */
'use strict';

const fs = require( 'fs' );
const path = require( 'path' );
const { chromium } = require( 'playwright' );
const { BASE, ARTIFACTS, VIEWPORTS, PAGES, newContext, settle } = require( './lib.cjs' );

( async () => {
	const only = process.argv.slice( 2 );
	const pages = only.length ? PAGES.filter( ( p ) => only.includes( p.name ) ) : PAGES;
	const dir = path.join( ARTIFACTS, 'screens' );
	fs.mkdirSync( dir, { recursive: true } );
	const browser = await chromium.launch();
	const rows = [];

	for ( const target of pages ) {
		for ( const viewport of VIEWPORTS ) {
			for ( const mode of [ 'light', 'dark' ] ) {
				const context = await newContext( browser, viewport, mode );
				const page = await context.newPage();
				const errors = [];
				page.on( 'console', ( msg ) => {
					// The 404 template is served with a 404 status on purpose.
					if ( msg.type() === 'error' && ! ( target.name === '404' && /status of 404/.test( msg.text() ) ) ) {
						errors.push( msg.text() );
					}
				} );
				page.on( 'pageerror', ( err ) => errors.push( err.message ) );
				await page.goto( BASE + target.path, { waitUntil: 'networkidle' } );
				await settle( page );
				const m = await page.evaluate( () => {
					const limit = document.documentElement.clientWidth;
					const wide = [];
					document.querySelectorAll( 'body *' ).forEach( ( el ) => {
						const r = el.getBoundingClientRect();
						if ( r.width && r.right > limit + 1 && ! el.closest( '.mrtu-fleet__grid, .related .card-grid, dialog, .wp-block-table, .mrtu-pricing__table-wrap' ) ) {
							wide.push( el.tagName.toLowerCase() + ( typeof el.className === 'string' && el.className ? '.' + el.className.trim().split( /\s+/ )[ 0 ] : '' ) );
						}
					} );
					return {
						scrollWidth: document.documentElement.scrollWidth,
						innerWidth: window.innerWidth,
						h1: document.querySelectorAll( 'h1' ).length,
						themeAtBody: window.__mrtuThemeAtBody,
						wide: [ ...new Set( wide ) ].slice( 0, 5 ),
					};
				} );
				const file = path.join( dir, `${ target.name }-${ viewport.name }-${ mode }.png` );
				await page.screenshot( { path: file, fullPage: true } );
				const row = {
					page: target.name,
					viewport: viewport.name,
					mode,
					overflow: m.scrollWidth > m.innerWidth || m.wide.length > 0,
					scrollWidth: m.scrollWidth,
					innerWidth: m.innerWidth,
					h1: m.h1,
					themeOk: m.themeAtBody === mode,
					errors,
					wide: m.wide,
				};
				row.ok = ! row.overflow && row.h1 === 1 && row.themeOk && errors.length === 0;
				rows.push( row );
				console.log( `${ row.ok ? 'PASS' : 'FAIL' }  ${ target.name.padEnd( 8 ) } ${ viewport.name.padStart( 4 ) } ${ mode.padEnd( 5 ) } sw=${ m.scrollWidth }/${ m.innerWidth } h1=${ m.h1 } theme@body=${ m.themeAtBody }${ errors.length ? ' errors=' + errors.join( ' | ' ) : '' }${ m.wide.length ? ' wide=' + m.wide.join( ',' ) : '' }` );
				await context.close();
			}
		}
	}

	await browser.close();
	fs.writeFileSync( path.join( ARTIFACTS, 'screens.json' ), JSON.stringify( rows, null, 2 ) );
	const failed = rows.filter( ( r ) => ! r.ok );
	console.log( `\n${ rows.length - failed.length }/${ rows.length } screen checks passed (screenshots in ${ dir })` );
	process.exit( failed.length ? 1 : 0 );
} )();
