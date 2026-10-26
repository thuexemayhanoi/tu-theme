// Builds the Mr Tu theme front-end assets.
//
// CSS: concatenates theme/mr-tu/assets/css/src/*.css (sorted by file name) into assets/css/main.css,
// and a subset of those files + assets/css/editor-src/*.css into assets/css/editor.css (editor
// canvas). Transpiled + minified with Lightning CSS for Safari 15+/Chrome 100+/Firefox 100+.
// JS: minifies assets/js/theme.js into assets/js/theme.min.js with esbuild (enqueued unless
// SCRIPT_DEBUG). Prints raw and gzip sizes so budgets stay visible (CSS < 60 KB, JS < 6 KB).
//
// Usage: node scripts/build-css.mjs
import { readFileSync, readdirSync, writeFileSync, existsSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { gzipSync } from 'node:zlib';
import { transform } from 'lightningcss';

const root = join( dirname( fileURLToPath( import.meta.url ) ), '..' );
const theme = join( root, 'theme/mr-tu' );
const src = 'assets/css/src';
const editorSrc = 'assets/css/editor-src';

const list = ( dir ) =>
	existsSync( join( theme, dir ) )
		? readdirSync( join( theme, dir ) )
				.filter( ( f ) => f.endsWith( '.css' ) )
				.sort()
				.map( ( f ) => `${ dir }/${ f }` )
		: [];

// Front-end files shared with the editor canvas (tokens, fonts, block skins, prose).
const editorShared = [ '00-fonts.css', '01-tokens.css', '04-buttons.css', '05-objects.css', '22-plugin-blocks.css', '30-page-prose.css' ].map(
	( f ) => `${ src }/${ f }`
);

// Home-page-only files ship as a second stylesheet (inc/assets.php enqueues it on the front page and
// on pages that use the home sections) so inner pages do not download ~19 KB of unused CSS.
const homeOnly = ( f ) => /\/2[01]-home-/.test( f );

const bundles = [
	{ out: 'assets/css/main.css', files: list( src ).filter( ( f ) => ! homeOnly( f ) ) },
	{ out: 'assets/css/home.css', files: list( src ).filter( homeOnly ) },
	{ out: 'assets/css/editor.css', files: [ ...editorShared, ...list( editorSrc ) ] },
];

// Explicit targets (no browserslist dependency): versions encoded as major << 16.
const v = ( major, minor = 0 ) => ( major << 16 ) | ( minor << 8 );
const targets = { safari: v( 15 ), ios_saf: v( 15 ), chrome: v( 100 ), firefox: v( 100 ), edge: v( 100 ) };

const report = ( label, buffer ) =>
	console.log( `[build] ${ label }: ${ ( buffer.length / 1024 ).toFixed( 1 ) } KB (gzip ${ ( gzipSync( buffer ).length / 1024 ).toFixed( 1 ) } KB)` );

for ( const bundle of bundles ) {
	if ( ! bundle.files.length ) {
		continue;
	}
	const source = bundle.files.map( ( f ) => `/* ${ f } */\n` + readFileSync( join( theme, f ), 'utf8' ) ).join( '\n' );
	const { code, warnings } = transform( {
		filename: bundle.out,
		code: Buffer.from( source ),
		minify: true,
		targets,
		drafts: { customMedia: true },
	} );
	for ( const w of warnings ) {
		console.warn( `[css] ${ w.message } (${ w.loc?.line ?? '?' }:${ w.loc?.column ?? '?' })` );
	}
	writeFileSync( join( theme, bundle.out ), code );
	report( `${ bundle.out } ← ${ bundle.files.length } files`, code );
}

const jsIn = join( theme, 'assets/js/theme.js' );
if ( existsSync( jsIn ) ) {
	let esbuild = null;
	try {
		esbuild = await import( 'esbuild' );
	} catch ( e ) {
		console.warn( '[js] esbuild not installed — theme.min.js not rebuilt (run npm install).' );
	}
	if ( esbuild ) {
		const result = await esbuild.transform( readFileSync( jsIn, 'utf8' ), {
			minify: true,
			target: [ 'safari15', 'chrome100', 'firefox100' ],
			legalComments: 'none',
		} );
		const code = Buffer.from( result.code );
		writeFileSync( join( theme, 'assets/js/theme.min.js' ), code );
		report( 'assets/js/theme.min.js', code );
	}
}
