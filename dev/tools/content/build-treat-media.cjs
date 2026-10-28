/**
 * Builds the treat-media package (2026-10-24) from the owner's originals, LOCAL ONLY:
 *   - photos → WebP at their own size (never upscaled; the manifest may crop),
 *   - video posters → WebP frames taken from the actual footage at the video's display size
 *     (the browser applies the rotation metadata, so posters have the real orientation),
 *   - videos → byte-for-byte copies under descriptive names (H.264/AAC MP4, played as supplied).
 * Originals are only read. Output: a media package outside the web root, with SHA256SUMS, ready to
 * upload next to the code package; dev/tools/content/treat-media-20261024.php imports it.
 *
 *   NODE_PATH=<dir>/node_modules node dev/tools/content/build-treat-media.cjs [sourceDir] [outDir]
 */
'use strict';

const puppeteer = require( 'puppeteer-core' );
const crypto = require( 'crypto' );
const fs = require( 'fs' );
const http = require( 'http' );
const path = require( 'path' );

const ROOT = path.resolve( __dirname, '../../..' );
const SRC = process.argv[ 2 ] || path.join( ROOT, 'handoff/Cater_Ice_Cream_WebP_Assets/treats' );
const OUT = process.argv[ 3 ] || 'C:/laragon/release/catericecream/media-20261024-treats';
const MANIFEST = path.join( __dirname, 'treat-media-20261024.json' );
const BROWSER = 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe';
const QUALITY = 0.86;

const manifest = JSON.parse( fs.readFileSync( MANIFEST, 'utf8' ) );
const missing = [ ...new Set( Object.values( manifest.assets ).map( ( a ) => a.source ) ) ].filter( ( f ) => ! fs.existsSync( path.join( SRC, f ) ) );
if ( missing.length ) {
	console.error( 'Missing originals in ' + SRC + ':\n  ' + missing.join( '\n  ' ) );
	process.exit( 1 );
}
fs.mkdirSync( OUT, { recursive: true } );

// Tiny local server for the originals (range requests let the browser seek in the videos).
const server = http.createServer( ( req, res ) => {
	const file = path.join( SRC, decodeURIComponent( req.url.split( '?' )[ 0 ] ) );
	if ( '/' === req.url ) {
		res.end( '<!doctype html><title>build</title>' );
		return;
	}
	if ( ! file.startsWith( SRC ) || ! fs.existsSync( file ) ) {
		res.writeHead( 404 );
		res.end();
		return;
	}
	const size = fs.statSync( file ).size;
	const type = /\.mp4$/i.test( file ) ? 'video/mp4' : 'image/jpeg';
	const m = /bytes=(\d+)-(\d*)/.exec( req.headers.range || '' );
	if ( m ) {
		const start = +m[ 1 ];
		const end = m[ 2 ] ? +m[ 2 ] : size - 1;
		res.writeHead( 206, { 'Content-Range': `bytes ${ start }-${ end }/${ size }`, 'Accept-Ranges': 'bytes', 'Content-Length': end - start + 1, 'Content-Type': type } );
		fs.createReadStream( file, { start, end } ).pipe( res );
	} else {
		res.writeHead( 200, { 'Content-Length': size, 'Content-Type': type, 'Accept-Ranges': 'bytes' } );
		fs.createReadStream( file ).pipe( res );
	}
} );

( async () => {
	await new Promise( ( r ) => server.listen( 0, r ) );
	const base = 'http://localhost:' + server.address().port + '/';
	const browser = await puppeteer.launch( { executablePath: BROWSER, headless: true } );
	const report = [];
	try {
		const page = await browser.newPage();
		await page.goto( base );
		for ( const [ key, a ] of Object.entries( manifest.assets ) ) {
			const target = path.join( OUT, a.file );
			if ( 'video' === a.kind ) {
				fs.copyFileSync( path.join( SRC, a.source ), target );
				const info = await page.evaluate( async ( src ) => {
					const v = document.createElement( 'video' );
					v.preload = 'metadata';
					v.src = src;
					await new Promise( ( ok, bad ) => { v.onloadedmetadata = ok; v.onerror = () => bad( new Error( 'cannot decode' ) ); } );
					return { width: v.videoWidth, height: v.videoHeight, duration: Math.round( v.duration * 10 ) / 10 };
				}, base + encodeURIComponent( a.source ) );
				report.push( { key, file: a.file, ...info } );
				continue;
			}
			const out = await page.evaluate( async ( src, kind, time, crop, q ) => {
				let el;
				let w;
				let h;
				if ( 'poster' === kind ) {
					el = document.createElement( 'video' );
					el.muted = true;
					el.preload = 'auto';
					el.src = src;
					await new Promise( ( ok, bad ) => { el.onloadeddata = ok; el.onerror = () => bad( new Error( 'cannot decode' ) ); } );
					el.currentTime = time;
					await new Promise( ( ok ) => { el.onseeked = ok; } );
					w = el.videoWidth;
					h = el.videoHeight;
				} else {
					el = new Image();
					el.src = src;
					await el.decode();
					w = el.naturalWidth;
					h = el.naturalHeight;
				}
				const c = crop || { x: 0, y: 0, width: w, height: h };
				const canvas = document.createElement( 'canvas' );
				canvas.width = Math.min( c.width, w - c.x );
				canvas.height = Math.min( c.height, h - c.y );
				canvas.getContext( '2d' ).drawImage( el, c.x, c.y, canvas.width, canvas.height, 0, 0, canvas.width, canvas.height );
				return { data: canvas.toDataURL( 'image/webp', q ), width: canvas.width, height: canvas.height };
			}, base + encodeURIComponent( a.source ), a.kind, a.time || 0, a.crop || null, QUALITY );
			if ( ! out.data.startsWith( 'data:image/webp' ) ) {
				throw new Error( key + ': the browser did not produce WebP' );
			}
			fs.writeFileSync( target, Buffer.from( out.data.split( ',' )[ 1 ], 'base64' ) );
			report.push( { key, file: a.file, width: out.width, height: out.height } );
		}
	} finally {
		await browser.close();
		server.close();
	}
	const sums = report.map( ( r ) => {
		r.bytes = fs.statSync( path.join( OUT, r.file ) ).size;
		r.sha256 = crypto.createHash( 'sha256' ).update( fs.readFileSync( path.join( OUT, r.file ) ) ).digest( 'hex' );
		return r.sha256 + ' *' + r.file;
	} );
	fs.copyFileSync( MANIFEST, path.join( OUT, path.basename( MANIFEST ) ) );
	fs.writeFileSync( path.join( OUT, 'SHA256SUMS' ), sums.join( '\n' ) + '\n' );
	fs.writeFileSync( path.join( OUT, 'build-report.json' ), JSON.stringify( report, null, '\t' ) + '\n' );
	report.forEach( ( r ) => console.log( `${ r.key.padEnd( 24 ) } ${ r.file.padEnd( 28 ) } ${ r.width }x${ r.height }${ r.duration ? ' ' + r.duration + 's' : '' } ${ Math.round( r.bytes / 1024 ) } KB` ) );
	console.log( 'Package: ' + OUT );
} )().catch( ( e ) => {
	console.error( e );
	server.close();
	process.exit( 1 );
} );
