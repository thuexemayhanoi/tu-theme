/**
 * Playwright smoke test — business blocks + Quick Contact (local dev env only).
 *
 *   WP_URL=http://localhost:8083 \
 *   WP_CLI="php -d memory_limit=1024M /home/user/wpenv-business/bin/wp-cli.phar --allow-root --path=/home/user/wpenv-business/wordpress" \
 *   node tests/e2e/business/smoke.mjs
 *
 * Checks (iPhone width 375px): pricing tabs keyboard navigation, fleet filter chips, Quick Contact
 * open/close with the keyboard + hiding (avoid zone, typing, assistant events), compact state, no console
 * errors, no horizontal overflow. Prints PASS/FAIL lines and "RESULT: passed/total"; exits 1 on failure.
 */
import { createRequire } from 'node:module';
import { execSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { mkdirSync } from 'node:fs';
import path from 'node:path';

const require = createRequire( import.meta.url );
const { chromium } = require( 'playwright' );

const here = path.dirname( fileURLToPath( import.meta.url ) );
const WP_CLI = process.env.WP_CLI || 'wp';
const WP_URL = ( process.env.WP_URL || 'http://localhost:8083' ).replace( /\/$/, '' );
const ARTIFACTS = path.resolve( here, '../artifacts' );
const fixture = path.join( here, 'fixture.php' );

let passed = 0;
let failed = 0;
function check( condition, label, detail ) {
	if ( condition ) {
		passed++;
		console.log( `PASS: ${ label }` );
	} else {
		failed++;
		console.log( `FAIL: ${ label }${ detail ? ` — ${ detail }` : '' }` );
	}
}

function wp( mode ) {
	const out = execSync( `${ WP_CLI } eval-file ${ JSON.stringify( fixture ) } ${ mode }`, { encoding: 'utf8' } );
	const line = out.trim().split( '\n' ).pop();
	return JSON.parse( line );
}

const attr = ( locator, name ) => locator.getAttribute( name );
const activeId = ( page ) => page.evaluate( () => document.activeElement && document.activeElement.id );
const qcClass = ( page, name ) => page.evaluate( ( n ) => document.querySelector( '[data-mrtu-qc]' ).classList.contains( n ), name );
const waitQc = ( page, name, on ) =>
	page
		.waitForFunction( ( [ n, v ] ) => document.querySelector( '[data-mrtu-qc]' ).classList.contains( n ) === v, [ name, on ], { timeout: 3000 } )
		.then( () => true )
		.catch( () => false );

const setup = wp( 'setup' );
const url = setup.url.replace( /^https?:\/\/[^/]+/, WP_URL );
const browser = await chromium.launch();

try {
	const context = await browser.newContext( { viewport: { width: 375, height: 740 }, deviceScaleFactor: 2 } );
	const page = await context.newPage();
	const errors = [];
	page.on( 'console', ( msg ) => {
		if ( 'error' === msg.type() ) {
			errors.push( msg.text() );
		}
	} );
	page.on( 'pageerror', ( error ) => errors.push( error.message ) );

	const response = await page.goto( url, { waitUntil: 'networkidle' } );
	check( response && 200 === response.status(), 'page loads (200)', response && response.status() );
	check( await page.evaluate( () => document.documentElement.classList.contains( 'mrtu-js' ) ), 'html.mrtu-js flag set in <head>' );

	/* Pricing tabs. */
	const tabs = page.locator( '.mrtu-pricing [role="tab"]' );
	check( 3 === ( await tabs.count() ), 'pricing: 3 tabs' );
	check( await tabs.nth( 0 ).isVisible(), 'pricing: tabs visible with JS' );
	const panelOf = async ( i ) => page.locator( '#' + ( await attr( tabs.nth( i ), 'aria-controls' ) ) );
	check( ( await ( await panelOf( 0 ) ).isVisible() ) && ! ( await ( await panelOf( 1 ) ).isVisible() ), 'pricing: only the default panel is visible' );

	await tabs.nth( 0 ).focus();
	await page.keyboard.press( 'ArrowRight' );
	check( 'true' === ( await attr( tabs.nth( 1 ), 'aria-selected' ) ), 'pricing: ArrowRight selects the next tab' );
	check( ( await activeId( page ) ) === ( await attr( tabs.nth( 1 ), 'id' ) ), 'pricing: focus follows selection' );
	check( '0' === ( await attr( tabs.nth( 1 ), 'tabindex' ) ) && '-1' === ( await attr( tabs.nth( 0 ), 'tabindex' ) ), 'pricing: roving tabindex' );
	check( ( await ( await panelOf( 1 ) ).isVisible() ) && ! ( await ( await panelOf( 0 ) ).isVisible() ), 'pricing: weekly panel shown, daily hidden' );
	check( await ( await panelOf( 1 ) ).evaluate( ( el ) => el.classList.contains( 'is-active' ) && ! el.hidden ), 'pricing: .is-active + hidden toggled' );
	await page.keyboard.press( 'End' );
	check( 'true' === ( await attr( tabs.nth( 2 ), 'aria-selected' ) ), 'pricing: End selects the last tab' );
	await page.keyboard.press( 'ArrowRight' );
	check( 'true' === ( await attr( tabs.nth( 0 ), 'aria-selected' ) ), 'pricing: ArrowRight wraps to the first tab' );
	await page.keyboard.press( 'ArrowLeft' );
	check( 'true' === ( await attr( tabs.nth( 2 ), 'aria-selected' ) ), 'pricing: ArrowLeft wraps to the last tab' );
	await page.keyboard.press( 'Home' );
	check( 'true' === ( await attr( tabs.nth( 0 ), 'aria-selected' ) ) && ( await ( await panelOf( 0 ) ).isVisible() ), 'pricing: Home selects the first tab' );
	await tabs.nth( 2 ).click();
	check( 'true' === ( await attr( tabs.nth( 2 ), 'aria-selected' ) ), 'pricing: click selects a tab' );

	/* Fleet filter. */
	const fleet = page.locator( '.mrtu-fleet' );
	const visibleCount = () => fleet.locator( '.mrtu-vehicle:visible' ).count();
	const total = await fleet.locator( '.mrtu-vehicle' ).count();
	check( total > 1 && total === ( await visibleCount() ), 'fleet: all models visible initially', `${ total }` );
	const manual = fleet.locator( '.mrtu-fleet__filter[data-filter="manual"]' );
	await manual.click();
	const manualCount = await fleet.locator( '.mrtu-vehicle[data-category="manual"]' ).count();
	check( 'true' === ( await attr( manual, 'aria-pressed' ) ), 'fleet: chip aria-pressed=true' );
	check( 'false' === ( await attr( fleet.locator( '.mrtu-fleet__filter[data-filter="all"]' ), 'aria-pressed' ) ), 'fleet: other chips aria-pressed=false' );
	check( manualCount === ( await visibleCount() ), 'fleet: only matching models visible', `${ manualCount }` );
	const live = ( await fleet.locator( '.mrtu-fleet__status' ).textContent() ) || '';
	check( live.includes( String( manualCount ) ), 'fleet: count announced in the live region', live );
	const scooter = fleet.locator( '.mrtu-fleet__filter[data-filter="scooter"]' );
	await scooter.focus();
	await page.keyboard.press( 'Enter' );
	check(
		'true' === ( await attr( scooter, 'aria-pressed' ) ) && ( await fleet.locator( '.mrtu-vehicle[data-category="scooter"]' ).count() ) === ( await visibleCount() ),
		'fleet: keyboard activation filters'
	);
	await fleet.locator( '.mrtu-fleet__filter[data-filter="all"]' ).click();
	check( total === ( await visibleCount() ), 'fleet: "All" restores every model' );

	/* Quick Contact. */
	await page.evaluate( () => window.scrollTo( 0, 0 ) );
	await page.waitForTimeout( 150 );
	const qc = page.locator( '[data-mrtu-qc]' );
	const toggle = qc.locator( '.mrtu-qc__toggle' );
	const panel = qc.locator( '.mrtu-qc__panel' );
	check( await toggle.isVisible(), 'quick contact: toggle visible' );
	const box = await toggle.boundingBox();
	check( box && box.height >= 56 && box.width >= 56, 'quick contact: toggle ≥ 56px', box && `${ box.width }x${ box.height }` );
	check( box && box.x + box.width <= 375 && box.y + box.height <= 740, 'quick contact: toggle inside the viewport' );
	check( 900 === Number( await qc.evaluate( ( el ) => getComputedStyle( el ).zIndex ) ), 'quick contact: z-index 900' );

	await toggle.focus();
	await page.keyboard.press( 'Enter' );
	check( 'true' === ( await attr( toggle, 'aria-expanded' ) ), 'quick contact: Enter opens (aria-expanded=true)' );
	check( await panel.isVisible(), 'quick contact: panel visible when open' );
	check( await page.evaluate( () => document.activeElement && document.activeElement.classList.contains( 'mrtu-qc__item' ) ), 'quick contact: focus moves to the first item' );
	const item = qc.locator( '.mrtu-qc__item' ).first();
	await page.waitForTimeout( 400 ); // Let the open animation (scale .98 → 1) finish before measuring.
	const itemBox = await item.boundingBox();
	check( itemBox && itemBox.height >= 48, 'quick contact: items ≥ 48px', itemBox && `${ itemBox.height }` );
	check( 'tel:+84816659199' === ( await attr( qc.locator( '.mrtu-qc__item--call' ), 'href' ) ), 'quick contact: call item links to the verified phone' );
	await page.keyboard.press( 'Escape' );
	check( 'false' === ( await attr( toggle, 'aria-expanded' ) ) && ! ( await panel.isVisible() ), 'quick contact: Escape closes' );
	check( await toggle.evaluate( ( el ) => el === document.activeElement ), 'quick contact: focus returns to the toggle' );
	await page.keyboard.press( 'Space' );
	check( 'true' === ( await attr( toggle, 'aria-expanded' ) ), 'quick contact: Space opens' );
	await page.mouse.click( 20, 300 );
	check( 'false' === ( await attr( toggle, 'aria-expanded' ) ), 'quick contact: outside click closes' );

	await page.evaluate( () => window.scrollTo( 0, 400 ) );
	check( await waitQc( page, 'is-compact', true ), 'quick contact: .is-compact after scrolling down 240px' );
	const compactBox = await toggle.boundingBox();
	check( compactBox && Math.round( compactBox.width ) === 56, 'quick contact: compact toggle keeps the 56px icon button', compactBox && `${ compactBox.width }` );
	await page.evaluate( () => window.scrollTo( 0, 0 ) );
	check( await waitQc( page, 'is-compact', false ), 'quick contact: label returns near the top' );

	await page.locator( '.mrtu-contact-card' ).scrollIntoViewIfNeeded();
	check( await waitQc( page, 'is-hidden', true ), 'quick contact: hidden while [data-mrtu-qc-avoid] is in view' );
	await page.waitForTimeout( 350 );
	check( ! ( await toggle.isVisible() ), 'quick contact: toggle not visible in the avoid zone' );
	check( 'true' === ( await attr( qc, 'aria-hidden' ) ) && null !== ( await attr( qc, 'inert' ) ), 'quick contact: inert + aria-hidden while hidden' );
	await page.evaluate( () => window.scrollTo( 0, 0 ) );
	check( await waitQc( page, 'is-hidden', false ), 'quick contact: shown again when the avoid zone leaves the viewport' );

	await page.evaluate( () => {
		const input = document.createElement( 'input' );
		input.type = 'text';
		input.id = 'mrtu-e2e-input';
		input.setAttribute( 'aria-label', 'test' );
		document.body.prepend( input );
	} );
	await page.focus( '#mrtu-e2e-input' );
	check( await waitQc( page, 'is-hidden', true ), 'quick contact: hidden while a text field is focused' );
	await page.evaluate( () => document.activeElement.blur() );
	check( await waitQc( page, 'is-hidden', false ), 'quick contact: shown again after the field loses focus' );
	await page.evaluate( () => document.getElementById( 'mrtu-e2e-input' ).remove() );

	await page.evaluate( () => document.dispatchEvent( new CustomEvent( 'mrtu:assistant-opened' ) ) );
	check( await waitQc( page, 'is-hidden', true ), 'quick contact: hidden while the assistant is open' );
	await page.evaluate( () => document.dispatchEvent( new CustomEvent( 'mrtu:assistant-closed' ) ) );
	check( await waitQc( page, 'is-hidden', false ), 'quick contact: back after the assistant closes' );

	/* Layout at 375px. */
	await page.evaluate( () => window.scrollTo( 0, 0 ) );
	const overflow = await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth );
	check( overflow <= 0, 'layout: no horizontal overflow at 375px', `${ overflow }px` );
	const wide = await page.evaluate( () =>
		Array.from( document.querySelectorAll( '.mrtu-pricing, .mrtu-pricing *, .mrtu-fleet, .mrtu-fleet *, .mrtu-contact-card, .mrtu-cta *' ) )
			.filter( ( el ) => el.getClientRects().length && el.getBoundingClientRect().right > document.documentElement.clientWidth + 1 )
			.map( ( el ) => el.className || el.tagName )
			.slice( 0, 5 )
	);
	check( 0 === wide.length, 'layout: no block element wider than the viewport', wide.join( ', ' ) );

	try {
		mkdirSync( ARTIFACTS, { recursive: true } );
		await page.screenshot( { path: path.join( ARTIFACTS, 'business-375.png' ), fullPage: true } );
		await qc.locator( '.mrtu-qc__toggle' ).click();
		await page.screenshot( { path: path.join( ARTIFACTS, 'business-375-qc-open.png' ) } );
	} catch ( e ) {
		// Screenshots are diagnostics only.
	}

	check( 0 === errors.length, 'no console errors', errors.join( ' | ' ) );
	await context.close();

	/* Desktop sanity. */
	const desktop = await browser.newContext( { viewport: { width: 1280, height: 900 } } );
	const dpage = await desktop.newPage();
	const derrors = [];
	dpage.on( 'pageerror', ( error ) => derrors.push( error.message ) );
	dpage.on( 'console', ( msg ) => 'error' === msg.type() && derrors.push( msg.text() ) );
	await dpage.goto( url, { waitUntil: 'networkidle' } );
	const doverflow = await dpage.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth );
	check( doverflow <= 0 && 0 === derrors.length, 'desktop 1280px: no overflow, no errors', `${ doverflow }px ${ derrors.join( ' | ' ) }` );
	try {
		await dpage.screenshot( { path: path.join( ARTIFACTS, 'business-1280.png' ), fullPage: true } );
	} catch ( e ) {
		// Diagnostics only.
	}
	await desktop.close();
} catch ( error ) {
	check( false, 'unexpected error', error && error.stack ? error.stack : String( error ) );
} finally {
	await browser.close();
	wp( 'teardown' );
}

console.log( `RESULT: ${ passed }/${ passed + failed }` );
process.exit( failed ? 1 : 0 );
