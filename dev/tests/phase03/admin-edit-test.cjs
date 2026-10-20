/**
 * Phase 03 browser verification (local only): edit a treat in the WooCommerce product screen and
 * an event in the block editor, then confirm the public pages, and take slice screenshots.
 * Content is snapshotted first and restored in `finally`, even if a check fails.
 *
 *   NODE_PATH=<dir>/node_modules node dev/tests/phase03/admin-edit-test.cjs
 */
'use strict';

const puppeteer = require( 'puppeteer-core' );
const { execFileSync } = require( 'child_process' );
const fs = require( 'fs' );
const path = require( 'path' );

const BASE = 'https://catericecream.test';
const ROOT = path.resolve( __dirname, '../../..' );
const OUT = path.join( ROOT, 'dev/tests/output/phase03' );
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

async function scrollAll( page ) {
	await page.evaluate( async () => {
		for ( let y = 0; y < document.body.scrollHeight; y += 500 ) {
			window.scrollTo( 0, y );
			await new Promise( ( r ) => setTimeout( r, 120 ) );
		}
		window.scrollTo( 0, 0 );
		await document.fonts.ready;
	} );
	await new Promise( ( r ) => setTimeout( r, 400 ) );
}

( async () => {
	const ids = JSON.parse( last( wp( 'eval-file', 'dev/tests/phase03/state.php', 'ids' ) ) );
	const snapshot = path.join( OUT, 'state-snapshot.json' );
	wp( 'eval-file', 'dev/tests/phase03/state.php', 'snapshot', snapshot );
	const auth = JSON.parse( last( wp( 'eval-file', 'dev/tests/phase02/auth-cookies.php', 'create' ) ) );
	const browser = await puppeteer.launch( { executablePath: BROWSER, headless: true, acceptInsecureCerts: true } );
	process.on( 'exit', () => { try { browser.process() && browser.process().kill(); } catch ( e ) {} } );
	try {
		const page = await browser.newPage();
		await page.setCookie( ...auth.cookies.map( ( c ) => ( { name: c.name, value: c.value, domain: 'catericecream.test', path: '/', secure: true, httpOnly: true } ) ) );
		page.on( 'dialog', ( d ) => d.accept() );
		await page.setViewport( { width: 1440, height: 1000 } );

		console.log( '== Treat editing (WooCommerce product screen + ACF + pairings box)' );
		// WooCommerce admin keeps background requests open, so wait for the editor itself, not network idle.
		await page.goto( BASE + '/wp-admin/post.php?post=' + ids.dd + '&action=edit', { waitUntil: 'domcontentloaded', timeout: 60000 } );
		await page.waitForSelector( '[name="acf[field_cic_hero_eyebrow]"]', { timeout: 60000 } );
		await page.waitForSelector( '#publish:not(.disabled)', { timeout: 60000 } );
		const screen = await page.evaluate( () => ( {
			acf: !! document.querySelector( '[name="acf[field_cic_hero_eyebrow]"]' ),
			tabs: Array.from( document.querySelectorAll( '#acf-group_cic_treat .acf-tab-button' ) ).map( ( t ) => t.textContent.trim() ),
			pairings: document.querySelectorAll( '[data-cic-pairing]' ).length,
			identity: ( document.querySelector( '#cic-identity code' ) || {} ).textContent,
			price: ( document.querySelector( '#_regular_price' ) || {} ).value,
		} ) );
		check( 'E1 product screen shows ACF composition tabs, 4 pairing rows and the stable key', screen.acf && screen.tabs.includes( 'Occasion pairings' ) && 4 === screen.pairings && 'dippin-dots' === screen.identity && '' === screen.price, screen );
		await page.evaluate( () => document.querySelector( '#cic-pairings' ).scrollIntoView() );
		await page.screenshot( { path: path.join( OUT, 'admin-treat-pairings.png' ) } );
		await page.evaluate( () => {
			document.querySelector( '[name="acf[field_cic_hero_eyebrow]"]' ).value = 'Edited eyebrow from the admin';
			const rows = document.querySelectorAll( '[data-cic-pairing]' );
			const birthdayRow = Array.from( rows ).find( ( r ) => /Birthdays/.test( r.querySelector( 'select' ).selectedOptions[ 0 ].textContent ) );
			birthdayRow.querySelector( 'textarea[name$="[description]"]' ).value = 'Pairing text edited once in the admin.';
		} );
		await Promise.all( [ page.waitForNavigation( { waitUntil: 'domcontentloaded', timeout: 60000 } ), page.click( '#publish' ) ] );
		await page.waitForSelector( '#post', { timeout: 60000 } );
		const updated = await page.evaluate( () => !! document.querySelector( '#message.updated, .notice-success' ) );
		check( 'E2 product saved through the normal Update button', updated );
		await page.goto( BASE + '/treats/dippin-dots/', { waitUntil: 'networkidle0' } );
		const treatHtml = await page.content();
		await page.goto( BASE + '/events/birthday/', { waitUntil: 'networkidle0' } );
		const eventHtml = await page.content();
		check( 'E3 the edited ACF field shows on the treat page', treatHtml.includes( 'Edited eyebrow from the admin' ) );
		check( 'E4 the pairing text edited once appears on BOTH the treat and the event page', treatHtml.includes( 'Pairing text edited once in the admin.' ) && eventHtml.includes( 'Pairing text edited once in the admin.' ) );

		console.log( '== Event editing (block editor + ACF meta box)' );
		await page.goto( BASE + '/wp-admin/post.php?post=' + ids.birthday + '&action=edit', { waitUntil: 'domcontentloaded' } );
		await page.waitForFunction( () => window.wp && window.wp.data && window.wp.data.select( 'core/editor' ) && document.querySelector( '[name="acf[field_cic_story_heading]"]' ), { timeout: 60000 } );
		await page.evaluate( () => {
			try {
				window.wp.data.dispatch( 'core/preferences' ).set( 'core/edit-post', 'welcomeGuide', false );
			} catch ( e ) {}
			const input = document.querySelector( '[name="acf[field_cic_story_heading]"]' );
			input.value = 'Every age, <em>edited in the block editor.</em>';
			input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			window.wp.data.dispatch( 'core/editor' ).editPost( { excerpt: window.wp.data.select( 'core/editor' ).getEditedPostAttribute( 'excerpt' ) + ' ' } );
		} );
		await page.evaluate( () => window.wp.data.dispatch( 'core/editor' ).savePost() );
		await page.waitForFunction( () => {
			const ed = window.wp.data.select( 'core/editor' );
			const mb = window.wp.data.select( 'core/edit-post' );
			return ! ed.isSavingPost() && ! ( mb.isSavingMetaBoxes && mb.isSavingMetaBoxes() );
		}, { timeout: 60000 } );
		await new Promise( ( r ) => setTimeout( r, 1500 ) );
		await page.screenshot( { path: path.join( OUT, 'admin-event-block-editor.png' ) } );
		await page.goto( BASE + '/events/birthday/', { waitUntil: 'networkidle0' } );
		check( 'E5 ACF field saved from the block editor appears on the event page (accent markup kept, nothing else)', ( await page.content() ).includes( 'Every age, <em>edited in the block editor.</em>' ) );
	} finally {
		try {
			wp( 'eval-file', 'dev/tests/phase03/state.php', 'restore', snapshot );
		} catch ( e ) {
			console.log( 'RESTORE FAILED: ' + e.message );
		}
		try {
			wp( 'eval-file', 'dev/tests/phase02/auth-cookies.php', 'destroy', auth.token );
		} catch ( e ) {}
	}

	console.log( '== Slice screenshots and responsive checks (anonymous, restored content)' );
	const anon = await browser.createBrowserContext();
	const p = await anon.newPage();
	for ( const [ name, url ] of [ [ 'treat-dippin-dots', '/treats/dippin-dots/' ], [ 'event-birthday', '/events/birthday/' ], [ 'treats-list', '/treats/' ], [ 'events-list', '/events/' ] ] ) {
		for ( const w of [ 390, 768, 1440 ] ) {
			await p.setViewport( { width: w, height: 900 } );
			await p.goto( BASE + url, { waitUntil: 'networkidle0' } );
			await scrollAll( p );
			if ( ! name.endsWith( 'list' ) || 1440 === w ) {
				await p.screenshot( { path: path.join( OUT, `${ name }-${ w }.png` ), fullPage: true } );
			}
			const overflow = await p.evaluate( () => document.documentElement.scrollWidth - window.innerWidth );
			check( `R ${ url } at ${ w }px has no horizontal overflow`, overflow <= 0, overflow );
		}
	}
	await p.setViewport( { width: 1440, height: 900 } );
	await p.goto( BASE + '/treats/dippin-dots/', { waitUntil: 'networkidle0' } );
	const order = await p.$$eval( '.cic-treat > section', ( s ) => s.map( ( x ) => x.id ) );
	check( 'S1 treat sections in the approved order', 'hero,feelings,occasions,themes,detail,quote' === order.join( ',' ), order );
	const lcp = await p.$$eval( 'img[fetchpriority="high"]', ( i ) => i.length );
	check( 'S2 exactly one high-priority image per page (the hero photo)', 1 === lcp, lcp );
	await p.goto( BASE + '/events/birthday/', { waitUntil: 'networkidle0' } );
	const evOrder = await p.$$eval( '.cic-event > section', ( s ) => s.map( ( x ) => x.id ) );
	check( 'S3 Birthday sections in the approved order', 'hero,starter,treats,stories,planning,quote' === evOrder.join( ',' ), evOrder );
	const ctx = await p.evaluate( () => ( {
		body: document.body.className.includes( 'cic-context-birthday' ),
		strong: getComputedStyle( document.querySelector( '.cic-hero__title strong' ) ).color,
		balloons: Array.from( document.querySelectorAll( '.cic-balloon' ) ).every( ( b ) => 'true' === b.getAttribute( 'aria-hidden' ) ),
		form: document.querySelector( '.cic-starter' ).getAttribute( 'method' ),
		hidden: document.querySelector( '.cic-starter [name=event]' ).value,
		h1s: document.querySelectorAll( 'h1' ).length,
	} ) );
	check( 'S4 Birthday uses its preset (violet third line from the central token), decorations hidden from AT, one h1', ctx.body && 'rgb(118, 82, 184)' === ctx.strong && ctx.balloons && 1 === ctx.h1s, ctx );
	check( 'S5 quote starter carries the occasion by POST (no personal data in the URL)', 'post' === ctx.form && 'birthday' === ctx.hidden, ctx );
	await browser.close();

	const failed = results.filter( ( r ) => ! r ).length;
	console.log( '== RESULT: ' + ( results.length - failed ) + ' passed, ' + failed + ' failed' );
	process.exitCode = failed ? 1 : 0;
} )().catch( ( e ) => {
	console.error( e );
	process.exitCode = 2;
	process.exit( 2 ); // Never leave a headless browser keeping the process alive.
} );
