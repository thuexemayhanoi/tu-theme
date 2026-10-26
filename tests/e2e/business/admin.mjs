/**
 * Playwright smoke test — business admin screens at iPhone width (local dev env only, admin/admin).
 *
 *   WP_URL=http://localhost:8083 WP_CLI="php … wp-cli.phar --allow-root --path=…" node tests/e2e/business/admin.mjs
 *
 * Store information: validation error keeps the submitted values (no data loss), inline errors, live badges,
 * E.164 suggestion, save, export download, import, restore. Prices: inline range error, preview, reference
 * toggle. Vehicles: add, reorder, delete, media picker loaded. Quick Call: status + save. No console errors,
 * no horizontal overflow. The fixture restores every option afterwards.
 */
import { createRequire } from 'node:module';
import { execSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { readFileSync, mkdirSync } from 'node:fs';
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
	return execSync( `${ WP_CLI } eval-file ${ JSON.stringify( fixture ) } ${ mode }`, { encoding: 'utf8' } );
}
const admin = ( slug, extra = '' ) => `${ WP_URL }/wp-admin/admin.php?page=${ slug }${ extra }`;

wp( 'state' );
mkdirSync( ARTIFACTS, { recursive: true } );
const browser = await chromium.launch();

try {
	const context = await browser.newContext( { viewport: { width: 375, height: 800 }, acceptDownloads: true } );
	const page = await context.newPage();
	const errors = [];
	page.on( 'pageerror', ( error ) => errors.push( error.message ) );
	// Core admin loads Gravatar avatars from the internet; the sandbox blocks them — ignore external failures.
	page.on( 'console', ( msg ) => 'error' === msg.type() && ! /ERR_TUNNEL_CONNECTION_FAILED|ERR_NAME_NOT_RESOLVED|ERR_INTERNET_DISCONNECTED/.test( msg.text() ) && errors.push( msg.text() ) );
	page.on( 'dialog', ( dialog ) => dialog.accept() );

	await page.goto( `${ WP_URL }/wp-login.php` );
	await page.fill( '#user_login', 'admin' );
	await page.fill( '#user_pass', 'admin' );
	await Promise.all( [ page.waitForNavigation(), page.click( '#wp-submit' ) ] );

	const noOverflow = async ( label ) => {
		const overflow = await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth );
		check( overflow <= 0, `${ label }: no horizontal overflow at 375px`, `${ overflow }px` );
	};
	const notice = () => page.locator( '.mrtu-notice' ).first().textContent().catch( () => '' );

	/* Store information. */
	await page.goto( admin( 'mrtu-business' ) );
	check( 'Store information' === ( await page.locator( '.mrtu-admin__title' ).textContent() ), 'store: screen renders' );
	check( ( await page.locator( '.mrtu-badge--pending' ).count() ) > 3, 'store: PENDING VERIFICATION badges shown' );
	check( await page.locator( '.mrtu-savebar' ).isVisible(), 'store: sticky save bar' );
	check( ( await page.locator( '.mrtu-meta' ).first().textContent() ).length > 0, 'store: last-updated line' );
	await noOverflow( 'store' );
	await page.screenshot( { path: path.join( ARTIFACTS, 'admin-store-375.png' ), fullPage: true } );

	const brand = page.locator( '[name="business[brand][value]"]' );
	const e164 = page.locator( '[name="business[phone][e164]"]' );
	await brand.fill( 'Mr Tu Kept' );
	await e164.fill( '+8412' );
	await Promise.all( [ page.waitForNavigation(), page.click( '.mrtu-savebar button[type="submit"]' ) ] );
	check( ( await notice() ).includes( 'Nothing was saved' ), 'store: validation error notice' );
	check( 'true' === ( await e164.getAttribute( 'aria-invalid' ) ), 'store: invalid field marked aria-invalid' );
	check( ( await page.locator( '.mrtu-field__error' ).count() ) > 0 && ( await page.locator( '.mrtu-error-summary' ).isVisible() ), 'store: inline error + summary' );
	check( '+8412' === ( await e164.inputValue() ) && 'Mr Tu Kept' === ( await brand.inputValue() ), 'store: submitted values kept after the error (no data loss)' );
	await e164.fill( '' );
	const display = page.locator( '[name="business[phone][display]"]' );
	await display.fill( '0912 345 678' );
	check( await page.locator( '[data-mrtu-e164-suggest]' ).isVisible(), 'store: E.164 suggestion appears' );
	await page.click( '[data-mrtu-e164-use]' );
	check( '+84912345678' === ( await e164.inputValue() ), 'store: suggestion fills the E.164 field' );
	await display.fill( '0816 659 199' );
	await e164.fill( '+84816659199' );
	const addressGroup = page.locator( '[data-mrtu-group]' ).filter( { has: page.locator( '[name="business[address][street]"]' ) } );
	await addressGroup.locator( '.mrtu-toggle__label' ).first().click();
	check( 'Verified' === ( await addressGroup.locator( '[data-mrtu-badge]' ).textContent() ), 'store: badge follows the verified toggle live' );
	await addressGroup.locator( '.mrtu-toggle__label' ).first().click();
	await Promise.all( [ page.waitForNavigation(), page.click( '.mrtu-savebar button[type="submit"]' ) ] );
	check( ( await notice() ).includes( 'saved' ), 'store: save succeeds', await notice() );
	check( 'Mr Tu Kept' === ( await brand.inputValue() ), 'store: saved value displayed' );
	check( ( await page.locator( '.mrtu-history__item' ).count() ) >= 1, 'store: history lists the automatic backup' );

	const [ download ] = await Promise.all( [ page.waitForEvent( 'download' ), page.click( '#mrtu-backup button:has-text("Download backup")' ) ] );
	const file = path.join( ARTIFACTS, download.suggestedFilename() );
	await download.saveAs( file );
	const exported = JSON.parse( readFileSync( file, 'utf8' ) );
	check( /^mrtu-business-\d{8}-\d{6}\.json$/.test( download.suggestedFilename() ) && 'Mr Tu Kept' === exported.business.brand.value, 'store: export downloads JSON', download.suggestedFilename() );

	await page.setInputFiles( '#mrtu-import-file', file );
	await Promise.all( [ page.waitForNavigation(), page.click( '.mrtu-import button[type="submit"]' ) ] );
	check( ( await notice() ).includes( 'imported' ), 'store: import succeeds', await notice() );
	await page.setInputFiles( '#mrtu-import-file', { name: 'bad.json', mimeType: 'application/json', buffer: Buffer.from( '{"business": {' ) } );
	await Promise.all( [ page.waitForNavigation(), page.click( '.mrtu-import button[type="submit"]' ) ] );
	check( ( await notice() ).includes( 'not valid JSON' ), 'store: broken file rejected', await notice() );

	const restoreButtons = page.locator( '.mrtu-history button[name="snapshot"]' );
	const before = await restoreButtons.count();
	await Promise.all( [ page.waitForNavigation(), restoreButtons.last().click() ] );
	check( ( await notice() ).includes( 'restored' ), 'store: restore succeeds', await notice() );
	check( ( await restoreButtons.count() ) === Math.min( 20, before + 1 ), 'store: restore adds a "before restore" backup' );

	/* Prices. */
	await page.goto( admin( 'mrtu-pricing' ) );
	check( await page.locator( '.mrtu-preview .mrtu-pricing' ).isVisible(), 'prices: public preview rendered' );
	check( await page.locator( '.mrtu-preview [role="tab"]' ).first().isVisible(), 'prices: preview tabs work in admin (mrtu-js)' );
	check( 'numeric' === ( await page.locator( '[name="pricing[vehicles][0][price][day][min]"]' ).getAttribute( 'inputmode' ) ), 'prices: numeric keyboard on iPhone' );
	await noOverflow( 'prices' );
	await page.screenshot( { path: path.join( ARTIFACTS, 'admin-prices-375.png' ), fullPage: true } );
	await page.fill( '[name="pricing[vehicles][0][price][day][min]"]', '300000' );
	await page.fill( '[name="pricing[vehicles][0][price][day][max]"]', '200000' );
	await Promise.all( [ page.waitForNavigation(), page.click( '.mrtu-savebar button[type="submit"]' ) ] );
	check( ( await page.locator( '.mrtu-range.mrtu-has-error' ).count() ) === 1, 'prices: min > max shown inline' );
	check( '300000' === ( await page.inputValue( '[name="pricing[vehicles][0][price][day][min]"]' ) ), 'prices: submitted amounts kept after the error' );
	await page.fill( '[name="pricing[vehicles][0][price][day][max]"]', '' );
	await page.locator( '[name="show_reference_prices"][type="checkbox"]' ).evaluate( ( el ) => {
		el.checked = false;
	} );
	await Promise.all( [ page.waitForNavigation(), page.click( '.mrtu-savebar button[type="submit"]' ) ] );
	check( ( await notice() ).includes( 'Prices saved' ), 'prices: save succeeds', await notice() );
	check( ( await page.locator( '.mrtu-preview .mrtu-price--on-request' ).count() ) > 0 && 0 === ( await page.locator( '.mrtu-preview .mrtu-price--reference' ).count() ), 'prices: reference toggle off → preview shows "Price on request"' );

	/* Vehicles. */
	await page.goto( admin( 'mrtu-vehicles' ) );
	const rows = page.locator( '.mrtu-list__item' );
	const count = await rows.count();
	check( count >= 5, 'vehicles: list renders', `${ count }` );
	await noOverflow( 'vehicles' );
	await Promise.all( [ page.waitForNavigation(), page.click( 'a.button-primary:has-text("Add vehicle")' ) ] );
	check( 5 === ( await page.locator( 'details.mrtu-lang' ).count() ), 'vehicles: 5 language descriptions' );
	check(
		await page
			.waitForFunction( () => !! ( window.wp && window.wp.media ), null, { timeout: 5000 } )
			.then( () => true )
			.catch( () => false ),
		'vehicles: media library loaded on this screen'
	);
	await noOverflow( 'vehicle edit' );
	// Skip the browser's own "required" check to exercise the server-side validation.
	await page.locator( '[name="vehicle[name]"]' ).evaluate( ( el ) => el.removeAttribute( 'required' ) );
	await Promise.all( [ page.waitForNavigation(), page.click( '.mrtu-savebar button[type="submit"]' ) ] );
	check( ( await page.locator( '.mrtu-error-summary' ).count() ) === 1, 'vehicles: empty name rejected' );
	await page.fill( '[name="vehicle[name]"]', 'Honda Test Bike' );
	await page.selectOption( '[name="vehicle[category]"]', 'manual' );
	await page.fill( '[name="vehicle[desc_i18n][vi]"]', 'Xe thử nghiệm.' );
	await page.screenshot( { path: path.join( ARTIFACTS, 'admin-vehicle-edit-375.png' ), fullPage: true } );
	await Promise.all( [ page.waitForNavigation(), page.click( '.mrtu-savebar button[type="submit"]' ) ] );
	check( ( await notice() ).includes( 'Vehicle saved' ) && count + 1 === ( await rows.count() ), 'vehicles: new vehicle added', await notice() );
	check( ( await rows.last().textContent() ).includes( 'honda-test-bike' ), 'vehicles: id derived from the name' );
	await Promise.all( [ page.waitForNavigation(), rows.last().locator( 'button[value^="up:"]' ).click() ] );
	check( ( await rows.nth( count - 1 ).textContent() ).includes( 'Honda Test Bike' ), 'vehicles: move up' );
	await Promise.all( [ page.waitForNavigation(), rows.nth( count - 1 ).locator( 'button[value^="delete:"]' ).click() ] );
	check( count === ( await rows.count() ) && ! ( await page.locator( '.mrtu-list' ).textContent() ).includes( 'Test Bike' ), 'vehicles: delete (with confirm)' );

	/* Quick Call. */
	await page.goto( admin( 'mrtu-quick-contact' ) );
	check( 6 === ( await page.locator( '.mrtu-qc-item' ).count() ), 'quick call: six item rows with status' );
	check( ( await page.locator( '.mrtu-qc-item__status' ).first().textContent() ).includes( 'Ready' ), 'quick call: call item ready (phone verified)' );
	check( ( await page.locator( '.mrtu-qc-item__status' ).nth( 1 ).textContent() ).includes( 'WhatsApp' ), 'quick call: reason for hidden WhatsApp' );
	await noOverflow( 'quick call' );
	await page.screenshot( { path: path.join( ARTIFACTS, 'admin-quick-call-375.png' ), fullPage: true } );
	await page.click( 'label[for="mrtu-qc-collapse_on_scroll-"]' );
	await Promise.all( [ page.waitForNavigation(), page.click( '.mrtu-savebar button[type="submit"]' ) ] );
	check( ( await notice() ).includes( 'saved' ), 'quick call: save succeeds', await notice() );

	check( 0 === errors.length, 'admin: no console errors', errors.join( ' | ' ) );
	await context.close();
} catch ( error ) {
	check( false, 'unexpected error', error && error.stack ? error.stack : String( error ) );
} finally {
	await browser.close();
	wp( 'teardown' );
}

console.log( `RESULT: ${ passed }/${ passed + failed }` );
process.exit( failed ? 1 : 0 );
