/**
 * Two-step quote flow with saved progress (2026-10-23). Local only, synthetic @example.test
 * addresses, mail captured by Mailpit. Enquiry settings and test records are restored by the
 * Phase 06 state helper in `finally`; test mail is deleted. One Edge instance, always closed.
 *
 *   NODE_PATH=<dir>/node_modules node dev/tests/quote-flow/browser-test.cjs
 */
'use strict';

const puppeteer = require( 'puppeteer-core' );
const { execFileSync } = require( 'child_process' );
const crypto = require( 'crypto' );
const fs = require( 'fs' );
const http = require( 'http' );
const path = require( 'path' );

const BASE = 'https://catericecream.test';
const ROOT = path.resolve( __dirname, '../../..' );
const OUT = path.join( ROOT, 'dev/tests/output/quote-flow' );
const WP = 'C:\\laragon\\bin\\wp-cli\\wp.cmd';
const BROWSER = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const ORDER = [ 'birthday', 'wedding', 'graduation', 'prom', 'corporate', 'anniversary' ];
const AXE = require.resolve( 'axe-core/axe.min.js' );
fs.mkdirSync( OUT, { recursive: true } );

const results = [];
const check = ( label, ok, detail ) => {
	results.push( !! ok );
	console.log( ( ok ? 'PASS ' : 'FAIL ' ) + label + ( ! ok && detail !== undefined ? ' — ' + JSON.stringify( detail ).slice( 0, 700 ) : '' ) );
};
const wp = ( ...args ) => execFileSync( 'cmd', [ '/c', WP, ...args ], { cwd: ROOT, encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'ignore' ], timeout: 120000 } );
const last = ( s ) => s.trim().split( '\n' ).pop();
const state = ( ...a ) => last( wp( 'eval-file', 'dev/tests/phase06/state.php', ...a ) );
const sleep = ( ms ) => new Promise( ( r ) => setTimeout( r, ms ) );
// Inquiry records for an address: id, stage, workflow status, assignee and selected fields.
const records = ( email ) => JSON.parse( last( wp( 'eval-file', 'dev/tests/quote-flow/state.php', 'records', email ) ) );
const mailpit = ( pathname, method = 'GET', body = '' ) => new Promise( ( resolve ) => {
	const req = http.request( 'http://localhost:8025' + pathname, { method, headers: body ? { 'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength( body ) } : {} }, ( res ) => {
		let data = '';
		res.on( 'data', ( d ) => { data += d; } );
		res.on( 'end', () => { try { resolve( JSON.parse( data || '{}' ) ); } catch ( e ) { resolve( {} ); } } );
	} );
	req.on( 'error', () => resolve( {} ) );
	if ( body ) {
		req.write( body );
	}
	req.end();
} );
const mails = async ( to ) => ( await mailpit( '/api/v1/search?query=' + encodeURIComponent( 'to:"' + to + '"' ) + '&limit=50' ) ).messages || [];

let browser;
( async () => {
	const snapshot = path.join( OUT, 'state.json' );
	const operator = 'operator-qf@example.test';
	const who = 'qf-' + crypto.randomBytes( 3 ).toString( 'hex' ) + '@example.test';
	const other = 'qf-other-' + crypto.randomBytes( 3 ).toString( 'hex' ) + '@example.test';
	state( 'snapshot', snapshot );
	try {
		state( 'recipient', operator );
		state( 'reset-limits' );
		browser = await puppeteer.launch( { executablePath: BROWSER, headless: true, acceptInsecureCerts: true } );
		const ctxA = await browser.createBrowserContext();
		const page = await ctxA.newPage();
		const errors = [];
		page.on( 'pageerror', ( e ) => errors.push( e.message ) );
		const draftCalls = [];
		page.on( 'request', ( r ) => {
			if ( r.url().includes( '/cic/v1/quote-draft' ) ) {
				draftCalls.push( { url: r.url().replace( BASE, '' ), body: r.postData() || '' } );
			}
		} );
		const go = async ( url ) => page.goto( BASE + url, { waitUntil: 'networkidle2' } );
		const shot = ( name ) => page.screenshot( { path: path.join( OUT, name ), fullPage: true } );
		const axe = async ( selector ) => {
			await page.addScriptTag( { path: AXE } );
			return page.evaluate( async ( sel ) => ( await window.axe.run( sel, { runOnly: [ 'wcag2a', 'wcag2aa', 'wcag21aa', 'best-practice' ] } ) ).violations.map( ( v ) => v.id + ' (' + v.nodes.length + ')' ), selector );
		};
		const visible = ( sel ) => page.$eval( sel, ( e ) => !! ( e.offsetWidth || e.offsetHeight ) ).catch( () => false );

		/* ================= Layout ================= */
		await page.setViewport( { width: 1440, height: 900 } );
		await go( '/get-a-quote/?event=graduation&guests=60' );
		const l1 = await page.evaluate( () => {
			const top = ( sel ) => Array.from( document.querySelectorAll( sel ) ).filter( ( e ) => e.offsetParent ).map( ( e ) => Math.round( e.getBoundingClientRect().top ) );
			const step1 = document.querySelector( '.cic-qf__step[data-step="1"]' );
			const names = Array.from( step1.querySelectorAll( '[name]' ) ).map( ( i ) => i.name ).filter( ( n, i, a ) => a.indexOf( n ) === i );
			return {
				step1: ! step1.hidden,
				step2: ! document.querySelector( '.cic-qf__step[data-step="2"]' ).hidden,
				names,
				treatsRow: new Set( top( '.cic-treat' ) ).size,
				treats: document.querySelectorAll( '.cic-treat' ).length,
				contactRow: top( '[data-name="your_name"], [data-name="your_email"]' ),
				subhead: ( document.querySelector( '.cic-qf__subhead' ) || {} ).textContent,
				consent: ( document.querySelector( '.cic-qf__consent' ) || {} ).textContent,
				noOptional: ! document.querySelector( '.cic-qf [name="flavours"], .cic-qf [name="party_theme"], .cic-qf [name="dietary"], .cic-qf [name="notes"]' ),
				capture: /sav(ed|ing)|autosav/i.test( document.querySelector( '.cic-qf' ).innerText ),
			};
		} );
		check( 'L1 Step 1 shows name, email, phone, contact method and treats; heading and consent line; no optional fields; no capture notice', l1.step1 && ! l1.step2 && [ 'your_name', 'your_email', 'phone', 'contact_pref', 'treats[]', 'treats_unsure' ].every( ( n ) => l1.names.includes( n ) ) && 'Pick your favorites, or let us help you choose.' === l1.subhead && /By continuing, you agree that we may contact you about your event\./.test( l1.consent ) && l1.noOptional && ! l1.capture, l1 );
		check( 'L2 desktop: all four treats on one row; name and email side by side', 1 === l1.treatsRow && 4 === l1.treats && 2 === l1.contactRow.length && l1.contactRow[ 0 ] === l1.contactRow[ 1 ], l1 );
		await shot( 'step1-1440.png' );

		/* ================= No draft before Continue ================= */
		await page.type( '#cic-f-your-name', 'Quote Flow Tester' );
		await page.type( '#cic-f-your-email', who );
		await page.click( '#cic-f-phone' );
		await page.type( '#cic-f-phone', '(818) 555-0142' );
		await page.click( 'input[name="treats[]"][value="dippin-dots"] + .cic-treat__box' );
		await page.click( 'body' );
		await sleep( 1500 );
		check( 'D1 typing Step 1 (and leaving fields) sends nothing and stores nothing', 0 === draftCalls.length && 0 === records( who ).length, { calls: draftCalls.length } );

		/* Invalid contact details: useful errors, no request. */
		await page.$eval( '#cic-f-your-email', ( i ) => { i.value = 'not-an-email'; } );
		await page.click( '[data-cic-next]' );
		await sleep( 400 );
		const bad = await page.evaluate( () => ( { tip: ( document.querySelector( '[data-name="your_email"] .cic-tip' ) || {} ).textContent, summary: ! document.querySelector( '[data-cic-errors]' ).hidden, step: document.querySelector( '.cic-qf__step[data-step="2"]' ).hidden } ) );
		check( 'D2 invalid email: field error and summary, stays on Step 1, no request', /valid email/i.test( bad.tip || '' ) && bad.summary && bad.step && 0 === draftCalls.length, bad );
		const server422 = await page.evaluate( async ( u ) => ( await fetch( u, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CIC-Draft': '1' }, body: JSON.stringify( { key: 'k'.repeat( 24 ), rev: '1', fields: { your_name: '', your_email: 'x', treats: [] } } ) } ) ).status, BASE + '/wp-json/cic/v1/quote-draft' );
		check( 'D2b the server rejects invalid Step 1 values too (422, nothing stored)', 422 === server422 && 0 === records( who ).length, server422 );
		await page.$eval( '#cic-f-your-email', ( i ) => { i.value = ''; } );
		await page.type( '#cic-f-your-email', who );

		/* Continue fails once (network): error with retry, values kept, then one draft. */
		await sleep( 3200 ); // Spam guard: Step 1 may not be sent within 3 s of rendering.
		await page.setRequestInterception( true );
		let failOnce = true;
		const intercept = ( r ) => {
			if ( failOnce && r.url().endsWith( '/cic/v1/quote-draft' ) ) {
				failOnce = false;
				r.abort( 'failed' );
				return;
			}
			r.continue();
		};
		page.on( 'request', intercept );
		await page.click( '[data-cic-next]' );
		await sleep( 1500 );
		const fail = await page.evaluate( () => ( { box: ( document.querySelector( '[data-cic-failure]' ) || {} ).innerText || '', hidden: document.querySelector( '[data-cic-failure]' ).hidden, name: document.querySelector( '#cic-f-your-name' ).value, step2: ! document.querySelector( '.cic-qf__step[data-step="2"]' ).hidden } ) );
		check( 'D3 a failed Continue shows a clear error with "Try again", keeps the values and stays on Step 1', ! fail.hidden && /couldn.t save/i.test( fail.box ) && /Try again/.test( fail.box ) && 'Quote Flow Tester' === fail.name && ! fail.step2, fail );
		await page.click( '[data-cic-failure] button' );
		await page.waitForSelector( '.cic-qf__step[data-step="2"]:not([hidden])', { timeout: 15000 } );
		page.off( 'request', intercept );
		await page.setRequestInterception( false );
		let rec = records( who );
		check( 'D4 the retried Continue creates exactly one Incomplete draft with the Step 1 details', 1 === rec.length && 'incomplete' === rec[ 0 ].stage && 'Quote Flow Tester' === rec[ 0 ].name, rec );
		const draftId = rec[ 0 ] ? rec[ 0 ].id : 0;
		const access = await page.evaluate( () => ( { cookieVisible: document.cookie.includes( 'cic_qd' ), url: location.href, storage: JSON.stringify( Object.assign( {}, window.localStorage ) ) + JSON.stringify( Object.assign( {}, window.sessionStorage ) ) } ) );
		const cookies = await page.cookies();
		const qd = cookies.find( ( c ) => 'cic_qd' === c.name );
		check( 'S1 draft access is an HttpOnly, Secure, SameSite=Strict cookie; no token or personal data in the URL or browser storage', qd && qd.httpOnly && qd.secure && 'Strict' === qd.sameSite && ! access.cookieVisible && ! /cic_qd|qf-|Tester|%40/.test( access.url ) && ! access.storage.includes( who ) && ! access.storage.includes( qd.value.split( '.' )[ 1 ] ), { qd: qd && { httpOnly: qd.httpOnly, secure: qd.secure, sameSite: qd.sameSite }, url: access.url } );

		/* ================= Step 2 ================= */
		const l3 = await page.evaluate( () => {
			const tiles = Array.from( document.querySelectorAll( '.cic-tile:not(.cic-tile--other)' ) );
			const names = Array.from( document.querySelectorAll( '.cic-qf__step[data-step="2"] [name]' ) ).map( ( i ) => i.name ).filter( ( n, i, a ) => a.indexOf( n ) === i );
			return {
				order: tiles.map( ( t ) => t.querySelector( 'input' ).value ),
				rows: new Set( tiles.map( ( t ) => Math.round( t.getBoundingClientRect().top ) ) ).size,
				clipped: tiles.some( ( t ) => { const l = t.querySelector( '.cic-tile__label' ); return l.scrollWidth > l.clientWidth + 1; } ),
				checked: ( document.querySelector( 'input[name="event"]:checked' ) || {} ).value,
				guests: document.querySelector( '#cic-f-guests' ).value,
				names,
				secondary: Array.from( document.querySelectorAll( '.cic-qf__secondary [name]' ) ).map( ( i ) => i.name ),
			};
		} );
		check( 'E1 Step 2: six occasions in the required order on one desktop row, no clipped labels', JSON.stringify( l3.order ) === JSON.stringify( ORDER ) && 1 === l3.rows && ! l3.clipped, l3 );
		check( 'E2 fields in order (occasion, date, guests, city/ZIP, then venue, start time, duration as secondary); event and guests from the link kept', [ 'event', 'event_date', 'guests', 'location' ].every( ( n, i, a ) => l3.names.indexOf( n ) > ( i ? l3.names.indexOf( a[ i - 1 ] ) : -1 ) ) && JSON.stringify( l3.secondary ) === JSON.stringify( [ 'venue', 'serve_time', 'serve_duration' ] ) && 'graduation' === l3.checked && '60' === l3.guests, l3 );
		await shot( 'step2-1440.png' );
		const axe2 = await axe( '.cic-quote-card' );
		check( 'X2 axe-core: no violations in Step 2 of the form', 0 === axe2.length, axe2 );

		/* Autosave: selections and fields on blur update the SAME draft. */
		const before = draftCalls.length;
		await page.click( 'input[name="event"][value="prom"] + .cic-tile__box' );
		await sleep( 1200 );
		await page.click( '#cic-f-guests', { clickCount: 3 } );
		await page.type( '#cic-f-guests', '81' );
		await page.click( '#cic-f-guests', { clickCount: 3 } );
		await page.type( '#cic-f-guests', '82' );
		await page.click( '#cic-f-guests', { clickCount: 3 } );
		await page.type( '#cic-f-guests', '83' );
		await page.click( '#cic-f-location' );
		await sleep( 1500 );
		rec = records( who );
		const saves = draftCalls.slice( before ).filter( ( c ) => c.url.endsWith( '/save' ) );
		check( 'A1 changes autosave to the same draft (selection + text on blur), newest value kept (83), no per-keystroke requests', 1 === rec.length && rec[ 0 ].id === draftId && 'prom' === rec[ 0 ].event && '83' === rec[ 0 ].guests && saves.length >= 1 && saves.length <= 4, { rec: rec[ 0 ], saves: saves.length } );
		const noBadge = await page.evaluate( () => ! /sav(ed|ing)/i.test( document.querySelector( '.cic-qf' ).innerText ) );
		check( 'A2 no "Saving…" / "Saved" messages during the steps', noBadge );

		/* Older request cannot overwrite; concurrent duplicates cannot create a second inquiry. */
		const stale = await page.evaluate( async ( u ) => ( await ( await fetch( u, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CIC-Draft': '1' }, body: JSON.stringify( { rev: '5', fields: { guests: '7' } } ) } ) ).json() ), BASE + '/wp-json/cic/v1/quote-draft/save' );
		check( 'A3 a delayed older autosave is ignored (newer values stay)', false === stale.applied && '83' === records( who )[ 0 ].guests, stale );

		/* Back to Step 1: values retained; edits update the same draft. */
		await page.click( '[data-cic-back]' );
		await sleep( 300 );
		const back = await page.evaluate( () => ( { name: document.querySelector( '#cic-f-your-name' ).value, step1: ! document.querySelector( '.cic-qf__step[data-step="1"]' ).hidden } ) );
		await page.click( '#cic-f-your-name', { clickCount: 3 } );
		await page.type( '#cic-f-your-name', 'Quote Flow Tester Jr' );
		await page.click( '#cic-f-phone' );
		await sleep( 1500 );
		rec = records( who );
		check( 'B1 Back shows Step 1 with values; editing the name updates the same draft', back.step1 && 'Quote Flow Tester' === back.name && 1 === rec.length && 'Quote Flow Tester Jr' === rec[ 0 ].name, { back, rec: rec[ 0 ] } );
		await page.click( '[data-cic-next]' );
		await page.waitForSelector( '.cic-qf__step[data-step="2"]:not([hidden])', { timeout: 15000 } );

		/* Unauthorised access is rejected. */
		const ctxB = await browser.createBrowserContext();
		const stranger = await ctxB.newPage();
		await stranger.goto( BASE + '/get-a-quote/', { waitUntil: 'domcontentloaded' } );
		const denied = await stranger.evaluate( async ( u, id ) => {
			const call = async ( headers, body ) => ( await fetch( u, { method: 'POST', credentials: 'same-origin', headers: Object.assign( { 'Content-Type': 'application/json' }, headers ), body: JSON.stringify( body ) } ) ).status;
			return [
				await call( { 'X-CIC-Draft': '1' }, { rev: '9', fields: { your_name: 'Hacker' } } ),
				await call( { 'X-CIC-Draft': '1' }, { rev: '9', key: 'Z'.repeat( 32 ), fields: { your_name: 'Hacker' } } ),
				await call( {}, { rev: '9', fields: { your_name: 'Hacker' } } ),
			];
		}, BASE + '/wp-json/cic/v1/quote-draft/save', draftId );
		await stranger.setCookie( { name: 'cic_qd', value: draftId + '.' + 'A'.repeat( 43 ), domain: 'catericecream.test', path: '/', secure: true, httpOnly: true } );
		const forged = await stranger.evaluate( async ( u ) => ( await fetch( u, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CIC-Draft': '1' }, body: JSON.stringify( { rev: '9', fields: { your_name: 'Hacker' } } ) } ) ).status, BASE + '/wp-json/cic/v1/quote-draft/save' );
		await ctxB.close();
		check( 'U1 another browser cannot edit the draft: no cookie, a guessed key, missing header and a forged cookie with the real id are all rejected', denied.every( ( s ) => s >= 401 && s <= 403 ) && 403 === forged && 'Quote Flow Tester Jr' === records( who )[ 0 ].name, { denied, forged } );

		/* Admin: the draft is shown as Incomplete with contact details and last activity. */
		const auth = JSON.parse( last( wp( 'eval-file', 'dev/tests/phase02/auth-cookies.php', 'create' ) ) );
		const ctxAdmin = await browser.createBrowserContext();
		const adminPage = await ctxAdmin.newPage();
		await adminPage.setCookie( ...auth.cookies.map( ( c ) => ( { name: c.name, value: c.value, domain: 'catericecream.test', path: '/', secure: true, httpOnly: true } ) ) );
		const adminView = async () => {
			await adminPage.goto( BASE + '/wp-admin/admin.php?page=cic-enquiries', { waitUntil: 'domcontentloaded' } );
			const row = await adminPage.$eval( `#cic-enquiry-list tr[data-enquiry="${ draftId }"]`, ( r ) => ( { stage: r.dataset.stage, text: r.innerText } ) ).catch( () => null );
			const badge = await adminPage.$eval( '#adminmenu .awaiting-mod, #adminmenu .update-plugins', ( e ) => e.textContent ).catch( () => '' );
			await adminPage.goto( BASE + '/wp-admin/admin.php?page=cic-enquiries&stage=incomplete', { waitUntil: 'domcontentloaded' } );
			const filtered = await adminPage.$$eval( '#cic-enquiry-list tr[data-enquiry]', ( r ) => r.map( ( x ) => +x.dataset.enquiry ) );
			await adminPage.goto( BASE + '/wp-admin/admin.php?page=cic-enquiries&enquiry=' + draftId, { waitUntil: 'domcontentloaded' } );
			const detail = await adminPage.evaluate( () => ( { h1: document.querySelector( 'h1' ).innerText, completion: ( document.querySelector( '#cic-completion' ) || {} ).innerText || '', customer: document.body.innerText, notif: ( document.querySelector( '#cic-notifications' ) || {} ).innerText || '' } ) );
			return { row, badge, filtered, detail };
		};
		const adm = await adminView();
		await adminPage.screenshot( { path: path.join( OUT, 'admin-incomplete.png' ), fullPage: true } );
		check( 'AD1 admin list: the draft shows as "Incomplete" with "Not submitted yet" and last activity; the Incomplete filter finds it', adm.row && 'incomplete' === adm.row.stage && /Incomplete/.test( adm.row.text ) && /Not submitted yet/.test( adm.row.text ) && /Last activity/.test( adm.row.text ) && adm.filtered.includes( draftId ), adm.row );
		check( 'AD2 admin detail: contact details, preferred contact, treats, last activity and completion status; never called abandoned; no notifications', /Incomplete/.test( adm.detail.h1 ) && /Incomplete: Step 1 was saved/.test( adm.detail.completion ) && /Last activity/.test( adm.detail.completion ) && adm.detail.customer.includes( who ) && /Prefers/.test( adm.detail.customer ) && /Dippin/.test( adm.detail.customer ) && ! /abandon/i.test( adm.detail.customer ) && /None/.test( adm.detail.notif ), adm.detail.completion );

		/* Concurrent Step 1 requests with one key: one inquiry. */
		const ctxC = await browser.createBrowserContext();
		const twin = await ctxC.newPage();
		await twin.goto( BASE + '/get-a-quote/', { waitUntil: 'networkidle2' } );
		const ts = await twin.$eval( '[data-cic-ts]', ( i ) => i.value );
		await sleep( 3200 );
		const statuses = await twin.evaluate( async ( u, ts2, email ) => {
			const body = JSON.stringify( { key: 'concurrentkey0123456789abc', rev: '100', fields: { your_name: 'Twin', your_email: email, contact_pref: 'email', treats_unsure: '1' }, cic_ts: ts2, cic_website: '' } );
			const one = () => fetch( u, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CIC-Draft': '1' }, body } ).then( ( r ) => r.status );
			return Promise.all( [ one(), one(), one() ] );
		}, BASE + '/wp-json/cic/v1/quote-draft', ts, other );
		await ctxC.close();
		check( 'R1 three simultaneous Continue requests with one key create one inquiry (others replay or are told to retry)', 1 === records( other ).length && statuses.every( ( s ) => [ 200, 201, 409 ].includes( s ) ), { statuses, n: records( other ).length } );

		/* ================= Final submission ================= */
		await page.click( '#cic-f-event-date' );
		await page.keyboard.press( 'ArrowDown' );
		await page.keyboard.press( 'PageDown' );
		const qDate = await page.evaluate( () => document.activeElement.getAttribute( 'data-date' ) );
		await page.keyboard.press( 'Enter' );
		// Excluded location first: the coverage rules block it before anything is sent.
		await page.click( '#cic-f-location', { clickCount: 3 } );
		await page.type( '#cic-f-location', '90220' );
		await page.click( '.cic-button--send' );
		// The coverage check is a REST round trip (1–2 s locally, longer behind a pending autosave).
		await page.waitForFunction( () => [ ...document.querySelectorAll( '[data-name="location"] .cic-tip, [data-name="location_detail"] .cic-tip' ) ].some( ( t ) => t.textContent.trim() ), { timeout: 10000 } ).catch( () => {} );
		const blocked = await page.evaluate( () => ( { tip: ( document.querySelector( '[data-name="location"] .cic-tip' ) || document.querySelector( '[data-name="location_detail"] .cic-tip' ) || {} ).textContent || '', sent: ! document.querySelector( '[data-cic-received]' ).hidden, summary: document.querySelector( '[data-cic-errors]' ).hidden ? '' : document.querySelector( '[data-cic-errors]' ).innerText.slice( 0, 200 ), busy: document.querySelector( 'form.cic-form--quote' ).getAttribute( 'aria-busy' ), loc: document.querySelector( '#cic-f-location' ).value, active: document.activeElement && ( document.activeElement.id || document.activeElement.className ) } ) );
		check( 'V1 coverage rules still apply: an excluded / ambiguous ZIP stops the submission with a message', '' !== blocked.tip && ! blocked.sent && 1 === records( who ).length && 'incomplete' === records( who )[ 0 ].stage, blocked );
		await page.click( '#cic-f-location', { clickCount: 3 } );
		await page.type( '#cic-f-location', '91101' );
		await page.click( '#cic-f-venue' );
		await page.type( '#cic-f-venue', 'Test Hall' );
		await page.click( '#cic-f-guests' );
		await sleep( 3600 );
		const opBefore = ( await mails( operator ) ).length;
		await page.click( '.cic-button--send' );
		await page.waitForSelector( '[data-cic-received]:not([hidden])', { timeout: 30000 } );
		await sleep( 1500 );
		rec = records( who );
		const done = await page.evaluate( () => {
			const g = {};
			let emptyShown = false;
			document.querySelectorAll( '[data-cic-glance-box] [data-g]' ).forEach( ( r ) => { const v = r.querySelector( '[data-g-value]' ).textContent; if ( r.offsetParent ) { g[ r.getAttribute( 'data-g' ) ] = v; emptyShown = emptyShown || '' === v.trim(); } } );
			g.__emptyShown = emptyShown;
			return { title: document.querySelector( '#cic-glance-title' ).textContent, g, more: ! document.querySelector( '[data-cic-more]' ).hidden, heading: document.querySelector( '#cic-received-title' ).textContent, text: document.querySelector( '[data-cic-received]' ).innerText };
		} );
		check( 'F1 final submission completes the SAME inquiry (one record, Submitted) with the latest values through CF7', 1 === rec.length && rec[ 0 ].id === draftId && 'submitted' === rec[ 0 ].stage && 'prom' === rec[ 0 ].ctx.event && 83 === rec[ 0 ].ctx.guests && qDate === rec[ 0 ].ctx.date && 'Quote Flow Tester Jr' === rec[ 0 ].name && 'Test Hall' === rec[ 0 ].ctx.venue, rec );
		check( 'F2 success shows "Your event at a glance" with contact, event and treats, never a booking', 'Your event at a glance' === done.title && done.g.name === 'Quote Flow Tester Jr' && done.g.email === who && 'Prom' === done.g.event && /83 guests/.test( done.g.guests || '' ) && /Dippin/.test( done.g.treats || '' ) && ! done.g.__emptyShown && ! /\bbooked\b|confirmed booking|price is/i.test( done.text ) && done.more, done );
		await sleep( 2000 );
		const opMails = ( await mails( operator ) ).length - opBefore;
		const ack = await mails( who );
		check( 'F3 exactly one operator notification and one acknowledgement (local mail capture)', 1 === opMails && 1 === ack.length, { opMails, ack: ack.length } );
		await shot( 'success-1440.png' );
		const axe3 = await axe( '[data-cic-received]' );
		check( 'X3 axe-core: no violations in the confirmation (summary and optional details)', 0 === axe3.length, axe3 );

		/* A delayed autosave cannot reopen or change the submitted inquiry. */
		const late = await page.evaluate( async ( u ) => ( await ( await fetch( u, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CIC-Draft': '1' }, body: JSON.stringify( { rev: '9999999999999990', fields: { guests: '1', your_name: 'Late' } } ) } ) ).json() ), BASE + '/wp-json/cic/v1/quote-draft/save' );
		rec = records( who );
		check( 'F4 a delayed draft save after submission does not revert it to Incomplete or change values', 'submitted' === rec[ 0 ].stage && '83' === String( rec[ 0 ].ctx.guests ) && 'Quote Flow Tester Jr' === rec[ 0 ].name, { late, rec: rec[ 0 ] } );

		/* Optional details after submission: same inquiry, discreet feedback, no new mail, workflow untouched. */
		wp( 'post', 'meta', 'update', String( draftId ), '_cic_status', 'contacted' );
		wp( 'post', 'meta', 'update', String( draftId ), '_cic_assignee', '1' );
		await page.type( '#cic-more-notes', 'Please bring extra spoons.' );
		await page.type( '#cic-more-dietary', 'One guest has a nut allergy.' );
		await page.click( '#cic-more-party_theme' );
		await page.waitForFunction( () => /Saved/.test( document.querySelector( '[data-cic-more-status]' ).textContent ), { timeout: 15000 } );
		await sleep( 1500 );
		rec = records( who );
		const opAfter = ( await mails( operator ) ).length - opBefore;
		check( 'O1 optional details save automatically to the same inquiry ("Saved" only after the server confirmed)', 1 === rec.length && 'Please bring extra spoons.' === rec[ 0 ].notes && 'One guest has a nut allergy.' === rec[ 0 ].dietary, rec[ 0 ] );
		check( 'O2 no new inquiry, no resent notification, status (contacted) and assignee unchanged', 1 === opAfter && 1 === ( await mails( who ) ).length && 'contacted' === rec[ 0 ].status && 1 === rec[ 0 ].assignee, { opAfter, rec: rec[ 0 ] } );

		const adm2 = await adminView();
		check( 'AD3 after submission the same inquiry shows as "Submitted" with the optional details visible to staff', adm2.row && 'submitted' === adm2.row.stage && /Submitted/.test( adm2.detail.completion ) && adm2.detail.customer.includes( 'Please bring extra spoons.' ) && adm2.detail.customer.includes( 'One guest has a nut allergy.' ) && ! adm2.filtered.includes( draftId ), { row: adm2.row, completion: adm2.detail.completion } );
		await ctxAdmin.close();
		wp( 'eval-file', 'dev/tests/phase02/auth-cookies.php', 'destroy' );

		/* Reload of the confirmation (same browser): summary and optional details still there. */
		await page.reload( { waitUntil: 'networkidle2' } );
		const reload = await page.evaluate( () => ( { url: location.href, glance: ! document.querySelector( '[data-cic-glance-box]' ).hidden, notes: ( document.querySelector( '#cic-more-notes' ) || {} ).value } ) );
		check( 'O3 reloading the confirmation shows the summary and saved optional details to this browser only; the URL holds only the random receipt', reload.glance && 'Please bring extra spoons.' === reload.notes && /received=[a-f0-9]{32}/.test( reload.url ) && ! reload.url.includes( '%40' ), reload );
		const ctxD = await browser.createBrowserContext();
		const outsider = await ctxD.newPage();
		await outsider.goto( reload.url, { waitUntil: 'networkidle2' } );
		const outside = await outsider.evaluate( () => ( { glance: ! document.querySelector( '[data-cic-glance-box]' ).hidden, more: ! document.querySelector( '[data-cic-more]' ).hidden, body: document.body.innerText } ) );
		await ctxD.close();
		check( 'U2 the same confirmation link in another browser shows no contact details or optional section', ! outside.glance && ! outside.more && ! outside.body.includes( who ), outside );

		/* ================= Mobile layout ================= */
		const ctxE = await browser.createBrowserContext();
		const mob = await ctxE.newPage();
		await mob.setViewport( { width: 390, height: 844 } );
		await mob.goto( BASE + '/get-a-quote/', { waitUntil: 'networkidle2' } );
		const m1 = await mob.evaluate( () => {
			const top = ( sel ) => Array.from( document.querySelectorAll( sel ) ).map( ( e ) => Math.round( e.getBoundingClientRect().top ) );
			return { overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth, contact: top( '[data-name="your_name"], [data-name="your_email"]' ), treatRows: new Set( top( '.cic-treat' ) ).size };
		} );
		await mob.screenshot( { path: path.join( OUT, 'step1-390.png' ), fullPage: true } );
		// Step 2 at 390 px (shown for layout only, without saving: visual check of wrapping).
		const m2 = await mob.evaluate( () => {
			document.querySelector( '.cic-qf__step[data-step="1"]' ).hidden = true;
			document.querySelector( '.cic-qf__step[data-step="2"]' ).hidden = false;
			const tiles = Array.from( document.querySelectorAll( '.cic-tile:not(.cic-tile--other)' ) );
			return { overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth, rows: new Set( tiles.map( ( t ) => Math.round( t.getBoundingClientRect().top ) ) ).size, clipped: tiles.some( ( t ) => { const l = t.querySelector( '.cic-tile__label' ); return l.scrollWidth > l.clientWidth + 1; } ), font: parseFloat( getComputedStyle( tiles[ 0 ].querySelector( '.cic-tile__label' ) ).fontSize ) };
		} );
		await mob.screenshot( { path: path.join( OUT, 'step2-390.png' ), fullPage: true } );
		await ctxE.close();
		check( 'M1 390 px: no horizontal scrolling, contact fields stacked, treats two per row', 0 === m1.overflow && m1.contact[ 0 ] !== m1.contact[ 1 ] && 2 === m1.treatRows, m1 );
		check( 'M2 390 px: occasions wrap (two rows of three) without clipping or tiny text', 0 === m2.overflow && 2 === m2.rows && ! m2.clipped && m2.font >= 14, m2 );

		check( 'X1 no JavaScript errors', 0 === errors.length, errors );
	} catch ( e ) {
		check( 'run completed without an exception', false, e.stack );
	} finally {
		if ( browser ) {
			await browser.close().catch( () => {} );
		}
		try {
			const ids = [];
			for ( const to of [ who, other, operator ] ) {
				( await mails( to ) ).forEach( ( m ) => ids.push( m.ID ) );
			}
			if ( ids.length ) {
				await mailpit( '/api/v1/messages', 'DELETE', JSON.stringify( { IDs: ids } ) );
			}
			console.log( last( wp( 'eval-file', 'dev/tests/phase06/state.php', 'restore', snapshot ) ) );
			fs.unlinkSync( snapshot );
			const left = records( who ).length + records( other ).length;
			check( 'Z1 test inquiries, drafts and mail removed; settings restored', 0 === left, left );
		} catch ( e ) {
			console.log( 'CLEANUP ERROR ' + e.message );
		}
		console.log( `\nQuote flow browser checks: ${ results.filter( Boolean ).length }/${ results.length } passed` );
	}
} )();
