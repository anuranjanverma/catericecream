/**
 * Phase 07 browser verification for the eight detail pages (local only, one browser,
 * sequential, always closed): true-width screenshots (390/768/1440, full + first view + sections),
 * overflow, mobile reading order, reduced motion, contextual quote actions and starters (outside-area
 * blocking, carried selections, no success claim), and global style inheritance (a Design System
 * action colour change reaches every CTA; restored in `finally`).
 *
 *   NODE_PATH=<dir>/node_modules node dev/tests/phase07/browser-test.cjs
 */
'use strict';

const puppeteer = require( 'puppeteer-core' );
const { execFileSync } = require( 'child_process' );
const fs = require( 'fs' );
const path = require( 'path' );

const BASE = 'https://catericecream.test';
const ROOT = path.resolve( __dirname, '../../..' );
const OUT = path.join( ROOT, 'dev/tests/output/phase07' );
const WP = 'C:\\laragon\\bin\\wp-cli\\wp.cmd';
const BROWSER = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const OUTSIDE = 'Sorry, we do not currently serve this area. Please choose another event location.';
fs.mkdirSync( OUT, { recursive: true } );

const PAGES = {
	birthday: '/events/birthday-party-ice-cream-catering/',
	wedding: '/events/wedding-ice-cream-catering/',
	anniversary: '/events/anniversary-ice-cream-catering/',
	corporate: '/events/corporate-event-ice-cream-catering/',
	'dippin-dots': '/treats/dippin-dots-catering/',
	'dipn-bananas': '/treats/dipn-bananas-catering/',
	'classic-ice-cream': '/treats/classic-ice-cream-catering/',
	'frozen-yogurt': '/treats/frozen-yogurt-catering/',
};

const results = [];
const check = ( label, ok, detail ) => {
	results.push( !! ok );
	console.log( ( ok ? 'PASS ' : 'FAIL ' ) + label + ( ! ok && detail !== undefined ? ' — ' + JSON.stringify( detail ) : '' ) );
};
const wp = ( ...args ) => execFileSync( 'cmd', [ '/c', WP, ...args ], { cwd: ROOT, encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'ignore' ] } );
const last = ( s ) => s.trim().split( '\n' ).pop();

async function settle( page ) {
	await page.evaluate( async () => {
		for ( let y = 0; y < document.body.scrollHeight; y += 600 ) {
			window.scrollTo( 0, y );
			await new Promise( ( r ) => setTimeout( r, 50 ) );
		}
		window.scrollTo( 0, 0 );
		await document.fonts.ready;
		await Promise.all( Array.from( document.images ).map( ( i ) => { i.loading = 'eager'; i.decoding = 'sync'; return i.decode().catch( () => {} ); } ) );
	} );
}

let browser;
( async () => {
	const snapshot = path.join( OUT, 'state-browser.json' );
	wp( 'eval-file', 'dev/tests/phase07/state.php', 'snapshot', snapshot );
	browser = await puppeteer.launch( { executablePath: BROWSER, headless: true, acceptInsecureCerts: true } );
	try {
		const page = await browser.newPage();

		// ---- Screenshots + overflow at true widths ----
		const overflow = {};
		for ( const w of [ 390, 768, 1440 ] ) {
			await page.setViewport( { width: w, height: 900, deviceScaleFactor: 1 } );
			for ( const [ name, url ] of Object.entries( PAGES ) ) {
				await page.goto( BASE + url, { waitUntil: 'networkidle0' } );
				await settle( page );
				overflow[ name + '@' + w ] = await page.evaluate( () => document.documentElement.scrollWidth - window.innerWidth );
				await page.screenshot( { path: path.join( OUT, `${ name }-${ w }.png` ), fullPage: true } );
				await page.screenshot( { path: path.join( OUT, `${ name }-${ w }-viewport.png` ) } );
				if ( 768 !== w ) {
					const ids = await page.$$eval( 'article > section[id]', ( els ) => els.map( ( e ) => e.id ) );
					for ( const id of ids ) {
						const el = await page.$( 'article > section#' + id );
						if ( el && ( await el.boundingBox() ) ) {
							await el.screenshot( { path: path.join( OUT, `section-${ name }-${ w }-${ id }.png` ) } );
						}
					}
				}
			}
		}
		check( 'B1 no horizontal overflow on any of the 8 pages at 390/768/1440', Object.values( overflow ).every( ( v ) => v <= 0 ), Object.fromEntries( Object.entries( overflow ).filter( ( [ , v ] ) => v > 0 ) ) );

		// ---- Mobile reading order ----
		await page.setViewport( { width: 390, height: 900, deviceScaleFactor: 1 } );
		const order = {};
		const top = ( sel ) => page.$eval( sel, ( el ) => el.getBoundingClientRect().top + window.scrollY );
		await page.goto( BASE + PAGES.wedding, { waitUntil: 'networkidle0' } );
		order.wedding = ( await top( '.cic-wed-hero__photo' ) ) < ( await top( '.cic-wed-hero__title' ) );
		await page.goto( BASE + PAGES[ 'classic-ice-cream' ], { waitUntil: 'networkidle0' } );
		order.classic = ( await top( '.cic-classic-hero__card' ) ) < ( await top( '.cic-classic-hero__scoops' ) );
		await page.goto( BASE + PAGES.anniversary, { waitUntil: 'networkidle0' } );
		order.anniversary = ( await top( '.cic-split-hero__title' ) ) < ( await top( '.cic-split-hero__main' ) );
		await page.goto( BASE + PAGES[ 'frozen-yogurt' ], { waitUntil: 'networkidle0' } );
		order.yogurt = ( await top( '.cic-hero__title' ) ) < ( await top( '.cic-yogurt-hero__product' ) );
		check( 'B2 mobile reading order: Wedding photo then card (21); Classic card then scoops; split heroes text then photo', Object.values( order ).every( Boolean ), order );
		const cols = await page.$$eval( '.cic-occ-cards > li', ( li ) => new Set( li.map( ( l ) => Math.round( l.getBoundingClientRect().left ) ) ).size );
		check( 'B3 mobile occasion cards stay two per row (readable, not squeezed)', 2 === cols, cols );

		// ---- Reduced motion ----
		await page.setViewport( { width: 1440, height: 900, deviceScaleFactor: 1 } );
		await page.goto( BASE + PAGES.wedding, { waitUntil: 'networkidle0' } );
		const moving = await page.$eval( '.cic-motif__trail', ( el ) => getComputedStyle( el ).animationName );
		await page.emulateMediaFeatures( [ { name: 'prefers-reduced-motion', value: 'reduce' } ] );
		await page.goto( BASE + PAGES.wedding, { waitUntil: 'networkidle0' } );
		const still = await page.$$eval( '.cic-motif', ( els ) => els.map( ( el ) => getComputedStyle( el ).animationName + '|' + getComputedStyle( el.querySelector( '.cic-motif__trail' ) || el ).animationName ) );
		await page.goto( BASE + PAGES.birthday, { waitUntil: 'networkidle0' } );
		const confetti = await page.$$eval( '.cic-confetti__piece', ( els ) => els.map( ( el ) => getComputedStyle( el ).animationName ) );
		await page.emulateMediaFeatures( [ { name: 'prefers-reduced-motion', value: 'no-preference' } ] );
		check( 'B4 Wedding line motion runs normally and stops with reduced motion (confetti also still)', 'cic-trace' === moving && still.every( ( s ) => ! /cic-/.test( s ) ) && confetti.every( ( a ) => 'none' === a ), { moving, still, confetti: confetti.slice( 0, 2 ) } );

		// ---- Quote actions ----
		await page.evaluate( () => window.sessionStorage.clear() );
		await page.goto( BASE + PAGES.wedding, { waitUntil: 'networkidle0' } );
		await Promise.all( [ page.waitForNavigation( { waitUntil: 'domcontentloaded' } ), page.click( '.cic-wed-hero .cic-button' ) ] );
		const wq = new URL( page.url() );
		const wsum = await page.evaluate( () => ( { ev: ( document.querySelector( 'input[name=event]:checked' ) || {} ).value, text: document.querySelector( 'main' ).innerText } ) );
		check( 'B5 Wedding "Plan our wedding treats" opens the quote route with the occasion selected; no success claim', '/get-a-quote/' === wq.pathname && 'wedding' === wq.searchParams.get( 'event' ) && 'wedding' === wsum.ev && ! /thank you|received|submitted/i.test( wsum.text ), page.url() );
		await page.goto( BASE + PAGES[ 'dipn-bananas' ], { waitUntil: 'networkidle0' } );
		await Promise.all( [ page.waitForNavigation( { waitUntil: 'domcontentloaded' } ), page.click( '.cic-treat__hero .cic-button' ) ] );
		const bq = new URL( page.url() );
		const stored = await page.evaluate( () => JSON.parse( window.sessionStorage.getItem( 'cic_quote_context' ) || '{}' ) );
		check( 'B6 DipN\' Bananas CTA carries the treat; the earlier explicit occasion is preserved in the context', 'dipn-bananas' === bq.searchParams.get( 'treat' ) && 'wedding' === stored.event && 'dipn-bananas' === stored.treat, { url: page.url(), stored } );

		await page.evaluate( () => window.sessionStorage.clear() );
		await page.goto( BASE + PAGES.corporate, { waitUntil: 'networkidle0' } );
		await page.type( '#cic-event-quote-location', '90224' );
		await page.keyboard.press( 'Escape' );
		await page.type( '#cic-event-quote-guests', '40' );
		await page.$eval( '#cic-event-quote-location', ( el ) => el.form.requestSubmit() );
		await page.waitForFunction( () => { const e = document.querySelector( '#cic-event-quote-error' ); return e && ! e.hidden; }, { timeout: 5000 } );
		const blocked = await page.evaluate( () => ( { path: location.pathname, msg: document.querySelector( '#cic-event-quote-error' ).textContent, guests: document.querySelector( '#cic-event-quote-guests' ).value } ) );
		check( 'B7 event starter blocks a confirmed outside-area ZIP with the exact error and keeps other answers', '/events/corporate-event-ice-cream-catering/' === blocked.path && OUTSIDE === blocked.msg && '40' === blocked.guests, blocked );
		await page.$eval( '#cic-event-quote-location', ( el ) => { el.value = '91101'; } );
		await Promise.all( [ page.waitForNavigation( { waitUntil: 'domcontentloaded' } ), page.$eval( '#cic-event-quote-location', ( el ) => el.form.requestSubmit() ) ] );
		const cq = new URL( page.url() );
		check( 'B8 event starter continues with event, location and guests only', 'corporate' === cq.searchParams.get( 'event' ) && '91101' === cq.searchParams.get( 'location' ) && '40' === cq.searchParams.get( 'guests' ) && 3 === [ ...cq.searchParams.keys() ].length, page.url() );

		await page.evaluate( () => window.sessionStorage.clear() );
		await page.goto( BASE + PAGES[ 'frozen-yogurt' ], { waitUntil: 'networkidle0' } );
		await page.type( '#cic-treat-quote-location', '93101' );
		await page.keyboard.press( 'Escape' );
		await page.select( '#cic-treat-quote-event', 'anniversary' );
		await Promise.all( [ page.waitForNavigation( { waitUntil: 'domcontentloaded' } ), page.$eval( '#cic-treat-quote-location', ( el ) => el.form.requestSubmit() ) ] );
		const yq = new URL( page.url() );
		const ysum = await page.evaluate( () => ( { ev: ( document.querySelector( 'input[name=event]:checked' ) || {} ).value, treat: !! ( document.querySelector( 'input[name="treats[]"][value=frozen-yogurt]' ) || {} ).checked, loc: ( document.querySelector( '#cic-f-location' ) || {} ).value } ) );
		check( 'B9 treat starter carries the treat with the chosen occasion and location; quote route shows them', 'frozen-yogurt' === yq.searchParams.get( 'treat' ) && 'anniversary' === yq.searchParams.get( 'event' ) && '93101' === yq.searchParams.get( 'location' ) && ysum.treat && 'anniversary' === ysum.ev && '93101' === ysum.loc, page.url() );

		await page.evaluate( () => window.sessionStorage.clear() );
		await page.goto( BASE + PAGES.birthday, { waitUntil: 'networkidle0' } );
		await page.type( '#cic-starter-location', '91106' );
		await page.keyboard.press( 'Escape' );
		await Promise.all( [ page.waitForNavigation( { waitUntil: 'domcontentloaded' } ), page.$eval( '#cic-starter-location', ( el ) => el.form.requestSubmit() ) ] );
		const bdq = new URL( page.url() );
		check( 'B10 Birthday starter strip now uses the context helper (GET, event fixed, location carried)', 'birthday' === bdq.searchParams.get( 'event' ) && '91106' === bdq.searchParams.get( 'location' ), page.url() );

		// ---- Global style inheritance ----
		const before = {};
		const readStyles = async () => {
			const out = {};
			for ( const [ name, url ] of Object.entries( PAGES ) ) {
				await page.goto( BASE + url, { waitUntil: 'domcontentloaded' } );
				out[ name ] = await page.evaluate( () => {
					const btn = document.querySelector( 'article .cic-button' );
					const h1 = document.querySelector( 'h1' );
					return { bg: getComputedStyle( btn ).backgroundColor, font: getComputedStyle( h1 ).fontFamily.split( ',' )[ 0 ].replace( /"/g, '' ) };
				} );
			}
			return out;
		};
		Object.assign( before, await readStyles() );
		const set = last( wp( 'eval-file', 'dev/tests/phase07/state.php', 'set-action-color', '#1F6FB2' ) );
		const after = await readStyles();
		check( 'B11 one Design System change (primary action colour) restyles the CTA on all 8 pages without editing them', 'ok' === set && Object.values( after ).every( ( s ) => 'rgb(31, 111, 178)' === s.bg ) && Object.values( before ).every( ( s ) => 'rgb(31, 111, 178)' !== s.bg ), { set, after } );
		check( 'B12 every page inherits the global heading family across occasion moods', Object.values( before ).every( ( s ) => 'Fraunces' === s.font ), before );
		wp( 'eval-file', 'dev/tests/phase07/state.php', 'restore', snapshot );
		const restored = await readStyles();
		check( 'B13 restoring the setting returns every CTA to the original colour', JSON.stringify( restored ) === JSON.stringify( before ), restored );
	} finally {
		try {
			wp( 'eval-file', 'dev/tests/phase07/state.php', 'restore', snapshot );
		} catch ( e ) {
			console.log( 'RESTORE FAILED ' + e.message );
		}
		await browser.close();
	}
	const failed = results.filter( ( r ) => ! r ).length;
	console.log( '== RESULT: ' + ( results.length - failed ) + ' passed, ' + failed + ' failed' );
	process.exitCode = failed ? 1 : 0;
} )().catch( async ( e ) => {
	console.error( e );
	try {
		wp( 'eval-file', 'dev/tests/phase07/state.php', 'restore', path.join( OUT, 'state-browser.json' ) );
	} catch ( x ) {}
	try {
		if ( browser ) {
			await browser.close();
		}
	} catch ( x ) {}
	process.exit( 1 );
} );
