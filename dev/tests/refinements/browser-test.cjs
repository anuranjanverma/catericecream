/**
 * Refinement checks (2026-10-21): consistent location labels, the event-date picker, the Site Icon,
 * event/date field icons and the California map preview. Local only; one Edge instance, run
 * sequentially and always closed. Uses the Phase 06 state helper for a snapshot/restore of
 * test-created enquiries and synthetic @example.test addresses; Mailpit messages for those
 * addresses are deleted afterwards. Screenshots: dev/tests/output/refinements/.
 *
 *   NODE_PATH=<dir>/node_modules node dev/tests/refinements/browser-test.cjs
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
const OUT = path.join( ROOT, 'dev/tests/output/refinements' );
const WP = 'C:\\laragon\\bin\\wp-cli\\wp.cmd';
const BROWSER = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
fs.mkdirSync( OUT, { recursive: true } );

const results = [];
const check = ( label, ok, detail ) => {
	results.push( !! ok );
	console.log( ( ok ? 'PASS ' : 'FAIL ' ) + label + ( ! ok && detail !== undefined ? ' — ' + JSON.stringify( detail ).slice( 0, 600 ) : '' ) );
};
const wp = ( ...args ) => execFileSync( 'cmd', [ '/c', WP, ...args ], { cwd: ROOT, encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'ignore' ], timeout: 120000 } );
const last = ( s ) => s.trim().split( '\n' ).pop();
const state = ( ...a ) => last( wp( 'eval-file', 'dev/tests/phase06/state.php', ...a ) );
const sleep = ( ms ) => new Promise( ( r ) => setTimeout( r, ms ) );
const email = ( tag ) => 'ref-' + tag + '-' + crypto.randomBytes( 3 ).toString( 'hex' ) + '@example.test';
const used = [];

const request = ( url, { method = 'GET', body = '', headers = {} } = {} ) => new Promise( ( resolve ) => {
	const u = new URL( url );
	const lib = 'http:' === u.protocol ? http : https;
	const h = Object.assign( { 'User-Agent': 'Mozilla/5.0 CIC-Refinements-Test' }, headers );
	if ( body ) {
		h[ 'Content-Length' ] = Buffer.byteLength( body );
	}
	const req = lib.request( u, { method, rejectUnauthorized: false, headers: h, agent: false }, ( res ) => {
		let data = '';
		res.on( 'data', ( d ) => { data += d; } );
		res.on( 'end', () => resolve( { code: res.statusCode, type: res.headers[ 'content-type' ] || '', location: res.headers.location || '', body: data } ) );
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
	const snapshot = path.join( OUT, 'state.json' );
	const operator = 'operator-ref@example.test';
	const countyImage = last( wp( 'post', 'meta', 'get', '180', 'cic_sa_county_3_image' ) );
	wp( 'eval-file', 'dev/tests/phase06/state.php', 'snapshot', snapshot );
	try {
		state( 'recipient', operator );
		state( 'reset-limits' );
		browser = await puppeteer.launch( { executablePath: BROWSER, headless: true, acceptInsecureCerts: true } );
		let page = await browser.newPage();
		const errors = [];
		const watch = ( p ) => {
			p.on( 'pageerror', ( e ) => errors.push( e.message ) );
			p.on( 'console', ( m ) => {
				if ( 'error' === m.type() && ! /favicon|net::ERR/.test( m.text() ) ) {
					errors.push( m.text() );
				}
			} );
		};
		watch( page );
		const go = ( url, p = page ) => p.goto( BASE + url, { waitUntil: 'networkidle2' } );
		const shot = ( name, p = page, full = false ) => p.screenshot( { path: path.join( OUT, name ), fullPage: full } );
		const val = ( sel, p = page ) => p.$eval( sel, ( e ) => e.value ).catch( () => null );
		const ctx = ( p = page ) => p.evaluate( () => JSON.parse( window.sessionStorage.getItem( 'cic_quote_context' ) || '{}' ) );
		const pick = async ( sel, text, p = page ) => {
			await p.click( sel, { clickCount: 3 } );
			await p.keyboard.press( 'Backspace' );
			await p.type( sel, text, { delay: 40 } );
			await p.waitForSelector( '.cic-ac-list:not([hidden]) [role=option]', { timeout: 5000 } );
			await p.keyboard.press( 'ArrowDown' );
			await p.keyboard.press( 'Enter' );
		};

		/* ================= 1. Location labels ================= */
		console.log( '== Location labels' );
		await page.setViewport( { width: 1440, height: 1000 } );
		await go( '/' );
		await pick( '#cic-home-search-location', 'Los An' );
		check( 'L1 choosing the suggestion shows "Los Angeles, CA" and remembers its key', 'Los Angeles, CA' === await val( '#cic-home-search-location' ) && 'los-angeles-ca' === await page.$eval( '#cic-home-search-location', ( e ) => e.getAttribute( 'data-cic-location-key' ) ), await val( '#cic-home-search-location' ) );
		await page.select( '#cic-home-search-event', 'birthday' );
		await shot( 'hero-1440.png' );
		await Promise.all( [ page.waitForNavigation( { waitUntil: 'networkidle2' } ), page.click( '.cic-search__submit' ) ] );
		let c = await ctx();
		check( 'L2 quote form shows "Los Angeles, CA"; the context keeps the key; the URL and the context carry only the key', 'Los Angeles, CA' === await val( '#cic-f-location' ) && 'los-angeles-ca' === c.location && 'los-angeles-ca' === new URL( page.url() ).searchParams.get( 'location' ), { v: await val( '#cic-f-location' ), c, url: page.url() } );
		await go( '/events/birthday/' );
		check( 'L3 another page\'s starter shows "Los Angeles, CA" (not los-angeles-ca)', 'Los Angeles, CA' === await val( '#cic-starter-location' ), await val( '#cic-starter-location' ) );
		await go( '/treats/dippin-dots/' );
		const treatLoc = await page.$eval( '.cic-qs input[data-cic-context=location]', ( e ) => e.value ).catch( () => null );
		check( 'L4 treat-page quote starter shows "Los Angeles, CA"', 'Los Angeles, CA' === treatLoc, treatLoc );
		await go( '/get-a-quote/' );
		await page.reload( { waitUntil: 'networkidle2' } );
		const afterReload = await val( '#cic-f-location' );
		await go( '/events/birthday/' );
		await page.goBack( { waitUntil: 'networkidle2' } );
		const afterBack = await val( '#cic-f-location' );
		await page.goForward( { waitUntil: 'networkidle2' } );
		const afterForward = await val( '#cic-starter-location' );
		check( 'L5 reload, Back and Forward keep "Los Angeles, CA"', 'Los Angeles, CA' === afterReload && 'Los Angeles, CA' === afterBack && 'Los Angeles, CA' === afterForward, { afterReload, afterBack, afterForward } );

		// Fresh session: direct quote links and an older stored key without a label.
		const fresh = await browser.createBrowserContext();
		const p2 = await fresh.newPage();
		watch( p2 );
		await p2.setViewport( { width: 768, height: 1000 } );
		await go( '/get-a-quote/?location=los-angeles-ca', p2 );
		const direct = await val( '#cic-f-location', p2 );
		await go( '/get-a-quote/?location=91101', p2 );
		const zip = await val( '#cic-f-location', p2 );
		await p2.evaluate( () => window.sessionStorage.clear() );
		await go( '/get-a-quote/?location=nowhere-ca', p2 );
		const unknown = await val( '#cic-f-location', p2 );
		const html = await p2.content();
		check( 'L6 direct links: city key → label, ZIP stays the ZIP, unknown key leaves the field empty (never the slug)', 'Los Angeles, CA' === direct && '91101' === zip && '' === unknown && ! html.includes( 'value="nowhere-ca"' ), { direct, zip, unknown } );
		await p2.evaluate( () => window.sessionStorage.setItem( 'cic_quote_context', JSON.stringify( { location: 'pasadena-ca' } ) ) );
		await go( '/events/birthday/', p2 );
		await sleep( 800 );
		check( 'L7 an older stored key without a label is resolved through the location repository', 'Pasadena, CA' === await val( '#cic-starter-location', p2 ), await val( '#cic-starter-location', p2 ) );
		await fresh.close();

		// Editing after a selection, exclusion and a new choice.
		await go( '/events/birthday/' );
		await page.focus( '#cic-starter-location' );
		await page.keyboard.press( 'End' );
		await page.keyboard.type( 'x' );
		check( 'L8 editing the text after a choice clears the stale key', null === await page.$eval( '#cic-starter-location', ( e ) => e.getAttribute( 'data-cic-location-key' ) ) );
		await page.click( '#cic-starter-location', { clickCount: 3 } );
		await page.keyboard.press( 'Backspace' );
		await page.type( '#cic-starter-location', 'Compton', { delay: 30 } );
		await page.keyboard.press( 'Escape' );
		await page.click( '.cic-starter button[type=submit]' );
		await sleep( 1200 );
		const blocked = await page.$eval( '#cic-starter-error', ( e ) => ! e.hidden && e.textContent.trim() ).catch( () => '' );
		check( 'L9 the excluded city (Compton) is still blocked and stays on the page', !! blocked && /events\/birthday/.test( page.url() ), blocked );
		await pick( '#cic-starter-location', 'Pasad' );
		await Promise.all( [ page.waitForNavigation( { waitUntil: 'networkidle2' } ), page.click( '.cic-starter button[type=submit]' ) ] );
		c = await ctx();
		check( 'L10 a new choice replaces the old one: "Pasadena, CA" with key pasadena-ca', 'Pasadena, CA' === await val( '#cic-f-location' ) && 'pasadena-ca' === c.location, { v: await val( '#cic-f-location' ), c } );

		// Shared ZIP clarification (fixture 90220 is shared with excluded Compton).
		await page.evaluate( () => window.sessionStorage.clear() );
		await go( '/get-a-quote/' );
		await page.click( 'input[name=event][value=birthday]' );
		await page.click( '[name=date_unknown]' ).catch( async () => page.click( '[name=date_unknown]' ) );
		await page.type( '#cic-f-guests', '40' );
		await page.type( '#cic-f-location', '90220' );
		await page.click( '.cic-qf__step[data-step="1"] [data-cic-next]' );
		await sleep( 1500 );
		const detailShown = await page.$eval( '[data-cic-detail]', ( e ) => ! e.hidden ).catch( () => false );
		check( 'L11 shared ZIP still asks for the venue city before continuing', detailShown && '1' === await page.$eval( '.cic-qf__step:not([hidden])', ( e ) => e.getAttribute( 'data-step' ) ) );

		// Changing the location on the quote form: validated on Next, then kept across a reload.
		await page.evaluate( () => window.sessionStorage.clear() );
		await go( '/get-a-quote/?location=los-angeles-ca&event=birthday' );
		await page.click( '[name=date_unknown]' );
		await page.type( '#cic-f-guests', '30' );
		await pick( '#cic-f-location', 'Pasad' );
		await page.click( '.cic-qf__step[data-step="1"] [data-cic-next]' );
		await page.waitForSelector( '.cic-qf__step[data-step="2"]:not([hidden])', { timeout: 8000 } );
		const urlAfter = new URL( page.url() ).searchParams.get( 'location' );
		await page.reload( { waitUntil: 'networkidle2' } );
		const afterChange = await val( '#cic-f-location' );
		check( 'L12 a changed location is validated on Next, written to the URL as its key and kept after reload ("Pasadena, CA")', 'pasadena-ca' === urlAfter && 'Pasadena, CA' === afterChange, { urlAfter, afterChange } );

		/* ================= 2. Date picker ================= */
		console.log( '== Date picker' );
		for ( const w of [ 390, 768, 1440 ] ) {
			await page.setViewport( { width: w, height: 900 } );
			await page.evaluate( () => window.sessionStorage.clear() );
			await go( '/get-a-quote/' );
			// Click the icon area at the left of the control.
			const box = await page.$eval( '#cic-f-event-date', ( e ) => { const r = e.getBoundingClientRect(); return { x: r.left + 14, y: r.top + r.height / 2 }; } );
			await page.evaluate( () => document.querySelector( '#cic-f-event-date' ).scrollIntoView( { block: 'center' } ) );
			const box2 = await page.$eval( '#cic-f-event-date', ( e ) => { const r = e.getBoundingClientRect(); return { x: r.left + 14, y: r.top + r.height / 2 }; } );
			await page.mouse.click( box2.x, box2.y );
			await sleep( 400 );
			const dlg = await page.$eval( '.cic-dp', ( d ) => { const r = d.getBoundingClientRect(); return { open: ! d.hidden, left: r.left, right: r.right, vw: window.innerWidth, labels: Array.from( d.querySelectorAll( '.cic-dp__nav' ) ).map( ( b ) => b.getAttribute( 'aria-label' ) ) }; } ).catch( () => ( { open: false } ) );
			check( `D1 @${ w } clicking the icon area opens the calendar inside the viewport with labelled month/year buttons`, dlg.open && dlg.left >= 0 && dlg.right <= dlg.vw && 4 === dlg.labels.length && dlg.labels.every( Boolean ), Object.assign( dlg, { box } ) );
			await shot( `datepicker-${ w }.png` );
			await page.keyboard.press( 'Escape' );
			const closed = await page.$eval( '.cic-dp', ( d ) => d.hidden );
			check( `D2 @${ w } Escape closes and focus returns to the date field`, closed && 'cic-f-event-date' === await page.evaluate( () => document.activeElement.id ) );
			if ( 1440 !== w ) {
				continue;
			}
			await page.keyboard.press( 'ArrowDown' );
			const inGrid = await page.evaluate( () => document.activeElement.classList.contains( 'cic-dp__day' ) );
			const t1 = await page.$eval( '.cic-dp__title', ( e ) => e.textContent );
			await page.keyboard.press( 'PageDown' );
			const t2 = await page.$eval( '.cic-dp__title', ( e ) => e.textContent );
			await page.keyboard.press( 'ArrowRight' );
			const want = await page.evaluate( () => document.activeElement.getAttribute( 'data-date' ) );
			await page.keyboard.press( 'Enter' );
			const shown = await val( '#cic-f-event-date' );
			const canonical = await val( '[name=event_date]' );
			const [ y, m, d ] = want.split( '-' );
			check( 'D3 keyboard: Down arrow opens into the grid, PageDown changes month, Enter picks; field shows MM/DD/YYYY, value stays Y-m-d', inGrid && t1 !== t2 && shown === `${ m }/${ d }/${ y }` && canonical === want, { inGrid, t1, t2, shown, canonical, want } );
			await page.click( '#cic-f-event-date', { clickCount: 3 } );
			await page.keyboard.press( 'Escape' );
			await page.keyboard.press( 'Backspace' );
			await page.keyboard.type( '12/24/2026' );
			const openWhileTyping = await page.$eval( '.cic-dp', ( d ) => ! d.hidden );
			await page.keyboard.press( 'Tab' );
			check( 'D4 manual entry 12/24/2026 is accepted on leaving the field; typing does not open the calendar', '2026-12-24' === await val( '[name=event_date]' ) && ! openWhileTyping && '12/24/2026' === await val( '#cic-f-event-date' ), { openWhileTyping, v: await val( '[name=event_date]' ) } );
			await page.click( '#cic-f-event-date', { clickCount: 3 } );
			await page.keyboard.press( 'Escape' );
			await page.keyboard.press( 'Backspace' );
			await page.keyboard.type( '13/45/2026' );
			await page.keyboard.press( 'Tab' );
			await page.click( 'input[name=event][value=wedding]' );
			await page.type( '#cic-f-guests', '50' );
			await pick( '#cic-f-location', 'Pasad' );
			await page.click( '.cic-qf__step[data-step="1"] [data-cic-next]' );
			await sleep( 600 );
			const err = await page.$eval( '#cic-f-event-date', ( e ) => e.getAttribute( 'aria-invalid' ) );
			check( 'D5 an impossible date is reported on the visible date field (existing validation)', 'true' === err, err );
			await page.click( '[name=date_unknown]' );
			const disabled = await page.$eval( '#cic-f-event-date', ( e ) => e.disabled && '' === e.value );
			await page.click( '[name=date_unknown]' );
			const enabled = await page.$eval( '#cic-f-event-date', ( e ) => ! e.disabled );
			check( 'D6 "Date not set yet" clears and disables the date field; unticking enables it again', disabled && enabled );
		}
		// Touch: a tap opens the calendar with focus inside it (no on-screen keyboard).
		const touchCtx = await browser.createBrowserContext();
		const tp = await touchCtx.newPage();
		watch( tp );
		await tp.setViewport( { width: 390, height: 844, isMobile: true, hasTouch: true } );
		await go( '/get-a-quote/', tp );
		await tp.evaluate( () => document.querySelector( '#cic-f-event-date' ).scrollIntoView( { block: 'center' } ) );
		const tb = await tp.$eval( '#cic-f-event-date', ( e ) => { const r = e.getBoundingClientRect(); return { x: r.left + r.width / 2, y: r.top + r.height / 2 }; } );
		await tp.touchscreen.tap( tb.x, tb.y );
		await sleep( 500 );
		const touchState = await tp.evaluate( () => ( { open: ! document.querySelector( '.cic-dp' ).hidden, focus: document.activeElement.className } ) );
		check( 'D7 touch: tapping the date control opens the calendar and moves focus into it', touchState.open && /cic-dp__day/.test( touchState.focus ), touchState );
		const day = await tp.$( '.cic-dp__day:not([aria-disabled])' );
		const dayBox = await day.boundingBox();
		await tp.touchscreen.tap( dayBox.x + dayBox.width / 2, dayBox.y + dayBox.height / 2 );
		await sleep( 300 );
		check( 'D8 touch: tapping a day fills MM/DD/YYYY and closes the calendar; day targets are at least 44px tall', /^\d{2}\/\d{2}\/\d{4}$/.test( await val( '#cic-f-event-date', tp ) ) && dayBox.height >= 44, { v: await val( '#cic-f-event-date', tp ), h: dayBox.height } );
		await touchCtx.close();
		// Starter date travels to the quote form.
		await page.setViewport( { width: 390, height: 900 } );
		await page.evaluate( () => window.sessionStorage.clear() );
		await go( '/events/birthday/' );
		await page.evaluate( () => document.querySelector( '#cic-starter-date' ).scrollIntoView( { block: 'center' } ) );
		await page.click( '#cic-starter-date' );
		await page.keyboard.press( 'ArrowDown' );
		await page.keyboard.press( 'PageDown' );
		const starterWant = await page.evaluate( () => document.activeElement.getAttribute( 'data-date' ) );
		await page.keyboard.press( 'Enter' );
		await shot( 'starter-date-390.png' );
		await pick( '#cic-starter-location', 'Los An' );
		await Promise.all( [ page.waitForNavigation( { waitUntil: 'networkidle2' } ), page.click( '.cic-starter button[type=submit]' ) ] );
		const [ sy, sm, sd ] = starterWant.split( '-' );
		check( 'D9 a starter date carries to the quote form as MM/DD/YYYY (context keeps Y-m-d)', `${ sm }/${ sd }/${ sy }` === await val( '#cic-f-event-date' ) && starterWant === ( await ctx() ).date, { shown: await val( '#cic-f-event-date' ), want: starterWant } );

		/* ================= 3. Icons ================= */
		console.log( '== Icons' );
		await page.setViewport( { width: 1440, height: 1000 } );
		await go( '/' );
		const heroIcons = await page.evaluate( () => Array.from( document.querySelectorAll( '.cic-search__field' ) ).map( ( f ) => ( { icon: ( f.querySelector( '.cic-icon' ) || {} ).getAttribute ? f.querySelector( '.cic-icon' ).getAttribute( 'class' ) : '', label: ( f.querySelector( 'label' ) || {} ).textContent } ) ) );
		check( 'I1 hero search: event type uses the party icon, date keeps the calendar, text labels remain', /party/.test( heroIcons[ 1 ].icon ) && /calendar/.test( heroIcons[ 2 ].icon ) && heroIcons.every( ( f ) => f.label && f.label.trim() ), heroIcons );
		await go( '/treats/dippin-dots/' );
		const qsIcons = await page.evaluate( () => ( {
			event: ( document.querySelector( '.cic-qs__field--event .cic-icon' ) || { getAttribute: () => '' } ).getAttribute( 'class' ),
			date: ( document.querySelector( '.cic-qs__field--date .cic-icon' ) || { getAttribute: () => '' } ).getAttribute( 'class' ),
			labels: Array.from( document.querySelectorAll( '.cic-qs__field label' ) ).map( ( l ) => l.textContent.trim() ),
		} ) );
		check( 'I2 quote starter: event type uses the party icon, date keeps the calendar, labels remain', /party/.test( qsIcons.event ) && /calendar/.test( qsIcons.date ) && qsIcons.labels.every( Boolean ), qsIcons );
		const glance = await request( BASE + '/get-a-quote/' );
		check( 'I3 review summary and confirmation: event rows use the party icon, the date row keeps the calendar', /data-cic-glance="event"><span class="cic-field__icon" aria-hidden="true"><svg class="cic-icon cic-icon--party/.test( glance.body ) && /data-cic-glance="date"><span class="cic-field__icon" aria-hidden="true"><svg class="cic-icon cic-icon--calendar/.test( glance.body ) && /data-chip="event"[^>]*><svg class="cic-icon cic-icon--party/.test( glance.body ) );

		/* ================= 4. Favicon ================= */
		console.log( '== Site Icon' );
		const home = await request( BASE + '/' );
		const links = ( home.body.match( /<link[^>]+rel=["'](?:shortcut )?(?:icon|apple-touch-icon)["'][^>]*>/g ) || [] );
		const tiles = ( home.body.match( /msapplication-TileImage/g ) || [] ).length;
		const urls = links.map( ( l ) => /href=["']([^"']+)/.exec( l )[ 1 ] );
		const fetched = await Promise.all( urls.map( ( u ) => request( u ) ) );
		check( 'F1 one WordPress Site Icon set (32, 192, apple-touch 180) with no duplicate or conflicting declarations; PNG files served', 3 === links.length && 1 === tiles && /32x32/.test( urls[ 0 ] ) && /192x192/.test( urls[ 1 ] ) && /180x180/.test( urls[ 2 ] ) && fetched.every( ( r ) => 200 === r.code && /image\/png/.test( r.type ) ), { links, types: fetched.map( ( r ) => r.code + ' ' + r.type ) } );
		const ico = await request( BASE + '/favicon.ico' );
		check( 'F2 /favicon.ico points to the Site Icon', 302 === ico.code && /cropped-cater-site-icon-32x32\.png/.test( ico.location ), ico.code + ' ' + ico.location );

		/* ================= 5. Map ================= */
		console.log( '== California map' );
		for ( const w of [ 390, 768, 1440 ] ) {
			await page.setViewport( { width: w, height: 900 } );
			await page.emulateMediaFeatures( [ { name: 'prefers-reduced-motion', value: 'no-preference' } ] );
			await go( '/service-areas/' );
			const pre = await page.evaluate( () => {
				const m = document.querySelector( '[data-cic-map]' );
				const r = m.getBoundingClientRect();
				return { pending: m.classList.contains( 'cic-ca-map--pending' ) || m.classList.contains( 'cic-ca-map--in' ), inView: r.top < window.innerHeight && r.bottom > 0, imgs: m.querySelectorAll( '.cic-ca-map__card img' ).length, buttons: Array.from( m.querySelectorAll( '.cic-ca-map__point' ) ).every( ( p ) => 'button' === p.getAttribute( 'role' ) && '0' === p.getAttribute( 'tabindex' ) ) };
			} );
			await page.evaluate( () => document.querySelector( '[data-cic-map]' ).scrollIntoView( { block: 'center' } ) );
			await sleep( 1600 );
			const settled = await page.evaluate( () => {
				const m = document.querySelector( '[data-cic-map]' );
				const pin = m.querySelector( '.cic-ca-map__pin' );
				return { classes: m.className, opacity: getComputedStyle( pin ).opacity, anim: getComputedStyle( pin ).animationName };
			} );
			check( `M1 @${ w } counties are buttons; pins animate once on entering the viewport, then settle (no running animation); no photo loaded before use`, pre.buttons && 0 === pre.imgs && ( pre.pending || pre.inView ) && ! /pending|--in/.test( settled.classes ) && '1' === settled.opacity && 'none' === settled.anim, { pre, settled } );
			const pin = await page.$( '.cic-ca-map__point .cic-ca-map__pin' );
			if ( w >= 768 ) {
				await pin.hover();
			} else {
				await page.$eval( '.cic-ca-map__point', ( p ) => p.dispatchEvent( new MouseEvent( 'click', { bubbles: true } ) ) );
			}
			await sleep( 400 );
			const card = await page.$eval( '.cic-ca-map__card', ( c ) => { const r = c.getBoundingClientRect(); return { shown: ! c.hidden, name: c.querySelector( '.cic-ca-map__name' ).textContent, note: c.querySelector( '.cic-ca-map__note' ).textContent, img: !! c.querySelector( '.cic-ca-map__media img' ), l: r.left, r: r.right, t: r.top, b: r.bottom, vw: window.innerWidth, vh: window.innerHeight }; } );
			check( `M2 @${ w } ${ w >= 768 ? 'hover' : 'tap' } shows the county preview inside the viewport, with the reference-point note and the approved county photo`, card.shown && /County/.test( card.name ) && /not a service boundary/.test( card.note ) && card.img && card.l >= 0 && card.r <= card.vw && card.t >= 0 && card.b <= card.vh, card );
			await shot( `map-${ w }.png` );
			await page.keyboard.press( 'Escape' );
		}
		// Keyboard: focus shows, Enter moves into the card, Escape returns focus.
		await page.setViewport( { width: 1440, height: 1000 } );
		await go( '/about/' );
		await page.$eval( '.cic-ca-map__point', ( p ) => p.focus() );
		await sleep( 200 );
		const onFocus = await page.$eval( '.cic-ca-map__card', ( c ) => ! c.hidden );
		await page.keyboard.press( 'Enter' );
		const inCard = await page.evaluate( () => document.activeElement.closest( '.cic-ca-map__card' ) !== null );
		await page.keyboard.press( 'Escape' );
		const back = await page.evaluate( () => ( { hidden: document.querySelector( '.cic-ca-map__card' ).hidden, focusOnPoint: document.activeElement.classList.contains( 'cic-ca-map__point' ), expanded: document.activeElement.getAttribute( 'aria-expanded' ) } ) );
		check( 'M3 keyboard: focus shows the preview, Enter moves into it, Escape closes and returns focus to the county', onFocus && inCard && back.hidden && back.focusOnPoint && 'false' === back.expanded, { onFocus, inCard, back } );
		// Touch tap and tap-outside dismissal at 390.
		const mt = await browser.createBrowserContext();
		const mp = await mt.newPage();
		watch( mp );
		await mp.setViewport( { width: 390, height: 844, isMobile: true, hasTouch: true } );
		await go( '/contact/', mp );
		await mp.evaluate( () => document.querySelector( '[data-cic-map]' ).scrollIntoView( { block: 'center' } ) );
		await sleep( 1600 );
		const pb = await ( await mp.$( '.cic-ca-map__point .cic-ca-map__pin' ) ).boundingBox();
		await mp.touchscreen.tap( pb.x + pb.width / 2, pb.y + pb.height / 2 );
		await sleep( 400 );
		const tapped = await mp.$eval( '.cic-ca-map__card', ( c ) => { const r = c.getBoundingClientRect(); return { shown: ! c.hidden, l: r.left, r: r.right, vw: window.innerWidth }; } );
		await shot( 'map-contact-touch-390.png', mp );
		await mp.touchscreen.tap( 20, 400 );
		await sleep( 300 );
		const dismissed = await mp.$eval( '.cic-ca-map__card', ( c ) => c.hidden );
		check( 'M4 touch: tapping a pin opens the preview inside the viewport; tapping elsewhere closes it', tapped.shown && tapped.l >= 0 && tapped.r <= tapped.vw && dismissed, { tapped, dismissed } );
		// Reduced motion: no entrance animation at all.
		await mp.emulateMediaFeatures( [ { name: 'prefers-reduced-motion', value: 'reduce' } ] );
		await go( '/contact/', mp );
		const rm = await mp.evaluate( () => ( { cls: document.querySelector( '[data-cic-map]' ).className, opacity: getComputedStyle( document.querySelector( '.cic-ca-map__pin' ) ).opacity } ) );
		check( 'M5 reduced motion: pins are shown immediately with no animation classes', ! /pending|--in/.test( rm.cls ) && '1' === rm.opacity, rm );
		await mt.close();
		// A county without a photo gets a text-only card (slot image temporarily removed, restored in finally).
		wp( 'post', 'meta', 'update', '180', 'cic_sa_county_3_image', '' );
		await go( '/service-areas/' );
		await page.evaluate( () => document.querySelector( '[data-cic-map]' ).scrollIntoView( { block: 'center' } ) );
		await sleep( 1400 );
		const textOnly = await page.evaluate( async () => {
			const p = Array.from( document.querySelectorAll( '.cic-ca-map__point' ) ).find( ( x ) => /Santa Barbara/.test( x.getAttribute( 'data-name' ) ) );
			p.focus();
			await new Promise( ( r ) => setTimeout( r, 200 ) );
			const c = document.querySelector( '.cic-ca-map__card' );
			return { shown: ! c.hidden, media: c.querySelector( '.cic-ca-map__media' ).hidden, img: !! c.querySelector( 'img' ) };
		} );
		check( 'M6 a county without an approved photo shows a text-only preview', textOnly.shown && textOnly.media && ! textOnly.img, textOnly );

		/* ================= 6. One complete quote submission ================= */
		console.log( '== Quote submission' );
		state( 'reset-limits' );
		const who = email( 'submit' );
		used.push( who );
		await page.setViewport( { width: 390, height: 900 } );
		await page.evaluate( () => window.sessionStorage.clear() );
		await go( '/' );
		await pick( '#cic-home-search-location', 'Los An' );
		await page.select( '#cic-home-search-event', 'birthday' );
		await page.click( '#cic-home-search-date' );
		await page.keyboard.press( 'ArrowDown' );
		await page.keyboard.press( 'PageDown' );
		const qDate = await page.evaluate( () => document.activeElement.getAttribute( 'data-date' ) );
		await page.keyboard.press( 'Enter' );
		await Promise.all( [ page.waitForNavigation( { waitUntil: 'networkidle2' } ), page.click( '.cic-search__submit' ) ] );
		await page.type( '#cic-f-guests', '60' );
		// Los Angeles is only partly served in the fixtures, so the form asks for the event ZIP first.
		await page.click( '.cic-qf__step[data-step="1"] [data-cic-next]' );
		await page.waitForSelector( '[data-cic-detail]:not([hidden])', { timeout: 8000 } );
		check( 'Q0 a partly served city asks for the event ZIP (clarification preserved)', true );
		await page.type( '#cic-f-location-detail', '90012' );
		await page.click( '.cic-qf__step[data-step="1"] [data-cic-next]' );
		await page.waitForSelector( '.cic-qf__step[data-step="2"]:not([hidden])', { timeout: 8000 } );
		await page.click( 'input[name="treats[]"]' );
		await page.click( '.cic-qf__step[data-step="2"] [data-cic-next]' );
		await page.waitForSelector( '.cic-qf__step[data-step="3"]:not([hidden])' );
		const [ qy, qm, qd ] = qDate.split( '-' );
		const glanceVals = await page.evaluate( () => ( {
			location: document.querySelector( '[data-cic-glance="location"] [data-cic-glance-value]' ).textContent,
			date: document.querySelector( '[data-cic-glance="date"] [data-cic-glance-value]' ).textContent,
		} ) );
		check( 'Q1 review summary shows "Los Angeles, CA · 90012" and the date as MM/DD/YYYY', 'Los Angeles, CA · 90012' === glanceVals.location && `${ qm }/${ qd }/${ qy }` === glanceVals.date, glanceVals );
		await shot( 'review-390.png', page, true );
		await page.type( '#cic-f-your-name', 'Refinement Tester' );
		await page.type( '#cic-f-your-email', who );
		await sleep( 3500 ); // The spam guard rejects forms sent faster than 3 s after rendering.
		await page.click( '.cic-button--send' );
		await page.waitForSelector( '[data-cic-received]:not([hidden])', { timeout: 30000 } ).catch( async ( e ) => {
			console.log( 'DIAG', JSON.stringify( await page.evaluate( () => ( {
				summary: ( document.querySelector( '[data-cic-errors]' ) || {} ).textContent,
				output: ( document.querySelector( '.wpcf7-response-output' ) || {} ).textContent,
				invalid: Array.from( document.querySelectorAll( '[aria-invalid="true"]' ) ).map( ( x ) => x.name || x.id ),
				status: ( document.querySelector( 'form.wpcf7-form' ) || {} ).getAttribute( 'data-status' ),
				date: document.querySelector( '[name=event_date]' ).value,
			} ) ) ) );
			throw e;
		} );
		await sleep( 1500 );
		const chip = await page.$eval( '[data-chip="location"] [data-chip-value]', ( e ) => e.textContent );
		await shot( 'confirmation-390.png', page, true );
		await page.reload( { waitUntil: 'networkidle2' } );
		await sleep( 1500 );
		const saved = JSON.parse( state( 'enquiry', who ) );
		const operatorMail = ( await mails( operator ) ).length;
		const customerMail = ( await mails( who ) ).length;
		check( 'Q2 confirmation chip shows "Los Angeles, CA 90012" (repository label + event ZIP)', 'Los Angeles, CA 90012' === chip, chip );
		check( 'Q3 saved exactly once in Flamingo (also after refreshing the confirmation) with the label "Los Angeles, CA 90012", key los-angeles-ca and date label MM/DD/YYYY', 1 === saved.count && 'Los Angeles, CA 90012' === saved.context.location_label && 'los-angeles-ca' === saved.context.location && `${ qm }/${ qd }/${ qy }` === saved.context.date_label && qDate === saved.context.date, saved );
		check( 'Q4 local mail capture received one operator notification and one acknowledgement', 1 === operatorMail && 1 === customerMail, { operatorMail, customerMail } );

		check( 'X1 no JavaScript errors during the run', 0 === errors.length, errors );
	} catch ( e ) {
		check( 'run completed without an exception', false, e.stack );
	} finally {
		if ( browser ) {
			await browser.close().catch( () => {} );
		}
		try {
			wp( 'post', 'meta', 'update', '180', 'cic_sa_county_3_image', countyImage );
			const ids = [];
			for ( const to of used.concat( [ 'operator-ref@example.test' ] ) ) {
				( await mails( to ) ).forEach( ( m ) => ids.push( m.ID ) );
			}
			if ( ids.length ) {
				await request( 'http://localhost:8025/api/v1/messages', { method: 'DELETE', body: JSON.stringify( { IDs: ids } ), headers: { 'Content-Type': 'application/json' } } );
			}
			console.log( last( wp( 'eval-file', 'dev/tests/phase06/state.php', 'restore', snapshot ) ) );
			fs.unlinkSync( snapshot );
		} catch ( e ) {
			console.log( 'CLEANUP ERROR ' + e.message );
		}
		const passed = results.filter( Boolean ).length;
		console.log( `\nRefinement browser checks: ${ passed }/${ results.length } passed` );
		process.exitCode = passed === results.length ? 0 : 1;
	}
} )();
