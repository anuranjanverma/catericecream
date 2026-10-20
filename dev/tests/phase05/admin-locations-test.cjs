/**
 * Phase 05 admin verification (local only, one browser, sequential, always closed).
 * Through the real Service Areas screens: city list (fixture labels), slug change with a one-hop
 * redirect and restore, city content creation + authenticated preview (noindex, banner, override),
 * CSV upload → dry run → apply, and the audit log. Everything is restored in `finally`.
 *
 *   NODE_PATH=<dir>/node_modules node dev/tests/phase05/admin-locations-test.cjs
 */
'use strict';

const puppeteer = require( 'puppeteer-core' );
const { execFileSync } = require( 'child_process' );
const fs = require( 'fs' );
const https = require( 'https' );
const path = require( 'path' );

const BASE = 'https://catericecream.test';
const ROOT = path.resolve( __dirname, '../../..' );
const OUT = path.join( ROOT, 'dev/tests/output/phase05' );
const WP = 'C:\\laragon\\bin\\wp-cli\\wp.cmd';
const BROWSER = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
fs.mkdirSync( OUT, { recursive: true } );

const results = [];
const check = ( label, ok, detail ) => {
	results.push( !! ok );
	console.log( ( ok ? 'PASS ' : 'FAIL ' ) + label + ( ! ok && detail !== undefined ? ' — ' + JSON.stringify( detail ) : '' ) );
};
const wp = ( ...args ) => execFileSync( 'cmd', [ '/c', WP, ...args ], { cwd: ROOT, encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'ignore' ] } );
const last = ( s ) => s.trim().split( '\n' ).pop();

/** Raw status + Location without following redirects (local self-signed certificate). */
const head = ( url ) => new Promise( ( resolve ) => {
	https.get( url, { rejectUnauthorized: false, headers: { 'Cache-Control': 'no-cache' } }, ( res ) => {
		res.resume();
		resolve( { code: res.statusCode, location: res.headers.location || '' } );
	} ).on( 'error', ( e ) => resolve( { code: 0, location: e.message } ) );
} );

let browser;
( async () => {
	const snapshot = path.join( OUT, 'state-admin.json' );
	const csv = path.join( OUT, 'admin-import-test.csv' );
	const state = ( ...a ) => last( wp( 'eval-file', 'dev/tests/phase05/state.php', ...a ) );
	let auth = null;
	wp( 'eval-file', 'dev/tests/phase05/state.php', 'snapshot', snapshot );
	try {
		const pid = state( 'city-id', 'pasadena' );
		fs.writeFileSync( csv, 'state_code,county_name,city_name\nCA,Los Angeles County,Testville Phase Five\n' );
		auth = JSON.parse( last( wp( 'eval-file', 'dev/tests/phase02/auth-cookies.php', 'create' ) ) );
		browser = await puppeteer.launch( { executablePath: BROWSER, headless: true, acceptInsecureCerts: true } );
		const page = await browser.newPage();
		await page.setCookie( ...auth.cookies.map( ( c ) => ( { name: c.name, value: c.value, domain: 'catericecream.test', path: '/', secure: true, httpOnly: true } ) ) );
		await page.setViewport( { width: 1440, height: 1100 } );

		// City list.
		await page.goto( BASE + '/wp-admin/admin.php?page=cic-locations', { waitUntil: 'domcontentloaded' } );
		const list = await page.evaluate( () => ( {
			rows: document.querySelectorAll( '.widefat tbody tr' ).length,
			fixture: document.querySelectorAll( '.cic-badge' ).length,
			pasadena: Array.from( document.querySelectorAll( '.row-title' ) ).some( ( a ) => 'Pasadena, CA' === a.textContent ),
			menu: !! document.querySelector( '#toplevel_page_cic-locations' ),
		} ) );
		await page.screenshot( { path: path.join( OUT, 'admin-cities.png' ), fullPage: true } );
		check( 'A1 Service Areas → Cities lists the location records with FIXTURE labels', list.menu && list.rows >= 20 && list.fixture >= 20 && list.pasadena, list );
		await page.goto( BASE + '/wp-admin/admin.php?page=cic-locations&s=pasa', { waitUntil: 'domcontentloaded' } );
		const searched = await page.$$eval( '.row-title', ( a ) => a.map( ( x ) => x.textContent ) );
		check( 'A2 city search narrows the list', 1 === searched.length && 'Pasadena, CA' === searched[ 0 ], searched );

		// Slug change through the form → one-hop redirect → restore.
		await page.goto( BASE + '/wp-admin/admin.php?page=cic-locations&city=' + pid, { waitUntil: 'domcontentloaded' } );
		await page.screenshot( { path: path.join( OUT, 'admin-city-edit.png' ), fullPage: true } );
		const editor = await page.evaluate( () => ( {
			zips: document.querySelectorAll( 'select[name^="override["]' ).length,
			publish: document.querySelector( '#cic-publish_status' ).value,
			index: document.querySelector( '#cic-index_status' ).value,
		} ) );
		check( 'A3 city editor shows status, index status and per-ZIP review decisions', 6 === editor.zips && 'published' === editor.publish && 'index' === editor.index, editor );
		await page.$eval( '#cic-city_slug', ( el ) => { el.value = 'pasadena-admin-test'; } );
		await Promise.all( [ page.waitForNavigation( { waitUntil: 'domcontentloaded' } ), page.click( '#submit' ) ] );
		const saved = await page.$eval( '.notice-success', ( n ) => n.textContent.trim() ).catch( () => '' );
		const oldUrl = await head( BASE + '/service-areas/california/pasadena/' );
		const newUrl = await head( BASE + '/service-areas/california/pasadena-admin-test/' );
		check( 'A4 slug saved in admin: old URL → one 301 to the new canonical, new URL 200', saved.startsWith( 'City saved.' ) && 301 === oldUrl.code && oldUrl.location === BASE + '/service-areas/california/pasadena-admin-test/' && 200 === newUrl.code, { saved, oldUrl, newUrl } );
		await page.$eval( '#cic-city_slug', ( el ) => { el.value = 'pasadena'; } );
		await Promise.all( [ page.waitForNavigation( { waitUntil: 'domcontentloaded' } ), page.click( '#submit' ) ] );
		const restored = await head( BASE + '/service-areas/california/pasadena/' );
		const legacy = await head( BASE + '/service-areas/california/pasadena-admin-test/' );
		check( 'A5 slug restored in admin: canonical 200 again; the test slug now redirects back', 200 === restored.code && 301 === legacy.code, { restored, legacy } );

		// City content + authenticated preview.
		await Promise.all( [ page.waitForNavigation( { waitUntil: 'domcontentloaded' } ), page.click( 'form:has(input[value="cic_city_editorial"]) button' ) ] );
		await page.waitForSelector( '#acf-group_cic_city', { timeout: 30000 } );
		const ed = await page.evaluate( () => ( {
			reviewed: !! document.querySelector( '[data-name="cic_city_reviewed"]' ),
			tabs: Array.from( document.querySelectorAll( '#acf-group_cic_city .acf-tab-button' ) ).map( ( t ) => t.textContent.trim() ),
			link: !! document.querySelector( '#cic-city-link' ),
		} ) );
		check( 'A6 "Create city content" opens a private record with the city fields, review switch and city link box', ed.reviewed && ed.link && ed.tabs.includes( 'Hero and search' ) && ed.tabs.includes( 'Search engines' ), ed );
		await page.$eval( '[data-name="cic_city_subtitle"] input', ( el ) => { el.value = 'Admin preview override for Pasadena.'; } );
		await Promise.all( [ page.waitForNavigation( { waitUntil: 'domcontentloaded' } ), page.click( '#save-post' ) ] );
		await page.screenshot( { path: path.join( OUT, 'admin-city-content.png' ), fullPage: true } );
		const previewHref = await page.$eval( '#cic-city-preview', ( a ) => a.href ).catch( () => '' );
		const preview = await browser.newPage();
		await preview.setCookie( ...auth.cookies.map( ( c ) => ( { name: c.name, value: c.value, domain: 'catericecream.test', path: '/', secure: true, httpOnly: true } ) ) );
		const resp = await preview.goto( previewHref, { waitUntil: 'domcontentloaded' } );
		const pv = await preview.evaluate( () => ( {
			banner: !! document.querySelector( '.cic-loc-notice--preview' ),
			override: document.body.textContent.includes( 'Admin preview override for Pasadena.' ),
			robots: ( document.querySelector( 'meta[name=robots]' ) || {} ).content || '',
		} ) );
		await preview.screenshot( { path: path.join( OUT, 'admin-preview.png' ) } );
		await preview.close();
		check( 'A7 Preview opens the virtual city URL with the draft override, a preview banner and noindex', previewHref.includes( '/service-areas/california/pasadena/' ) && 200 === resp.status() && pv.banner && pv.override && /noindex/.test( pv.robots ) && resp.headers()[ 'x-robots-tag' ], { previewHref, pv } );
		const publicCtx = await browser.createBrowserContext();
		const anon = await publicCtx.newPage();
		await anon.goto( BASE + '/service-areas/california/pasadena/', { waitUntil: 'domcontentloaded' } );
		const leaked = await anon.evaluate( () => document.body.textContent.includes( 'Admin preview override for Pasadena.' ) );
		await publicCtx.close();
		check( 'A8 the unpublished override is not visible to the public', ! leaked );

		// Import: upload → dry run → apply.
		await page.goto( BASE + '/wp-admin/admin.php?page=cic-locations-import', { waitUntil: 'domcontentloaded' } );
		const upload = await page.$( '#cic-import-file' );
		await upload.uploadFile( csv );
		await page.select( '#cic-import-type', 'cities' );
		await page.select( '#cic-import-dataset', 'fixture' );
		await page.type( '#cic-import-source', 'test:phase05-admin' );
		await Promise.all( [ page.waitForNavigation( { waitUntil: 'domcontentloaded' } ), page.click( '#submit' ) ] );
		const dry = await page.evaluate( () => document.querySelector( '.card' ) ? document.querySelector( '.card' ).textContent : '' );
		const notYet = state( 'city-id', 'testville-phase-five' );
		await page.screenshot( { path: path.join( OUT, 'admin-import-dry-run.png' ), fullPage: true } );
		check( 'A9 upload produces a dry-run report (1 to create) and writes nothing yet', /1 to create, 0 to update, 0 unchanged, 0 rejected/.test( dry ) && '0' === notYet, { dry: dry.slice( 0, 200 ), notYet } );
		await Promise.all( [ page.waitForNavigation( { waitUntil: 'domcontentloaded' } ), page.click( '#apply' ) ] );
		const applied = await page.evaluate( () => document.body.textContent.includes( 'Import applied' ) );
		const row = state( 'city-row', 'testville-phase-five' );
		const testville = await head( BASE + '/service-areas/california/testville-phase-five/' );
		check( 'A10 apply creates the city as draft/noindex with its dataset and source; not public (404)', applied && /"draft"/.test( row ) && /"noindex"/.test( row ) && /test:phase05-admin/.test( row ) && 404 === testville.code, { applied, row, testville } );
		await page.goto( BASE + '/wp-admin/admin.php?page=cic-locations-log', { waitUntil: 'domcontentloaded' } );
		const log = await page.evaluate( () => document.body.textContent );
		await page.screenshot( { path: path.join( OUT, 'admin-log.png' ), fullPage: true } );
		check( 'A11 the log records the slug changes and the import (dry run and applied)', log.includes( 'city_update' ) && log.includes( 'test:phase05-admin' ) && log.includes( 'dry run' ) && log.includes( 'applied' ) );
	} finally {
		try {
			wp( 'eval-file', 'dev/tests/phase05/state.php', 'restore', snapshot );
		} catch ( e ) {
			console.log( 'RESTORE FAILED ' + e.message );
		}
		try {
			if ( auth ) {
				wp( 'eval-file', 'dev/tests/phase02/auth-cookies.php', 'destroy', auth.token );
			}
		} catch ( e ) {}
		try {
			fs.unlinkSync( csv );
		} catch ( e ) {}
		if ( browser ) {
			await browser.close();
		}
	}
	const after = await head( BASE + '/service-areas/california/pasadena/' );
	const gone = await head( BASE + '/service-areas/california/pasadena-admin-test/' );
	check( 'A12 cleanup: canonical slug serves 200, test slug and city removed (404), no city content left', 200 === after.code && 404 === gone.code && '0' === state( 'leftovers', 'testville-phase-five' ), { after, gone } );
	const failed = results.filter( ( r ) => ! r ).length;
	console.log( '== RESULT: ' + ( results.length - failed ) + ' passed, ' + failed + ' failed' );
	process.exitCode = failed ? 1 : 0;
} )().catch( async ( e ) => {
	console.error( e );
	try {
		if ( browser ) {
			await browser.close();
		}
	} catch ( x ) {}
	process.exit( 1 );
} );
