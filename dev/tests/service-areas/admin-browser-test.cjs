/**
 * Service Areas admin enhancement — browser checks (local only, one browser, sequential, always
 * closed). Drives the real screens: Add City, ZIP check + confirm, shared-ZIP scope warning,
 * city-only decision, removing a relationship, city content inheritance + reset, previews, slug
 * redirect + collision, an import conflict, and unauthorised requests. Screenshots for the admin
 * walkthrough go to dev/tests/output/service-areas/. Everything is restored in `finally`.
 *
 *   NODE_PATH=<dir>/node_modules node dev/tests/service-areas/admin-browser-test.cjs
 */
'use strict';

const puppeteer = require( 'puppeteer-core' );
const { execFileSync } = require( 'child_process' );
const fs = require( 'fs' );
const https = require( 'https' );
const path = require( 'path' );

const BASE = 'https://catericecream.test';
const ROOT = path.resolve( __dirname, '../../..' );
const OUT = path.join( ROOT, 'dev/tests/output/service-areas' );
const WP = 'C:\\laragon\\bin\\wp-cli\\wp.cmd';
const BROWSER = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
fs.mkdirSync( OUT, { recursive: true } );

const results = [];
const check = ( label, ok, detail ) => {
	results.push( !! ok );
	console.log( ( ok ? 'PASS ' : 'FAIL ' ) + label + ( ! ok && detail !== undefined ? ' — ' + JSON.stringify( detail ) : '' ) );
};
const wp = ( ...args ) => execFileSync( 'cmd', [ '/c', WP, ...args ], { cwd: ROOT, encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'ignore' ], timeout: 120000 } );
const last = ( s ) => s.trim().split( '\n' ).pop();
const state = ( ...a ) => last( wp( 'eval-file', 'dev/tests/service-areas/state.php', ...a ) );

/** Raw request without following redirects (local self-signed certificate). */
const request = ( url, { method = 'GET', cookies = [], body = '' } = {} ) => new Promise( ( resolve ) => {
	const headers = { 'Cache-Control': 'no-cache' };
	if ( cookies.length ) {
		headers.Cookie = cookies.map( ( c ) => c.name + '=' + c.value ).join( '; ' );
	}
	if ( body ) {
		headers[ 'Content-Type' ] = 'application/x-www-form-urlencoded';
		headers[ 'Content-Length' ] = Buffer.byteLength( body );
	}
	const req = https.request( url, { method, rejectUnauthorized: false, headers }, ( res ) => {
		let data = '';
		res.on( 'data', ( d ) => { data += d; } );
		res.on( 'end', () => resolve( { code: res.statusCode, location: res.headers.location || '', robots: res.headers[ 'x-robots-tag' ] || '', body: data } ) );
	} );
	req.on( 'error', ( e ) => resolve( { code: 0, location: e.message, body: '' } ) );
	if ( body ) {
		req.write( body );
	}
	req.end();
} );

let browser;
( async () => {
	const snapshot = path.join( OUT, 'state-browser.json' );
	const csv = path.join( OUT, 'conflict-test.csv' );
	let auth = null;
	wp( 'eval-file', 'dev/tests/service-areas/state.php', 'snapshot', snapshot );
	try {
		const data = JSON.parse( state( 'test-data' ) );
		const { shared, fresh, others } = data;
		check( 'S0 test data: shared fixture ZIP ' + shared + ' (' + others.length + ' cities) and unused ZIP ' + fresh, shared && fresh && others.length >= 2, data );

		auth = JSON.parse( last( wp( 'eval-file', 'dev/tests/phase02/auth-cookies.php', 'create' ) ) );
		browser = await puppeteer.launch( { executablePath: BROWSER, headless: true, acceptInsecureCerts: true } );
		const page = await browser.newPage();
		page.on( 'dialog', ( d ) => d.accept() );
		await page.setCookie( ...auth.cookies.map( ( c ) => ( { name: c.name, value: c.value, domain: 'catericecream.test', path: '/', secure: true, httpOnly: true } ) ) );
		await page.setViewport( { width: 1440, height: 1000 } );
		const go = ( url ) => page.goto( BASE + url, { waitUntil: 'domcontentloaded' } );
		const submit = ( selector ) => Promise.all( [ page.waitForNavigation( { waitUntil: 'domcontentloaded' } ), page.click( selector ) ] );
		const notice = () => page.$$eval( '.notice-success p, .notice-error p', ( n ) => n.map( ( x ) => x.textContent.trim() ).join( ' | ' ) );
		const shot = ( name ) => page.screenshot( { path: path.join( OUT, name ), fullPage: true } );

		// 1. City list + Add City.
		await go( '/wp-admin/admin.php?page=cic-locations' );
		const list = await page.evaluate( () => ( {
			add: !! document.querySelector( '#cic-add-city' ),
			actions: document.querySelectorAll( '.cic-row-actions' ).length,
			preview: Array.from( document.querySelectorAll( '.cic-row-actions a' ) ).filter( ( a ) => 'Preview' === a.textContent ).length,
			oldNotice: document.body.textContent.includes( 'historical city template' ),
			cov: document.querySelector( '#cic-city-list td[data-coverage] .cic-pill' )?.textContent || '',
		} ) );
		await shot( 'sa-01-city-list.png' );
		check( 'B1 Cities screen has Add City, per-row Edit city / content / Preview actions, readable coverage, no outdated notice', list.add && list.actions >= 20 && list.preview >= 20 && ! list.oldNotice && ! /_/.test( list.cov ), list );
		await submit( '#cic-add-city' );
		await page.type( '#cic-city_name', 'Phase Test Vista' );
		const autoslug = await page.$eval( '#cic-city_slug', ( el ) => el.value );
		await page.type( '#cic-county_name', 'Los Angeles County' );
		await page.select( '#cic-dataset', 'fixture' );
		await shot( 'sa-02-add-city.png' );
		check( 'B2 Add City form auto-generates an editable slug and states the Draft/Noindex/no-coverage start', 'phase-test-vista' === autoslug && ( await page.$eval( 'body', ( b ) => b.textContent.includes( 'never approves service coverage' ) ) ), autoslug );
		await submit( '#submit' );
		const created = await page.evaluate( () => ( {
			url: location.href,
			cards: Array.from( document.querySelectorAll( '#cic-status .cic-pill' ) ).map( ( p ) => p.textContent ),
			noZips: !! document.querySelector( '#cic-no-zips' ),
			msg: document.querySelector( '.notice-success' )?.textContent || '',
		} ) );
		const cityId = state( 'city-id', 'phase-test-vista' );
		await shot( 'sa-03-new-city.png' );
		check( 'B3 new city opens in its editor: Draft, Noindex, coverage Not confirmed, no ZIPs', +cityId > 0 && created.cards.includes( 'Draft (not public)' ) && created.cards.includes( 'Noindex' ) && created.cards.includes( 'Not confirmed' ) && created.noZips, created );

		// City-level preview (no content record yet) and the draft's public URL.
		const previewHref = await page.$eval( '#cic-preview', ( a ) => a.href );
		const pv = await page.browser().newPage();
		await pv.setCookie( ...auth.cookies.map( ( c ) => ( { name: c.name, value: c.value, domain: 'catericecream.test', path: '/', secure: true, httpOnly: true } ) ) );
		const pvRes = await pv.goto( previewHref, { waitUntil: 'domcontentloaded' } );
		const pvInfo = await pv.evaluate( () => ( { banner: !! document.querySelector( '.cic-loc-notice--preview' ), robots: document.querySelector( 'meta[name="robots"]' )?.content || '', h1: document.querySelector( 'h1' )?.textContent || '' } ) );
		await pv.close();
		const publicDraft = await request( BASE + '/service-areas/california/phase-test-vista/' );
		check( 'B4 Preview works for a draft city without content (banner, noindex); public URL is 404', 200 === pvRes.status() && pvInfo.banner && /noindex/.test( pvInfo.robots ) && 404 === publicDraft.code, { status: pvRes.status(), pvInfo, publicDraft: publicDraft.code } );

		// Duplicate + invalid via the form.
		await go( '/wp-admin/admin.php?page=cic-locations&action=new' );
		await page.type( '#cic-city_name', 'Phase Test Vista' );
		await page.type( '#cic-county_name', 'Los Angeles County' );
		await page.select( '#cic-dataset', 'fixture' );
		await submit( '#submit' );
		const dupErr = await notice();
		const refilled = await page.$eval( '#cic-city_name', ( el ) => el.value );
		await shot( 'sa-10-duplicate-error.png' );
		check( 'B5 duplicate city is refused with a clear message and the form is refilled', /already exists/.test( dupErr ) && 'Phase Test Vista' === refilled, dupErr );

		// 2. ZIP check step.
		await go( '/wp-admin/admin.php?page=cic-locations&city=' + cityId );
		await page.type( '#cic-add-zips-input', fresh + ', ' + shared + '\n123 ' + shared );
		await submit( '#cic-check-zips' );
		const plan = await page.evaluate( () => Array.from( document.querySelectorAll( '#cic-zip-plan tbody tr' ) ).map( ( r ) => ( { plan: r.dataset.plan, text: r.textContent } ) ) );
		await ( await page.$( '#cic-add-zips' ) ).evaluate( ( el ) => el.closest( '.cic-section' ).id = 'cic-add-zips-section' );
		await ( await page.$( '#cic-add-zips-section' ) ).screenshot( { path: path.join( OUT, 'sa-04-zip-check.png' ) } );
		const sharedRow = plan.find( ( p ) => 'link' === p.plan ) || { text: '' };
		check( 'B6 check lists a new ZIP, the shared ZIP with its other cities and scope, an invalid entry and a repeat — nothing saved yet', plan.some( ( p ) => 'create' === p.plan ) && others.every( ( o ) => sharedRow.text.includes( o.label ) ) && /does not change coverage/.test( sharedRow.text ) && plan.some( ( p ) => 'invalid' === p.plan ) && plan.some( ( p ) => 'repeated' === p.plan ) && '0' === state( 'mapping-count', cityId ), plan );
		await submit( '#cic-confirm-zips' );
		const linked = await page.evaluate( () => Array.from( document.querySelectorAll( '#cic-zip-table tbody tr' ) ).map( ( r ) => r.dataset.zip ) );
		check( 'B7 confirm adds both ZIPs (new one “Not reviewed yet”)', linked.includes( fresh ) && linked.includes( shared ) && 'unreviewed' === state( 'zip-status', fresh ), { linked, msg: await notice() } );
		await ( await page.$( '#cic-zips' ) ).evaluate( ( el ) => el.closest( '.cic-section' ).id = 'cic-zips-section' );
		await ( await page.$( '#cic-zips-section' ) ).screenshot( { path: path.join( OUT, 'sa-05-zip-table.png' ) } );

		// 3. Scope warning on a shared ZIP-wide change (not saved), then a city-only decision.
		const sel = `select[name="zip_status[${ shared }]"]`;
		const original = await page.$eval( sel, ( s ) => s.value );
		await page.select( sel, 'unreviewed' === original ? 'manual_review' : 'unreviewed' );
		const warn = await page.$eval( `tr[data-zip="${ shared }"] .cic-scope-warn`, ( w ) => ( { hidden: w.hidden, text: w.textContent } ) );
		await page.$eval( `tr[data-zip="${ shared }"]`, ( el ) => el.scrollIntoView( { block: 'center' } ) );
		await page.screenshot( { path: path.join( OUT, 'sa-06-scope-warning.png' ) } );
		check( 'B8 changing ZIP coverage on a shared ZIP warns that every linked city is affected', ! warn.hidden && others.every( ( o ) => warn.text.includes( o.label ) ), warn );
		await page.select( sel, original );
		const covBefore = JSON.parse( state( 'others-coverage', shared, cityId ) );
		await page.select( `select[name="override[${ shared }]"]`, 'disabled' );
		await submit( '#submit' );
		const saveMsg = await notice();
		const covAfter = JSON.parse( state( 'others-coverage', shared, cityId ) );
		const result = await page.$eval( `tr[data-zip="${ shared }"] td[data-effective]`, ( td ) => td.dataset.effective );
		check( 'B9 a city-only decision saves for this city only (message says so; other cities unchanged)', /Phase Test Vista only/.test( saveMsg ) && 'disabled' === result && JSON.stringify( covBefore ) === JSON.stringify( covAfter ), { saveMsg, covBefore, covAfter } );

		// ZIP-wide review of the new ZIP (manual decision) for the import conflict check.
		await page.select( `select[name="zip_status[${ fresh }]"]`, 'enabled' );
		await submit( '#submit' );
		const wideMsg = await notice();
		check( 'B10 a ZIP-wide change reports how many cities it applies to', new RegExp( 'ZIP ' + fresh + ' coverage is now “Served” for its 1 linked city' ).test( wideMsg ), wideMsg );

		// 4. Remove the shared relationship.
		// Clicked from a timer: a native confirm() inside page.click() stalls Puppeteer's navigation wait.
		await Promise.all( [ page.waitForNavigation( { waitUntil: 'domcontentloaded' } ), page.$eval( `button[form="cic-unlink-${ shared }"]`, ( b ) => { setTimeout( () => b.click(), 0 ); } ) ] );
		const unlinkMsg = await notice();
		const stillOthers = JSON.parse( state( 'zip-cities', shared ) );
		check( 'B11 removing a relationship keeps the ZIP and its links to other cities', /record is kept/.test( unlinkMsg ) && JSON.stringify( stillOthers ) === JSON.stringify( others ) && ! ( await page.$( `tr[data-zip="${ shared }"]` ) ), { unlinkMsg, stillOthers } );

		// 5. Slug change → 301, and collision.
		await page.select( '#cic-publish_status', 'published' );
		await page.$eval( '#cic-city_slug', ( el ) => { el.value = 'phase-test-vista-new'; } );
		await submit( '#submit' );
		const hop = await request( BASE + '/service-areas/california/phase-test-vista/' );
		check( 'B12 slug change keeps the old address as a one-hop 301 redirect', 301 === hop.code && hop.location.endsWith( '/service-areas/california/phase-test-vista-new/' ), hop );
		await page.$eval( '#cic-city_slug', ( el ) => { el.value = 'pasadena'; } );
		await submit( '#submit' );
		const clash = await notice();
		await shot( 'sa-11-slug-collision.png' );
		check( 'B13 a slug used by another city is refused with the city named', /already used by Pasadena/.test( clash ) && 'phase-test-vista-new' === await page.$eval( '#cic-city_slug', ( el ) => el.value ), clash );
		await page.select( '#cic-publish_status', 'draft' );
		await submit( '#submit' );

		// 6. Import conflict with the manual ZIP decision (dry run only).
		fs.writeFileSync( csv, 'zip,state_code,county_label,service_status\n' + fresh + ',CA,Los Angeles County,disabled\n' );
		await go( '/wp-admin/admin.php?page=cic-locations-import' );
		await ( await page.$( '#cic-import-file' ) ).uploadFile( csv );
		await page.select( '#cic-import-type', 'zips' );
		await page.select( '#cic-import-dataset', 'fixture' );
		await page.type( '#cic-import-source', 'Service Areas conflict test' );
		await page.click( 'input[name="apply_status"]' );
		await submit( '#submit' );
		const conflict = await page.evaluate( () => ( { list: document.querySelector( '#cic-import-conflicts + ul' )?.textContent || '', summary: document.querySelector( '.card strong' )?.textContent || '' } ) );
		await shot( 'sa-09-import-conflict.png' );
		check( 'B14 a CSV dry run lists the manual coverage decision as a conflict instead of overwriting it', /Kept the manual decision/.test( conflict.list ) && /1 conflicts with manual edits/.test( conflict.summary ), conflict );

		// 7. City content: inherited values, override, reset.
		await go( '/wp-admin/admin.php?page=cic-locations&city=' + cityId );
		await submit( '#cic-create-content' );
		const field = '.acf-field[data-name="cic_city_eyebrow"]';
		await page.waitForSelector( field + ' .cic-inherited' );
		const inh = await page.$eval( field, ( f ) => ( { note: f.querySelector( '.cic-inherited' ).textContent, value: f.querySelector( 'input[type=text]' ).value } ) );
		await shot( 'sa-07-content-inherited.png' );
		check( 'B15 empty override fields show the effective inherited content (tokens resolved), value stays empty', /Inherited from the city template/.test( inh.note ) && '' === inh.value, inh );
		await page.type( field + ' input[type=text]', 'Ice cream in {city}' );
		await submit( '#save-post' );
		const over = await page.$eval( field, ( f ) => ( { note: f.querySelector( '.cic-inherited' ).textContent, reset: !! f.querySelector( '.cic-reset-override' ) } ) );
		await page.$eval( field, ( el ) => el.scrollIntoView( { block: 'center' } ) );
		await page.screenshot( { path: path.join( OUT, 'sa-08-content-override.png' ) } );
		check( 'B16 a saved override shows the template value and Reset to inherited', /Overrides the city template/.test( over.note ) && over.reset, over );
		await page.click( field + ' .cic-reset-override' );
		await submit( '#save-post' );
		const reset = await page.$eval( field, ( f ) => ( { note: f.querySelector( '.cic-inherited' ).textContent, value: f.querySelector( 'input[type=text]' ).value } ) );
		const stored = state( 'eyebrow', cityId );
		check( 'B17 Reset to inherited empties the override; nothing inherited is copied into it', /Inherited from the city template/.test( reset.note ) && '' === reset.value && '[]' === stored, { reset, stored } );
		const contentPreview = await page.$eval( '#cic-city-preview', ( a ) => a.href );
		const cp = await request( contentPreview, { cookies: auth.cookies } );
		check( 'B18 content record preview opens the city page for managers (noindex)', 200 === cp.code && /noindex/.test( cp.robots ), { code: cp.code, robots: cp.robots } );

		// 8. Unauthorised requests.
		const form = 'action=cic_city_create&city_name=Phase+Unauth&county_name=Los+Angeles+County&state_code=CA&dataset=fixture';
		const anon = await request( BASE + '/wp-admin/admin-post.php', { method: 'POST', body: form + '&_wpnonce=x' } );
		const badNonce = await request( BASE + '/wp-admin/admin-post.php', { method: 'POST', cookies: auth.cookies, body: form + '&_wpnonce=invalid' } );
		const editor = JSON.parse( last( wp( 'eval-file', 'dev/tests/service-areas/state.php', 'editor-cookies' ) ) );
		const ed = await request( BASE + '/wp-admin/admin-post.php', { method: 'POST', cookies: editor.cookies, body: form + '&_wpnonce=' + editor.nonce } );
		const edScreen = await request( BASE + '/wp-admin/admin.php?page=cic-locations', { cookies: editor.cookies } );
		const unlinkAnon = await request( BASE + '/wp-admin/admin-post.php', { method: 'POST', cookies: editor.cookies, body: 'action=cic_city_zip_unlink&city_id=' + cityId + '&zip=' + fresh + '&_wpnonce=' + editor.nonce } );
		const made = state( 'city-id', 'phase-unauth' );
		const stillLinked = state( 'mapping-count', cityId );
		check( 'B19 logged-out, bad-nonce and non-manager (editor) requests are rejected; nothing is created or removed', ! editor.can && [ 200, 302, 400 ].includes( anon.code ) && ! /City created/.test( anon.location ) &&403 === badNonce.code && 403 === ed.code && 403 === unlinkAnon.code && 200 !== edScreen.code && '0' === made && '1' === stillLinked, { anon: anon.code, badNonce: badNonce.code, editor: ed.code, screen: edScreen.code, unlink: unlinkAnon.code, made, stillLinked } );
	} catch ( e ) {
		check( 'Unexpected error: ' + e.message, false );
		if ( browser ) {
			const pages = await browser.pages().catch( () => [] );
			const p = pages[ pages.length - 1 ];
			if ( p ) {
				console.log( 'At: ' + p.url() );
				await p.screenshot( { path: path.join( OUT, 'failure.png' ), fullPage: true } ).catch( () => {} );
			}
		}
	} finally {
		if ( browser ) {
			await browser.close().catch( () => {} );
		}
		try {
			if ( auth ) {
				wp( 'eval-file', 'dev/tests/phase02/auth-cookies.php', 'destroy', auth.token );
			}
			wp( 'eval-file', 'dev/tests/service-areas/state.php', 'restore', snapshot );
			const left = state( 'city-id', 'phase-test-vista-new' ) + state( 'city-id', 'phase-test-vista' );
			check( 'Z1 state restored and browser closed (no test city left)', '00' === left, left );
		} catch ( e ) {
			check( 'Z1 restore failed: ' + e.message, false );
		}
		fs.rmSync( snapshot, { force: true } );
		fs.rmSync( csv, { force: true } );
		const passed = results.filter( Boolean ).length;
		console.log( `Service Areas browser checks: ${ passed }/${ results.length } passed` );
		process.exitCode = passed === results.length ? 0 : 1;
	}
} )();
