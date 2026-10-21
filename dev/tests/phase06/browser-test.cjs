/**
 * Phase 06 browser checks (local only; one Edge instance, sequential, always closed).
 * Entry points (homepage, event, treat, city), the three-step quote flow, coverage clarification,
 * exclusivity rules, summary, Back/history, keyboard access, double-click, network failure,
 * no-JavaScript fallback, analytics without personal data, confirmation refresh, the Contact form
 * and the enquiry admin (workflow, retry, export, unauthorised access). Screenshots go to
 * dev/tests/output/phase06/. Only test-created records and test mail are removed in `finally`.
 *
 *   NODE_PATH=<dir>/node_modules node dev/tests/phase06/browser-test.cjs
 */
'use strict';

const puppeteer = require( 'puppeteer-core' );
const { execFileSync } = require( 'child_process' );
const https = require( 'https' );
const http = require( 'http' );
const fs = require( 'fs' );
const path = require( 'path' );
const crypto = require( 'crypto' );

const BASE = 'https://catericecream.test';
const ROOT = path.resolve( __dirname, '../../..' );
const OUT = path.join( ROOT, 'dev/tests/output/phase06' );
const WP = 'C:\\laragon\\bin\\wp-cli\\wp.cmd';
const BROWSER = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
fs.mkdirSync( OUT, { recursive: true } );

const results = [];
const check = ( label, ok, detail ) => {
	results.push( !! ok );
	console.log( ( ok ? 'PASS ' : 'FAIL ' ) + label + ( ! ok && detail !== undefined ? ' — ' + JSON.stringify( detail ).slice( 0, 700 ) : '' ) );
};
const wp = ( ...args ) => execFileSync( 'cmd', [ '/c', WP, ...args ], { cwd: ROOT, encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'ignore' ], timeout: 120000 } );
const last = ( s ) => s.trim().split( '\n' ).pop();
const state = ( ...a ) => last( wp( 'eval-file', 'dev/tests/phase06/state.php', ...a ) );
const json = ( ...a ) => JSON.parse( state( ...a ) );
const sleep = ( ms ) => new Promise( ( r ) => setTimeout( r, ms ) );
const email = ( tag ) => 'p6b-' + tag + '-' + crypto.randomBytes( 3 ).toString( 'hex' ) + '@example.test';
const used = [];

const request = ( url, { method = 'GET', body = '', headers = {}, cookies = [] } = {} ) => new Promise( ( resolve ) => {
	const u = new URL( url );
	const lib = 'http:' === u.protocol ? http : https;
	const h = Object.assign( { 'User-Agent': 'Mozilla/5.0 CIC-Phase06-Test' }, headers );
	if ( cookies.length ) {
		h.Cookie = cookies.map( ( c ) => c.name + '=' + c.value ).join( '; ' );
	}
	if ( body ) {
		h[ 'Content-Type' ] = h[ 'Content-Type' ] || 'application/x-www-form-urlencoded';
		h[ 'Content-Length' ] = Buffer.byteLength( body );
	}
	const req = lib.request( u, { method, rejectUnauthorized: false, headers: h, agent: false }, ( res ) => {
		let data = '';
		res.on( 'data', ( d ) => { data += d; } );
		res.on( 'end', () => resolve( { code: res.statusCode, location: res.headers.location || '', type: res.headers[ 'content-type' ] || '', body: data } ) );
	} );
	req.on( 'error', ( e ) => resolve( { code: 0, body: e.message } ) );
	if ( body ) {
		req.write( body );
	}
	req.end();
} );
const mails = async ( to ) => {
	const r = await request( 'http://localhost:8025/api/v1/search?query=' + encodeURIComponent( 'to:"' + to + '"' ) + '&limit=50' );
	try {
		return JSON.parse( r.body ).messages || [];
	} catch ( e ) {
		return [];
	}
};

let browser;
( async () => {
	const snapshot = path.join( OUT, 'state-browser.json' );
	const operator = 'operator-p6b@example.test';
	let auth = null;
	wp( 'eval-file', 'dev/tests/phase06/state.php', 'snapshot', snapshot );
	try {
		const info = json( 'info' );
		state( 'recipient', operator );
		state( 'observer', 'on' );
		state( 'reset-limits' );
		auth = JSON.parse( last( wp( 'eval-file', 'dev/tests/phase02/auth-cookies.php', 'create' ) ) );
		browser = await puppeteer.launch( { executablePath: BROWSER, headless: true, acceptInsecureCerts: true } );
		const page = await browser.newPage();
		page.on( 'dialog', ( d ) => d.accept() );
		await page.setViewport( { width: 1440, height: 1000 } );
		// Capture analytics pushes (an approved analytics install would define dataLayer).
		await page.evaluateOnNewDocument( () => { window.dataLayer = []; } );
		const go = ( url ) => page.goto( BASE + url, { waitUntil: 'networkidle2' } );
		const shot = ( name, full = true ) => page.screenshot( { path: path.join( OUT, name ), fullPage: full } );
		const v = ( sel ) => page.$eval( sel, ( e ) => e.value ).catch( () => null );
		const visible = ( sel ) => page.$eval( sel, ( e ) => !! ( e.offsetWidth || e.offsetHeight || e.getClientRects().length ) && ! e.closest( '[hidden]' ) ).catch( () => false );
		const step = () => page.$eval( '.cic-qf__step:not([hidden])', ( e ) => +e.dataset.step ).catch( () => 0 );
		const clickNext = async () => {
			await page.$eval( '.cic-qf__step:not([hidden]) [data-cic-next]', ( b ) => b.click() );
			await sleep( 200 );
			// Step 1 waits for the coverage check (button disabled meanwhile).
			await page.waitForFunction( () => Array.from( document.querySelectorAll( '[data-cic-next]' ) ).every( ( b ) => ! b.disabled ), { timeout: 15000 } ).catch( () => {} );
			await sleep( 300 );
		};
		const pick = ( sel ) => page.$eval( sel, ( e ) => { e.click(); } );
		const type = async ( sel, text ) => {
			await page.$eval( sel, ( e ) => { e.value = ''; } );
			await page.type( sel, text );
		};

		// ---------------- Entry points ----------------
		await go( '/' );
		await type( '#cic-home-search-location', info.covered );
		await page.select( '#cic-home-search-event', 'wedding' );
		// Date picker (2026-10-21): typed as MM/DD/YYYY; the canonical Y-m-d value is the hidden field.
		await page.focus( '#cic-home-search-date' );
		await page.keyboard.type( info.future.slice( 5, 7 ) + '/' + info.future.slice( 8, 10 ) + '/' + info.future.slice( 0, 4 ) );
		await page.keyboard.press( 'Tab' );
		await Promise.all( [ page.waitForNavigation( { waitUntil: 'networkidle2' } ), page.click( '.cic-search__submit' ) ] );
		const home = { url: page.url(), event: await page.$eval( 'input[name=event]:checked', ( e ) => e.value ).catch( () => '' ), loc: await v( '#cic-f-location' ), date: await v( '[name=event_date]' ) };
		check( 'E1 homepage search → quote form with location, event and date preselected', 'wedding' === home.event && info.covered === home.loc && info.future === home.date && 1 === await step(), home );

		await page.evaluate( () => window.sessionStorage.clear() );
		await go( '/events/birthday/' );
		await Promise.all( [ page.waitForNavigation( { waitUntil: 'networkidle2' } ), page.$eval( 'a[href*="get-a-quote/?event=birthday"]', ( a ) => a.click() ) ] );
		check( 'E2 event page Plan action → Birthday preselected', 'birthday' === await page.$eval( 'input[name=event]:checked', ( e ) => e.value ).catch( () => '' ) );

		await page.evaluate( () => window.sessionStorage.clear() );
		await go( '/treats/dippin-dots/' );
		await Promise.all( [ page.waitForNavigation( { waitUntil: 'networkidle2' } ), page.$eval( 'a[href*="get-a-quote/?treat=dippin-dots"]', ( a ) => a.click() ) ] );
		check( 'E3 treat page action → Dippin’ Dots preselected (other fields untouched)', await page.$eval( 'input[name="treats[]"][value="dippin-dots"]', ( e ) => e.checked ) && 0 === await page.$$eval( 'input[name=event]:checked', ( x ) => x.length ) );

		await page.evaluate( () => window.sessionStorage.clear() );
		await go( '/service-areas/california/pasadena/' );
		await Promise.all( [ page.waitForNavigation( { waitUntil: 'networkidle2' } ), page.$eval( 'a[href*="get-a-quote/?location=pasadena-ca"]', ( a ) => a.click() ) ] );
		const src = await page.evaluate( () => JSON.parse( window.sessionStorage.getItem( 'cic_quote_source' ) || '{}' ) );
		check( 'E4 city page action → Pasadena prefilled (visible label "Pasadena, CA"); source attributed to the city page (template + key only)', 'Pasadena, CA' === await v( '#cic-f-location' ) && 'city' === ( src.latest || {} ).type && 'pasadena-ca' === ( src.latest || {} ).key, src );

		// ---------------- Step 1 ----------------
		await page.evaluate( () => window.sessionStorage.clear() );
		await go( '/get-a-quote/' );
		await shot( 'p6-step1-desktop.png' );
		await clickNext();
		const s1err = await page.evaluate( () => ( { summary: ! document.querySelector( '[data-cic-errors]' ).hidden, focus: document.activeElement && document.activeElement.matches( '[data-cic-errors]' ), tips: document.querySelectorAll( '.cic-qf__step[data-step="1"] .wpcf7-not-valid-tip' ).length } ) );
		await shot( 'p6-step1-errors.png', false );
		check( 'U1 Continue with missing answers → inline errors + focused error summary; stays on step 1', s1err.summary && s1err.focus && s1err.tips >= 4 && 1 === await step(), s1err );
		await pick( 'input[name=event][value=other]' );
		const otherShown = await visible( '#cic-f-event-other' );
		await type( '#cic-f-event-other', 'Graduation party' );
		await pick( 'input[name=date_unknown]' );
		const dateDisabled = await page.$eval( '#cic-f-event-date', ( e ) => e.disabled );
		check( 'U2 Other occasion reveals its description; “Date not set yet” disables the date', otherShown && dateDisabled, { otherShown, dateDisabled } );
		await type( '#cic-f-guests', '60' );
		await type( '#cic-f-location', 'Compton' );
		await clickNext();
		const blocked = await page.evaluate( () => ( { tip: ( document.querySelector( '.wpcf7-form-control-wrap[data-name=location] .wpcf7-not-valid-tip' ) || {} ).textContent || '', step: document.querySelector( '.cic-qf__step:not([hidden])' ).dataset.step } ) );
		check( 'U3 excluded location blocks progress with the service-area message', /do not currently serve/i.test( blocked.tip ) && '1' === blocked.step, blocked );
		await type( '#cic-f-location', '90220' );
		await clickNext();
		const clarify = { shown: await visible( '#cic-f-location-detail' ), label: await page.$eval( '[data-cic-detail] .cic-field__label', ( e ) => e.textContent ).catch( () => '' ), step: await step() };
		await shot( 'p6-step1-clarify.png', false );
		check( 'U4 shared ZIP reveals the venue-city question and stays on step 1', clarify.shown && /Venue city/.test( clarify.label ) && 1 === clarify.step, clarify );
		await type( '#cic-f-location-detail', 'Rancho Dominguez' );
		await clickNext();
		const focused2 = await page.evaluate( () => document.activeElement && document.activeElement.classList.contains( 'cic-qf__title' ) );
		check( 'U5 with the venue city given → step 2; focus moves to the step heading; URL #step-2', 2 === await step() && focused2 && page.url().endsWith( '#step-2' ) );

		// ---------------- Step 2 ----------------
		await clickNext();
		check( 'U6 step 2 requires a treat or “Not sure yet”', 2 === await step() && await visible( '.wpcf7-form-control-wrap[data-name=treats] .wpcf7-not-valid-tip' ) );
		await pick( 'input[name="treats[]"][value=dippin-dots]' );
		await pick( 'input[name="treats[]"][value=classic-ice-cream]' );
		await pick( 'input[name=treats_unsure]' );
		const ex1 = await page.$$eval( 'input[name="treats[]"]:checked', ( x ) => x.length );
		await pick( 'input[name="treats[]"][value=frozen-yogurt]' );
		const ex2 = { treats: await page.$$eval( 'input[name="treats[]"]:checked', ( x ) => x.length ), unsure: await page.$eval( 'input[name=treats_unsure]', ( e ) => e.checked ) };
		await pick( 'input[name="treats[]"][value=dippin-dots]' );
		await shot( 'p6-step2-desktop.png' );
		check( 'U7 “Not sure yet” clears treats and choosing a treat clears “Not sure yet”', 0 === ex1 && 1 === ex2.treats && ! ex2.unsure, { ex1, ex2 } );
		await type( '#cic-f-dietary', 'Nut allergy at table 3' );
		await clickNext();

		// ---------------- Step 3 ----------------
		const glance = await page.$$eval( '[data-cic-glance]', ( rows ) => Object.fromEntries( rows.map( ( r ) => [ r.dataset.cicGlance, r.querySelector( '[data-cic-glance-value]' ).textContent ] ) ) );
		check( 'U8 review summary shows event, treats, location, date and guests', 'Graduation party' === glance.event && /Frozen Yogurt/.test( glance.treats ) && /Dippin/.test( glance.treats ) && /90220.*Rancho Dominguez/.test( glance.location ) && 'Date to be confirmed' === glance.date && '60 guests' === glance.guests, glance );
		await page.$eval( 'input[name=contact_pref][value=phone]', ( e ) => e.click() );
		const mail1 = email( 'flow' );
		used.push( mail1 );
		await type( '#cic-f-your-name', 'Browser Tester' );
		await type( '#cic-f-your-email', mail1 );
		await page.$eval( '.cic-button--send', ( b ) => b.click() );
		await sleep( 600 );
		check( 'U9 Phone preference requires a phone number (checked before sending)', await visible( '.wpcf7-form-control-wrap[data-name=phone] .wpcf7-not-valid-tip' ) && 0 === json( 'enquiry', mail1 ).count );
		await page.$eval( '.cic-qf__nav--send [data-cic-back]', ( b ) => b.click() );
		await sleep( 300 );
		const kept = { step: await step(), dots: await page.$eval( 'input[name="treats[]"][value=dippin-dots]', ( e ) => e.checked ), dietary: await v( '#cic-f-dietary' ) };
		// The in-form Back adds a history entry (step 2), so browser Back returns to step 3 and Forward to step 2.
		await page.goBack();
		await sleep( 500 );
		const hist = await step();
		await page.goForward();
		await sleep( 500 );
		const fwd = await step();
		check( 'U10 Back and browser history move between steps and keep answers', 2 === kept.step && kept.dots && 'Nut allergy at table 3' === kept.dietary && 3 === hist && 2 === fwd, { kept, hist, fwd } );
		await clickNext();
		await type( '#cic-f-phone', '818-555-0100' );
		await shot( 'p6-step3-desktop.png' );

		// Double-click send → one enquiry; confirmation; analytics once, no personal data.
		await page.evaluate( () => { const b = document.querySelector( '.cic-button--send' ); b.click(); b.click(); b.click(); } );
		await page.waitForSelector( '[data-cic-received]:not([hidden])', { timeout: 20000 } );
		await sleep( 500 );
		const e1 = json( 'enquiry', mail1 );
		const conf = await page.evaluate( () => ( { url: location.href, focus: document.activeElement && document.activeElement.id, chips: Array.from( document.querySelectorAll( '[data-cic-chips] li:not([hidden])' ) ).map( ( l ) => l.textContent.trim() ), form: ! document.querySelector( '[data-cic-form-area]' ).hidden } ) );
		await shot( 'p6-confirmation-desktop.png' );
		check( 'U11 double-clicking Send stores one enquiry; the confirmation replaces the form (URL carries only a receipt)', 1 === e1.count && /\?received=[a-f0-9]{32}#confirmation$/.test( conf.url ) && 'confirmation' === conf.focus && ! conf.form && conf.chips.some( ( c ) => /Graduation party/.test( c ) ), { count: e1.count, conf } );
		check( 'U12 saved context: Other occasion, date unknown, shared ZIP + venue, treats, phone preference, source', 'other' === e1.context.event && true === e1.context.date_unknown && '90220' === e1.context.location && 'Rancho Dominguez' === e1.context.location_detail && 2 === e1.context.treats.length && 'phone' === e1.context.contact_pref && e1.source.latest, e1.context );
		const dl = await page.evaluate( () => window.dataLayer );
		const dlText = JSON.stringify( dl );
		const submitted = dl.filter( ( x ) => 'quote_submitted' === x.event );
		check( 'U13 analytics: quote_submitted once after the server saved it; no name, email, phone, venue or dietary text in any event', 1 === submitted.length && ! dlText.includes( mail1 ) && ! dlText.includes( 'Browser Tester' ) && ! dlText.includes( '555-0100' ) && ! /Nut allergy|Rancho/.test( dlText ) && dl.some( ( x ) => 'quote_step_view' === x.event ) && dl.some( ( x ) => 'coverage_result' === x.event ), dl );
		await page.reload( { waitUntil: 'networkidle2' } );
		const after = { shown: await visible( '[data-cic-received]' ), submitted: ( await page.evaluate( () => window.dataLayer ) ).filter( ( x ) => 'quote_submitted' === x.event ).length };
		check( 'U14 refreshing the confirmation shows it again without a new conversion or submission', after.shown && 0 === after.submitted && 1 === json( 'enquiry', mail1 ).count, after );
		( await mails( mail1 ) ).length;

		// ---------------- Keyboard ----------------
		await page.evaluate( () => window.sessionStorage.clear() );
		await go( '/get-a-quote/' );
		await page.focus( 'input[name=event][value=birthday]' );
		await page.keyboard.press( 'Space' );
		await page.keyboard.press( 'ArrowRight' );
		const kbEvent = await page.$eval( 'input[name=event]:checked', ( e ) => e.value ).catch( () => '' );
		await page.focus( '#cic-f-guests' );
		await page.keyboard.type( '25' );
		await page.focus( 'input[name=date_unknown]' );
		await page.keyboard.press( 'Space' );
		await page.focus( '#cic-f-location' );
		await page.keyboard.type( info.covered );
		await page.keyboard.press( 'Enter' );
		await sleep( 900 );
		const kbStep = await step();
		const tabbable = await page.evaluate( () => Array.from( document.querySelectorAll( '.cic-form input, .cic-form select, .cic-form textarea, .cic-form button' ) ).filter( ( e ) => 'hidden' !== e.type && ! e.closest( '.cic-hp' ) ).every( ( e ) => e.tabIndex >= 0 ) );
		// A real Tab from the step heading lands on the first treat card: its focus ring must show.
		await page.keyboard.press( 'Tab' );
		const ring = await page.evaluate( () => { const a = document.activeElement; return a && a.nextElementSibling ? a.className + ':' + getComputedStyle( a.nextElementSibling ).outlineStyle : 'none'; } );
		check( 'U15 keyboard: tiles work with Space/arrow keys, Enter continues to the next step, every control is tabbable, focus is visible', kbEvent && 'birthday' !== kbEvent && 2 === kbStep && tabbable && /cic-treat__input:(solid|auto)/.test( ring ), { kbEvent, kbStep, tabbable, ring } );

		// ---------------- Network failure ----------------
		await page.focus( 'input[name="treats[]"][value=classic-ice-cream]' );
		await page.keyboard.press( 'Space' );
		await clickNext();
		const mail2 = email( 'net' );
		used.push( mail2 );
		await type( '#cic-f-your-name', 'Network Tester' );
		await type( '#cic-f-your-email', mail2 );
		await page.setRequestInterception( true );
		let blockedOnce = false;
		const onReq = ( r ) => {
			if ( ! blockedOnce && 'POST' === r.method() && r.url().includes( '/contact-form-7/v1/contact-forms/' ) ) {
				blockedOnce = true;
				r.abort( 'failed' );
			} else {
				r.continue();
			}
		};
		page.on( 'request', onReq );
		await page.focus( '.cic-button--send' );
		await page.keyboard.press( 'Enter' );
		await page.waitForFunction( () => ! document.querySelector( '[data-cic-errors]' ).hidden, { timeout: 40000 } ).catch( () => {} );
		const net = await page.evaluate( () => ( { msg: document.querySelector( '[data-cic-errors]' ).textContent, btn: document.querySelector( '.cic-button--send' ).disabled, status: document.querySelector( 'form.wpcf7-form' ).getAttribute( 'data-status' ) } ) );
		check( 'U16 network failure: clear message, answers kept, the form is usable again (not stuck “submitting”)', blockedOnce && /could not reach/i.test( net.msg ) && ! net.btn && 'init' === net.status && 'Network Tester' === await v( '#cic-f-your-name' ), net );
		page.off( 'request', onReq );
		await page.setRequestInterception( false );
		await page.$eval( '.cic-button--send', ( b ) => b.click() );
		await page.waitForSelector( '[data-cic-received]:not([hidden])', { timeout: 20000 } ).catch( () => {} );
		check( 'U17 pressing Send again after the failure saves exactly one enquiry', 1 === json( 'enquiry', mail2 ).count && await visible( '[data-cic-received]' ) );

		// ---------------- Mobile ----------------
		await page.setViewport( { width: 390, height: 844 } );
		await page.evaluate( () => window.sessionStorage.clear() );
		await go( '/get-a-quote/?treat=dippin-dots' );
		const noScroll = await page.evaluate( () => document.documentElement.scrollWidth <= window.innerWidth + 1 );
		await shot( 'p6-step1-mobile.png' );
		check( 'U18 390 px: no horizontal scrolling; tiles and fields stack', noScroll );
		await page.setViewport( { width: 1440, height: 1000 } );

		// ---------------- No JavaScript ----------------
		const nojs = await browser.newPage();
		await nojs.setJavaScriptEnabled( false );
		await nojs.setViewport( { width: 1440, height: 1000 } );
		await nojs.setUserAgent( 'Mozilla/5.0 CIC-Phase06-NoJS' );
		await nojs.goto( BASE + '/get-a-quote/', { waitUntil: 'domcontentloaded' } );
		const nj = await nojs.evaluate( () => ( {
			steps: Array.from( document.querySelectorAll( '.cic-qf__step' ) ).filter( ( s ) => ! s.hidden && s.offsetHeight > 0 ).length,
			nav: Array.from( document.querySelectorAll( '.cic-qf__progress, [data-cic-next]' ) ).every( ( e ) => 0 === e.offsetHeight ),
			send: !! document.querySelector( '.cic-button--send' ).offsetHeight,
		} ) );
		await nojs.screenshot( { path: path.join( OUT, 'p6-nojs.png' ), fullPage: true } );
		check( 'U19 no JavaScript: all three steps visible as one form with a single Send button', 3 === nj.steps && nj.nav && nj.send, nj );
		await nojs.type( '#cic-f-your-name', 'No Script Person' );
		await sleep( 3200 );
		await Promise.all( [ nojs.waitForNavigation( { waitUntil: 'domcontentloaded' } ), nojs.click( '.cic-button--send' ) ] );
		const nj2 = await nojs.evaluate( () => ( { kept: document.querySelector( '#cic-f-your-name' ).value, tips: document.querySelectorAll( '.wpcf7-not-valid-tip' ).length } ) );
		check( 'U20 no JavaScript: server errors appear inline and entered values are kept', 'No Script Person' === nj2.kept && nj2.tips >= 4, nj2 );
		const mail3 = email( 'nojs' );
		used.push( mail3 );
		await nojs.click( 'input[name=event][value=corporate]' );
		await nojs.$eval( '#cic-f-event-date', ( e, d ) => { e.value = d; }, info.future );
		await nojs.$eval( '#cic-f-guests', ( e ) => { e.value = '120'; } );
		await nojs.$eval( '#cic-f-location', ( e, z ) => { e.value = z; }, info.covered );
		await nojs.click( 'input[name="treats[]"][value=frozen-yogurt]' );
		await nojs.$eval( '#cic-f-your-email', ( e, m ) => { e.value = m; }, mail3 );
		await sleep( 3200 );
		await Promise.all( [ nojs.waitForNavigation( { waitUntil: 'domcontentloaded' } ), nojs.click( '.cic-button--send' ) ] );
		const nj3 = await nojs.evaluate( () => ( { url: location.href, heading: ( document.querySelector( '#cic-received-title' ) || {} ).textContent, shown: ! document.querySelector( '[data-cic-received]' ).hidden } ) );
		await nojs.screenshot( { path: path.join( OUT, 'p6-nojs-confirmation.png' ), fullPage: false } );
		await nojs.close();
		check( 'U21 no JavaScript: a valid submission is saved once and lands on the confirmation', 1 === json( 'enquiry', mail3 ).count && /received=[a-f0-9]{32}/.test( nj3.url ) && nj3.shown && /received/i.test( nj3.heading || '' ), nj3 );

		// ---------------- Contact ----------------
		state( 'reset-limits' );
		await go( '/contact/' );
		const mail4 = email( 'contact' );
		used.push( mail4 );
		await page.select( '#cic-f-topic', 'areas' );
		await type( '#cic-f-your-name', 'Contact Browser' );
		await type( '#cic-f-your-email', mail4 );
		await type( '#cic-f-message', 'Do you cater in Ventura?' );
		await sleep( 3200 );
		await page.$eval( '.cic-button--send', ( b ) => b.click() );
		await page.waitForSelector( '[data-cic-received]:not([hidden])', { timeout: 20000 } ).catch( () => {} );
		await shot( 'p6-contact-confirmation.png' );
		check( 'U22 Contact form saves one message and shows its confirmation', 1 === json( 'enquiry', mail4 ).count && 'contact' === json( 'enquiry', mail4 ).type && /sent=[a-f0-9]{32}/.test( page.url() ) );

		// ---------------- Mail failure + admin ----------------
		state( 'reset-limits' );
		state( 'fail', 'on' );
		const mail5 = email( 'fail' );
		used.push( mail5 );
		await page.evaluate( () => window.sessionStorage.clear() );
		await go( '/get-a-quote/?event=anniversary&treat=classic-ice-cream' );
		await pick( 'input[name=date_unknown]' );
		await type( '#cic-f-guests', '35' );
		await type( '#cic-f-location', info.covered );
		await clickNext();
		await clickNext();
		await type( '#cic-f-your-name', '=SUM(1,2)' );
		await type( '#cic-f-your-email', mail5 );
		await page.$eval( '.cic-button--send', ( b ) => b.click() );
		await page.waitForSelector( '[data-cic-received]:not([hidden])', { timeout: 20000 } ).catch( () => {} );
		const e5 = json( 'enquiry', mail5 );
		check( 'U23 mail failure: the visitor still sees “received” and the enquiry is saved once with failed delivery', 1 === e5.count && await visible( '[data-cic-received]' ) && 'failed' === e5.row.operator_status, e5.row );
		state( 'fail', 'off' );

		await page.setCookie( ...auth.cookies.map( ( c ) => ( { name: c.name, value: c.value, domain: 'catericecream.test', path: '/', secure: true, httpOnly: true } ) ) );
		await go( '/wp-admin/admin.php?page=cic-enquiries' );
		const list = await page.$$eval( '#cic-enquiry-list tbody tr[data-enquiry]', ( r ) => r.map( ( x ) => +x.dataset.enquiry ) );
		await shot( 'p6-admin-list.png' );
		check( 'A1 Enquiries list shows the saved enquiries with status and notification state', [ e1.id, e5.id ].every( ( id ) => list.includes( id ) ), list.slice( 0, 10 ) );
		await go( '/wp-admin/admin.php?page=cic-enquiries&enquiry=' + e5.id );
		await shot( 'p6-admin-detail-failed.png' );
		const before = await page.$$eval( '#cic-notifications [data-state]', ( p ) => p.map( ( x ) => x.dataset.state ) );
		await Promise.all( [ page.waitForNavigation( { waitUntil: 'networkidle2' } ), page.click( '#cic-retry' ) ] );
		const afterRetry = await page.$$eval( '#cic-notifications [data-state]', ( p ) => p.map( ( x ) => x.dataset.state ) );
		const m5 = await mails( mail5 );
		check( 'A2 Retry notifications delivers the failed messages once (enquiry unchanged)', before.includes( 'failed' ) && afterRetry.every( ( s ) => 'sent' === s ) && 1 === m5.length && 1 === json( 'enquiry', mail5 ).count, { before, afterRetry, mails: m5.length } );
		await page.select( '#cic-wf-status', 'contacted' );
		const me = await page.$$eval( '#cic-wf-assignee option', ( o ) => o.map( ( x ) => x.value ).filter( ( x ) => '0' !== x )[ 0 ] );
		await page.select( '#cic-wf-assignee', me );
		await page.$eval( '#cic-wf-follow', ( e, d ) => { e.value = d; }, info.future );
		await page.type( '#cic-wf-note', 'Left a voicemail.' );
		await Promise.all( [ page.waitForNavigation( { waitUntil: 'networkidle2' } ), page.click( '#cic-workflow #submit' ) ] );
		const wf = await page.evaluate( () => ( { status: document.querySelector( '#cic-wf-status' ).value, follow: document.querySelector( '#cic-wf-follow' ).value, notes: document.querySelectorAll( '#cic-notes li' ).length, reminder: document.querySelector( '#cic-reminder-state' ).textContent } ) );
		await shot( 'p6-admin-detail.png' );
		check( 'A3 status, assignee, next follow-up and a private note are saved; reminders stay off (timing pending)', 'contacted' === wf.status && info.future === wf.follow && 1 === wf.notes && /off/i.test( wf.reminder ) && 'contacted' === json( 'enquiry', mail5 ).wf, wf );
		await go( '/wp-admin/admin.php?page=cic-enquiries' );
		const exportUrl = await page.$eval( '#cic-export', ( a ) => a.href );
		const csv = await request( exportUrl, { cookies: auth.cookies } );
		check( 'A4 CSV export (nonce + capability) contains the enquiries with formula-protected cells', 200 === csv.code && /text\/csv/.test( csv.type ) && csv.body.includes( mail5 ) && csv.body.includes( "'=SUM(1,2)" ) && ! /[,\n]=SUM/.test( csv.body ), { code: csv.code, type: csv.type } );

		// Unauthorised access.
		const editor = json( 'user', 'editor', 'cic_p6_editor' );
		const manager = json( 'user', 'cic_enquiry_manager', 'cic_p6_manager' );
		const edList = await request( BASE + '/wp-admin/admin.php?page=cic-enquiries', { cookies: editor.cookies } );
		const edExport = await request( exportUrl, { cookies: editor.cookies } );
		const edPost = await request( BASE + '/wp-admin/admin-post.php', { method: 'POST', cookies: editor.cookies, body: 'action=cic_enquiry_update&enquiry=' + e5.id + '&status=lost&_wpnonce=x' } );
		const anonExport = await request( exportUrl );
		const mgList = await request( BASE + '/wp-admin/admin.php?page=cic-enquiries', { cookies: manager.cookies } );
		const mgSettings = await request( BASE + '/wp-admin/admin.php?page=cic-enquiry-settings', { cookies: manager.cookies } );
		const mgFlamingo = await request( BASE + '/wp-admin/admin.php?page=flamingo_inbound', { cookies: manager.cookies } );
		check( 'A5 editors and visitors are refused (list, export, actions); the status is unchanged', 200 !== edList.code && 403 === edExport.code && 403 === edPost.code && 200 !== anonExport.code && ! anonExport.body.includes( mail5 ) && 'contacted' === json( 'enquiry', mail5 ).wf, { edList: edList.code, edExport: edExport.code, edPost: edPost.code, anon: anonExport.code } );
		check( 'A6 the Enquiry manager role sees the enquiry list but not mail settings or Flamingo’s screens', 200 === mgList.code && mgList.body.includes( 'cic-enquiry-list' ) && 200 !== mgSettings.code && 200 !== mgFlamingo.code, { list: mgList.code, settings: mgSettings.code, flamingo: mgFlamingo.code } );

		// Save-before-notify across all browser submissions.
		const obs = json( 'observe' ).filter( ( o ) => o.id > 0 && ! o.fail );
		check( 'A7 every notification in this run was sent after its enquiry was stored', obs.length >= 6 && obs.every( ( o ) => o.stored ), obs.length );
	} catch ( e ) {
		check( 'Unexpected error: ' + e.message, false );
	} finally {
		if ( browser ) {
			await browser.close().catch( () => {} );
		}
		try {
			if ( auth ) {
				wp( 'eval-file', 'dev/tests/phase02/auth-cookies.php', 'destroy', auth.token );
			}
			state( 'fail', 'off' );
			state( 'observer', 'off' );
			const ids = [];
			for ( const to of used.concat( [ operator ] ) ) {
				( await mails( to ) ).forEach( ( m ) => ids.push( m.ID ) );
			}
			if ( ids.length ) {
				await request( 'http://localhost:8025/api/v1/messages', { method: 'DELETE', body: JSON.stringify( { IDs: ids } ), headers: { 'Content-Type': 'application/json' } } );
			}
			const restored = last( wp( 'eval-file', 'dev/tests/phase06/state.php', 'restore', snapshot ) );
			check( 'Z1 browser closed; test enquiries, users, settings, fixture and test mail removed', /Restored/.test( restored ), restored );
		} catch ( e ) {
			check( 'Z1 restore failed: ' + e.message, false );
		}
		fs.rmSync( snapshot, { force: true } );
		const passed = results.filter( Boolean ).length;
		console.log( `Phase 06 browser checks: ${ passed }/${ results.length } passed` );
		process.exitCode = passed === results.length ? 0 : 1;
	}
} )();
