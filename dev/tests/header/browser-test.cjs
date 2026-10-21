/**
 * Desktop compact sticky header checks (2026-10-21). Local only; one Edge instance, sequential,
 * always closed. Header settings changed by the test are restored in `finally` (dev/tests/header/
 * state.php); the admin test session is destroyed. Screenshots: dev/tests/output/header/.
 *
 *   NODE_PATH=<dir>/node_modules node dev/tests/header/browser-test.cjs
 */
'use strict';

const puppeteer = require( 'puppeteer-core' );
const { execFileSync } = require( 'child_process' );
const fs = require( 'fs' );
const path = require( 'path' );

const BASE = 'https://catericecream.test';
const ROOT = path.resolve( __dirname, '../../..' );
const OUT = path.join( ROOT, 'dev/tests/output/header' );
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
const state = ( ...a ) => last( wp( 'eval-file', 'dev/tests/header/state.php', ...a ) );
const sleep = ( ms ) => new Promise( ( r ) => setTimeout( r, ms ) );

// Baseline geometry recorded before the change (header height, logo width x height, top-row height).
const BASE_GEO = { 1440: { header: 145, logo: [ 190, 95 ], top: 34 }, 768: { header: 118, logo: [ 136, 68 ], top: 34 }, 390: { header: 118, logo: [ 136, 68 ], top: 34 } };

let browser;
( async () => {
	const snapshot = path.join( OUT, 'layout.json' );
	let auth = null;
	state( 'snapshot', snapshot );
	try {
		browser = await puppeteer.launch( { executablePath: BROWSER, headless: true, acceptInsecureCerts: true } );
		const page = await browser.newPage();
		const errors = [];
		page.on( 'pageerror', ( e ) => errors.push( e.message ) );
		const go = ( url, p = page ) => p.goto( BASE + url, { waitUntil: 'networkidle2' } );
		const shot = ( name, p = page ) => p.screenshot( { path: path.join( OUT, name ) } );
		const geo = ( p = page ) => p.evaluate( () => {
			const h = document.querySelector( '[data-cic-header]' );
			const r = ( el ) => ( el ? el.getBoundingClientRect() : null );
			const top = h.querySelector( '.cic-header__top' );
			const main = r( h.querySelector( '.cic-header__main' ) );
			const logo = r( h.querySelector( '.cic-logo img' ) );
			const cta = h.querySelector( '.cic-header__quote' );
			const links = Array.from( h.querySelectorAll( '.cic-nav--primary > ul > li > a' ) );
			return {
				compact: h.classList.contains( 'is-compact' ),
				headerTop: Math.round( r( h ).top ),
				headerH: Math.round( h.offsetHeight ),
				topH: top ? Math.round( r( top ).height ) : null,
				topVis: top ? getComputedStyle( top ).visibility : null,
				mainBottom: Math.round( main.bottom ),
				mainH: Math.round( main.height ),
				logo: [ Math.round( logo.width ), Math.round( logo.height ) ],
				ctaOk: !! cta && r( cta ).height >= 44 && r( cta ).bottom <= main.bottom + 1 && '1' === getComputedStyle( cta ).opacity,
				linksOk: links.length > 0 && links.every( ( a ) => { const b = a.getBoundingClientRect(); return b.height >= 44 && b.top >= main.top - 1 && b.bottom <= main.bottom + 1; } ),
				inline: [ h.style.getPropertyValue( '--cic-header-full' ), h.style.getPropertyValue( '--cic-top-h' ) ].join( '|' ),
				offset: getComputedStyle( document.documentElement ).getPropertyValue( '--cic-header-offset' ).trim(),
				visible: getComputedStyle( document.documentElement ).getPropertyValue( '--cic-header-visible' ).trim(),
				sw: document.documentElement.scrollWidth,
				vw: window.innerWidth,
				y: Math.round( window.scrollY ),
			};
		} );
		const wheel = async ( dy, steps = 1, p = page ) => {
			for ( let i = 0; i < steps; i++ ) {
				await p.mouse.wheel( { deltaY: dy } );
				await sleep( 60 );
			}
			await sleep( 450 );
		};
		const atTop = async ( p = page ) => {
			await p.evaluate( () => window.scrollTo( 0, 0 ) );
			await sleep( 500 );
		};

		/* ---------------- Desktop: down, up, top ---------------- */
		console.log( '== Desktop 1440' );
		await page.setViewport( { width: 1440, height: 900 } );
		await go( '/' );
		await page.mouse.move( 700, 600 );
		let g = await geo();
		const b = BASE_GEO[ 1440 ];
		check( 'D1 at the top: unchanged full header (145 px, logo 190×95, top row 34 px), not compact', ! g.compact && b.header === g.headerH && b.logo[ 0 ] === g.logo[ 0 ] && b.logo[ 1 ] === g.logo[ 1 ] && b.top === g.topH, g );
		await shot( 'desktop-top-1440.png' );
		const anchorBefore = await page.evaluate( () => document.querySelector( 'main' ).getBoundingClientRect().top + window.scrollY );
		await wheel( 120, 6 );
		g = await geo();
		const anchorAfter = await page.evaluate( () => document.querySelector( 'main' ).getBoundingClientRect().top + window.scrollY );
		check( 'D2 scrolling down: compact; top row collapsed and hidden; main row shorter; logo 140 px; links and CTA visible and ≥44 px', g.compact && 0 === g.topH && 'hidden' === g.topVis && g.mainH < 100 && 140 === g.logo[ 0 ] && g.linksOk && g.ctaOk, g );
		check( 'D3 no content jump: the page content keeps its document position; header flow height unchanged; no horizontal overflow', Math.abs( anchorAfter - anchorBefore ) < 1 && b.header === g.headerH && g.sw === g.vw, { anchorBefore, anchorAfter, g } );
		check( 'D4 offsets: anchors keep the full height; sticky sub-navigation follows the visible (compact) height', `${ b.header }px` === g.offset && `${ g.mainBottom }px` === g.visible, g );
		await shot( 'desktop-compact-1440.png' );
		await wheel( -40, 1 );
		g = await geo();
		check( 'D5 scrolling up restores the top row and full height', ! g.compact && b.top === g.topH && 'visible' === g.topVis && 190 === g.logo[ 0 ] && g.y > 80, g );
		await wheel( 120, 4 );
		await atTop();
		g = await geo();
		check( 'D6 reaching the top always restores the full header', ! g.compact && b.header === g.headerH && b.top === g.topH, g );

		/* ---------------- Flicker ---------------- */
		await page.evaluate( () => window.scrollTo( 0, 900 ) );
		await sleep( 400 );
		await page.evaluate( () => {
			window.__cicToggles = 0;
			new MutationObserver( () => { window.__cicToggles++; } ).observe( document.querySelector( '[data-cic-header]' ), { attributes: true, attributeFilter: [ 'class' ] } );
		} );
		for ( let i = 0; i < 20; i++ ) {
			await page.mouse.wheel( { deltaY: i % 2 ? -6 : 6 } );
			await sleep( 40 );
		}
		await sleep( 400 );
		const small = await page.evaluate( () => window.__cicToggles );
		await page.evaluate( () => { window.__cicToggles = 0; } );
		for ( let i = 0; i < 8; i++ ) {
			await page.mouse.wheel( { deltaY: i % 2 ? -150 : 150 } );
			await sleep( 120 );
		}
		await sleep( 400 );
		const rapid = await page.evaluate( () => window.__cicToggles );
		check( 'F1 tiny back-and-forth scrolling (±6 px) never toggles the header', 0 === small, small );
		check( 'F2 rapid real direction changes toggle at most once per change (no oscillation)', rapid <= 8, rapid );

		/* ---------------- Dropdowns, keyboard, focus ---------------- */
		console.log( '== Dropdowns and keyboard' );
		await atTop();
		await page.evaluate( () => window.scrollTo( 0, 700 ) );
		await sleep( 300 );
		await wheel( -40, 1 ); // Expanded, mid-page.
		await page.click( '.cic-nav--primary .cic-submenu-toggle' );
		await sleep( 200 );
		await page.mouse.move( 700, 700 );
		await wheel( 120, 4 );
		g = await geo();
		const ddOpen = await page.$eval( '.cic-nav--primary .menu-item-has-children', ( li ) => li.classList.contains( 'is-open' ) );
		check( 'K1 an open dropdown keeps the header expanded while scrolling down', ddOpen && ! g.compact, { ddOpen, g } );
		await shot( 'desktop-dropdown-open-1440.png' );
		await page.keyboard.press( 'Escape' ); // Focus is on the submenu toggle: Escape closes the dropdown.
		await page.evaluate( () => document.activeElement && document.activeElement.blur() );
		await wheel( 120, 3 );
		g = await geo();
		check( 'K2 after closing the dropdown, scrolling down compacts again', g.compact, g );
		// Keyboard focus entering the header restores it and keeps it expanded.
		await page.focus( 'main' ).catch( () => {} );
		await page.evaluate( () => { const l = document.querySelector( '.cic-header__quote' ); l.setAttribute( 'data-test', '1' ); } );
		await page.keyboard.down( 'Shift' );
		let guard = 0;
		while ( guard++ < 80 && ! ( await page.evaluate( () => !! document.activeElement.closest( '[data-cic-header]' ) ) ) ) {
			await page.keyboard.press( 'Tab' );
		}
		await page.keyboard.up( 'Shift' );
		await sleep( 400 );
		g = await geo();
		const focusInfo = await page.evaluate( () => { const a = document.activeElement; const s = getComputedStyle( a ); return { tag: a.tagName, cls: a.className, visible: a.matches( ':focus-visible' ), outline: s.outlineStyle + ' ' + s.outlineWidth, shadow: s.boxShadow }; } );
		check( 'K3 keyboard focus entering the header (Shift+Tab) restores the full header', ! g.compact && focusInfo.visible, { g, focusInfo } );
		await wheel( 120, 4 );
		g = await geo();
		check( 'K4 the header stays expanded while keyboard focus is inside it', ! g.compact, g );
		check( 'K5 focus is visibly indicated in the header', /solid|auto/.test( focusInfo.outline ) && ! /0px/.test( focusInfo.outline ) || 'none' !== focusInfo.shadow, focusInfo );
		await shot( 'desktop-keyboard-focus-1440.png' );
		await page.evaluate( () => document.activeElement.blur() );

		/* ---------------- Top-row links leave the keyboard order ---------------- */
		state( 'set', 'top', 'show_phone', '1' );
		await go( '/' );
		await page.mouse.move( 700, 600 );
		await wheel( 120, 6 );
		const topLink = await page.evaluate( () => {
			const a = document.querySelector( '.cic-header__top a' );
			if ( ! a ) {
				return { present: false };
			}
			a.focus();
			return { present: true, vis: getComputedStyle( a ).visibility, focused: document.activeElement === a, compact: document.querySelector( '[data-cic-header]' ).classList.contains( 'is-compact' ) };
		} );
		check( 'A1 collapsed top-row links are hidden from keyboard focus (visibility hidden, cannot receive focus)', topLink.present && topLink.compact && 'hidden' === topLink.vis && ! topLink.focused, topLink );
		state( 'restore', snapshot );

		/* ---------------- Anchors and sticky sub-navigation ---------------- */
		console.log( '== Anchors' );
		await go( '/treats/' );
		await page.mouse.move( 700, 600 );
		const jump = await page.$$eval( '.cic-jump a[href*="#"]', ( as ) => as.map( ( a ) => a.getAttribute( 'href' ) ) );
		const target = jump[ jump.length - 1 ];
		await page.click( `.cic-jump a[href="${ target }"]` );
		await sleep( 1500 );
		const anchorState = await page.evaluate( ( hash ) => {
			const id = hash.split( '#' )[ 1 ];
			const t = document.getElementById( id );
			const h = document.querySelector( '[data-cic-header]' );
			const main = h.querySelector( '.cic-header__main' ).getBoundingClientRect();
			const jumpNav = document.querySelector( '.cic-jump' ).getBoundingClientRect();
			return { targetTop: Math.round( t.getBoundingClientRect().top ), headerBottom: Math.round( main.bottom ), jumpTop: Math.round( jumpNav.top ), jumpBottom: Math.round( jumpNav.bottom ), compact: h.classList.contains( 'is-compact' ) };
		}, target );
		check( 'N1 an anchor jump lands its target below the header and the sticky jump bar (not hidden)', anchorState.targetTop >= anchorState.jumpBottom - 1 && anchorState.targetTop >= anchorState.headerBottom, anchorState );
		check( 'N2 the sticky jump bar sits snugly under the visible header', Math.abs( anchorState.jumpTop - anchorState.headerBottom ) <= 2, anchorState );
		await shot( 'desktop-anchor-treats-1440.png' );
		// Upward anchor jump while compact: target still clear of the re-expanded header.
		await page.click( `.cic-jump a[href="${ jump[ 0 ] }"]` );
		await sleep( 1500 );
		const upState = await page.evaluate( ( hash ) => {
			const t = document.getElementById( hash.split( '#' )[ 1 ] );
			const h = document.querySelector( '[data-cic-header]' );
			return { targetTop: Math.round( t.getBoundingClientRect().top ), headerBottom: Math.round( h.querySelector( '.cic-header__main' ).getBoundingClientRect().bottom ), jumpBottom: Math.round( document.querySelector( '.cic-jump' ).getBoundingClientRect().bottom ) };
		}, jump[ 0 ] );
		check( 'N3 an upward anchor jump (header re-expanding) still keeps the target visible', upState.targetTop >= upState.headerBottom && upState.targetTop >= upState.jumpBottom - 1, upState );

		/* ---------------- Admin toolbar ---------------- */
		auth = JSON.parse( last( wp( 'eval-file', 'dev/tests/phase02/auth-cookies.php', 'create' ) ) );
		const adminCtx = await browser.createBrowserContext(); // Separate cookies: later checks stay logged out.
		const ap = await adminCtx.newPage();
		ap.on( 'pageerror', ( e ) => errors.push( e.message ) );
		await ap.setViewport( { width: 1440, height: 900 } );
		await ap.setCookie( ...auth.cookies.map( ( c ) => ( { name: c.name, value: c.value, domain: 'catericecream.test', path: '/', secure: true, httpOnly: true } ) ) );
		await go( '/', ap );
		await ap.mouse.move( 700, 600 );
		const ga = await geo( ap );
		await wheel( 120, 6, ap );
		const gb = await geo( ap );
		const bar = await ap.evaluate( () => ( document.getElementById( 'wpadminbar' ) || { offsetHeight: 0 } ).offsetHeight );
		check( 'W1 with the admin toolbar the header stays below it in both states; offsets include the toolbar', bar > 0 && bar === ga.headerTop && bar === gb.headerTop && gb.compact && `${ b.header + bar }px` === gb.offset && `${ gb.mainBottom }px` === gb.visible, { bar, ga, gb } );
		await shot( 'desktop-admin-bar-compact-1440.png', ap );
		await adminCtx.close();

		/* ---------------- Reduced motion ---------------- */
		await page.emulateMediaFeatures( [ { name: 'prefers-reduced-motion', value: 'reduce' } ] );
		await go( '/' );
		await page.mouse.move( 700, 600 );
		await page.mouse.wheel( { deltaY: 600 } );
		await sleep( 120 );
		const rm = await page.evaluate( () => {
			const h = document.querySelector( '[data-cic-header]' );
			const logo = h.querySelector( '.cic-logo' );
			return { compact: h.classList.contains( 'is-compact' ), dur: getComputedStyle( logo ).transitionDuration, w: Math.round( logo.getBoundingClientRect().width ) };
		} );
		check( 'M1 reduced motion: the header changes state instantly (no transition)', rm.compact && 140 === rm.w && /^(0s|1e-05s|0\.00001s)/.test( rm.dur ), rm );
		await page.emulateMediaFeatures( [ { name: 'prefers-reduced-motion', value: 'no-preference' } ] );

		/* ---------------- Breakpoint resize ---------------- */
		console.log( '== Resize' );
		await go( '/' );
		await page.mouse.move( 700, 600 );
		await wheel( 120, 6 );
		const before = await geo();
		await page.setViewport( { width: 900, height: 900 } );
		await sleep( 500 );
		const narrow = await geo();
		check( 'R1 narrowing below 1024 px removes the compact class and inline variables; the mobile header is unchanged (118 px, logo 136 px)', before.compact && ! narrow.compact && '|' === narrow.inline && 118 === narrow.headerH && 136 === narrow.logo[ 0 ] && 34 === narrow.topH, { before, narrow } );
		await atTop();
		await page.setViewport( { width: 1440, height: 900 } );
		await sleep( 900 );
		const wideAgain = await geo();
		check( 'R2 widening again at the top shows the full desktop header', ! wideAgain.compact && 145 === wideAgain.headerH && 190 === wideAgain.logo[ 0 ], wideAgain );

		/* ---------------- Mobile and tablet unchanged ---------------- */
		for ( const w of [ 390, 768 ] ) {
			console.log( `== ${ w }` );
			await page.setViewport( { width: w, height: 844 } );
			await go( '/' );
			const t0 = await geo();
			await page.evaluate( () => window.scrollTo( 0, 1400 ) );
			await sleep( 500 );
			const t1 = await geo();
			await page.evaluate( () => window.scrollTo( 0, 1100 ) );
			await sleep( 500 );
			const t2 = await geo();
			const bw = BASE_GEO[ w ];
			const same = ( x ) => ! x.compact && bw.header === x.headerH && bw.logo[ 0 ] === x.logo[ 0 ] && bw.logo[ 1 ] === x.logo[ 1 ] && bw.top === x.topH && '|' === x.inline && x.sw === x.vw;
			check( `U1 @${ w } header identical to the baseline at the top, scrolling down and scrolling up`, same( t0 ) && same( t1 ) && same( t2 ), { t0, t1, t2 } );
			await shot( `mobile-scrolled-${ w }.png` );
			await page.click( '[data-cic-drawer-open]' );
			await sleep( 300 );
			const opened = await page.evaluate( () => ! document.querySelector( '[data-cic-drawer]' ).hidden && document.activeElement.classList.contains( 'cic-drawer__close' ) );
			await page.keyboard.press( 'Escape' );
			await sleep( 200 );
			const closed = await page.evaluate( () => document.querySelector( '[data-cic-drawer]' ).hidden && document.activeElement.matches( '[data-cic-drawer-open]' ) );
			check( `U2 @${ w } mobile menu opens (focus in drawer) and closes with Escape (focus returns)`, opened && closed, { opened, closed } );
		}

		/* ---------------- Settings honoured ---------------- */
		console.log( '== Settings' );
		await page.setViewport( { width: 1440, height: 900 } );
		state( 'set', 'main', 'sticky', '0' );
		await go( '/' );
		await page.mouse.move( 700, 600 );
		await wheel( 120, 6 );
		const notSticky = await page.evaluate( () => { const h = document.querySelector( '[data-cic-header]' ); return { attr: h.hasAttribute( 'data-cic-compact' ), compact: h.classList.contains( 'is-compact' ), pos: getComputedStyle( h ).position, top: Math.round( h.getBoundingClientRect().top ) }; } );
		check( 'S1 sticky header disabled: normal document scrolling, no compact behaviour', ! notSticky.attr && ! notSticky.compact && 'sticky' !== notSticky.pos && notSticky.top < 0, notSticky );
		state( 'restore', snapshot );
		state( 'set', 'main', 'compact', '0' );
		await go( '/' );
		await page.mouse.move( 700, 600 );
		await wheel( 120, 6 );
		g = await geo();
		check( 'S2 compact setting off: the sticky header stays full height', ! g.compact && 145 === g.headerH && 0 === g.headerTop, g );
		state( 'restore', snapshot );
		state( 'set', 'top', 'show_desktop', '0' );
		await go( '/' );
		await page.mouse.move( 700, 600 );
		const hid0 = await geo();
		await wheel( 120, 6 );
		await wheel( -40, 1 );
		const hid1 = await geo();
		check( 'S3 a top row hidden on desktop in the admin stays hidden at the top and after restoring', 0 === hid0.topH && 0 === hid1.topH && ! hid1.compact, { hid0, hid1 } );
		state( 'restore', snapshot );

		/* ---------------- Quote page and 1024 px ---------------- */
		await page.setViewport( { width: 1024, height: 800 } );
		await go( '/get-a-quote/' );
		await page.mouse.move( 500, 500 );
		await wheel( 120, 6 );
		g = await geo();
		check( 'Q1 quote page at 1024 px: the shared header compacts, links and CTA fit, no horizontal overflow', g.compact && g.linksOk && g.ctaOk && g.sw === g.vw, g );
		await shot( 'quote-compact-1024.png' );

		check( 'X1 no JavaScript errors', 0 === errors.length, errors );
	} catch ( e ) {
		check( 'run completed without an exception', false, e.stack );
	} finally {
		if ( browser ) {
			await browser.close().catch( () => {} );
		}
		try {
			console.log( state( 'restore', snapshot ) );
			fs.unlinkSync( snapshot );
			if ( auth ) {
				wp( 'eval-file', 'dev/tests/phase02/auth-cookies.php', 'destroy', auth.token );
			}
		} catch ( e ) {
			console.log( 'CLEANUP ERROR ' + e.message );
		}
		const passed = results.filter( Boolean ).length;
		console.log( `\nHeader browser checks: ${ passed }/${ results.length } passed` );
		process.exitCode = passed === results.length ? 0 : 1;
	}
} )();
