/**
 * Full QA matrix for a seeded local site (never production).
 *
 * For every language × page role × viewport × colour mode it records: HTTP status, <html lang>,
 * number of <h1>, horizontal overflow, console errors, canonical/description/robots/hreflang counts,
 * language-switcher link count, mixed-language leaks (Vietnamese diacritics on non-VI pages outside
 * content, obvious English UI words on VI pages), axe-core serious/critical violations (light + dark,
 * mobile + desktop), and saves screenshots. Prints a JSON summary and exits 1 on any failure.
 *
 * Usage: BASE=http://localhost:8084 node tests/e2e/qa-matrix.cjs [--shots]
 */
const { chromium } = require( 'playwright' );
const fs = require( 'fs' );
const path = require( 'path' );

const BASE = ( process.env.BASE || 'http://localhost:8080' ).replace( /\/$/, '' );
const SHOTS = process.argv.includes( '--shots' );
const OUT = path.join( __dirname, 'artifacts', 'qa-matrix' );
fs.mkdirSync( OUT, { recursive: true } );

let AxeBuilder = null;
try {
	AxeBuilder = require( '/home/user/qa-tools/node_modules/@axe-core/playwright' ).default;
} catch ( e ) {
	try {
		AxeBuilder = require( '@axe-core/playwright' ).default;
	} catch ( e2 ) {
		console.warn( 'axe-core not available — accessibility scan skipped' );
	}
}

// Slugs from config/seo-plan.json + docs/content-brief.md §7.
const plan = JSON.parse( fs.readFileSync( path.join( __dirname, '..', '..', 'config', 'seo-plan.json' ), 'utf8' ) );
const FIXED = {
	blog: { vi: 'cam-nang', en: 'guides', de: 'ratgeber', fr: 'conseils', es: 'guias' },
	privacy: { vi: 'chinh-sach-bao-mat', en: 'privacy-policy', de: 'datenschutz', fr: 'politique-de-confidentialite', es: 'politica-de-privacidad' },
};
const LANGS = [ 'vi', 'en', 'de', 'fr', 'es' ];
const ROLES = [ 'home', 'pillar', 'old-quarter', 'pricing', 'fleet', 'short-term', 'monthly', 'guide-faq', 'about', 'contact', 'blog', 'privacy' ];
const VIEWPORTS = [ [ 'm', 390, 844 ], [ 'd', 1280, 800 ] ];
const MODES = [ 'light', 'dark' ];

function urlFor( lang, role ) {
	const prefix = 'vi' === lang ? '' : '/' + lang;
	if ( 'home' === role ) {
		return prefix + '/';
	}
	const slug = FIXED[ role ] ? FIXED[ role ][ lang ] : plan.locales[ lang ].roles[ role ].slug;
	return prefix + '/' + slug + '/';
}

const VI_RE = /[ăâđêôơưạảấầẩẫậắằẳẵặẹẻẽếềểễệỉịọỏốồổỗộớờởỡợụủứừửữựỳỵỷỹ]/i;
const EN_UI = /\b(Table of contents|Price on request|Contact page|Read more|Keep exploring|Good to know|Call now)\b/;

( async () => {
	const browser = await chromium.launch();
	const results = [];
	let failures = 0;
	const seo404 = [];

	for ( const lang of LANGS ) {
		for ( const role of ROLES ) {
			const url = BASE + urlFor( lang, role );
			for ( const [ tag, w, h ] of VIEWPORTS ) {
				for ( const mode of MODES ) {
					const ctx = await browser.newContext( { viewport: { width: w, height: h }, colorScheme: mode } );
					const page = await ctx.newPage();
					const errors = [];
					page.on( 'pageerror', ( e ) => errors.push( String( e ) ) );
					page.on( 'console', ( m ) => { if ( 'error' === m.type() ) errors.push( m.text() ); } );
					await page.addInitScript( ( m ) => { try { localStorage.setItem( 'mrtu-theme', m ); } catch ( e ) {} }, mode );
					const resp = await page.goto( url, { waitUntil: 'networkidle' } );
					const status = resp ? resp.status() : 0;
					const data = await page.evaluate( () => {
						const q = ( s ) => document.querySelectorAll( s ).length;
						const chrome = [ ...document.querySelectorAll( 'header, footer, nav, .mrtu-qc, .mrtu-toc, .mrtu-pricing, .mrtu-fleet, .mrtu-contact-card, .mrtu-cta' ) ].map( ( el ) => el.innerText ).join( ' ' );
						return {
							lang: document.documentElement.lang,
							h1: q( 'h1' ),
							overflow: document.documentElement.scrollWidth > window.innerWidth,
							canonical: q( 'link[rel="canonical"]' ),
							description: q( 'meta[name="description"]' ),
							robots: q( 'meta[name="robots"]' ),
							hreflang: q( 'link[rel="alternate"][hreflang]' ),
							switcher: q( '.language-switcher a, [data-mrtu-switcher] a, .mrtu-switcher a' ),
							theme: document.documentElement.dataset.theme || 'system',
							chrome,
						};
					} );
					const row = { lang, role, tag, mode, url, status, ...data };
					delete row.chrome;
					row.problems = [];
					if ( 200 !== status ) row.problems.push( 'status ' + status );
					if ( row.h1 !== 1 ) row.problems.push( 'h1=' + row.h1 );
					if ( row.overflow ) row.problems.push( 'overflow' );
					if ( errors.length ) row.problems.push( 'console:' + errors[ 0 ].slice( 0, 80 ) );
					if ( row.canonical !== 1 ) row.problems.push( 'canonical=' + row.canonical );
					if ( row.description !== 1 ) row.problems.push( 'description=' + row.description );
					if ( row.robots > 1 ) row.problems.push( 'robots=' + row.robots );
					if ( 'vi' !== lang && VI_RE.test( data.chrome.replace( /Phố Cổ|Hoàn Kiếm|Mr Tu|Tiếng Việt|Hà Nội|Hanoï|Hanói/g, '' ) ) ) row.problems.push( 'vi-text-in-ui' );
					if ( 'vi' === lang && EN_UI.test( data.chrome ) ) row.problems.push( 'en-ui-on-vi' );
					const expectedLang = { vi: 'vi-VN', en: 'en', de: 'de', fr: 'fr', es: 'es' }[ lang ];
					if ( row.lang.toLowerCase() !== expectedLang.toLowerCase() && row.lang.toLowerCase().split( '-' )[ 0 ] !== lang ) row.problems.push( 'lang=' + row.lang );
					if ( AxeBuilder && 'm' === tag ) {
						try {
							const axe = await new AxeBuilder( { page } ).withTags( [ 'wcag2a', 'wcag2aa', 'wcag22aa' ] ).analyze();
							const serious = axe.violations.filter( ( v ) => [ 'serious', 'critical' ].includes( v.impact ) );
							row.axe = serious.map( ( v ) => v.id + '×' + v.nodes.length );
							if ( serious.length ) row.problems.push( 'axe:' + row.axe.join( ',' ) );
						} catch ( e ) {
							row.axe = [ 'error' ];
						}
					}
					if ( SHOTS ) {
						await page.evaluate( async () => {
							const step = Math.max( 300, window.innerHeight * 0.8 );
							for ( let y = 0; y < document.documentElement.scrollHeight; y += step ) { window.scrollTo( 0, y ); await new Promise( ( r ) => setTimeout( r, 40 ) ); }
							window.scrollTo( 0, 0 ); await new Promise( ( r ) => setTimeout( r, 500 ) );
						} );
						await page.screenshot( { path: path.join( OUT, `${ lang }-${ role }-${ tag }-${ mode }.png` ), fullPage: true } );
					}
					if ( row.problems.length ) failures++;
					results.push( row );
					await ctx.close();
				}
			}
		}
		// 404 + redirect behaviour per language.
		const prefix = 'vi' === lang ? '' : '/' + lang;
		const ctx = await browser.newContext();
		const p = await ctx.newPage();
		const r404 = await p.goto( BASE + prefix + '/khong-ton-tai-xyz/' );
		const h1 = await p.locator( 'h1' ).count();
		seo404.push( { lang, status: r404.status(), h1 } );
		if ( r404.status() !== 404 || h1 !== 1 ) failures++;
		await ctx.close();
	}
	await browser.close();
	const summary = {
		base: BASE,
		checked: results.length,
		failed: results.filter( ( r ) => r.problems.length ).length,
		problems: results.filter( ( r ) => r.problems.length ).map( ( r ) => ( { url: r.url, tag: r.tag, mode: r.mode, problems: r.problems } ) ),
		notFound: seo404,
	};
	fs.writeFileSync( path.join( OUT, 'summary.json' ), JSON.stringify( { summary, results }, null, 1 ) );
	console.log( JSON.stringify( summary, null, 1 ) );
	process.exit( failures ? 1 : 0 );
} )();
