// Concatenates theme/mr-tu/assets/css/src/*.css (sorted by file name) into assets/css/main.css,
// then transpiles + minifies with Lightning CSS for Safari 15+/Chrome 100+/Firefox 100+.
// Usage: node scripts/build-css.mjs
import { readFileSync, readdirSync, writeFileSync, existsSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { transform } from 'lightningcss';

const root = join( dirname( fileURLToPath( import.meta.url ) ), '..' );
const bundles = [
	{ src: 'theme/mr-tu/assets/css/src', out: 'theme/mr-tu/assets/css/main.css' },
	{ src: 'theme/mr-tu/assets/css/editor-src', out: 'theme/mr-tu/assets/css/editor.css' },
];

// Explicit targets (no browserslist dependency): versions encoded as major << 16.
const v = ( major, minor = 0 ) => ( major << 16 ) | ( minor << 8 );
const targets = { safari: v( 15 ), ios_saf: v( 15 ), chrome: v( 100 ), firefox: v( 100 ), edge: v( 100 ) };

for ( const bundle of bundles ) {
	const dir = join( root, bundle.src );
	if ( ! existsSync( dir ) ) {
		continue;
	}
	const files = readdirSync( dir ).filter( ( f ) => f.endsWith( '.css' ) ).sort();
	const source = files.map( ( f ) => `/* ${ f } */\n` + readFileSync( join( dir, f ), 'utf8' ) ).join( '\n' );
	const { code, warnings } = transform( {
		filename: 'main.css',
		code: Buffer.from( source ),
		minify: true,
		targets,
		drafts: { customMedia: true },
	} );
	for ( const w of warnings ) {
		console.warn( `[css] ${ w.message } (${ w.loc?.line ?? '?' }:${ w.loc?.column ?? '?' })` );
	}
	writeFileSync( join( root, bundle.out ), code );
	console.log( `[css] ${ bundle.out } ← ${ files.length } files, ${ code.length } bytes` );
}
