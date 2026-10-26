/**
 * Accessibility QA with axe-core (WCAG 2.0/2.1/2.2 A + AA rules) on the main templates in light and
 * dark, desktop and mobile. Fails on any serious or critical violation; lists the rest.
 *
 * axe-core is loaded from @axe-core/playwright when installed, otherwise injected from the
 * axe-core package (no network needed): set AXE_PATH or keep /home/user/qa-tools/node_modules.
 *
 * Usage: node tests/e2e/theme/a11y.cjs
 */
'use strict';

const fs = require( 'fs' );
const path = require( 'path' );
const { chromium } = require( 'playwright' );
const { BASE, ARTIFACTS, PAGES, newContext, settle } = require( './lib.cjs' );

const AXE = process.env.AXE_PATH || '/home/user/qa-tools/node_modules/axe-core/axe.min.js';

( async () => {
	if ( ! fs.existsSync( AXE ) ) {
		console.error( `axe-core not found at ${ AXE } (npm i --prefix /home/user/qa-tools axe-core)` );
		process.exit( 2 );
	}
	const browser = await chromium.launch();
	const report = [];
	let blocking = 0;
	const targets = PAGES.filter( ( p ) => [ 'home', 'page', 'single', 'archive', 'search', '404' ].includes( p.name ) );

	for ( const target of targets ) {
		for ( const viewport of [ { name: '1280', width: 1280, height: 800 }, { name: '390', width: 390, height: 844, mobile: true } ] ) {
			for ( const mode of [ 'light', 'dark' ] ) {
				const context = await newContext( browser, viewport, mode, { reducedMotion: 'reduce' } );
				const page = await context.newPage();
				await page.goto( BASE + target.path, { waitUntil: 'networkidle' } );
				await settle( page );
				await page.addScriptTag( { path: AXE } );
				const result = await page.evaluate( async () =>
					window.axe.run( document, {
						runOnly: { type: 'tag', values: [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice' ] },
						resultTypes: [ 'violations' ],
					} )
				);
				const violations = result.violations.map( ( v ) => ( {
					id: v.id,
					impact: v.impact,
					nodes: v.nodes.length,
					sample: v.nodes.slice( 0, 3 ).map( ( n ) => n.target.join( ' ' ) + ( n.failureSummary ? ' :: ' + n.failureSummary.split( '\n' ).slice( 1, 2 ).join( '' ).trim() : '' ) ),
				} ) );
				const serious = violations.filter( ( v ) => v.impact === 'serious' || v.impact === 'critical' );
				blocking += serious.length;
				report.push( { page: target.name, viewport: viewport.name, mode, violations } );
				console.log( `${ serious.length ? 'FAIL' : 'PASS' }  ${ target.name.padEnd( 8 ) } ${ viewport.name.padStart( 4 ) } ${ mode.padEnd( 5 ) } serious/critical=${ serious.length } other=${ violations.length - serious.length }` );
				violations.forEach( ( v ) => console.log( `      ${ v.impact.padEnd( 8 ) } ${ v.id } (${ v.nodes })  ${ v.sample[ 0 ] || '' }` ) );
				await context.close();
			}
		}
	}

	await browser.close();
	fs.mkdirSync( ARTIFACTS, { recursive: true } );
	fs.writeFileSync( path.join( ARTIFACTS, 'a11y.json' ), JSON.stringify( report, null, 2 ) );
	console.log( `\nserious/critical violations: ${ blocking }` );
	process.exit( blocking ? 1 : 0 );
} )();
