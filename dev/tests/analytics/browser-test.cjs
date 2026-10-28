/**
 * Google Analytics 4 integration in a real browser (2026-10-25). One Edge instance, always closed.
 *
 * Production is simulated with a temporary MU plugin (wp-content/mu-plugins/zz-cic-analytics-test.php,
 * git-ignored) that filters cic_analytics_environment only for requests carrying a random per-run
 * header; it is deleted in `finally`. Nothing is submitted and no data is changed.
 *
 * Default mode sends NOTHING to Google: the gtag.js loader is answered with an empty local stub and
 * every other non-local request is aborted. The checks read window.dataLayer: the existing array is
 * kept, the receipt redaction sets a clean page_location / page_referrer, and the existing keys-only
 * quote.js pushes add no gtag command.
 *
 * --real-gtag loads the real gtag.js library (without the test header) to see which hits Google's
 * own library would send; every hit to Google Analytics is aborted and navigator.sendBeacon is
 * replaced, so no Analytics data leaves the machine. Run it only when that check is needed.
 *
 *   NODE_PATH=<dir>/node_modules node dev/tests/analytics/browser-test.cjs [--real-gtag]
 */
'use strict';

const puppeteer = require( 'puppeteer-core' );
const fs = require( 'fs' );
const path = require( 'path' );
const crypto = require( 'crypto' );

const BASE = 'https://catericecream.test';
const ROOT = path.resolve( __dirname, '../../..' );
const MU = path.join( ROOT, 'wp-content/mu-plugins/zz-cic-analytics-test.php' );
const BROWSER = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const ID = 'G-NXRRDRV5C2';
const LOADER = 'https://www.googletagmanager.com/gtag/js?id=' + ID;
const REAL = process.argv.includes( '--real-gtag' );
const TOKEN = crypto.randomBytes( 16 ).toString( 'hex' );
const HEADER = 'x-cic-analytics-test';

const results = [];
const check = ( label, ok, detail ) => {
	results.push( !! ok );
	console.log( ( ok ? 'PASS ' : 'FAIL ' ) + label + ( ! ok && detail !== undefined ? ' — ' + JSON.stringify( detail ).slice( 0, 900 ) : '' ) );
};
const sleep = ( ms ) => new Promise( ( r ) => setTimeout( r, ms ) );

// What the page holds: tag elements, gtag commands (Arguments) and plain pushes.
const snapshot = () => ( {
	loaders: document.querySelectorAll( 'script[src*="googletagmanager.com/gtag/js"]' ).length,
	inHead: !! document.head.querySelector( 'script[src*="googletagmanager.com/gtag/js"]' ),
	gtag: typeof window.gtag,
	isArray: Array.isArray( window.dataLayer ),
	commands: Array.isArray( window.dataLayer ) ? window.dataLayer.filter( ( e ) => Object.prototype.toString.call( e ) === '[object Arguments]' ).map( ( a ) => [ a[ 0 ], a[ 0 ] === 'js' ? 'date' : a[ 1 ], a[ 2 ] || null ] ) : [],
	pushes: Array.isArray( window.dataLayer ) ? window.dataLayer.filter( ( e ) => Object.prototype.toString.call( e ) !== '[object Arguments]' ).map( ( e ) => JSON.parse( JSON.stringify( e ) ) ) : [],
	height: document.documentElement.scrollHeight,
	title: ( () => {
		const t = document.querySelector( 'h1' );
		const r = t ? t.getBoundingClientRect() : null;
		return r ? [ Math.round( r.top ), Math.round( r.height ), Math.round( r.width ) ] : null;
	} )(),
} );

( async () => {
	let browser;
	const external = [];
	const hits = [];
	const consoleErrors = [];
	try {
		fs.writeFileSync( MU, "<?php\n// TEMPORARY test file (dev/tests/analytics); deleted by the test. Simulates the Analytics environment only for requests with the per-run header.\n"
			+ "if ( isset( $_SERVER['HTTP_X_CIC_ANALYTICS_TEST'] ) ) {\n"
			+ "\t$cic_t = explode( '|', (string) $_SERVER['HTTP_X_CIC_ANALYTICS_TEST'] );\n"
			+ "\tif ( 4 === count( $cic_t ) && hash_equals( '" + TOKEN + "', $cic_t[0] ) ) {\n"
			+ "\t\tadd_filter( 'cic_analytics_environment', static fn() => array( 'type' => $cic_t[1], 'home_host' => $cic_t[2], 'request_host' => $cic_t[3] ) );\n"
			+ "\t}\n}\n" );

		browser = await puppeteer.launch( { executablePath: BROWSER, headless: 'new', args: [ '--ignore-certificate-errors', '--no-first-run' ] } );
		const page = await browser.newPage();
		await page.setViewport( { width: 1440, height: 900 } );
		let simulate = null;
		await page.setRequestInterception( true );
		page.on( 'request', ( req ) => {
			const url = req.url();
			const local = url.startsWith( BASE + '/' ) || url.startsWith( 'data:' ) || url.startsWith( 'blob:' );
			const headers = Object.assign( {}, req.headers() );
			if ( local ) {
				if ( simulate ) {
					headers[ HEADER ] = TOKEN + '|' + simulate.join( '|' );
				}
				req.continue( { headers } );
				return;
			}
			external.push( url.split( '?' )[ 0 ] + ( url === LOADER ? '?id=' + ID : '' ) );
			if ( url === LOADER && REAL ) {
				delete headers[ HEADER ];
				req.continue( { headers } );
			} else if ( url === LOADER ) {
				req.respond( { status: 200, contentType: 'application/javascript', body: '/* local stub: nothing is sent */' } );
			} else {
				if ( /google-analytics\.com|analytics\.google\.com|doubleclick\.net|google\.com\/(g|ccm)\/collect/.test( url ) ) {
					hits.push( { url, body: req.postData() || '' } );
				}
				req.abort();
			}
		} );
		page.on( 'console', ( m ) => {
			if ( m.type() === 'error' ) {
				consoleErrors.push( m.text() );
			}
		} );
		// An existing dataLayer entry that the tag must keep; sendBeacon never leaves the page.
		await page.evaluateOnNewDocument( () => {
			window.dataLayer = [ { cic_pre: 'kept' } ];
			if ( navigator.sendBeacon ) {
				navigator.sendBeacon = function ( url, data ) {
					window.__cicBeacons = ( window.__cicBeacons || [] ).concat( [ String( url ) ] );
					return true;
				};
			}
		} );
		const open = async ( url, env, opts ) => {
			simulate = env;
			await page.goto( BASE + url, Object.assign( { waitUntil: 'networkidle0', timeout: 60000 }, opts || {} ) );
			return page.evaluate( snapshot );
		};

		/* 1. This local site as it is. */
		const localHome = await open( '/', null );
		const localQuote = await open( '/get-a-quote/?received=abc123', null );
		check( 'B1 local (.test): no gtag.js element, no gtag function, dataLayer untouched on home and Get a Quote',
			[ localHome, localQuote ].every( ( s ) => s.loaders === 0 && s.gtag === 'undefined' && s.commands.length === 0 ), [ localHome, localQuote ] );
		check( 'B2 local (.test): no request to any Google host', external.length === 0, external );
		external.length = 0;

		/* 2. Simulated production. */
		const PROD = [ 'production', 'catericecream.com', 'catericecream.com' ];
		const home = await open( '/', PROD );
		check( 'B3 production: exactly one gtag.js element, inside <head>', home.loaders === 1 && home.inHead, home );
		check( 'B4 production: the existing dataLayer entry is kept (first item unchanged)', home.isArray && home.pushes.length >= 1 && home.pushes[ 0 ].cic_pre === 'kept', home.pushes );
		const cmds = home.commands.map( ( c ) => c[ 0 ] + ( c[ 0 ] === 'config' ? ':' + c[ 1 ] : '' ) );
		check( 'B5 production: gtag commands are js → set → config ' + ID + ' (no consent or event command)', JSON.stringify( cmds ) === JSON.stringify( [ 'js', 'set', 'config:' + ID ] ), home.commands );
		const set = ( s ) => ( s.commands.find( ( c ) => c[ 0 ] === 'set' ) || [] )[ 1 ] || {};
		check( 'B6 production home: page_location is the clean page address', set( home ).page_location === BASE + '/', set( home ) );

		const receipt = await open( '/get-a-quote/?event=birthday&received=abc123#confirmation', PROD );
		check( 'B7 receipt URL: page_location keeps the context key and drops the receipt and #fragment', set( receipt ).page_location === BASE + '/get-a-quote/?event=birthday', set( receipt ) );
		const sent = await open( '/contact/?sent=xyz789', PROD );
		check( 'B8 contact receipt URL: page_location drops ?sent=', set( sent ).page_location === BASE + '/contact/', set( sent ) );
		const after = await open( '/', PROD, { referer: BASE + '/get-a-quote/?received=abc123' } );
		check( 'B9 next page after a confirmation: page_referrer drops the receipt', set( after ).page_referrer === BASE + '/get-a-quote/', set( after ) );

		// quote.js pushes (keys only) are plain objects, never gtag commands.
		await open( '/get-a-quote/', PROD );
		const next = await page.$( '[data-cic-next]' );
		if ( next ) {
			await next.click();
			await sleep( 600 );
		}
		const q = await page.evaluate( snapshot );
		const qEvents = q.pushes.filter( ( p ) => p.event ).map( ( p ) => p.event );
		check( 'B10 Get a Quote: existing keys-only quote pushes still reach dataLayer (' + qEvents.join( ', ' ) + ')', qEvents.includes( 'quote_validation_error' ), q.pushes );
		check( 'B11 Get a Quote: no gtag event command was added (still js → set → config)', q.commands.map( ( c ) => c[ 0 ] ).join() === 'js,set,config', q.commands );
		const pii = JSON.stringify( q.pushes ).match( /@|\d{3}[-. ]?\d{3}[-. ]?\d{4}/ );
		check( 'B12 Get a Quote: pushes carry keys only (no email or phone patterns)', ! pii, q.pushes );

		/* 3. Other environments and design. */
		const staging = await open( '/', [ 'staging', 'catericecream.com', 'catericecream.com' ] );
		const host = await open( '/', [ 'production', 'catericecream.com', 'catericecream.test' ] );
		check( 'B13 staging environment and a .test request host: no tag', staging.loaders === 0 && host.loaders === 0 && staging.gtag === 'undefined' && host.gtag === 'undefined', [ staging, host ] );
		check( 'B14 design unchanged: page height and headline box identical with and without the tag', localHome.height === home.height && JSON.stringify( localHome.title ) === JSON.stringify( home.title ), [ localHome.height, home.height, localHome.title, home.title ] );
		check( 'B15 no console errors on the tagged pages', consoleErrors.length === 0, consoleErrors );

		const google = external.filter( ( u ) => ! u.startsWith( 'https://www.googletagmanager.com/gtag/js' ) );
		if ( ! REAL ) {
			check( 'B16 production (stub mode): the loader was answered locally; no other Google request was attempted', google.length === 0 && hits.length === 0, { google, hits } );
		} else {
			// Real library: give it time to queue its hits, then inspect what it would have sent.
			await open( '/get-a-quote/?received=abc123', PROD );
			await sleep( 4000 );
			const beacons = await page.evaluate( () => window.__cicBeacons || [] );
			const all = hits.map( ( h ) => h.url + '\n' + h.body ).concat( beacons ).join( '\n' );
			const events = [ ...all.matchAll( /[?&\n]en=([^&\s]+)/g ) ].map( ( m ) => decodeURIComponent( m[ 1 ] ) );
			const dls = [ ...all.matchAll( /[?&]dl=([^&\s]+)/g ) ].map( ( m ) => decodeURIComponent( m[ 1 ] ) );
			console.log( 'INFO real gtag.js: aborted hits ' + hits.length + ', beacons kept local ' + beacons.length + ', events [' + [ ...new Set( events ) ].join( ', ' ) + ']' );
			check( 'B16 real gtag.js: hits were produced and every one was blocked locally', hits.length + beacons.length > 0, { external } );
			check( 'B17 real gtag.js: no quote_*/coverage_* event among the hits', ! events.some( ( e ) => /^(quote_|coverage_)/.test( e ) ), events );
			check( 'B18 real gtag.js: no hit carries a receipt (received=/sent=) or an email', ! /received%3D|received=|sent%3D|%40/.test( dls.join( ' ' ) ), dls );
		}
	} catch ( e ) {
		check( 'no unexpected error', false, String( e && e.stack || e ) );
	} finally {
		if ( browser ) {
			await browser.close();
		}
		if ( fs.existsSync( MU ) ) {
			fs.unlinkSync( MU );
		}
		const pass = results.filter( Boolean ).length;
		console.log( `Analytics browser checks${ REAL ? ' (real gtag.js, hits blocked)' : '' }: ${ pass }/${ results.length } passed; temporary MU plugin removed: ${ fs.existsSync( MU ) ? 'NO' : 'yes' }` );
		process.exitCode = pass === results.length ? 0 : 1;
	}
} )();
