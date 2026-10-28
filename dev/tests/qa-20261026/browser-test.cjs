/**
 * QA fixes of 2026-10-26 in a real browser (Edge). Sequential; one browser, always closed.
 *
 * Forms (item 3): the CF7 feedback request is made to fail the way a hosting security screen or a
 * dropped connection would (simulated in the browser, nothing changes on the server), plus an
 * unrelated script error during sending, a slow response beyond the 30 s watchdog, a double click,
 * and a quote whose first response is lost although the server saved it (the retry must show the
 * same inquiry, never a second one). Synthetic @example.test addresses only; mail is captured by
 * Mailpit. Test inquiries, their rows and mail are removed and settings restored in `finally`
 * (dev/tests/phase06/state.php snapshot/restore removes only records created by the test).
 *
 * Layout (items 2 and 8): quote step heading spacing and the Contact page's two-step card at
 * 1440 and 390 px, with screenshots in dev/tests/output/qa-20261026/.
 *
 *   NODE_PATH=<dir>/node_modules node dev/tests/qa-20261026/browser-test.cjs
 */
'use strict';

const puppeteer = require( 'puppeteer-core' );
const fs = require( 'fs' );
const path = require( 'path' );
const http = require( 'http' );
const crypto = require( 'crypto' );
const { execFileSync } = require( 'child_process' );

const BASE = 'https://catericecream.test';
const ROOT = path.resolve( __dirname, '../../..' );
const OUT = path.join( ROOT, 'dev/tests/output/qa-20261026' );
const WP = 'C:\\laragon\\bin\\wp-cli\\wp.cmd';
const BROWSER = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const FEEDBACK = /\/contact-form-7\/v1\/contact-forms\/\d+\/feedback(?:[?#]|$)/; // not …/feedback/schema
fs.mkdirSync( OUT, { recursive: true } );

const results = [];
const check = ( label, ok, detail ) => {
	results.push( !! ok );
	console.log( ( ok ? 'PASS ' : 'FAIL ' ) + label + ( ! ok && detail !== undefined ? ' — ' + JSON.stringify( detail ).slice( 0, 800 ) : '' ) );
};
const wp = ( ...args ) => execFileSync( 'cmd', [ '/c', WP, ...args ], { cwd: ROOT, encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'ignore' ], timeout: 120000 } );
const last = ( s ) => s.trim().split( '\n' ).pop();
const state = ( ...a ) => last( wp( 'eval-file', 'dev/tests/phase06/state.php', ...a ) );
const records = ( email ) => JSON.parse( last( wp( 'eval-file', 'dev/tests/quote-flow/state.php', 'records', email ) ) );
const sleep = ( ms ) => new Promise( ( r ) => setTimeout( r, ms ) );
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
const tag = crypto.randomBytes( 3 ).toString( 'hex' );
const addr = ( n ) => `qa26-${ n }-${ tag }@example.test`;

let browser;
( async () => {
	const snapshot = path.join( OUT, 'state.json' );
	const operator = `qa26-operator-${ tag }@example.test`;
	const used = [];
	state( 'snapshot', snapshot );
	try {
		state( 'recipient', operator );
		state( 'reset-limits' );
		browser = await puppeteer.launch( { executablePath: BROWSER, headless: true, acceptInsecureCerts: true } );

		// One fresh page per scenario; `route` decides what happens to the CF7 feedback request.
		const open = async ( url, route, width = 1440 ) => {
			const ctx = await browser.createBrowserContext();
			const page = await ctx.newPage();
			await page.setViewport( { width, height: 900 } );
			page.__errors = [];
			page.on( 'pageerror', ( e ) => page.__errors.push( e.message ) );
			if ( route ) {
				await page.setRequestInterception( true );
				page.on( 'request', ( r ) => ( FEEDBACK.test( r.url() ) ? route( r ) : r.continue() ) );
			}
			await page.goto( BASE + url, { waitUntil: 'networkidle2' } );
			page.__ctx = ctx;
			return page;
		};
		const close = async ( page ) => page.__ctx.close();
		const fillContact = async ( page, email ) => {
			used.push( email );
			await page.select( '#cic-f-topic', 'event' );
			await page.type( '#cic-f-your-name', 'QA Test Contact' );
			await page.type( '#cic-f-your-email', email );
			await page.type( '#cic-f-message', 'Synthetic QA test message (2026-10-26). Please ignore.' );
			await sleep( 3300 ); // The spam guard refuses forms sent within 3 s of rendering.
		};
		const ui = ( page ) => page.evaluate( () => {
			const box = document.querySelector( '[data-cic-errors]' );
			const btn = document.querySelector( '.cic-button--send' );
			return {
				error: box && ! box.hidden ? box.innerText.trim() : '',
				name: ( document.querySelector( '#cic-f-your-name' ) || {} ).value,
				message: ( document.querySelector( '#cic-f-message' ) || {} ).value,
				button: btn ? { disabled: btn.disabled, text: btn.textContent.trim() } : null,
				received: !! document.querySelector( '[data-cic-received]:not([hidden])' ),
				heading: ( document.querySelector( '#cic-received-title' ) || {} ).textContent || '',
				events: ( window.dataLayer || [] ).filter( ( e ) => e && e.event ).map( ( e ) => e.event ),
			};
		} );
		const securityPage = ( r ) => r.respond( { status: 403, contentType: 'text/html', body: '<!doctype html><title>Security check</title><p>Checking your browser…</p>' } );

		/* ============ 1. Security screen answers instead of WordPress ============ */
		let page = await open( '/contact/', securityPage );
		await page.evaluate( () => { window.dataLayer = []; } );
		const e1 = addr( 'c1' );
		await fillContact( page, e1 );
		await page.click( '.cic-button--send' );
		await page.waitForFunction( () => ! document.querySelector( '[data-cic-errors]' ).hidden, { timeout: 15000 } );
		let s = await ui( page );
		check( 'F1 HTTP 403 page (security screen): "didn’t go through" message with the phone number, answers kept, button usable again',
			/didn.t go through/i.test( s.error ) && /424-235-4942/.test( s.error ) && 'QA Test Contact' === s.name && /Synthetic QA/.test( s.message ) && s.button && ! s.button.disabled && ! s.received, s );
		check( 'F2 nothing was stored for the blocked request (it never reached WordPress)', 0 === records( e1 ).length );
		await page.screenshot( { path: path.join( OUT, 'contact-403-1440.png' ) } );
		await close( page );

		/* ============ 2. Dropped connection, then retry ============ */
		let drop = true;
		page = await open( '/contact/', ( r ) => ( drop ? ( drop = false, r.abort( 'failed' ) ) : r.continue() ) );
		const e2 = addr( 'c2' );
		await fillContact( page, e2 );
		await page.click( '.cic-button--send' );
		await page.waitForFunction( () => ! document.querySelector( '[data-cic-errors]' ).hidden, { timeout: 15000 } );
		s = await ui( page );
		check( 'F3 dropped connection: "couldn’t reach the server" message, answers kept, button usable', /couldn.t reach the server/i.test( s.error ) && 'QA Test Contact' === s.name && ! s.button.disabled, s );
		await page.click( '.cic-button--send' );
		await page.waitForSelector( '[data-cic-received]:not([hidden])', { timeout: 30000 } );
		s = await ui( page );
		check( 'F4 pressing the button again sends it: contact success message, exactly one inquiry, one acknowledgement', /We received your message/i.test( s.heading ) && 1 === records( e2 ).length && 1 === ( await mails( e2 ) ).length, { s, n: records( e2 ).length } );
		await close( page );

		/* ============ 3. Unrelated script error while sending ============ */
		page = await open( '/contact/', async ( r ) => { await sleep( 2500 ); r.continue(); } );
		const e3 = addr( 'c3' );
		await fillContact( page, e3 );
		await page.click( '.cic-button--send' );
		await sleep( 500 );
		await page.evaluate( () => { Promise.reject( new Error( 'unrelated third-party failure' ) ); } );
		await sleep( 600 );
		const mid = await ui( page );
		await page.waitForSelector( '[data-cic-received]:not([hidden])', { timeout: 30000 } );
		check( 'F5 an unrelated rejected promise during sending shows no error; the request completes once', '' === mid.error && 1 === records( e3 ).length, { mid, n: records( e3 ).length } );
		await close( page );

		/* ============ 4. Double click ============ */
		page = await open( '/contact/' );
		const e4 = addr( 'c4' );
		await fillContact( page, e4 );
		await page.evaluate( () => { const b = document.querySelector( '.cic-button--send' ); b.click(); b.click(); b.click(); } );
		await page.waitForSelector( '[data-cic-received]:not([hidden])', { timeout: 30000 } );
		await sleep( 1500 );
		check( 'F6 triple click on Send: one inquiry and one acknowledgement', 1 === records( e4 ).length && 1 === ( await mails( e4 ) ).length, records( e4 ).length );
		await close( page );

		/* ============ 5. Slower than the 30 s watchdog, but saved ============ */
		state( 'reset-limits' );
		page = await open( '/contact/', async ( r ) => { await sleep( 32000 ); r.continue(); } );
		const e5 = addr( 'c5' );
		await fillContact( page, e5 );
		const t0 = Date.now();
		await page.click( '.cic-button--send' );
		await page.waitForFunction( () => ! document.querySelector( '[data-cic-errors]' ).hidden, { timeout: 40000 } );
		const t1 = Date.now() - t0;
		s = await ui( page );
		await page.waitForSelector( '[data-cic-received]:not([hidden])', { timeout: 60000 } );
		const after = await ui( page );
		check( 'F7 no answer within 30 s: the network message appears (answers kept); when the late answer arrives, the success message replaces it; one inquiry', t1 >= 29000 && /couldn.t reach the server/i.test( s.error ) && 'QA Test Contact' === s.name && after.received && 1 === records( e5 ).length, { t1, s, n: records( e5 ).length } );
		await close( page );

		/* ============ 6. Quote: saved, but the response is lost; retry ============ */
		state( 'reset-limits' );
		const q = addr( 'q1' );
		used.push( q );
		let captured = null;
		page = await open( '/get-a-quote/?event=birthday&guests=40', ( r ) => {
			if ( ! captured ) {
				captured = { url: r.url(), body: r.postData(), type: r.headers()[ 'content-type' ] };
				r.abort( 'timedout' );
				return;
			}
			r.continue();
		} );
		await page.evaluate( () => { window.dataLayer = []; } );
		await page.type( '#cic-f-your-name', 'QA Test Quote' );
		await page.type( '#cic-f-your-email', q );
		await page.type( '#cic-f-phone', '(818) 555-0199' );
		await page.click( 'input[name="treats[]"][value="dippin-dots"] + .cic-treat__box' );
		await sleep( 3300 );
		await page.click( '[data-cic-next]' );
		await page.waitForSelector( '.cic-qf__step[data-step="2"]:not([hidden])', { timeout: 20000 } );
		const draft = records( q );
		await page.click( '#cic-f-event-date' );
		await page.keyboard.press( 'ArrowDown' );
		await page.keyboard.press( 'PageDown' );
		await page.keyboard.press( 'Enter' );
		await page.click( '#cic-f-location', { clickCount: 3 } );
		await page.type( '#cic-f-location', '91101' );
		await page.click( '#cic-f-guests' );
		await sleep( 3600 );
		await page.click( '.cic-button--send' );
		await page.waitForFunction( () => ! document.querySelector( '[data-cic-errors]' ).hidden, { timeout: 30000 } );
		const lost = await ui( page );
		// The lost request DID reach the server: replay the exact captured request (same submission key).
		const replay = await page.evaluate( async ( c ) => ( await fetch( c.url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': c.type, Accept: 'application/json' }, body: c.body } ) ).status, captured );
		const saved = records( q );
		await page.click( '.cic-button--send' );
		await page.waitForSelector( '[data-cic-received]:not([hidden])', { timeout: 30000 } );
		await sleep( 1200 );
		const fin = await page.evaluate( () => ( {
			glance: ( document.querySelector( '#cic-glance-title' ) || {} ).textContent || '',
			name: ( document.querySelector( '[data-g="name"] [data-g-value]' ) || {} ).textContent || '',
			events: ( window.dataLayer || [] ).filter( ( e ) => e && e.event ).map( ( e ) => JSON.parse( JSON.stringify( e ) ) ),
			url: location.href,
		} ) );
		const rec = records( q );
		const conv = fin.events.filter( ( e ) => 'quote_submitted' === e.event );
		check( 'F8 quote: the draft from Step 1 is one Incomplete inquiry', 1 === draft.length && 'incomplete' === draft[ 0 ].stage, draft );
		check( 'F9 quote, response lost after saving: the visitor sees the network message with answers kept (no conversion yet)', /couldn.t reach the server/i.test( lost.error ) && ! lost.events.includes( 'quote_submitted' ), lost );
		check( 'F10 the lost request had completed the SAME draft (one record, Submitted)', 200 === replay && 1 === saved.length && 'submitted' === saved[ 0 ].stage && saved[ 0 ].id === draft[ 0 ].id, { replay, saved } );
		check( 'F11 retry shows the same inquiry’s success with "Your event at a glance"; still one record', /Your event at a glance/.test( fin.glance ) && 'QA Test Quote' === fin.name && 1 === rec.length && rec[ 0 ].id === draft[ 0 ].id, { fin, rec } );
		check( 'F12 exactly one quote_submitted conversion, after the confirmed success, with keys only (no name, email, phone, receipt)', 1 === conv.length && ! /QA Test|example\.test|555|received/i.test( JSON.stringify( fin.events ) ) && /received=/.test( fin.url ), conv );
		check( 'F13 one operator notification and one acknowledgement for the quote (not two)', 1 === ( await mails( q ) ).length, ( await mails( q ) ).length );
		await page.screenshot( { path: path.join( OUT, 'quote-success-after-retry-1440.png' ), fullPage: false } );
		const pageErrors = page.__errors;
		await close( page );
		check( 'F14 no JavaScript errors in the quote scenario', 0 === pageErrors.length, pageErrors );

		/* ============ 7. Layout: step headings and the Contact card ============ */
		for ( const w of [ 1440, 390 ] ) {
			page = await open( '/get-a-quote/', null, w );
			const gaps = await page.evaluate( () => [ ...document.querySelectorAll( '.cic-qf__step' ) ].map( ( st ) => {
				st.hidden = false;
				const t = st.querySelector( '.cic-qf__title' ).getBoundingClientRect();
				const next = st.querySelector( '.cic-field__label, label' );
				return { title: st.querySelector( '.cic-qf__title' ).textContent, gap: Math.round( next.getBoundingClientRect().top - t.bottom ) };
			} ) );
			await page.evaluate( () => { document.querySelector( '.cic-qf__step[data-step="2"]' ).hidden = true; } );
			await page.screenshot( { path: path.join( OUT, `quote-step1-${ w }.png` ) } );
			const want = 1440 === w ? 20 : 16;
			check( `L1 ${ w } px: gap below "Your details & favorites" and "Your event" is ${ want } px (was 6)`, 2 === gaps.length && gaps.every( ( g ) => g.gap === want ), gaps );
			await close( page );

			page = await open( '/contact/', null, w );
			const card = await page.evaluate( () => ( {
				steps: [ ...document.querySelectorAll( '.cic-contact-steps li' ) ].map( ( li ) => li.querySelector( 'strong' ).textContent ),
				text: document.querySelector( '.cic-contact-steps' ).innerText,
				overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
			} ) );
			const el = await page.$( '.cic-contact-plan' );
			if ( el ) {
				await el.screenshot( { path: path.join( OUT, `contact-plan-${ w }.png` ) } );
			}
			check( `L2 ${ w } px: Contact "Planning an event?" lists the two real steps; no old three-step wording; no horizontal scroll`, JSON.stringify( card.steps ) === JSON.stringify( [ 'Your details & favorites', 'Your event' ] ) && ! /Review|Your treats/.test( card.text ) && 0 === card.overflow, card );
			await close( page );
		}
	} catch ( e ) {
		check( 'run completed without an exception', false, e.stack );
	} finally {
		if ( browser ) {
			await browser.close().catch( () => {} );
		}
		try {
			// Test inquiry identifiers (Flamingo message IDs) before they are removed.
			const created = used.map( ( e ) => [ e, records( e ).map( ( r ) => r.id ) ] ).filter( ( x ) => x[ 1 ].length );
			console.log( 'Test inquiries created: ' + ( created.map( ( x ) => x[ 0 ] + ' → #' + x[ 1 ].join( ', #' ) ).join( '; ' ) || 'none' ) );
			const ids = [];
			for ( const to of used.concat( [ operator ] ) ) {
				( await mails( to ) ).forEach( ( m ) => ids.push( m.ID ) );
			}
			if ( ids.length ) {
				await mailpit( '/api/v1/messages', 'DELETE', JSON.stringify( { IDs: ids } ) );
			}
			console.log( last( wp( 'eval-file', 'dev/tests/phase06/state.php', 'restore', snapshot ) ) );
			fs.unlinkSync( snapshot );
			const left = used.reduce( ( n, e ) => n + records( e ).length, 0 );
			check( 'Z1 test inquiries, rows and mail removed; settings restored', 0 === left, left );
		} catch ( e ) {
			console.log( 'CLEANUP ERROR ' + e.message );
		}
		console.log( `\nQA browser checks: ${ results.filter( Boolean ).length }/${ results.length } passed` );
	}
} )();
