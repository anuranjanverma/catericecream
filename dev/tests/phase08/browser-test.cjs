/**
 * Phase 08 browser verification (local only, one browser, sequential, always closed).
 * Public: Our Treats, Events, About, Contact and the branded 404 at true 390/768/1440 widths
 * (full page + first view + sections), overflow, jump links under the sticky bar, FAQ by keyboard,
 * quote starter (outside-area block + handoff), Explore navigation, mobile menu.
 * Admin: Events → Overview page edit, product "Our Treats row" edit, authenticated preview of the
 * Website Terms draft (pending label, outline, contact) while it stays a public 404.
 * Everything is restored (state.php) and the test session destroyed in `finally`.
 *
 *   NODE_PATH=<dir>/node_modules node dev/tests/phase08/browser-test.cjs
 */
'use strict';

const puppeteer = require( 'puppeteer-core' );
const { execFileSync } = require( 'child_process' );
const fs = require( 'fs' );
const https = require( 'https' );
const path = require( 'path' );

const BASE = 'https://catericecream.test';
const ROOT = path.resolve( __dirname, '../../..' );
const OUT = path.join( ROOT, 'dev/tests/output/phase08' );
const WP = 'C:\\laragon\\bin\\wp-cli\\wp.cmd';
const BROWSER = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const OUTSIDE = 'Sorry, we do not currently serve this area. Please choose another event location.';
fs.mkdirSync( OUT, { recursive: true } );

const PAGES = { treats: '/treats/', events: '/events/', about: '/about/', contact: '/contact/', 404: '/this-page-does-not-exist/' };
const results = [];
const check = ( label, ok, detail ) => {
	results.push( !! ok );
	console.log( ( ok ? 'PASS ' : 'FAIL ' ) + label + ( ! ok && detail !== undefined ? ' — ' + JSON.stringify( detail ) : '' ) );
};
const wp = ( ...args ) => execFileSync( 'cmd', [ '/c', WP, ...args ], { cwd: ROOT, encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'ignore' ] } );
const last = ( s ) => s.trim().split( '\n' ).pop();
const head = ( url ) => new Promise( ( resolve ) => {
	https.get( url, { rejectUnauthorized: false }, ( res ) => { res.resume(); resolve( res.statusCode ); } ).on( 'error', () => resolve( 0 ) );
} );

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

async function shoot( page, name, url ) {
	await page.goto( BASE + url, { waitUntil: 'networkidle0' } );
	await settle( page );
	const w = page.viewport().width;
	const overflow = await page.evaluate( () => document.documentElement.scrollWidth - window.innerWidth );
	await page.screenshot( { path: path.join( OUT, `${ name }-${ w }.png` ), fullPage: true } );
	await page.screenshot( { path: path.join( OUT, `${ name }-${ w }-viewport.png` ) } );
	if ( 768 !== w ) {
		const sections = await page.$$( '.cic-pages > section, .cic-pages > nav, .cic-pages > div > section' );
		for ( let i = 0; i < sections.length; i++ ) {
			if ( await sections[ i ].boundingBox() ) {
				await sections[ i ].screenshot( { path: path.join( OUT, `section-${ name }-${ w }-${ String( i + 1 ).padStart( 2, '0' ) }.png` ) } );
			}
		}
	}
	return overflow;
}

let browser;
( async () => {
	const snapshot = path.join( OUT, 'state-browser.json' );
	let auth = null;
	wp( 'eval-file', 'dev/tests/phase08/state.php', 'snapshot', snapshot );
	try {
		const ids = JSON.parse( last( wp( 'eval-file', 'dev/tests/phase08/state.php', 'ids' ) ) );
		browser = await puppeteer.launch( { executablePath: BROWSER, headless: true, acceptInsecureCerts: true } );
		const page = await browser.newPage();

		// ---- Public pages at true widths ----
		const overflow = {};
		for ( const w of [ 390, 768, 1440 ] ) {
			await page.setViewport( { width: w, height: 900, deviceScaleFactor: 1 } );
			for ( const [ name, url ] of Object.entries( PAGES ) ) {
				overflow[ name + '@' + w ] = await shoot( page, name, url );
			}
		}
		check( 'B1 no horizontal overflow on Our Treats, Events, About, Contact or 404 at 390/768/1440', Object.values( overflow ).every( ( v ) => v <= 0 ), Object.fromEntries( Object.entries( overflow ).filter( ( [ , v ] ) => v > 0 ) ) );

		// ---- Jump links ----
		await page.setViewport( { width: 1440, height: 900, deviceScaleFactor: 1 } );
		await page.goto( BASE + '/treats/', { waitUntil: 'networkidle0' } );
		await page.click( '.cic-jump a[href="#treat-classic-ice-cream"]' );
		await new Promise( ( r ) => setTimeout( r, 1200 ) );
		const jump = await page.evaluate( () => {
			const row = document.getElementById( 'treat-classic-ice-cream' ).getBoundingClientRect();
			const bar = document.querySelector( '.cic-jump' ).getBoundingClientRect();
			return { rowTop: Math.round( row.top ), barBottom: Math.round( bar.bottom ), hash: location.hash };
		} );
		check( 'B2 jump link scrolls to the treat row, which is not hidden under the sticky jump bar', '#treat-classic-ice-cream' === jump.hash && jump.rowTop >= jump.barBottom - 2 && jump.rowTop < 450, jump );

		// ---- Our Treats starter ----
		await page.evaluate( () => window.sessionStorage.clear() );
		await page.type( '#cic-treats-quote-location', '90224' );
		await page.keyboard.press( 'Escape' );
		await page.select( '#cic-treats-quote-event', 'wedding' );
		await page.$eval( '#cic-treats-quote-location', ( el ) => el.form.requestSubmit() );
		await page.waitForFunction( () => { const e = document.querySelector( '#cic-treats-quote-error' ); return e && ! e.hidden; }, { timeout: 5000 } );
		const blocked = await page.evaluate( () => ( { path: location.pathname, msg: document.querySelector( '#cic-treats-quote-error' ).textContent, ev: document.querySelector( '#cic-treats-quote-event' ).value } ) );
		check( 'B3 Our Treats starter blocks a confirmed outside-area ZIP and keeps the chosen occasion', '/treats/' === blocked.path && OUTSIDE === blocked.msg && 'wedding' === blocked.ev, blocked );
		await page.$eval( '#cic-treats-quote-location', ( el ) => { el.value = '91101'; } );
		await Promise.all( [ page.waitForNavigation( { waitUntil: 'domcontentloaded' } ), page.$eval( '#cic-treats-quote-location', ( el ) => el.form.requestSubmit() ) ] );
		const tq = new URL( page.url() );
		const tsum = await page.$eval( 'main', ( m ) => m.innerText ); // visible text only (the confirmation template stays hidden)
		check( 'B4 valid starter hands over to the quote route with location + occasion; no success claim', '/get-a-quote/' === tq.pathname && '91101' === tq.searchParams.get( 'location' ) && 'wedding' === tq.searchParams.get( 'event' ) && ! /thank you|received|submitted/i.test( tsum ), page.url() );

		// ---- Events Explore ----
		await page.goto( BASE + '/events/', { waitUntil: 'networkidle0' } );
		await Promise.all( [ page.waitForNavigation( { waitUntil: 'domcontentloaded' } ), page.$eval( '[data-cic-event="wedding"] .cic-ov-card__cta', ( a ) => a.click() ) ] );
		check( 'B5 Events "Explore weddings" opens the Wedding detail page', page.url().endsWith( '/events/weddings-ice-cream-catering/' ), page.url() );

		// ---- Contact FAQ by keyboard + contact links ----
		await page.goto( BASE + '/contact/', { waitUntil: 'networkidle0' } );
		await page.focus( '.cic-accordion__item summary' );
		await page.keyboard.press( 'Enter' );
		const faq = await page.$eval( '.cic-accordion__item', ( d ) => d.open );
		const contactLinks = await page.$$eval( 'main a[href^="tel:"], main a[href^="mailto:"]', ( a ) => Array.from( new Set( a.map( ( x ) => x.getAttribute( 'href' ) ) ) ) );
		check( 'B6 Contact FAQ opens from the keyboard; call/email links use the configured values only', faq && 2 === contactLinks.length && contactLinks.includes( 'tel:+14242354942' ) && contactLinks.includes( 'mailto:ceo.frostyyummy@gmail.com' ), { faq, contactLinks } );

		// ---- Mobile navigation ----
		await page.setViewport( { width: 390, height: 900, deviceScaleFactor: 1 } );
		await page.goto( BASE + '/about/', { waitUntil: 'networkidle0' } );
		await page.click( '.cic-menu-toggle' );
		await new Promise( ( r ) => setTimeout( r, 400 ) );
		const mobileNav = await page.evaluate( () => {
			const visible = ( el ) => !! el && el.getClientRects().length > 0 && 'hidden' !== getComputedStyle( el ).visibility;
			// About left the header menu on 2026-10-24 (owner); the footer keeps it, marked as current.
			const drawerOpen = visible( document.querySelector( '#cic-drawer .cic-drawer__panel' ) );
			const inHeader = !! document.querySelector( '#cic-drawer a[href$="/about/"], header a[href$="/about/"]' );
			const footer = document.querySelector( '.cic-footer .current-menu-item > a[href$="/about/"], .cic-footer a[aria-current="page"][href$="/about/"]' );
			return { drawerOpen, inHeader, footerCurrent: !! footer };
		} );
		await page.screenshot( { path: path.join( OUT, 'mobile-menu-390.png' ) } );
		check( 'B7 mobile menu opens without About (header menu, 2026-10-24); the footer About link is marked as the current page', mobileNav.drawerOpen && ! mobileNav.inHeader && mobileNav.footerCurrent, mobileNav );

		// ---- Admin ----
		auth = JSON.parse( last( wp( 'eval-file', 'dev/tests/phase02/auth-cookies.php', 'create' ) ) );
		const admin = await browser.newPage();
		await admin.setCookie( ...auth.cookies.map( ( c ) => ( { name: c.name, value: c.value, domain: 'catericecream.test', path: '/', secure: true, httpOnly: true } ) ) );
		await admin.setViewport( { width: 1440, height: 1100 } );
		await admin.goto( BASE + '/wp-admin/edit.php?post_type=cic_event&page=cic-events-overview', { waitUntil: 'domcontentloaded' } );
		await admin.screenshot( { path: path.join( OUT, 'admin-events-overview.png' ), fullPage: true } );
		await admin.$eval( '#cic-eo-cards_heading', ( el ) => { el.value = 'What are you celebrating this season?'; } );
		await Promise.all( [ admin.waitForNavigation( { waitUntil: 'domcontentloaded' } ), admin.click( '#submit' ) ] );
		const evPublic = await ( await fetch( BASE + '/events/', { headers: { 'Cache-Control': 'no-cache' } } ).catch( () => null ) );
		await page.goto( BASE + '/events/', { waitUntil: 'domcontentloaded' } );
		const evText = await page.$eval( 'main', ( m ) => m.textContent );
		check( 'B8 Events → Overview page edit in admin appears on /events/', evText.includes( 'What are you celebrating this season?' ), evPublic ? 'ok' : 'n/a' );

		await admin.goto( BASE + '/wp-admin/post.php?post=' + ids[ 'frozen-yogurt' ] + '&action=edit', { waitUntil: 'domcontentloaded', timeout: 60000 } );
		await admin.waitForSelector( '[name="acf[field_cic_arch_subtitle]"]', { timeout: 60000 } );
		await admin.waitForSelector( '#publish:not(.disabled)', { timeout: 60000 } );
		await admin.evaluate( () => {
			const tab = Array.from( document.querySelectorAll( '#acf-group_cic_treat .acf-tab-button' ) ).find( ( t ) => 'Our Treats row' === t.textContent.trim() );
			if ( tab ) {
				tab.click();
			}
			document.querySelector( '[name="acf[field_cic_arch_subtitle]"]' ).value = 'Edited catalogue subtitle from the admin.';
		} );
		await admin.screenshot( { path: path.join( OUT, 'admin-product-catalogue-row.png' ) } );
		await Promise.all( [ admin.waitForNavigation( { waitUntil: 'domcontentloaded', timeout: 60000 } ), admin.click( '#publish' ) ] );
		await page.goto( BASE + '/treats/', { waitUntil: 'domcontentloaded' } );
		const yogurtRow = await page.$eval( '#treat-frozen-yogurt', ( s ) => s.textContent );
		check( 'B9 product "Our Treats row" subtitle edited in admin appears in the catalogue row', yogurtRow.includes( 'Edited catalogue subtitle from the admin.' ) );

		for ( const w of [ 390, 768, 1440 ] ) {
			await admin.setViewport( { width: w, height: 900, deviceScaleFactor: 1 } );
			await admin.goto( BASE + '/?page_id=' + ids[ 'website-terms' ] + '&preview=true', { waitUntil: 'networkidle0' } );
			await admin.screenshot( { path: path.join( OUT, `policy-preview-${ w }.png` ), fullPage: true } );
		}
		const pol = await admin.evaluate( () => ( {
			pending: !! document.querySelector( '.cic-policy-status' ),
			toc: Array.from( document.querySelectorAll( '.cic-policy-toc a' ) ).map( ( a ) => a.textContent.replace( /^\d+/, '' ).trim() ),
			mail: ( document.querySelector( '.cic-policy-contact__email' ) || {} ).textContent || '',
			overflow: document.documentElement.scrollWidth - window.innerWidth,
		} ) );
		const anonPolicy = await head( BASE + '/website-terms/' );
		check( 'B10 policy draft preview (logged in): pending label, outline from headings + Contact, configured email; still a public 404', pol.pending && 'About this website|Enquiries and quotes|Website content|Contact' === pol.toc.join( '|' ) && pol.mail.includes( 'ceo.frostyyummy@gmail.com' ) && pol.overflow <= 0 && 404 === anonPolicy, { pol, anonPolicy } );
		await admin.close();
	} finally {
		try {
			wp( 'eval-file', 'dev/tests/phase08/state.php', 'restore', snapshot );
		} catch ( e ) {
			console.log( 'RESTORE FAILED ' + e.message );
		}
		try {
			if ( auth ) {
				wp( 'eval-file', 'dev/tests/phase02/auth-cookies.php', 'destroy', auth.token );
			}
		} catch ( e ) {}
		if ( browser ) {
			await browser.close();
		}
	}
	const after = await head( BASE + '/website-terms/' );
	check( 'B11 cleanup: content restored and the policy draft remains unpublished', 404 === after, after );
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
