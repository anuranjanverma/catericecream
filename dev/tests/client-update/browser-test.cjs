/**
 * Client update checks (2026-10-22): six events in the central order everywhere, Graduation
 * Parties and Proms pages at 390/768/1440, event preselection into the quote flow, phone links,
 * public contact name, legal name, logo sizes, and one complete Graduation quote submission
 * (saved once in Flamingo; one operator notification and one acknowledgement in Mailpit, with the
 * Alex Frost sign-off and the new phone number; recipient unchanged by the public contact
 * settings). Local only; synthetic @example.test addresses; one Edge instance, always closed.
 * Enquiry settings and test enquiries are restored by the Phase 06 state helper in `finally`;
 * test mail is deleted from Mailpit.
 *
 *   NODE_PATH=<dir>/node_modules node dev/tests/client-update/browser-test.cjs
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
const OUT = path.join( ROOT, 'dev/tests/output/client-update' );
const WP = 'C:\\laragon\\bin\\wp-cli\\wp.cmd';
const BROWSER = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const ORDER = [ 'birthday', 'wedding', 'graduation', 'prom', 'corporate', 'anniversary' ];
const SLUGS = [ 'birthday-party-ice-cream-catering', 'weddings-ice-cream-catering', 'graduation-party-ice-cream-catering', 'proms-ice-cream-catering', 'corporate-events-ice-cream-catering', 'anniversary-ice-cream-catering' ];
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
const same = ( a, b ) => JSON.stringify( a ) === JSON.stringify( b );

const mailpit = ( pathname, method = 'GET', body = '' ) => new Promise( ( resolve ) => {
	const req = http.request( 'http://localhost:8025' + pathname, { method, headers: body ? { 'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength( body ) } : {} }, ( res ) => {
		let data = '';
		res.on( 'data', ( d ) => { data += d; } );
		res.on( 'end', () => {
			try {
				resolve( JSON.parse( data || '{}' ) );
			} catch ( e ) {
				resolve( {} );
			}
		} );
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
	const operator = 'operator-cu@example.test';
	const who = 'cu-grad-' + crypto.randomBytes( 3 ).toString( 'hex' ) + '@example.test';
	wp( 'eval-file', 'dev/tests/phase06/state.php', 'snapshot', snapshot );
	try {
		state( 'recipient', operator );
		state( 'reset-limits' );
		browser = await puppeteer.launch( { executablePath: BROWSER, headless: true, acceptInsecureCerts: true } );
		const page = await browser.newPage();
		const errors = [];
		page.on( 'pageerror', ( e ) => errors.push( e.message ) );
		const go = ( url ) => page.goto( BASE + url, { waitUntil: 'networkidle2' } );
		const shot = ( name, full = true ) => page.screenshot( { path: path.join( OUT, name ), fullPage: full } );
		await page.setViewport( { width: 1440, height: 900 } );

		/* ================= 1. Central event order ================= */
		console.log( '== Order' );
		await go( '/' );
		const home = await page.evaluate( () => ( {
			submenu: Array.from( document.querySelectorAll( '.cic-nav--primary .sub-menu a[href*="/events/"]' ) ).map( ( a ) => a.getAttribute( 'href' ).split( '/events/' )[ 1 ].replace( /\/$/, '' ) ),
			cards: Array.from( document.querySelectorAll( '.cic-event-card' ) ).map( ( c ) => c.dataset.cicEvent ),
			columns: getComputedStyle( document.querySelector( '.cic-event-cards' ) ).gridTemplateColumns.split( ' ' ).length,
			search: Array.from( document.querySelectorAll( '#cic-home-search-event option' ) ).map( ( o ) => o.value ).filter( Boolean ),
			footer: Array.from( document.querySelectorAll( '.cic-footer a[href*="/events/"]' ) ).map( ( a ) => a.getAttribute( 'href' ) ),
		} ) );
		check( 'O1 header Events dropdown lists the six events in the required order', same( home.submenu, SLUGS ), home.submenu );
		check( 'O2 homepage event cards: six, in order, two rows of three at 1440 px', same( home.cards, ORDER ) && 3 === home.columns, home );
		check( 'O3 hero search options: six events in order, then "Other"', same( home.search, ORDER.concat( [ 'other' ] ) ), home.search );
		check( 'O4 footer: the Events link opens the overview (no per-event list to reorder)', home.footer.length >= 1 && home.footer.every( ( h ) => /\/events\/$/.test( h ) ), home.footer );
		await go( '/events/' );
		const overview = await page.$$eval( '.cic-ov-card', ( cs ) => cs.map( ( c ) => c.dataset.cicEvent ) );
		check( 'O5 events overview: six cards in order', same( overview, ORDER ), overview );
		await go( '/get-a-quote/' );
		const tiles = await page.$$eval( 'input[name="event"]', ( is ) => is.map( ( i ) => i.value ) );
		check( 'O6 quote form event tiles: six events in order, "Other" last', same( tiles, ORDER.concat( [ 'other' ] ) ), tiles );
		const ids = JSON.parse( last( wp( 'eval', 'echo wp_json_encode( array_column( CIC\\Content\\Catalog::selectable_events(), "id", "key" ) );' ) ) );
		for ( const treat of [ 'dippin-dots', 'frozen-yogurt', 'classic-ice-cream' ] ) {
			await go( '/treats/' + treat + '-catering/' );
			const occ = await page.$$eval( '[data-cic-pairing-event]', ( ls ) => ls.map( ( l ) => +l.dataset.cicPairingEvent ) );
			check( 'O7 ' + treat + ' occasion recommendations follow the central order', same( occ, ORDER.map( ( k ) => ids[ k ] ) ), occ );
		}
		await go( '/service-areas/' );
		const hub = await page.$$eval( '.cic-occasion', ( cs ) => cs.map( ( c ) => c.dataset.cicEvent ) );
		check( 'O8 Service Areas occasions: six in order', same( hub, ORDER ), hub );
		const city = await page.evaluate( () => ( document.querySelector( 'a[href*="/service-areas/california/"][href$="/"]:not([href$="/california/"])' ) || {} ).href || '' );
		if ( city ) {
			await page.goto( city, { waitUntil: 'networkidle2' } );
			const cityOcc = await page.$$eval( '.cic-occasion', ( cs ) => cs.map( ( c ) => c.dataset.cicEvent ) );
			check( 'O9 city page occasions: six in order (' + city.replace( BASE, '' ) + ')', same( cityOcc, ORDER ), cityOcc );
		}

		/* ================= 2. New pages at three widths ================= */
		console.log( '== New pages' );
		const pages = { graduation: '/events/graduation-party-ice-cream-catering/', prom: '/events/proms-ice-cream-catering/' };
		for ( const [ key, url ] of Object.entries( pages ) ) {
			for ( const width of [ 390, 768, 1440 ] ) {
				await page.setViewport( { width, height: 900 } );
				await go( url );
				const info = await page.evaluate( () => ( {
					h1: document.querySelectorAll( 'h1' ).length,
					title: document.title,
					desc: ( document.querySelector( 'meta[name="description"]' ) || {} ).content || '',
					canonical: ( document.querySelector( 'link[rel="canonical"]' ) || {} ).href || '',
					noindex: /noindex/.test( ( document.querySelector( 'meta[name="robots"]' ) || {} ).content || '' ),
					overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
					layout: document.querySelector( 'article.cic-event' ).className,
					quote: Array.from( document.querySelectorAll( 'a[href*="get-a-quote"]' ) ).map( ( a ) => a.getAttribute( 'href' ) ).filter( ( h ) => h.includes( 'event=' ) ),
					fixed: Array.from( document.querySelectorAll( 'input[name="event"][type="hidden"]' ) ).map( ( i ) => i.value ),
					motif: document.querySelectorAll( '.cic-motif--grad-cap, .cic-motif--starlight, .cic-motif--stars' ).length,
					imgs: Array.from( document.querySelectorAll( 'article img' ) ).filter( ( i ) => i.complete && 0 === i.naturalWidth ).length,
				} ) );
				if ( 1440 === width ) {
					// Yoast prints no canonical on noindex pages, and local sites are always noindex; the
					// production canonical is checked by dev/tests/qa-20261026/qa-test.php (I2).
					check( `N1 ${ key }: one H1, distinct title/description, canonical ${ url } (none while noindex)`, 1 === info.h1 && info.title.includes( 'graduation' === key ? 'Graduation Party' : 'Prom' ) && info.desc.length > 60 && ( info.canonical ? info.canonical.endsWith( url ) : info.noindex ), info );
					check( `N2 ${ key }: own composition (cic-event--${ key }) with its decorations`, info.layout.includes( 'cic-event--' + key ) && info.motif >= 1, info.layout );
					check( `N3 ${ key }: every quote action preselects event=${ key }`, info.quote.length >= 1 && info.quote.every( ( h ) => h.includes( 'event=' + key ) ) && info.fixed.length >= 1 && info.fixed.every( ( v ) => v === key ), { quote: info.quote, fixed: info.fixed } );
				}
				check( `N4 ${ key } at ${ width } px: no horizontal overflow, no broken images`, 0 === info.overflow && 0 === info.imgs, info );
				await shot( `${ key }-${ width }.png` );
			}
		}
		await go( '/events/anniversary-ice-cream-catering/' );
		check( 'N5 Anniversary page still works at /events/anniversary-ice-cream-catering/', 1 === ( await page.$$( 'h1' ) ).length && ( await page.$( '.cic-event--anniversary' ) ) !== null );

		/* ================= 3. Contact details, legal name, logo ================= */
		console.log( '== Contact and brand' );
		const tels = [];
		for ( const url of [ '/', '/about/', '/contact/', '/events/proms-ice-cream-catering/' ] ) {
			await page.setViewport( { width: 1440, height: 900 } );
			await go( url );
			tels.push( ...await page.$$eval( 'a[href^="tel:"]', ( as ) => as.map( ( a ) => a.getAttribute( 'href' ) ) ) );
		}
		check( 'C1 every phone link is tel:+14242354942', tels.length >= 6 && tels.every( ( t ) => 'tel:+14242354942' === t ), [ ...new Set( tels ) ] );
		await go( '/about/' );
		const about = await page.evaluate( () => document.body.innerText );
		check( 'C2 About introduces Alex Frost; the legal company stays Frosty Yummy LLC', about.includes( 'Say hello to Alex Frost.' ) && about.includes( 'Frosty Yummy LLC' ) && ! /Yan\b/.test( about ), null );
		await go( '/contact/' );
		const contact = await page.evaluate( () => document.body.innerText );
		check( 'C3 Contact shows "Call Alex Frost" and 424-235-4942', contact.includes( 'Call Alex Frost' ) && contact.includes( '424-235-4942' ) && ! /Yan\b/.test( contact ), null );
		const logos = {};
		for ( const width of [ 1440, 768, 390 ] ) {
			await page.setViewport( { width, height: 900 } );
			await go( '/' );
			logos[ width ] = await page.$eval( '.cic-logo img', ( i ) => Math.round( i.getBoundingClientRect().width ) );
		}
		const savedLayout = JSON.parse( last( wp( 'option', 'get', 'cic_site_layout', '--format=json' ) ) || '{}' );
		const want = { desktop: +( ( savedLayout.main || {} ).logo_width || 190 ), mobile: +( ( savedLayout.main || {} ).logo_width_mobile || 136 ) };
		check( `C4 header logo sizes follow the Header settings (${ want.desktop } desktop / ${ want.mobile } mobile)`, want.desktop === logos[ 1440 ] && want.mobile === logos[ 390 ] && want.mobile === logos[ 768 ], { logos, want } );

		/* ================= 4. Graduation quote submission ================= */
		console.log( '== Graduation quote' );
		state( 'reset-limits' );
		await page.setViewport( { width: 390, height: 900 } );
		await go( '/events/graduation-party-ice-cream-catering/' );
		await page.evaluate( () => window.sessionStorage.clear() );
		await page.type( '#cic-starter-location', '91101' );
		await page.click( '#cic-starter-date' );
		await page.keyboard.press( 'ArrowDown' );
		await page.keyboard.press( 'PageDown' );
		const qDate = await page.evaluate( () => document.activeElement.getAttribute( 'data-date' ) );
		await page.keyboard.press( 'Enter' );
		await page.type( '#cic-starter-guests', '80' );
		await Promise.all( [ page.waitForNavigation( { waitUntil: 'networkidle2' } ), page.click( '.cic-starter button[type="submit"]' ) ] );
		const pre = await page.evaluate( () => ( {
			url: location.search,
			event: ( document.querySelector( 'input[name="event"]:checked' ) || {} ).value,
			guests: document.querySelector( '#cic-f-guests' ).value,
		} ) );
		check( 'Q1 the Graduation starter opens the quote form with Graduation preselected and the guests carried', 'graduation' === pre.event && '80' === pre.guests, pre );
		// Two-step form (2026-10-23): details and favorites, Continue, then the event (prefilled).
		await page.type( '#cic-f-your-name', 'Client Update Tester' );
		await page.type( '#cic-f-your-email', who );
		await page.click( 'input[name="treats[]"] + .cic-treat__box' );
		await sleep( 3500 ); // The spam guard rejects requests sent faster than 3 s after rendering.
		await page.click( '.cic-qf__step[data-step="1"] [data-cic-next]' );
		await page.waitForSelector( '.cic-qf__step[data-step="2"]:not([hidden])', { timeout: 15000 } );
		await page.click( '.cic-button--send' );
		await page.waitForSelector( '[data-cic-received]:not([hidden])', { timeout: 30000 } );
		const heading = await page.$eval( '[data-cic-received]', ( e ) => e.innerText );
		await shot( 'graduation-confirmation-390.png' );
		await sleep( 1500 );
		const saved = JSON.parse( state( 'enquiry', who ) );
		check( 'Q2 confirmation reads "Thanks! We received your quote request."', heading.includes( 'Thanks! We received your quote request.' ), heading.slice( 0, 200 ) );
		check( 'Q3 saved once in Flamingo as a Graduation quote (event key graduation, label Graduation)', 1 === saved.count && 'graduation' === saved.context.event && 'Graduation' === saved.context.event_label && qDate === saved.context.date, saved.context );
		const op = await mails( operator );
		const cu = await mails( who );
		check( 'Q4 local mail capture: one operator notification (configured recipient) and one acknowledgement', 1 === op.length && 1 === cu.length, { op: op.length, cu: cu.length } );
		if ( cu.length && op.length ) {
			const ack = await mailpit( '/api/v1/message/' + cu[ 0 ].ID );
			const note = await mailpit( '/api/v1/message/' + op[ 0 ].ID );
			check( 'Q5 acknowledgement: American English, "Event: Graduation", signed Alex Frost (owner-edited sign-off line kept), phone 424-235-4942', 'We got your quote request' === ack.Subject && /Event: Graduation/.test( ack.Text ) && /\r?\nAlex Frost\s*\r?\n/.test( ack.Text ) && ack.Text.includes( '424-235-4942' ), { subject: ack.Subject, text: ( ack.Text || '' ).slice( 0, 500 ) } );
			check( 'Q6 acknowledgement has no Reply-To (none configured); operator mail replies to the customer', 0 === ( ack.ReplyTo || [] ).length && ( note.ReplyTo || [] ).some( ( r ) => r.Address === who ), { ack: ack.ReplyTo, note: note.ReplyTo } );
		}

		check( 'X1 no JavaScript errors during the run', 0 === errors.length, errors );
	} catch ( e ) {
		check( 'run completed without an exception', false, e.stack );
	} finally {
		if ( browser ) {
			await browser.close().catch( () => {} );
		}
		try {
			const ids = [];
			for ( const to of [ who, operator ] ) {
				( await mails( to ) ).forEach( ( m ) => ids.push( m.ID ) );
			}
			if ( ids.length ) {
				await mailpit( '/api/v1/messages', 'DELETE', JSON.stringify( { IDs: ids } ) );
			}
			console.log( last( wp( 'eval-file', 'dev/tests/phase06/state.php', 'restore', snapshot ) ) );
			fs.unlinkSync( snapshot );
		} catch ( e ) {
			console.log( 'CLEANUP ERROR ' + e.message );
		}
		const passed = results.filter( Boolean ).length;
		console.log( `\nClient update browser checks: ${ passed }/${ results.length } passed` );
	}
} )();
