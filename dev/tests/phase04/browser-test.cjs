/**
 * Phase 04 browser verification (local only, one browser, sequential, always closed).
 * Hero search handoff and context persistence, area check, navigation/anchor offset, keyboard
 * focus, tel links, global header edit across templates, homepage editing in the block editor,
 * horizontal overflow and final screenshots. Everything edited is restored in `finally`.
 *
 *   NODE_PATH=<dir>/node_modules node dev/tests/phase04/browser-test.cjs
 */
'use strict';

const puppeteer = require( 'puppeteer-core' );
const { execFileSync } = require( 'child_process' );
const fs = require( 'fs' );
const path = require( 'path' );

const BASE = 'https://catericecream.test';
const ROOT = path.resolve( __dirname, '../../..' );
const OUT = path.join( ROOT, 'dev/tests/output/phase04' );
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
const laDate = ( days ) => {
	const d = new Date( new Date().toLocaleString( 'en-US', { timeZone: 'America/Los_Angeles' } ) );
	d.setDate( d.getDate() + days );
	return d.getFullYear() + '-' + String( d.getMonth() + 1 ).padStart( 2, '0' ) + '-' + String( d.getDate() ).padStart( 2, '0' );
};

async function settle( page ) {
	await page.evaluate( async () => {
		for ( let y = 0; y < document.body.scrollHeight; y += 500 ) {
			window.scrollTo( 0, y );
			await new Promise( ( r ) => setTimeout( r, 80 ) );
		}
		window.scrollTo( 0, 0 );
		await document.fonts.ready;
		await Promise.all( Array.from( document.images ).map( ( i ) => { i.loading = 'eager'; i.decoding = 'sync'; return i.decode().catch( () => {} ); } ) );
	} );
	await new Promise( ( r ) => setTimeout( r, 300 ) );
}

let browser;
( async () => {
	const snapshot = path.join( OUT, 'state-snapshot.json' );
	wp( 'eval-file', 'dev/tests/phase04/state.php', 'snapshot', snapshot );
	const front = parseInt( last( wp( 'eval-file', 'dev/tests/phase04/state.php', 'front' ) ), 10 );
	const auth = JSON.parse( last( wp( 'eval-file', 'dev/tests/phase02/auth-cookies.php', 'create' ) ) );
	browser = await puppeteer.launch( { executablePath: BROWSER, headless: true, acceptInsecureCerts: true } );
	try {
		const ctx = await browser.createBrowserContext();
		const page = await ctx.newPage();
		page.on( 'dialog', ( d ) => d.accept() );
		const date = laDate( 30 );

		console.log( '== Hero search handoff' );
		await page.setViewport( { width: 1440, height: 900 } );
		await page.goto( BASE + '/', { waitUntil: 'networkidle0' } );
		const stored0 = await page.evaluate( () => window.sessionStorage.getItem( 'cic_quote_context' ) );
		check( 'H1 viewing the homepage stores no selection', null === stored0, stored0 );
		await page.type( '#cic-home-search-location', '<x>' );
		await page.click( '.cic-search__submit' );
		const invalid = await page.evaluate( () => ( { url: location.pathname, err: ! document.querySelector( '.cic-search__error' ).hidden, aria: document.querySelector( '#cic-home-search-location' ).getAttribute( 'aria-invalid' ), focus: document.activeElement.id } ) );
		check( 'H2 invalid location stays on the page with an announced error and focus on the field', '/' === invalid.url && invalid.err && 'true' === invalid.aria && 'cic-home-search-location' === invalid.focus, invalid );
		await page.evaluate( () => { document.querySelector( '#cic-home-search-location' ).value = ''; } );
		await page.type( '#cic-home-search-location', 'Pasadena' );
		await page.select( '#cic-home-search-event', 'anniversary' );
		// Since the 2026-10-21 refinements the date field is the MM/DD/YYYY picker (canonical value hidden).
		const mdy = ( d ) => d.slice( 5, 7 ) + '/' + d.slice( 8, 10 ) + '/' + d.slice( 0, 4 );
		await page.focus( '#cic-home-search-date' );
		await page.keyboard.type( mdy( date ) );
		await page.keyboard.press( 'Tab' );
		await Promise.all( [ page.waitForNavigation( { waitUntil: 'networkidle0' } ), page.click( '.cic-search__submit' ) ] );
		// The context parameters are read, then removed from the address bar: check the URL that was opened.
		const opened = () => page.evaluate( () => performance.getEntriesByType( 'navigation' )[ 0 ].name );
		const landed = new URL( await opened() );
		check( 'H3 Plan My Event opens /get-a-quote/ with only validated non-personal context', '/get-a-quote/' === landed.pathname && 'anniversary' === landed.searchParams.get( 'event' ) && 'pasadena-ca' === landed.searchParams.get( 'location' ) && date === landed.searchParams.get( 'date' ) && 3 === Array.from( landed.searchParams.keys() ).length, page.url() );
		// Since Phase 06 the quote route is the real form: the validated selections arrive prefilled.
		const summary = await page.evaluate( () => ( {
			event: ( document.querySelector( 'input[name=event]:checked' ) || {} ).value,
			location: ( document.querySelector( '#cic-f-location' ) || {} ).value,
			date: ( document.querySelector( '[name=event_date]' ) || {} ).value,
			shown: ( document.querySelector( '#cic-f-event-date' ) || {} ).value,
		} ) );
		check( 'H4 the quote form arrives prefilled with the server-validated selections (no availability claim)', 'anniversary' === summary.event && 'Pasadena, CA' === summary.location && date === summary.date && mdy( date ) === summary.shown && ! /available|reserved|confirmed for/i.test( await page.$eval( 'main', ( m ) => m.innerText ) ), summary );

		console.log( '== Explicit selections persist across pages' );
		await page.goto( BASE + '/treats/dippin-dots/', { waitUntil: 'networkidle0' } );
		const afterView = JSON.parse( await page.evaluate( () => window.sessionStorage.getItem( 'cic_quote_context' ) ) );
		check( 'P1 viewing a treat page does not change the stored selection', 'anniversary' === afterView.event && ! afterView.treat, afterView );
		await Promise.all( [ page.waitForNavigation( { waitUntil: 'networkidle0' } ), page.click( '.cic-treat__hero .cic-button' ) ] );
		const t = new URL( await opened() );
		check( 'P2 treat quote action adds the treat and keeps event, location and date', 'dippin-dots' === t.searchParams.get( 'treat' ) && 'anniversary' === t.searchParams.get( 'event' ) && 'pasadena-ca' === t.searchParams.get( 'location' ) && date === t.searchParams.get( 'date' ), page.url() );
		await page.goto( BASE + '/events/birthday/', { waitUntil: 'networkidle0' } );
		await Promise.all( [ page.waitForNavigation( { waitUntil: 'networkidle0' } ), page.click( '.cic-event__hero .cic-button' ) ] );
		const e = new URL( await opened() );
		check( 'P3 a newer explicit event choice wins; unrelated choices are kept', 'birthday' === e.searchParams.get( 'event' ) && 'dippin-dots' === e.searchParams.get( 'treat' ) && 'pasadena-ca' === e.searchParams.get( 'location' ), page.url() );
		await page.goto( BASE + '/', { waitUntil: 'networkidle0' } );
		const prefill = await page.evaluate( () => ( { loc: document.querySelector( '#cic-home-search-location' ).value, ev: document.querySelector( '#cic-home-search-event' ).value, date: document.querySelector( '.cic-search [name=date]' ).value, shown: document.querySelector( '#cic-home-search-date' ).value, min: document.querySelector( '.cic-search [name=date]' ).min } ) );
		check( 'P4 returning home prefills the search from the explicit selections; past dates are blocked', 'Pasadena, CA' === prefill.loc && 'birthday' === prefill.ev && date === prefill.date && mdy( date ) === prefill.shown && prefill.min === laDate( 0 ), prefill );

		console.log( '== Area check (coverage adapter pending)' );
		await page.type( '#cic-area-location', '94105' );
		await page.click( '.cic-area-check .cic-button' );
		await page.waitForFunction( () => ! document.querySelector( '.cic-area-check__result' ).hidden );
		const area = await page.$eval( '.cic-area-check__result', ( r ) => ( { status: r.getAttribute( 'data-status' ), text: r.textContent, link: ( r.querySelector( 'a' ) || {} ).href || '' } ) );
		check( 'A1 a valid ZIP without reviewed data gets an honest "confirm with your quote" answer (never "covered") and a quote link', 'unknown' === area.status && /can.t confirm/i.test( area.text ) && area.link.includes( 'location=94105' ), area );
		await page.evaluate( () => { document.querySelector( '#cic-area-location' ).value = '!!'; } );
		await page.click( '.cic-area-check .cic-button' );
		check( 'A2 malformed input asks for a ZIP or city without calling it outside the area', 'true' === await page.$eval( '#cic-area-location', ( i ) => i.getAttribute( 'aria-invalid' ) ) );

		console.log( '== Navigation, keyboard and tel links' );
		await page.goto( BASE + '/', { waitUntil: 'networkidle0' } );
		const nav = await page.$$eval( '.cic-nav--primary > ul > li > a', ( as ) => as.map( ( a ) => a.textContent.trim() ) );
		// About left the header menu on 2026-10-24 (owner); it stays in the footer.
		check( 'N1 global menu: Our Treats, Events, How It Works, Service Areas + Get a Quote', 'Our Treats,Events,How It Works,Service Areas' === nav.join( ',' ) && !! ( await page.$( '.cic-header__quote' ) ), nav );
		await page.click( '.cic-nav--primary a[href$="#how-it-works"]' );
		await new Promise( ( r ) => setTimeout( r, 800 ) );
		const anchor = await page.evaluate( () => ( { top: Math.round( document.getElementById( 'how-it-works' ).getBoundingClientRect().top ), header: Math.round( document.querySelector( '.cic-header' ).getBoundingClientRect().bottom ) } ) );
		check( 'N2 How It Works scrolls to the process section below the sticky header', anchor.top >= anchor.header && anchor.top <= anchor.header + 40, anchor );
		await page.goto( BASE + '/', { waitUntil: 'networkidle0' } );
		await page.keyboard.press( 'Tab' );
		const skip = await page.evaluate( () => ( { cls: document.activeElement.className, visible: getComputedStyle( document.activeElement ).clip !== 'rect(0px, 0px, 0px, 0px)' } ) );
		check( 'K1 first Tab reaches the visible skip link', skip.cls.includes( 'cic-skip-link' ) && skip.visible, skip );
		let reached = false;
		let ring = '';
		for ( let i = 0; i < 40 && ! reached; i++ ) {
			await page.keyboard.press( 'Tab' );
			reached = await page.evaluate( () => 'cic-home-search-location' === document.activeElement.id );
		}
		if ( reached ) {
			ring = await page.$eval( '.cic-search__field', ( f ) => getComputedStyle( f ).outlineStyle + ' ' + getComputedStyle( f ).outlineWidth );
		}
		check( 'K2 keyboard reaches the hero search; the field shows a visible focus ring', reached && ring.startsWith( 'solid 3px' ), ring );
		const tels = await page.$$eval( 'a[href^="tel:"]', ( as ) => as.map( ( a ) => a.getAttribute( 'href' ) + '|' + a.textContent.replace( /\D/g, '' ) ) );
		check( 'K3 every tel: link matches the displayed global number', tels.length >= 2 && tels.every( ( t ) => 'tel:+14242354942|4242354942' === t || 'tel:+14242354942|14242354942' === t ), tels );
		await page.setViewport( { width: 390, height: 844 } );
		await page.goto( BASE + '/', { waitUntil: 'networkidle0' } );
		await page.click( '.cic-menu-toggle' );
		const drawerOpen = await page.evaluate( () => ! document.getElementById( 'cic-drawer' ).hidden && document.activeElement.classList.contains( 'cic-drawer__close' ) );
		await page.keyboard.press( 'Escape' );
		const drawerClosed = await page.evaluate( () => document.getElementById( 'cic-drawer' ).hidden && document.activeElement.classList.contains( 'cic-menu-toggle' ) );
		check( 'K4 mobile drawer opens with focus on Close; Escape closes and returns focus', drawerOpen && drawerClosed );

		console.log( '== Global header edit reaches every template' );
		wp( 'eval-file', 'dev/tests/phase04/state.php', 'set-top-text', 'Phase 04 global header test' );
		const seen = [];
		for ( const url of [ '/', '/treats/dippin-dots/', '/events/birthday/', '/get-a-quote/', '/treats/' ] ) {
			await page.goto( BASE + url, { waitUntil: 'domcontentloaded' } );
			seen.push( ( await page.content() ).includes( 'Phase 04 global header test' ) );
		}
		check( 'G1 one top-row edit appears on home, treat, event, quote and catalogue pages', seen.every( Boolean ), seen );
		wp( 'eval-file', 'dev/tests/phase04/state.php', 'restore', snapshot );

		console.log( '== Homepage editing (block editor + ACF fields)' );
		await page.setCookie( ...auth.cookies.map( ( c ) => ( { name: c.name, value: c.value, domain: 'catericecream.test', path: '/', secure: true, httpOnly: true } ) ) );
		await page.setViewport( { width: 1440, height: 1000 } );
		await page.goto( BASE + '/wp-admin/post.php?post=' + front + '&action=edit', { waitUntil: 'domcontentloaded', timeout: 60000 } );
		await page.waitForFunction( () => window.wp && window.wp.data && window.wp.data.select( 'core/editor' ) && document.querySelector( '[name="acf[field_cic_home_eyebrow]"]' ), { timeout: 60000 } );
		await page.evaluate( () => {
			try {
				window.wp.data.dispatch( 'core/preferences' ).set( 'core/edit-post', 'welcomeGuide', false );
			} catch ( err ) {}
			const input = document.querySelector( '[name="acf[field_cic_home_eyebrow]"]' );
			input.value = 'Edited homepage eyebrow';
			input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			window.wp.data.dispatch( 'core/editor' ).editPost( { meta: {} } );
			// Return the save promise so the check below cannot see "not saving" before the save
			// (and the ACF meta-box save) has started — same fix as admin-fields-test.cjs.
			return window.wp.data.dispatch( 'core/editor' ).savePost();
		} );
		await new Promise( ( r ) => setTimeout( r, 800 ) );
		await page.waitForFunction( () => {
			const ed = window.wp.data.select( 'core/editor' );
			const mb = window.wp.data.select( 'core/edit-post' );
			return ! ed.isSavingPost() && ! ( mb.isSavingMetaBoxes && mb.isSavingMetaBoxes() );
		}, { timeout: 60000 } );
		await new Promise( ( r ) => setTimeout( r, 1500 ) );
		await page.screenshot( { path: path.join( OUT, 'admin-home-fields.png' ) } );
		const anon = await browser.createBrowserContext();
		const ap = await anon.newPage();
		await ap.goto( BASE + '/', { waitUntil: 'domcontentloaded' } );
		check( 'E1 a homepage field edited in the block editor appears on the public homepage', ( await ap.content() ).includes( 'Edited homepage eyebrow' ) );
		await anon.close();
		wp( 'eval-file', 'dev/tests/phase04/state.php', 'restore', snapshot );
		await ctx.close();

		console.log( '== Overflow and final screenshots (anonymous)' );
		const shots = await browser.createBrowserContext();
		const sp = await shots.newPage();
		for ( const w of [ 390, 768, 1440 ] ) {
			await sp.setViewport( { width: w, height: 900 } );
			await sp.goto( BASE + '/', { waitUntil: 'networkidle0' } );
			await settle( sp );
			const overflow = await sp.evaluate( () => document.documentElement.scrollWidth - window.innerWidth );
			check( `O ${ w }px homepage has no horizontal overflow`, overflow <= 0, overflow );
			await sp.screenshot( { path: path.join( OUT, `home-${ w }.png` ), fullPage: true } );
			await sp.screenshot( { path: path.join( OUT, `home-${ w }-viewport.png` ) } );
			if ( 768 !== w ) {
				// Readable section captures at the real CSS width (element screenshots, unscaled).
				for ( const section of await sp.$$( '.cic-home > section' ) ) {
					const id = await section.evaluate( ( el ) => el.id );
					await section.screenshot( { path: path.join( OUT, `section-${ w }-${ id }.png` ) } );
				}
			}
		}
		const order = await sp.$$eval( '.cic-home > section', ( s ) => s.map( ( x ) => x.id ) );
		check( 'S1 homepage sections render in the approved order', 'hero,values,treats,events,how-it-works,faq,coverage,cta' === order.join( ',' ), order );
		await shots.close();
	} finally {
		try {
			wp( 'eval-file', 'dev/tests/phase04/state.php', 'restore', snapshot );
		} catch ( err ) {
			console.log( 'RESTORE FAILED ' + err.message );
		}
		try {
			wp( 'eval-file', 'dev/tests/phase02/auth-cookies.php', 'destroy', auth.token );
		} catch ( err ) {}
		await browser.close();
	}
	const failed = results.filter( ( r ) => ! r ).length;
	console.log( '== RESULT: ' + ( results.length - failed ) + ' passed, ' + failed + ' failed' );
	process.exitCode = failed ? 1 : 0;
} )().catch( async ( err ) => {
	console.error( err );
	try {
		if ( browser ) {
			await browser.close();
		}
	} catch ( e ) {}
	process.exit( 2 );
} );
