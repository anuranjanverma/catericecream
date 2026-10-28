/**
 * Search dropdown stacking, homepage hero spacing and the authentic treat media (2026-10-24).
 * Read-only: it types into search fields and plays videos but submits nothing and changes no data.
 * One Edge instance, always closed.
 *
 *   NODE_PATH=<dir>/node_modules node dev/tests/media/browser-test.cjs
 */
'use strict';

const puppeteer = require( 'puppeteer-core' );
const fs = require( 'fs' );
const path = require( 'path' );

const BASE = 'https://catericecream.test';
const ROOT = path.resolve( __dirname, '../../..' );
const OUT = path.join( ROOT, 'dev/tests/output/media' );
const BROWSER = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const AXE = require.resolve( 'axe-core/axe.min.js' );
fs.mkdirSync( OUT, { recursive: true } );

const results = [];
const check = ( label, ok, detail ) => {
	results.push( !! ok );
	console.log( ( ok ? 'PASS ' : 'FAIL ' ) + label + ( ! ok && detail !== undefined ? ' — ' + JSON.stringify( detail ).slice( 0, 900 ) : '' ) );
};
const sleep = ( ms ) => new Promise( ( r ) => setTimeout( r, ms ) );
// The native player's play button (bottom-left of its control bar); clicking the picture itself
// does not reliably toggle playback in headless Chromium.
const playButton = async ( p, el ) => {
	const b = await el.boundingBox();
	await p.mouse.click( b.x + 24, b.y + b.height - 48 );
};

// Every template that uses the shared search / quote-starter location and date fields.
const SEARCHES = [
	[ 'home hero', '/', '#cic-home-search-location', '#cic-home-search-date' ],
	[ 'service areas hub', '/service-areas/', '.cic-search input[data-cic-context=location]:not([type=hidden])', '.cic-search [aria-haspopup=dialog]' ],
	[ 'city hero', '/service-areas/california/agoura-hills/', '.cic-search input[data-cic-context=location]:not([type=hidden])', '#cic-city-search-date' ],
	[ 'event starter', '/events/birthday/', '#cic-starter-location', '#cic-starter-date' ],
	[ 'treat starter (Dippin\u2019 Dots)', '/treats/dippin-dots/', '#cic-treat-quote-location', '#cic-treat-quote-date' ],
	[ 'treat starter (Frozen Yogurt)', '/treats/frozen-yogurt/', '#cic-treat-quote-location', '#cic-treat-quote-date' ],
];

// Options/days whose centre is inside the viewport and inside their own scroll box but painted
// over by something else.
const covered = ( selector ) => [ ...document.querySelectorAll( selector ) ].filter( ( it ) => {
	if ( ! it.getClientRects().length ) {
		return false;
	}
	const r = it.getBoundingClientRect();
	const cy = r.top + r.height / 2;
	if ( r.bottom > window.innerHeight || r.top < 0 ) {
		return false;
	}
	const box = it.closest( '.cic-ac-list' );
	if ( box ) {
		const b = box.getBoundingClientRect();
		if ( cy < b.top || cy > b.bottom ) {
			return false;
		}
	}
	const at = document.elementFromPoint( r.left + r.width / 2, cy );
	return ! ( at && ( at === it || it.contains( at ) ) );
} ).map( ( it ) => it.textContent.trim() || it.getAttribute( 'data-date' ) );

let browser;
( async () => {
	try {
		browser = await puppeteer.launch( { executablePath: BROWSER, headless: true, acceptInsecureCerts: true } );
		const page = await browser.newPage();
		const errors = [];
		page.on( 'pageerror', ( e ) => errors.push( e.message ) );
		const go = ( url ) => page.goto( BASE + url, { waitUntil: 'networkidle2' } );

		/* ================= 1. Search dropdowns ================= */
		const dd = {};
		for ( const w of [ 1440, 768, 390 ] ) {
			const touch = w < 600;
			await page.setViewport( { width: w, height: 900, isMobile: touch, hasTouch: touch } );
			for ( const [ name, url, loc, date ] of SEARCHES ) {
				const r = { };
				await go( url );
				if ( await page.$( loc ) ) {
					await page.$eval( loc, ( e ) => e.scrollIntoView( { block: 'center' } ) );
					await page.click( loc );
					await page.type( loc, 'San', { delay: 30 } );
					await page.waitForSelector( '.cic-ac-list:not([hidden]) [role=option]', { timeout: 8000 } ).catch( () => {} );
					r.options = ( await page.$$( '.cic-ac-list:not([hidden]) [role=option]' ) ).length;
					r.covered = await page.evaluate( covered, '.cic-ac-list:not([hidden]) [role=option]' );
					// The last visible option is the one the next section used to cover.
					const pick = await page.evaluateHandle( () => { const o = [ ...document.querySelectorAll( '.cic-ac-list:not([hidden]) [role=option]' ) ]; const box = document.querySelector( '.cic-ac-list:not([hidden])' ).getBoundingClientRect(); return o.filter( ( x ) => x.getBoundingClientRect().bottom <= box.bottom + 1 ).pop(); } );
					const want = await pick.evaluate( ( e ) => e.textContent.trim() );
					touch ? await pick.tap() : await pick.click();
					await sleep( 300 );
					r.pointer = ( await page.$eval( loc, ( e ) => e.value ) ) === want;
					await page.click( loc, { clickCount: 3 } );
					await page.keyboard.press( 'Backspace' );
					await page.type( loc, 'San', { delay: 30 } );
					await page.waitForSelector( '.cic-ac-list:not([hidden]) [role=option]', { timeout: 8000 } ).catch( () => {} );
					await page.keyboard.press( 'ArrowDown' );
					await page.keyboard.press( 'ArrowDown' );
					const active = await page.$eval( loc, ( i ) => { const id = i.getAttribute( 'aria-activedescendant' ); return id ? document.getElementById( id ).textContent.trim() : ''; } );
					await page.keyboard.press( 'Enter' );
					await sleep( 300 );
					r.keyboard = '' !== active && ( await page.$eval( loc, ( e ) => e.value ) ) === active && page.url().startsWith( BASE + url );
				}
				if ( await page.$( date ) ) {
					await go( url );
					await page.$eval( date, ( e ) => e.scrollIntoView( { block: 'center' } ) );
					await page.focus( date );
					await page.keyboard.press( 'ArrowDown' );
					await sleep( 400 );
					r.days = ( await page.$$( '.cic-dp:not([hidden]) .cic-dp__day:not([disabled])' ) ).length;
					r.daysCovered = ( await page.evaluate( covered, '.cic-dp:not([hidden]) .cic-dp__day:not([disabled])' ) ).length;
					const days = await page.$$( '.cic-dp:not([hidden]) .cic-dp__day:not([disabled])' );
					const last = days[ days.length - 1 ];
					const ymd = await last.evaluate( ( e ) => e.getAttribute( 'data-date' ) );
					touch ? await last.tap() : await last.click();
					await sleep( 300 );
					r.dayPicked = ymd === await page.evaluate( ( s ) => { const t = document.querySelector( s ); return ( document.getElementById( t.id + '-value' ) || {} ).value; }, date );
				}
				dd[ `${ name }@${ w }` ] = r;
			}
		}
		const ddBad = ( f ) => Object.entries( dd ).filter( ( [ , r ] ) => ! f( r ) ).map( ( [ k, r ] ) => [ k, r ] );
		check( 'D1 location suggestions are never covered by the next section (6 templates × 1440/768/390)', 0 === ddBad( ( r ) => r.options > 0 && 0 === r.covered.length ).length, ddBad( ( r ) => r.options > 0 && 0 === r.covered.length ) );
		check( 'D2 the lowest visible suggestion is selectable by mouse (desktop) and touch (390 px)', 0 === ddBad( ( r ) => r.pointer ).length, ddBad( ( r ) => r.pointer ) );
		check( 'D3 keyboard selection (ArrowDown + Enter) still works and stays on the page', 0 === ddBad( ( r ) => r.keyboard ).length, ddBad( ( r ) => r.keyboard ) );
		check( 'D4 date pickers are fully clickable (no covered days) and a tap/click picks the date', 0 === ddBad( ( r ) => r.days > 0 && 0 === r.daysCovered && r.dayPicked ).length, ddBad( ( r ) => r.days > 0 && 0 === r.daysCovered && r.dayPicked ) );
		// The lift applies only while a popover is open; afterwards the sections are back to normal.
		await page.setViewport( { width: 1440, height: 900 } );
		await go( '/' );
		const zClosed = await page.$eval( '.cic-home__hero', ( s ) => getComputedStyle( s ).zIndex );
		await page.click( '#cic-home-search-location' );
		await page.type( '#cic-home-search-location', 'San', { delay: 30 } );
		await page.waitForSelector( '.cic-ac-list:not([hidden]) [role=option]', { timeout: 8000 } );
		const zOpen = await page.$eval( '.cic-home__hero', ( s ) => getComputedStyle( s ).zIndex );
		const headerZ = await page.$eval( '.cic-header', ( h ) => +getComputedStyle( h ).zIndex );
		await page.keyboard.press( 'Escape' );
		await page.click( 'body' );
		await sleep( 200 );
		const zAfter = await page.$eval( '.cic-home__hero', ( s ) => getComputedStyle( s ).zIndex );
		check( 'D5 only the section with an open popover is lifted (z-index 2, below the sticky header), then returns to normal', 'auto' === zClosed && '2' === zOpen && headerZ > 2 && 'auto' === zAfter, { zClosed, zOpen, zAfter, headerZ } );

		/* ================= 2. Homepage hero ================= */
		const hero = {};
		for ( const w of [ 1440, 1280, 1100, 1024, 900, 768, 390 ] ) {
			await page.setViewport( { width: w, height: 1000 } );
			await go( '/' );
			hero[ w ] = await page.evaluate( () => {
				const h1 = document.querySelector( '.cic-home-hero__title' );
				const lead = document.querySelector( '.cic-home-hero__text .cic-lead' );
				const card = document.querySelector( '.cic-home-hero__search .cic-search' );
				const lines = Math.round( h1.getBoundingClientRect().height / parseFloat( getComputedStyle( h1 ).lineHeight ) );
				return { lines, font: Math.round( parseFloat( getComputedStyle( h1 ).fontSize ) ), leadBottom: Math.round( lead.getBoundingClientRect().bottom ), cardTop: Math.round( card.getBoundingClientRect().top ), overflow: document.documentElement.scrollWidth - window.innerWidth };
			} );
			await page.screenshot( { path: path.join( OUT, `hero-${ w }.png` ), clip: { x: 0, y: 0, width: w, height: Math.min( 1000, hero[ w ].cardTop + 200 ) } } );
		}
		check( 'H1 the intro never sits under the search card at any width (1440–390); no horizontal overflow', Object.values( hero ).every( ( h ) => h.leadBottom <= h.cardTop && h.overflow <= 0 ), hero );
		check( 'H2 desktop 1440/1280: the headline keeps its two-line shape (one row per line)', 2 === hero[ 1440 ].lines && 2 === hero[ 1280 ].lines, { 1440: hero[ 1440 ], 1280: hero[ 1280 ] } );
		check( 'H3 the headline never drops below the Design System mobile H1 size (44 px); phones and tablets keep their size', Object.values( hero ).every( ( h ) => h.font >= 44 ) && 44 === hero[ 390 ].font && hero[ 768 ].font >= 54, hero );

		/* ================= 3/4. Treat media ================= */
		const media = async ( url, w ) => {
			const p = await browser.newPage();
			const mp4 = [];
			p.on( 'request', ( r ) => { if ( /\.(mp4|webm)(\?|$)/.test( r.url() ) ) { mp4.push( r.url().split( '/' ).pop() ); } } );
			p.on( 'pageerror', ( e ) => errors.push( e.message ) );
			await p.setViewport( { width: w, height: 900 } );
			await p.goto( BASE + url, { waitUntil: 'networkidle0' } );
			await p.evaluate( async () => { for ( let y = 0; y < document.body.scrollHeight; y += 500 ) { window.scrollTo( 0, y ); await new Promise( ( r ) => setTimeout( r, 60 ) ); } } );
			await sleep( 500 );
			const info = await p.evaluate( () => ( {
				order: [ ...document.querySelectorAll( 'article.cic-treat > section' ) ].map( ( s ) => [ ...s.classList ].find( ( c ) => c.startsWith( 'cic-treat__' ) ) ),
				detail: ( ( document.querySelector( '.cic-treat__detail img' ) || {} ).currentSrc || '' ).split( '/' ).pop(),
				items: [ ...document.querySelectorAll( '.cic-media-item' ) ].map( ( li ) => {
					const v = li.querySelector( 'video' );
					const f = li.querySelector( '.cic-media-item__frame' ).getBoundingClientRect();
					const img = li.querySelector( 'img' );
					return {
						src: v ? v.querySelector( 'source' ).getAttribute( 'src' ).split( '/' ).pop() : ( img.currentSrc || img.src ).split( '/' ).pop(),
						poster: v ? v.getAttribute( 'poster' ).split( '/' ).pop() : '',
						attrs: v ? { controls: v.controls, playsinline: v.playsInline, preload: v.getAttribute( 'preload' ), autoplay: v.autoplay, muted: v.muted, loop: v.loop, w: +v.getAttribute( 'width' ), h: +v.getAttribute( 'height' ), name: v.getAttribute( 'aria-labelledby' ) && document.getElementById( v.getAttribute( 'aria-labelledby' ) ).textContent } : null,
						alt: img ? img.alt : '',
						frame: [ Math.round( f.width ), Math.round( f.height ) ],
						caption: ( li.querySelector( 'figcaption' ) || {} ).textContent || '',
						capFont: li.querySelector( 'figcaption' ) ? parseFloat( getComputedStyle( li.querySelector( 'figcaption' ) ).fontSize ) : 0,
					};
				} ),
				html: document.querySelector( 'article.cic-treat' ).innerHTML,
				overflow: document.documentElement.scrollWidth - window.innerWidth,
			} ) );
			return { p, mp4, info };
		};
		const ratio = ( a ) => a.frame[ 0 ] / a.frame[ 1 ];

		const dots = await media( '/treats/dippin-dots/', 1440 );
		const di = dots.info;
		check( 'M1 Dippin\u2019 Dots: the wedding setup photo is the large Product detail photo (replacing the AI macro)', /^dots-wedding-setup/.test( di.detail ), di.detail );
		check( 'M2 Dippin\u2019 Dots: "Photos and videos" sits after Product detail, before the quote starter (approved sections kept)', JSON.stringify( di.order ) === JSON.stringify( [ 'cic-treat__hero', 'cic-treat__feelings', 'cic-treat__occasions', 'cic-treat__themes', 'cic-treat__detail', 'cic-treat__media', 'cic-treat__quote' ] ), di.order );
		check( 'M3 Dippin\u2019 Dots media: serving video first (largest), birthday video second, posters from the footage, no banana assets', 2 === di.items.length && /^dots-serving-video/.test( di.items[ 0 ].src ) && /^dots-birthday-video/.test( di.items[ 1 ].src ) && /^dots-serving-poster/.test( di.items[ 0 ].poster ) && /^dots-birthday-poster/.test( di.items[ 1 ].poster ) && di.items[ 0 ].frame[ 0 ] > di.items[ 1 ].frame[ 0 ] && ! /bananas-/.test( di.html ), di.items );
		check( 'M4 videos: controls, playsinline, preload="none", no autoplay/muted/loop; width/height reserved; named by their caption', di.items.every( ( i ) => i.attrs && i.attrs.controls && i.attrs.playsinline && 'none' === i.attrs.preload && ! i.attrs.autoplay && ! i.attrs.muted && ! i.attrs.loop && i.attrs.w && i.attrs.h && i.attrs.name === i.caption ), di.items.map( ( i ) => i.attrs ) );
		check( 'M5 portrait orientation respected (576×1024 frames, 9:16)', di.items.every( ( i ) => 576 === i.attrs.w && 1024 === i.attrs.h && Math.abs( ratio( i ) - 0.5625 ) < 0.01 ), di.items.map( ( i ) => i.frame ) );
		check( 'M6 no video bytes are requested while loading and scrolling the page', 0 === dots.mp4.length, dots.mp4 );
		check( 'M7 factual captions: what is visible only (no names, dates, places or "our")', di.items.every( ( i ) => '' !== i.caption && ! /\b(our|we|Nikki|Christine|Nathan|20\d\d|California|Los Angeles)\b/i.test( i.caption ) ), di.items.map( ( i ) => i.caption ) );

		// Playback: a click starts it; the real (rotated) size is portrait; one video at a time.
		const p = dots.p;
		const vids = await p.$$( '[data-cic-media-video]' );
		await vids[ 0 ].evaluate( ( v ) => v.scrollIntoView( { block: 'center' } ) );
		await sleep( 300 );
		await playButton( p, vids[ 0 ] );
		await p.waitForFunction( () => { const v = document.querySelector( '[data-cic-media-video]' ); return v.currentTime > 0.6; }, { timeout: 20000 } ).catch( () => {} );
		const play1 = await vids[ 0 ].evaluate( ( v ) => ( { t: +v.currentTime.toFixed( 2 ), paused: v.paused, vw: v.videoWidth, vh: v.videoHeight, err: v.error && v.error.code } ) );
		check( 'M8 pressing play on the serving video plays it; the decoded picture is portrait 576×1024', play1.t > 0.5 && ! play1.paused && 576 === play1.vw && 1024 === play1.vh && ! play1.err, play1 );
		check( 'M9 only the played video was fetched', 1 === new Set( dots.mp4 ).size && /^dots-serving-video/.test( dots.mp4[ 0 ] || '' ), dots.mp4 );
		await vids[ 1 ].evaluate( ( v ) => v.scrollIntoView( { block: 'center' } ) );
		await sleep( 300 );
		await playButton( p, vids[ 1 ] );
		await p.waitForFunction( () => document.querySelectorAll( '[data-cic-media-video]' )[ 1 ].currentTime > 0.4, { timeout: 20000 } ).catch( () => {} );
		const both = await p.$$eval( '[data-cic-media-video]', ( vs ) => vs.map( ( v ) => ( { paused: v.paused, t: +v.currentTime.toFixed( 1 ) } ) ) );
		check( 'M10 starting the birthday video pauses the serving video (one at a time)', both[ 0 ].paused && ! both[ 1 ].paused && both[ 1 ].t > 0.3, both );
		await p.$$eval( '[data-cic-media-video]', ( vs ) => vs.forEach( ( v ) => v.pause() ) );
		const focusable = await vids[ 0 ].evaluate( ( v ) => { v.focus(); return document.activeElement === v; } );
		const tBefore = await vids[ 0 ].evaluate( ( v ) => v.currentTime );
		await p.keyboard.press( 'Space' );
		await sleep( 2000 );
		const kbd = await vids[ 0 ].evaluate( ( v ) => ( { paused: v.paused, t: v.currentTime } ) );
		await p.$$eval( '[data-cic-media-video]', ( vs ) => vs.forEach( ( v ) => v.pause() ) );
		check( 'M11 videos are keyboard-operable native players (focus + Space plays)', focusable && ! kbd.paused && kbd.t > tBefore, { focusable, kbd, tBefore } );
		await p.addScriptTag( { path: AXE } );
		const axeM = await p.evaluate( async () => ( await window.axe.run( '.cic-treat__media', { runOnly: [ 'wcag2a', 'wcag2aa', 'wcag21aa' ] } ) ).violations.map( ( v ) => v.id ) );
		check( 'M12 axe-core: no violations in the Dippin\u2019 Dots media section', 0 === axeM.length, axeM );
		await p.close();

		const ban = await media( '/treats/dipn-bananas/', 1440 );
		const bi = ban.info;
		check( 'B1 DipN\u2019 Bananas: storage photo first (not the hero), banana clip second; hero image unchanged; no Dippin\u2019 Dots media', 2 === bi.items.length && /^bananas-storage-trays/.test( bi.items[ 0 ].src ) && /^bananas-dipping-video/.test( bi.items[ 1 ].src ) && ! /dots-(serving|birthday|wedding)/.test( bi.html ) && /banana-hero/.test( bi.html.slice( 0, bi.html.indexOf( 'cic-treat__feelings' ) ) ), bi.items );
		check( 'B2 the low-resolution banana clip is a modest player: 128×224 source shown at most 1.5× (≤ 192 px wide), portrait', 128 === bi.items[ 1 ].attrs.w && 224 === bi.items[ 1 ].attrs.h && bi.items[ 1 ].frame[ 0 ] <= 192 && Math.abs( ratio( bi.items[ 1 ] ) - 128 / 224 ) < 0.01, bi.items[ 1 ] );
		check( 'B3 storage photo has descriptive alt text and a factual caption; no video bytes on load', /chocolate-covered bananas/i.test( bi.items[ 0 ].alt ) && /storage/i.test( bi.items[ 0 ].caption ) && 0 === ban.mp4.length, { alt: bi.items[ 0 ].alt, mp4: ban.mp4 } );
		const bv = await ban.p.$( '[data-cic-media-video]' );
		await bv.evaluate( ( v ) => v.scrollIntoView( { block: 'center' } ) );
		await sleep( 300 );
		await playButton( ban.p, bv );
		await ban.p.waitForFunction( () => document.querySelector( '[data-cic-media-video]' ).currentTime > 0.5, { timeout: 20000 } ).catch( () => {} );
		const bplay = await bv.evaluate( ( v ) => ( { t: +v.currentTime.toFixed( 2 ), vw: v.videoWidth, vh: v.videoHeight } ) );
		check( 'B4 the banana clip plays when play is pressed, with its rotation applied (128×224 portrait)', bplay.t > 0.4 && 128 === bplay.vw && 224 === bplay.vh, bplay );
		await ban.p.close();

		// Mobile: readable and sized to the screen.
		const mob = {};
		for ( const [ u, n ] of [ [ '/treats/dippin-dots/', 'dots' ], [ '/treats/dipn-bananas/', 'bananas' ] ] ) {
			const m = await media( u, 390 );
			mob[ n ] = { frames: m.info.items.map( ( i ) => i.frame ), cap: m.info.items.map( ( i ) => i.capFont ), overflow: m.info.overflow, mp4: m.mp4.length };
			const sec = await m.p.$( '.cic-treat__media' );
			await sec.screenshot( { path: path.join( OUT, `media-${ n }-390.png` ) } );
			await m.p.close();
		}
		check( 'R1 390 px: media fit the screen (≤ 358 px wide), captions ≥ 13 px, no overflow, no video bytes on load', Object.values( mob ).every( ( m ) => m.frames.every( ( f ) => f[ 0 ] <= 358 ) && m.cap.every( ( c ) => c >= 13 ) && m.overflow <= 0 && 0 === m.mp4 ) && mob.bananas.frames[ 1 ][ 0 ] <= 192, mob );

		// Other treat pages keep their approved compositions (no empty media section).
		const others = {};
		for ( const u of [ '/treats/classic-ice-cream/', '/treats/frozen-yogurt/' ] ) {
			await go( u );
			others[ u ] = await page.$$eval( '.cic-treat__media, .cic-media-grid', ( e ) => e.length );
		}
		check( 'R2 Classic Ice Cream and Frozen Yogurt are unchanged (no media section)', Object.values( others ).every( ( n ) => 0 === n ), others );
		check( 'X1 no JavaScript errors', 0 === errors.length, errors );
	} catch ( e ) {
		check( 'run completed without an exception', false, e.stack );
	} finally {
		if ( browser ) {
			await browser.close().catch( () => {} );
		}
		console.log( `\nSearch, hero and treat media checks: ${ results.filter( Boolean ).length }/${ results.length } passed` );
	}
} )();
