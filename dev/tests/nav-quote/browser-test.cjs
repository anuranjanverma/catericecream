/**
 * Header, quote-step and mobile-CTA updates (2026-10-24): step indicator layout and states, default
 * "Not sure yet" treat selection (page, draft and submitted values), the fixed mobile "Get a Quote"
 * button, About removed from the header menu, and the desktop phone beside the quote button.
 * Local only, synthetic @example.test addresses, mail captured by Mailpit; test inquiries, mail and
 * Enquiry settings are restored in `finally` (Phase 06 state helper). One Edge instance, always closed.
 *
 *   NODE_PATH=<dir>/node_modules node dev/tests/nav-quote/browser-test.cjs
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
const OUT = path.join( ROOT, 'dev/tests/output/nav-quote' );
const WP = 'C:\\laragon\\bin\\wp-cli\\wp.cmd';
const BROWSER = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const AXE = require.resolve( 'axe-core/axe.min.js' );
fs.mkdirSync( OUT, { recursive: true } );

const results = [];
const check = ( label, ok, detail ) => {
	results.push( !! ok );
	console.log( ( ok ? 'PASS ' : 'FAIL ' ) + label + ( ! ok && detail !== undefined ? ' — ' + JSON.stringify( detail ).slice( 0, 900 ) : '' ) );
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
		res.on( 'end', () => { try { resolve( JSON.parse( data ) ); } catch ( e ) { resolve( {} ); } } );
	} );
	req.on( 'error', () => resolve( {} ) );
	req.end( body );
} );
const mails = async ( to ) => ( ( await mailpit( '/api/v1/search?query=' + encodeURIComponent( 'to:' + to ) ) ).messages || [] );

// Saved Header settings decide the expected logo widths (owner-editable).
const LAYOUT = ( () => {
	try {
		return JSON.parse( last( wp( 'option', 'get', 'cic_site_layout', '--format=json' ) ) || '{}' );
	} catch ( e ) {
		return {};
	}
} )();
const LOGO_D = +( ( LAYOUT.main || {} ).logo_width || 190 );
const LOGO_C = +( ( LAYOUT.main || {} ).logo_width_compact || 140 );

let browser;
( async () => {
	const snapshot = path.join( OUT, 'state.json' );
	const operator = 'operator-nq@example.test';
	const who = 'nq-' + crypto.randomBytes( 3 ).toString( 'hex' ) + '@example.test';
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
				draftCalls.push( r.url().replace( BASE, '' ) );
			}
		} );
		const go = async ( p, url ) => p.goto( BASE + url, { waitUntil: 'networkidle2' } );
		const shot = ( name, full = false ) => page.screenshot( { path: path.join( OUT, name ), fullPage: full } );
		const axe = async ( p, selector ) => {
			await p.addScriptTag( { path: AXE } );
			return p.evaluate( async ( sel ) => ( await window.axe.run( sel, { runOnly: [ 'wcag2a', 'wcag2aa', 'wcag21aa', 'best-practice' ] } ) ).violations.map( ( v ) => v.id + ' (' + v.nodes.length + ')' ), selector );
		};

		/* ================= 1. Step indicator ================= */
		const steps = () => page.evaluate( () => {
			const ol = document.querySelector( '.cic-qf__progress ol' );
			const lis = ol.querySelectorAll( 'li' );
			const r = ( e ) => e.getBoundingClientRect();
			const b1 = r( lis[ 0 ].querySelector( 'button' ) );
			const b2 = r( lis[ 1 ].querySelector( 'button' ) );
			const n1 = r( lis[ 0 ].querySelector( '.cic-qf__num' ) );
			const n2 = r( lis[ 1 ].querySelector( '.cic-qf__num' ) );
			const L2 = r( lis[ 1 ] );
			const cw = parseFloat( getComputedStyle( lis[ 1 ], '::before' ).width );
			return {
				gapL: Math.round( ( L2.left - b1.right ) * 10 ) / 10,
				gapR: Math.round( ( b2.left - ( L2.left + cw ) ) * 10 ) / 10,
				connector: Math.round( cw ),
				circleY: [ n1.top + n1.height / 2, n2.top + n2.height / 2 ].map( Math.round ),
				lineY: Math.round( L2.top + L2.height / 2 ),
				endGap: Math.round( r( ol ).right - b2.right ),
				overflow: ol.scrollWidth - ol.clientWidth,
				page: document.documentElement.scrollWidth - window.innerWidth,
				clipped: Array.from( ol.querySelectorAll( '.cic-qf__steplabel' ) ).some( ( l ) => l.scrollWidth > l.clientWidth + 1 || r( l ).right > r( ol ).right + 1 ),
				font: parseFloat( getComputedStyle( ol.querySelector( '.cic-qf__steplabel' ) ).fontSize ),
				states: Array.from( lis ).map( ( li ) => ( li.classList.contains( 'is-current' ) ? 'current' : li.classList.contains( 'is-done' ) ? 'done' : 'upcoming' ) + ( 'step' === li.querySelector( 'button' ).getAttribute( 'aria-current' ) ? '[aria-current]' : '' ) + li.querySelector( '[data-cic-stepstate]' ).textContent ),
				colors: Array.from( lis ).map( ( li ) => getComputedStyle( li.querySelector( '.cic-qf__num' ) ).backgroundColor ),
			};
		} );
		const aligned = ( g ) => Math.abs( g.gapL - g.gapR ) <= 1 && g.gapL > 0 && g.gapL <= 16 && g.connector >= 24 && Math.abs( g.circleY[ 0 ] - g.circleY[ 1 ] ) <= 1 && Math.abs( g.lineY - g.circleY[ 0 ] ) <= 1 && g.endGap <= 1 && g.overflow <= 0 && g.page <= 0 && ! g.clipped;
		const byWidth = {};
		for ( const w of [ 1440, 768, 390 ] ) {
			await page.setViewport( { width: w, height: 900 } );
			await go( page, '/get-a-quote/' );
			byWidth[ w ] = await steps();
			await page.$eval( '.cic-qf__progress', ( e ) => e.scrollIntoView( { block: 'center' } ) );
			await page.screenshot( { path: path.join( OUT, `steps-step1-${ w }.png` ), clip: await page.$eval( '.cic-qf__progress', ( e ) => { const b = e.getBoundingClientRect(); return { x: 0, y: b.top + window.scrollY - 12, width: window.innerWidth, height: b.height + 24 }; } ) } );
		}
		check( 'P1 step indicator at 1440/768/390 px: [Step 1] — connector — [Step 2]; equal gaps either side of the connector; circles, labels and line on one centre line; no overflow or clipped labels', [ 1440, 768, 390 ].every( ( w ) => aligned( byWidth[ w ] ) ), byWidth );
		check( 'P2 390 px labels stay readable (≥ 13 px, wrapped not clipped)', byWidth[ 390 ].font >= 13 && ! byWidth[ 390 ].clipped, byWidth[ 390 ] );
		check( 'P3 Step 1 state: Step 1 current (aria-current="step"), Step 2 upcoming', 'current[aria-current]' === byWidth[ 1440 ].states[ 0 ] && 'upcoming' === byWidth[ 1440 ].states[ 1 ], byWidth[ 1440 ].states );

		/* ================= 2. Default treat selection ================= */
		await page.setViewport( { width: 1440, height: 900 } );
		await go( page, '/get-a-quote/' );
		const treats = () => page.evaluate( () => ( {
			unsure: document.querySelector( '[data-cic-unsure]' ).checked,
			chosen: Array.from( document.querySelectorAll( '[data-cic-treat]:checked' ) ).map( ( c ) => c.value ),
		} ) );
		const fresh = await treats();
		const look = await page.$eval( '.cic-unsure__box', ( b ) => ( { border: getComputedStyle( b ).borderTopWidth, bg: getComputedStyle( b ).backgroundColor, tick: getComputedStyle( b, '::after' ).content } ) );
		check( 'T1 fresh form: "Not sure yet" selected by default, no specific treat; clearly shown (2px border, tinted, check mark)', fresh.unsure && 0 === fresh.chosen.length && '2px' === look.border && /2713|✓/.test( look.tick ), { fresh, look } );
		const keys = await page.$$eval( '[data-cic-treat]', ( a ) => a.map( ( c ) => c.value ) );
		const tap = ( sel ) => page.click( sel + ' + span' );
		await tap( `[data-cic-treat][value="${ keys[ 0 ] }"]` );
		const t2 = await treats();
		await tap( `[data-cic-treat][value="${ keys[ 1 ] }"]` );
		const t3 = await treats();
		await tap( `[data-cic-treat][value="${ keys[ 0 ] }"]` );
		const t4 = await treats();
		await tap( `[data-cic-treat][value="${ keys[ 1 ] }"]` );
		const t5 = await treats();
		check( 'T2 choosing a treat clears "Not sure yet"; several treats can be chosen; removing the last treat restores "Not sure yet"', ! t2.unsure && 1 === t2.chosen.length && ! t3.unsure && 2 === t3.chosen.length && ! t4.unsure && 1 === t4.chosen.length && t5.unsure && 0 === t5.chosen.length, { t2, t3, t4, t5 } );
		await tap( `[data-cic-treat][value="${ keys[ 2 ] }"]` );
		await tap( `[data-cic-treat][value="${ keys[ 3 ] }"]` );
		await tap( '[data-cic-unsure]' );
		const t6 = await treats();
		await tap( '[data-cic-unsure]' );
		const t7 = await treats();
		check( 'T3 choosing "Not sure yet" clears the specific treats; it cannot be left with nothing selected', t6.unsure && 0 === t6.chosen.length && t7.unsure && 0 === t7.chosen.length, { t6, t7 } );
		await sleep( 800 );
		check( 'T4 selecting defaults and toggling treats sends nothing (no inquiry before Step 1 Continue)', 0 === draftCalls.length && 0 === records( who ).length, draftCalls );

		/* Continue with the default → the draft says "Not sure yet". */
		await page.type( '#cic-f-your-name', 'Nav Quote Tester' );
		await page.type( '#cic-f-your-email', who );
		await page.type( '#cic-f-phone', '(818) 555-0177' );
		await sleep( 3300 ); // Spam guard: Step 1 may not be sent within 3 s of rendering.
		await page.click( '[data-cic-next]' );
		await page.waitForSelector( '.cic-qf__step[data-step="2"]:not([hidden])', { timeout: 15000 } );
		let rec = records( who );
		check( 'T5 Continue with the default creates one draft whose treat value is "Not sure yet" (no specific treats)', 1 === rec.length && 'incomplete' === rec[ 0 ].stage && '1' === rec[ 0 ].unsure && 0 === rec[ 0 ].treats.length, rec );
		const s2 = await steps();
		check( 'P4 Step 2 state: Step 1 completed (announced "(completed)"), Step 2 current; three distinct state colours; layout still aligned', 'done (completed)' === s2.states[ 0 ] && 'current[aria-current]' === s2.states[ 1 ] && s2.colors[ 0 ] !== s2.colors[ 1 ] && byWidth[ 1440 ].colors[ 1 ] !== s2.colors[ 1 ] && aligned( s2 ), s2 );
		await page.$eval( '.cic-qf__progress', ( e ) => e.scrollIntoView( { block: 'center' } ) );
		await page.screenshot( { path: path.join( OUT, 'steps-step2-1440.png' ), clip: await page.$eval( '.cic-qf__progress', ( e ) => { const b = e.getBoundingClientRect(); return { x: 0, y: b.top + window.scrollY - 12, width: window.innerWidth, height: b.height + 24 }; } ) } );

		/* Back via the step indicator, choose two treats → the same draft is updated. */
		await page.click( '.cic-qf__progress li[data-step="1"] button' );
		await sleep( 300 );
		const back = await steps();
		await tap( `[data-cic-treat][value="${ keys[ 0 ] }"]` );
		await tap( `[data-cic-treat][value="${ keys[ 2 ] }"]` );
		await sleep( 1800 );
		rec = records( who );
		check( 'T6 after going back, choosing two treats autosaves them to the same draft and clears "Not sure yet" there too', 'current[aria-current]' === back.states[ 0 ] && 1 === rec.length && '' === rec[ 0 ].unsure && JSON.stringify( rec[ 0 ].treats.slice().sort() ) === JSON.stringify( [ keys[ 0 ], keys[ 2 ] ].sort() ), { back: back.states, rec } );
		await page.click( '[data-cic-next]' );
		await page.waitForSelector( '.cic-qf__step[data-step="2"]:not([hidden])', { timeout: 15000 } );

		/* Final submission: the submitted value equals the visible selection. */
		await page.click( 'input[name="event"][value="birthday"] + .cic-tile__box' );
		await page.click( '#cic-f-event-date' );
		await page.keyboard.press( 'ArrowDown' );
		await page.keyboard.press( 'PageDown' );
		await page.keyboard.press( 'Enter' );
		await page.click( '#cic-f-guests', { clickCount: 3 } );
		await page.type( '#cic-f-guests', '40' );
		await page.click( '#cic-f-location', { clickCount: 3 } );
		await page.type( '#cic-f-location', '91101' );
		await page.click( '#cic-f-venue' );
		await page.type( '#cic-f-venue', 'Test Hall' );
		await sleep( 1500 );
		await page.click( '.cic-button--send' );
		await page.waitForSelector( '[data-cic-received]:not([hidden])', { timeout: 30000 } );
		await sleep( 1500 );
		rec = records( who );
		const glance = await page.$eval( '[data-cic-glance-box] [data-g="treats"] [data-g-value]', ( e ) => e.textContent ).catch( () => '' );
		check( 'T7 the submitted inquiry (same record) has exactly the two visible treats and no "Not sure yet"; the summary lists them', 1 === rec.length && 'submitted' === rec[ 0 ].stage && '' === rec[ 0 ].unsure && 2 === rec[ 0 ].treats.length && 2 === glance.split( ',' ).length && ! /Not sure/.test( glance ), { rec, glance } );
		const cta0 = await page.$( '[data-cic-mobile-cta]' );
		check( 'C1 the Get a Quote page (form and success state) has no fixed mobile CTA', null === cta0 );

		/* Incoming preselection wins over the default. */
		const ctxB = await browser.createBrowserContext();
		const pb = await ctxB.newPage();
		await pb.setViewport( { width: 1440, height: 900 } );
		await go( pb, '/get-a-quote/?treat=' + keys[ 1 ] );
		const pre = await pb.evaluate( () => ( { unsure: document.querySelector( '[data-cic-unsure]' ).checked, chosen: Array.from( document.querySelectorAll( '[data-cic-treat]:checked' ) ).map( ( c ) => c.value ) } ) );
		check( 'T8 an incoming treat (link from a treat page) stays selected and "Not sure yet" is not added', ! pre.unsure && 1 === pre.chosen.length && keys[ 1 ] === pre.chosen[ 0 ], pre );
		await ctxB.close();
		const ctxB2 = await browser.createBrowserContext(); // Fresh session: nothing remembered.
		const pb2 = await ctxB2.newPage();
		await go( pb2, '/get-a-quote/?treat=no-such-treat' );
		const bogus = await pb2.evaluate( () => ( { unsure: document.querySelector( '[data-cic-unsure]' ).checked, chosen: document.querySelectorAll( '[data-cic-treat]:checked' ).length } ) );
		check( 'T9 an invalid incoming treat is ignored and the default applies', bogus.unsure && 0 === bogus.chosen, bogus );
		await ctxB2.close();

		/* ================= 4/5. Desktop header ================= */
		const header = () => page.evaluate( () => {
			const r = ( e ) => e.getBoundingClientRect();
			const call = document.querySelector( '.cic-header__actions .cic-header__call' );
			const quote = document.querySelector( '.cic-header__actions .cic-header__quote' );
			const nav = document.querySelector( '.cic-nav--primary' );
			const items = Array.from( document.querySelectorAll( '#cic-primary-menu > li' ) );
			const shown = call && call.offsetWidth > 0;
			return {
				items: items.map( ( li ) => li.querySelector( 'a' ).textContent.trim() ),
				about: !! document.querySelector( '.cic-header a[href$="/about/"]' ),
				shown,
				before: !! call && quote.previousElementSibling === call,
				href: call ? call.getAttribute( 'href' ) : '',
				visible: call && call.querySelector( '.cic-header__call-number' ).offsetWidth > 1 ? call.querySelector( '.cic-header__call-number' ).textContent.trim() : '',
				callW: shown ? Math.round( r( call ).width ) : 0,
				// Text lines (a Range ignores the 44 px touch padding): every menu label, the phone and the button on one line.
				wraps: Array.from( document.querySelectorAll( '#cic-primary-menu > li > a, .cic-header__call-number, .cic-header__quote' ) ).filter( ( e ) => e.offsetWidth > 1 ).some( ( e ) => { const g = document.createRange(); g.selectNodeContents( e ); const tops = Array.from( g.getClientRects() ).filter( ( x ) => x.width > 1 ).map( ( x ) => x.top ); return tops.length && Math.max( ...tops ) - Math.min( ...tops ) > 6; } ),
				name: call ? call.textContent.replace( /\s+/g, ' ' ).trim() : '',
				icon: !! ( call && call.querySelector( '.cic-header__call-icon svg[aria-hidden="true"]' ) ),
				gap: shown ? Math.round( r( quote ).left - r( call ).right ) : null,
				navGap: shown ? Math.round( r( call ).left - r( nav ).right ) : null,
				callH: shown ? Math.round( r( call ).height ) : 0,
				midDiff: shown ? Math.abs( ( r( call ).top + r( call ).height / 2 ) - ( r( quote ).top + r( quote ).height / 2 ) ) : 0,
				navRows: new Set( items.map( ( li ) => Math.round( r( li ).top ) ) ).size,
				quoteH: Math.round( r( quote ).height ),
				logo: Math.round( r( document.querySelector( '.cic-logo' ) ).width ),
				header: Math.round( r( document.querySelector( '.cic-header' ) ).height ),
				compact: document.querySelector( '.cic-header' ).classList.contains( 'is-compact' ),
				overflow: document.documentElement.scrollWidth - window.innerWidth,
				toggle: getComputedStyle( document.querySelector( '.cic-menu-toggle' ) ).display,
			};
		} );
		const desk = {};
		for ( const w of [ 1024, 1100, 1199, 1200, 1280, 1440 ] ) {
			await page.setViewport( { width: w, height: 900 } );
			await go( page, '/' );
			desk[ w ] = await header();
			await page.screenshot( { path: path.join( OUT, `header-${ w }.png` ), clip: { x: 0, y: 0, width: w, height: 160 } } );
		}
		const h = desk[ 1440 ];
		check( 'H1 About is gone from the desktop header menu; the other items keep their order', ! h.about && JSON.stringify( h.items ) === JSON.stringify( [ 'Our Treats', 'Events', 'How It Works', 'Service Areas' ] ), h.items );
		check( 'H2 desktop: phone immediately before "Get a Quote", shows 424-235-4942, links to tel:+14242354942, accessible name "Call 424-235-4942", decorative icon', h.shown && h.before && 'tel:+14242354942' === h.href && '424-235-4942' === h.visible && 'Call 424-235-4942' === h.name && h.icon, h );
		check( 'H3 1024/1100/1199/1200/1280/1440 px: one row, no text wrapping or overlap (phone ≥ 16 px from the menu, 12–24 px before the button), 44 px target, logo unchanged, no horizontal overflow', [ 1024, 1100, 1199, 1200, 1280, 1440 ].every( ( w ) => { const d = desk[ w ]; return d.shown && d.before && 1 === d.navRows && ! d.wraps && d.navGap >= 16 && d.gap >= 12 && d.gap <= 24 && d.callH >= 44 && d.callH <= 48 && d.midDiff <= 1.5 && d.logo === LOGO_D && d.overflow <= 0 && 'none' === d.toggle; } ), desk );
		check( 'H3b 1024–1199 px: the phone is a 44 px round icon link (name "Call 424-235-4942"); from 1200 px the number is shown', [ 1024, 1100, 1199 ].every( ( w ) => 44 === desk[ w ].callW && '' === desk[ w ].visible && 'Call 424-235-4942' === desk[ w ].name ) && [ 1200, 1280, 1440 ].every( ( w ) => '424-235-4942' === desk[ w ].visible ), Object.fromEntries( Object.entries( desk ).map( ( [ w, d ] ) => [ w, { callW: d.callW, visible: d.visible } ] ) ) );
		await page.setViewport( { width: 1440, height: 900 } );
		await go( page, '/' );
		await page.evaluate( () => window.scrollTo( 0, 900 ) );
		await sleep( 400 );
		await page.evaluate( () => window.scrollTo( 0, 1100 ) );
		await sleep( 700 );
		const hc = await header();
		await page.screenshot( { path: path.join( OUT, 'header-1440-compact.png' ), clip: { x: 0, y: await page.evaluate( () => window.scrollY ), width: 1440, height: 110 } } );
		await page.setViewport( { width: 1024, height: 800 } );
		await sleep( 500 );
		await page.evaluate( () => window.scrollTo( 0, 1400 ) );
		await sleep( 700 );
		const hc2 = await header();
		check( 'H4 compact sticky header (1440 and 1024 px): phone still before the button on one row, compact logo, no wrapping or overlap', hc.compact && hc.shown && hc.before && hc.logo === LOGO_C && hc.navGap >= 16 && hc.midDiff <= 1.5 && 1 === hc.navRows && ! hc.wraps && hc2.compact && hc2.shown && hc2.before && hc2.navGap >= 16 && 1 === hc2.navRows && ! hc2.wraps, { hc, hc2 } );
		await page.setViewport( { width: 1440, height: 900 } );
		await go( page, '/' );
		await page.click( '#cic-primary-menu > li.menu-item-has-children .cic-submenu-toggle' );
		await sleep( 300 );
		const dd = await page.evaluate( () => { const li = document.querySelector( '#cic-primary-menu > li.menu-item-has-children' ); const sub = li.querySelector( '.sub-menu' ); return { expanded: li.querySelector( '.cic-submenu-toggle' ).getAttribute( 'aria-expanded' ), visible: getComputedStyle( sub ).visibility !== 'hidden' && sub.getBoundingClientRect().height > 0 }; } );
		check( 'H5 dropdowns still open from the header', 'true' === dd.expanded && dd.visible, dd );
		const axeH = await axe( page, '.cic-header' );
		check( 'H6 axe-core: no violations in the desktop header', 0 === axeH.length, axeH );

		/* Tablet and phone header: unchanged behaviour (menu button, call in the drawer). */
		const mob = {};
		for ( const w of [ 768, 390 ] ) {
			await page.setViewport( { width: w, height: 844 } );
			await go( page, '/' );
			mob[ w ] = await header();
			await page.click( '[data-cic-drawer-open]' );
			await sleep( 300 );
			mob[ w ].drawer = await page.evaluate( () => ( { items: Array.from( document.querySelectorAll( '#cic-drawer-menu > li > a' ) ).map( ( a ) => a.textContent.trim() ), call: ( document.querySelector( '.cic-drawer__actions a[href^="tel:"]' ) || {} ).textContent } ) );
			await page.keyboard.press( 'Escape' );
		}
		check( 'H7 768/390 px: no phone beside the button (menu button kept); drawer without About, same order, call action kept', [ 768, 390 ].every( ( w ) => ! mob[ w ].shown && 'none' !== mob[ w ].toggle && JSON.stringify( mob[ w ].drawer.items ) === JSON.stringify( [ 'Our Treats', 'Events', 'How It Works', 'Service Areas' ] ) && /424-235-4942/.test( mob[ w ].drawer.call || '' ) ), mob );
		const about = await page.goto( BASE + '/about/', { waitUntil: 'domcontentloaded' } );
		const footerAbout = await page.$$eval( '.cic-footer a', ( a ) => a.filter( ( x ) => /\/about\/$/.test( x.href ) ).map( ( x ) => x.textContent.trim() ) );
		check( 'H8 the About page still works (200) and the footer still links to it', 200 === about.status() && footerAbout.includes( 'About' ), { status: about.status(), footerAbout } );

		/* ================= 3. Mobile fixed CTA ================= */
		await page.setViewport( { width: 390, height: 844, isMobile: true, hasTouch: true } );
		await go( page, '/' );
		const cta = () => page.evaluate( () => {
			const c = document.querySelector( '[data-cic-mobile-cta]' );
			if ( ! c ) {
				return null;
			}
			const a = c.querySelector( 'a' );
			const b = c.getBoundingClientRect();
			return {
				display: getComputedStyle( c ).display,
				shown: 'visible' === getComputedStyle( c ).visibility && ! c.classList.contains( 'is-hidden' ) && b.top < window.innerHeight,
				hidden: 'hidden' === getComputedStyle( c ).visibility,
				fixed: 'sticky' === getComputedStyle( c ).position && Math.round( b.bottom ) === window.innerHeight,
				h: Math.round( b.height ),
				top: b.top,
				href: a.href,
				text: a.textContent.trim(),
				overflow: document.documentElement.scrollWidth - window.innerWidth,
				buttonH: Math.round( a.getBoundingClientRect().height ),
			};
		} );
		const c1 = await cta();
		check( 'C2 390 px: bar pinned to the bottom of the screen with a full-width "Get a Quote" link to the Get a Quote page; ≥ 44 px target; no overflow', c1 && c1.shown && c1.fixed && /^Get a Quote/.test( c1.text ) && BASE + '/get-a-quote/' === c1.href && c1.buttonH >= 44 && c1.overflow <= 0, c1 );
		await page.screenshot( { path: path.join( OUT, 'cta-390-home.png' ) } );
		await page.evaluate( () => window.scrollTo( 0, document.documentElement.scrollHeight ) );
		await sleep( 400 );
		const bottom = await page.evaluate( () => {
			const links = Array.from( document.querySelectorAll( '.cic-footer a' ) ).filter( ( a ) => a.offsetParent );
			const lastLink = links[ links.length - 1 ];
			const bar = document.querySelector( '[data-cic-mobile-cta]' ).getBoundingClientRect();
			const covered = links.filter( ( a ) => { const r = a.getBoundingClientRect(); return r.bottom > bar.top && r.top < bar.bottom; } ).map( ( a ) => a.textContent.trim() );
			const foot = document.querySelector( '.cic-footer' ).getBoundingClientRect();
			return { lastBottom: Math.round( lastLink.getBoundingClientRect().bottom ), barTop: Math.round( bar.top ), footerBottom: Math.round( foot.bottom ), barBottom: Math.round( bar.bottom ), vh: window.innerHeight, covered };
		} );
		await page.screenshot( { path: path.join( OUT, 'cta-390-footer.png' ) } );
		check( 'C3 scrolled to the end, the bar rests below the footer (space reserved = its own height) and covers no footer link', 0 === bottom.covered.length && bottom.lastBottom <= bottom.barTop && bottom.footerBottom <= bottom.barTop + 1 && bottom.barBottom <= bottom.vh, bottom );
		const css = fs.readFileSync( path.join( ROOT, 'wp-content/themes/cater-ice-cream/assets/css/main.css' ), 'utf8' );
		check( 'C4 (static) the bottom padding of the bar includes env(safe-area-inset-bottom) for the iPhone home indicator', /padding: 0\.625rem 16px calc\(0\.625rem \+ env\(safe-area-inset-bottom/.test( css ) );

		// Keyboard: Tab from the last footer link reaches the bar's link, which is visible.
		await page.evaluate( () => { const l = Array.from( document.querySelectorAll( '.cic-footer a' ) ).filter( ( a ) => a.offsetParent ); l[ l.length - 1 ].focus(); } );
		await page.keyboard.press( 'Tab' );
		const kb = await page.evaluate( () => ( { inBar: !! document.activeElement.closest( '[data-cic-mobile-cta]' ), visible: 'visible' === getComputedStyle( document.querySelector( '[data-cic-mobile-cta]' ) ).visibility } ) );
		check( 'C5 keyboard: Tab reaches the bar link and it is visible while focused', kb.inBar && kb.visible, kb );

		// Typing hides it; leaving the field shows it again.
		await page.evaluate( () => window.scrollTo( 0, 0 ) );
		await page.focus( '#cic-home-search-location' );
		await sleep( 350 );
		const typing = await cta();
		await page.evaluate( () => document.activeElement.blur() );
		await sleep( 350 );
		const after = await cta();
		check( 'C6 hidden (and out of the tab order) while a text field has focus; back after leaving it', typing.hidden && after.shown, { typing, after } );

		// Drawer.
		await page.click( '[data-cic-drawer-open]' );
		await sleep( 350 );
		const inDrawer = await cta();
		await page.keyboard.press( 'Escape' );
		await sleep( 350 );
		const closed = await cta();
		check( 'C7 hidden while the mobile menu drawer is open; back after closing it', inDrawer.hidden && closed.shown, { inDrawer, closed } );

		// Date picker dialog (focus inside the dialog, not in a text field).
		const dateSel = await page.evaluate( () => { const d = document.querySelector( '.cic-search [aria-haspopup="dialog"]' ); return d ? '#' + d.id : ''; } );
		let picker = { skipped: true };
		if ( dateSel ) {
			await page.focus( dateSel );
			await page.keyboard.press( 'ArrowDown' );
			await sleep( 350 );
			picker = { open: await page.evaluate( () => !! document.querySelector( '.cic-dp:not([hidden])' ) && !! document.activeElement.closest( '.cic-dp' ) ), bar: await cta() };
			await page.keyboard.press( 'Escape' );
			await page.evaluate( () => document.activeElement.blur() );
			await sleep( 350 );
			picker.after = await cta();
		}
		check( 'C8 hidden while the date picker dialog is open; back after it closes', picker.open && picker.bar.hidden && picker.after.shown, picker );

		// Other pages, other widths, no JavaScript.
		const pagesShown = {};
		for ( const u of [ '/events/birthday-party-ice-cream-catering/', '/treats/dippin-dots-catering/', '/contact/', '/service-areas/' ] ) {
			await go( page, u );
			const c = await cta();
			pagesShown[ u ] = !! ( c && c.shown );
		}
		await go( page, '/get-a-quote/' );
		pagesShown[ '/get-a-quote/' ] = await page.evaluate( () => !! document.querySelector( '[data-cic-mobile-cta]' ) );
		check( 'C9 shown on event, treat, Contact and Service Areas pages; not printed on Get a Quote', Object.keys( pagesShown ).filter( ( u ) => '/get-a-quote/' !== u ).every( ( u ) => pagesShown[ u ] ) && ! pagesShown[ '/get-a-quote/' ], pagesShown );
		await go( page, '/contact/' );
		await page.focus( '#cic-f-your-name' ).catch( () => {} );
		await sleep( 350 );
		const contactTyping = await cta();
		check( 'C10 hidden while filling in the Contact form', contactTyping && contactTyping.hidden, contactTyping );
		const axeC = await ( async () => { await go( page, '/' ); return axe( page, '[data-cic-mobile-cta]' ); } )();
		check( 'C11 axe-core: no violations in the bar', 0 === axeC.length, axeC );
		const wide = {};
		for ( const w of [ 768, 1440 ] ) {
			await page.setViewport( { width: w, height: 900 } );
			await go( page, '/' );
			const c = await cta();
			wide[ w ] = { display: c.display, gapBelowFooter: await page.evaluate( () => Math.round( document.documentElement.scrollHeight - ( document.querySelector( '.cic-footer' ).getBoundingClientRect().bottom + window.scrollY ) ) ) };
		}
		check( 'C12 768 and 1440 px: no bar and nothing below the footer (desktop and tablet unchanged)', 'none' === wide[ 768 ].display && 'none' === wide[ 1440 ].display && wide[ 768 ].gapBelowFooter <= 0 && wide[ 1440 ].gapBelowFooter <= 0, wide );
		const ctxN = await browser.createBrowserContext();
		const pn = await ctxN.newPage();
		await pn.setJavaScriptEnabled( false );
		await pn.setViewport( { width: 390, height: 844 } );
		await pn.goto( BASE + '/', { waitUntil: 'domcontentloaded' } );
		const nojs = await pn.evaluate( () => { const c = document.querySelector( '[data-cic-mobile-cta]' ); return c ? { visible: 'visible' === getComputedStyle( c ).visibility && 'block' === getComputedStyle( c ).display, href: c.querySelector( 'a' ).getAttribute( 'href' ) } : null; } );
		await ctxN.close();
		check( 'C13 without JavaScript the bar is a visible, working link', nojs && nojs.visible && /\/get-a-quote\/$/.test( nojs.href ), nojs );
		check( 'C14 (static) no cookie notice or other fixed bottom bar exists on the site, so nothing can overlap the bar', ! /cookie/i.test( fs.readFileSync( path.join( ROOT, 'wp-content/themes/cater-ice-cream/footer.php' ), 'utf8' ) ) && 1 === ( css.match( /^\s*bottom: 0;/gm ) || [] ).length );

		check( 'X1 no JavaScript errors', 0 === errors.length, errors );
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
			const left = records( who ).length;
			check( 'Z1 test inquiry and mail removed; settings restored', 0 === left, left );
		} catch ( e ) {
			console.log( 'CLEANUP ERROR ' + e.message );
		}
		console.log( `\nHeader / quote / mobile CTA checks: ${ results.filter( Boolean ).length }/${ results.length } passed` );
	}
} )();
