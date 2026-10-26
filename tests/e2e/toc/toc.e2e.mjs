#!/usr/bin/env node
/**
 * E2E — table of contents (contract §2.9): keyboard toggle, scroll-spy (inline + theme aside),
 * reading progress, mobile link behaviour, script size/defer, console errors.
 *
 * Usage (dev server must be running for the env):
 *   MRTU_WP_ENV=/home/user/wpenv-toc node tests/e2e/toc/toc.e2e.mjs
 * Env: MRTU_WP_ENV (default /home/user/wpenv), MRTU_BASE_URL (default: the site's home option),
 *      MRTU_E2E_KEEP=1 keeps the fixture post.
 * Prints PASS:/FAIL:/SKIP: lines and "RESULT: <passed>/<total>"; exits 1 on failure.
 */
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const require = createRequire( import.meta.url );
const { chromium } = require( 'playwright' );

const HERE = dirname( fileURLToPath( import.meta.url ) );
const REPO = resolve( HERE, '../../..' );
const ENV = process.env.MRTU_WP_ENV || '/home/user/wpenv';
const SLUG = 'mrtu-toc-e2e';
const results = { pass: 0, fail: 0 };

function check( condition, label, detail = '' ) {
	if ( condition ) {
		results.pass++;
		console.log( `PASS: ${ label }` );
	} else {
		results.fail++;
		console.log( `FAIL: ${ label }${ detail ? ` — ${ detail }` : '' }` );
	}
}

function wp( ...args ) {
	return execFileSync(
		'php',
		[ '-d', 'memory_limit=1024M', `${ ENV }/bin/wp-cli.phar`, '--allow-root', `--path=${ ENV }/wordpress`, '--user=1', ...args ],
		{ encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'ignore' ] }
	).trim();
}

/* Fixture: 6 H2 + 3 H3, long enough to scroll on a phone and on a desktop. */
const words = ( n ) => Array.from( { length: n }, ( _, i ) => 'Mr Tu giao xe máy tận nơi ở Phố Cổ Hà Nội và hướng dẫn khách thuê xe an toàn'.split( ' ' )[ i % 18 ] ).join( ' ' ) + '.';
const p = ( n ) => `<!-- wp:paragraph -->\n<p>${ words( n ) }</p>\n<!-- /wp:paragraph -->\n\n`;
const h = ( level, text ) => `<!-- wp:heading${ 3 === level ? ' {"level":3}' : '' } -->\n<h${ level } class="wp-block-heading">${ text }</h${ level }>\n<!-- /wp:heading -->\n\n`;
const SECTIONS = [
	[ 2, 'Đặt cọc bao nhiêu?', 'dat-coc-bao-nhieu' ],
	[ 3, 'Führerschein &amp; Straße', 'fuhrerschein-strasse' ],
	[ 2, 'Où récupérer la moto ?', 'ou-recuperer-la-moto' ],
	[ 2, '¿Necesito pasaporte?', 'necesito-pasaporte' ],
	[ 3, 'Giấy tờ cần mang', 'giay-to-can-mang' ],
	[ 2, 'Giá thuê theo tháng', 'gia-thue-theo-thang' ],
	[ 3, 'Trả xe muộn', 'tra-xe-muon' ],
	[ 2, 'An toàn giao thông', 'an-toan-giao-thong' ],
	[ 2, 'Liên hệ Mr Tu', 'lien-he-mr-tu' ],
];
const CONTENT = p( 90 ) + SECTIONS.map( ( [ level, text ] ) => h( level, text ) + p( 220 ) ).join( '' );
const IDS = SECTIONS.map( ( s ) => s[ 2 ] );

let postId = wp( 'post', 'list', '--post_type=post', `--name=${ SLUG }`, '--post_status=any', '--field=ID' ).split( '\n' )[ 0 ];
if ( postId ) {
	wp( 'post', 'update', postId, `--post_content=${ CONTENT }`, '--post_status=publish' );
} else {
	postId = wp( 'post', 'create', '--post_type=post', '--post_status=publish', '--post_title=TOC e2e — Thuê xe máy Phố Cổ', `--post_name=${ SLUG }`, `--post_content=${ CONTENT }`, '--porcelain' );
}
const BASE = ( process.env.MRTU_BASE_URL || wp( 'option', 'get', 'home' ) ).replace( /\/$/, '' );
const PAGE_URL = `${ BASE }/?p=${ postId }`;

/* toc.js budget: < 2 KB minified (esbuild from this repo or the main checkout). */
try {
	const esbuild = require( require.resolve( 'esbuild', { paths: [ REPO, '/home/user/tu-theme' ] } ) );
	const source = readFileSync( `${ REPO }/plugin/mr-tu-multilang-seo/assets/js/toc.js`, 'utf8' );
	const min = esbuild.transformSync( source, { minify: true, legalComments: 'none' } ).code;
	check( Buffer.byteLength( min ) < 2048, `toc.js minified < 2 KB (${ Buffer.byteLength( min ) } bytes)` );
} catch ( e ) {
	console.log( 'SKIP: toc.js minified size (esbuild not installed)' );
}

const browser = await chromium.launch();
const errors = [];
let assetFallback = false;

/*
 * Plugin assets: served by the dev server when it can. scripts/router.php refuses files whose realpath() leaves the
 * docroot, which is the case for the symlinked repo plugin — then the file is served from this repository instead.
 */
async function serveAssets( context ) {
	await context.route( /\/wp-content\/plugins\/mr-tu-multilang-seo\/assets\/[^?#]+/, async ( route ) => {
		try {
			const response = await route.fetch();
			if ( 200 === response.status() ) {
				await route.fulfill( { response } );
				return;
			}
			assetFallback = true;
			const file = new URL( route.request().url() ).pathname.replace( /^.*\/mr-tu-multilang-seo\//, '' ).replace( /\/$/, '' );
			const type = file.endsWith( '.js' ) ? 'text/javascript' : 'text/css';
			await route.fulfill( { status: 200, contentType: type, body: readFileSync( `${ REPO }/plugin/mr-tu-multilang-seo/${ file }` ) } );
		} catch ( e ) {
			// The page (e.g. a tab opened by Ctrl+click) was closed while the request was in flight.
		}
	} );
}

function watch( page, label ) {
	page.on( 'console', ( msg ) => {
		if ( 'error' === msg.type() ) {
			errors.push( `${ label } console: ${ msg.text() }` );
		}
	} );
	page.on( 'pageerror', ( err ) => errors.push( `${ label } pageerror: ${ err.message }` ) );
	page.on( 'response', ( res ) => {
		if ( /mr-tu-multilang-seo\/assets\//.test( res.url() ) && 200 !== res.status() ) {
			errors.push( `${ label } asset ${ res.status() }: ${ res.url() }` );
		}
	} );
}

async function waitFor( page, fn, arg, timeout = 3000 ) {
	try {
		await page.waitForFunction( fn, arg, { timeout } );
		return true;
	} catch ( e ) {
		return false;
	}
}

const activeHrefs = ( page ) =>
	page.evaluate( () =>
		[ '[data-mrtu-toc]', '[data-mrtu-toc-aside]' ].map( ( sel ) =>
			[ ...document.querySelectorAll( `${ sel } a[aria-current="true"]` ) ].map( ( a ) => a.getAttribute( 'href' ) )
		)
	);

async function scrollToHeading( page, id ) {
	await page.evaluate( ( target ) => {
		const el = document.getElementById( target );
		window.scrollTo( 0, el.getBoundingClientRect().top + window.pageYOffset - 20 );
	}, id );
}

const readProgress = ( page ) => page.evaluate( () => document.documentElement.style.getPropertyValue( '--mrtu-read-progress' ) );

try {
	/* ---------- Desktop: keyboard, scroll-spy (inline + theme aside), progress ---------- */
	const desktop = await browser.newContext( { viewport: { width: 1280, height: 900 } } );
	await serveAssets( desktop );
	const page = await desktop.newPage();
	watch( page, 'desktop' );
	// Emulate a theme that prints a sticky aside from mrtu_toc_items() BEFORE the content.
	await page.route( /\?p=\d+$|\/mrtu-toc-e2e\/?$/, async ( route ) => {
		const response = await route.fetch();
		let body = await response.text();
		const list = body.match( /<ol class="mrtu-toc__list">[\s\S]*?<\/ol><\/nav>/ );
		if ( list ) {
			body = body.replace( /<body([^>]*)>/, `<body$1><nav data-mrtu-toc-aside aria-label="On this page" style="position:fixed;right:0;top:0;width:200px">${ list[ 0 ].replace( /<\/nav>$/, '' ) }</nav>` );
		}
		await route.fulfill( { response, body } );
	} );
	const response = await page.goto( PAGE_URL, { waitUntil: 'load' } );
	check( response && 200 === response.status(), `GET ${ PAGE_URL } → 200` );

	const state = await page.evaluate( () => {
		const toc = document.querySelectorAll( 'details[data-mrtu-toc]' );
		const script = document.querySelector( 'script[src*="assets/js/toc.js"]' );
		return {
			count: toc.length,
			open: toc[ 0 ] ? toc[ 0 ].open : null,
			badge: toc[ 0 ] ? toc[ 0 ].querySelector( '.mrtu-toc__count' ).textContent : '',
			links: toc[ 0 ] ? [ ...toc[ 0 ].querySelectorAll( 'a' ) ].map( ( a ) => a.getAttribute( 'href' ).slice( 1 ) ) : [],
			label: toc[ 0 ] ? toc[ 0 ].querySelector( 'nav' ).getAttribute( 'aria-label' ) : '',
			defer: script ? script.defer : false,
			aside: document.querySelectorAll( '[data-mrtu-toc-aside] a' ).length,
		};
	} );
	check( 1 === state.count, 'exactly one inline TOC' );
	check( false === state.open, 'TOC closed by default' );
	check( JSON.stringify( IDS ) === JSON.stringify( state.links ), 'TOC links point to the stable heading ids', state.links.join( ',' ) );
	check( String( IDS.length ) === state.badge, `count badge = ${ IDS.length }`, state.badge );
	check( '' !== state.label, 'nav has an aria-label' );
	check( state.defer, 'toc.js loaded with defer' );
	check( IDS.length === state.aside, 'emulated theme aside rendered' );

	// Keyboard: Tab to the summary, toggle with Enter / Space.
	let reached = false;
	for ( let i = 0; i < 80 && ! reached; i++ ) {
		await page.keyboard.press( 'Tab' );
		reached = await page.evaluate( () => document.activeElement && document.activeElement.matches( 'details[data-mrtu-toc] > summary' ) );
	}
	check( reached, 'summary reachable with Tab' );
	await page.keyboard.press( 'Enter' );
	check( await page.evaluate( () => document.querySelector( 'details[data-mrtu-toc]' ).open ), 'Enter opens the TOC' );
	await page.keyboard.press( 'Space' );
	check( false === ( await page.evaluate( () => document.querySelector( 'details[data-mrtu-toc]' ).open ) ), 'Space closes the TOC' );
	await page.keyboard.press( 'Enter' );
	const focusVisible = await page.evaluate( () => {
		const el = document.activeElement;
		const style = getComputedStyle( el );
		return el.matches( ':focus-visible' ) && 'none' !== style.outlineStyle && parseFloat( style.outlineWidth ) >= 2;
	} );
	check( focusVisible, 'summary shows a visible focus ring' );

	// Reading progress.
	await page.evaluate( () => window.scrollTo( 0, 0 ) );
	await page.waitForTimeout( 150 );
	const p0 = parseFloat( await readProgress( page ) );
	check( 0 === p0, `--mrtu-read-progress is 0.000 at the top (${ p0 })` );
	await page.evaluate( () => window.scrollTo( 0, ( document.documentElement.scrollHeight - window.innerHeight ) / 2 ) );
	await page.waitForTimeout( 200 );
	const pMid = await readProgress( page );
	check( /^\d\.\d{3}$/.test( pMid ) && parseFloat( pMid ) > 0.4 && parseFloat( pMid ) < 0.6, `progress ≈ 0.5 half-way, 3 decimals (${ pMid })` );
	await page.evaluate( () => window.scrollTo( 0, document.documentElement.scrollHeight ) );
	await page.waitForTimeout( 200 );
	check( '1.000' === ( await readProgress( page ) ), 'progress 1.000 at the bottom' );
	check( IDS[ IDS.length - 1 ] === ( ( await activeHrefs( page ) )[ 0 ][ 0 ] || '' ).slice( 1 ), 'at the very bottom the last section is current' );

	// Scroll-spy in the inline TOC and the aside.
	for ( const index of [ 2, 5, 0 ] ) {
		await scrollToHeading( page, IDS[ index ] );
		const ok = await waitFor(
			page,
			( id ) =>
				[ '[data-mrtu-toc]', '[data-mrtu-toc-aside]' ].every( ( sel ) => {
					const current = document.querySelectorAll( `${ sel } a[aria-current="true"]` );
					return 1 === current.length && current[ 0 ].getAttribute( 'href' ) === `#${ id }`;
				} ),
			IDS[ index ]
		);
		check( ok, `scroll to #${ IDS[ index ] } → aria-current="true" on its link (inline + aside, exactly one each)`, JSON.stringify( await activeHrefs( page ) ) );
	}

	// Desktop click: the details stays open, focus moves to the heading.
	await page.evaluate( () => window.scrollTo( 0, 0 ) );
	await page.click( `details[data-mrtu-toc] a[href="#${ IDS[ 3 ] }"]` );
	await page.waitForTimeout( 300 );
	const desktopClick = await page.evaluate( () => ( {
		open: document.querySelector( 'details[data-mrtu-toc]' ).open,
		focus: document.activeElement.id,
	} ) );
	check( desktopClick.open, '≥ 1024px: clicking a link keeps the TOC open' );
	check( IDS[ 3 ] === desktopClick.focus, '≥ 1024px: focus moved to the heading', desktopClick.focus );
	check( 0 === ( await page.evaluate( () => document.querySelectorAll( 'html[style*="scroll-behavior"], body[style*="scroll-behavior"]' ).length ) ), 'JS never forces scroll-behavior (theme CSS + reduced motion decide)' );
	await desktop.close();

	/* ---------- Phone: tap a link → TOC closes, focus on the heading ---------- */
	const phone = await browser.newContext( { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, reducedMotion: 'reduce' } );
	await serveAssets( phone );
	const mobile = await phone.newPage();
	watch( mobile, 'phone' );
	await mobile.goto( PAGE_URL, { waitUntil: 'load' } );
	await mobile.tap( 'details[data-mrtu-toc] > summary' );
	check( await mobile.evaluate( () => document.querySelector( 'details[data-mrtu-toc]' ).open ), 'phone: tapping the summary opens the TOC' );
	const target = IDS[ 4 ];
	await mobile.click( `details[data-mrtu-toc] a[href="#${ IDS[ 1 ] }"]`, { modifiers: [ 'Control' ] } );
	await mobile.waitForTimeout( 200 );
	const modified = await mobile.evaluate( ( id ) => ( {
		open: document.querySelector( 'details[data-mrtu-toc]' ).open,
		focus: document.activeElement === document.getElementById( id ),
	} ), IDS[ 1 ] );
	check( modified.open && ! modified.focus, 'phone: Ctrl+click (new tab) leaves the TOC open and focus in place' );
	for ( const extra of phone.pages().filter( ( pg ) => pg !== mobile ) ) {
		await extra.close();
	}
	await mobile.tap( `details[data-mrtu-toc] a[href="#${ target }"]` );
	const navigated = await waitFor( mobile, ( id ) => location.hash === `#${ id }`, target );
	const after = await mobile.evaluate( ( id ) => {
		const heading = document.getElementById( id );
		return {
			open: document.querySelector( 'details[data-mrtu-toc]' ).open,
			focus: document.activeElement === heading,
			tabindex: heading.getAttribute( 'tabindex' ),
			top: Math.round( heading.getBoundingClientRect().top ),
		};
	}, target );
	check( navigated, 'phone: location hash updated' );
	check( false === after.open, '< 1024px: clicking a link closes the TOC' );
	check( after.focus && '-1' === after.tabindex, 'phone: focus moved to the heading (tabindex=-1 added on demand)' );
	check( after.top >= -2 && after.top < 160, `phone: heading scrolled into view (top ${ after.top }px)` );
	await phone.close();
} catch ( e ) {
	check( false, 'e2e run', e.stack || String( e ) );
} finally {
	await browser.close();
}

check( 0 === errors.length, 'no console errors, page errors or failed TOC assets', errors.join( ' | ' ) );
if ( assetFallback ) {
	console.log( 'NOTE: the dev server could not serve the symlinked plugin assets (scripts/router.php realpath check); they were served from the repository.' );
}

if ( ! process.env.MRTU_E2E_KEEP ) {
	wp( 'post', 'delete', postId, '--force' );
}
console.log( `RESULT: ${ results.pass }/${ results.pass + results.fail }` );
process.exit( results.fail ? 1 : 0 );
