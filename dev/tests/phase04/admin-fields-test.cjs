/**
 * Phase 04 admin editing verification (local only, one browser, sequential, always closed).
 * Through the real "Homepage composition" field panel: change a heading, an image (via the Media
 * Library modal) and a CTA label, save in the block editor, confirm on the public homepage,
 * restore the original content and confirm the restore. Captures the expanded field controls.
 *
 *   NODE_PATH=<dir>/node_modules node dev/tests/phase04/admin-fields-test.cjs
 */
'use strict';

const puppeteer = require( 'puppeteer-core' );
const { execFileSync } = require( 'child_process' );
const path = require( 'path' );

const BASE = 'https://catericecream.test';
const ROOT = path.resolve( __dirname, '../../..' );
const OUT = path.join( ROOT, 'dev/tests/output/phase04' );
const WP = 'C:\\laragon\\bin\\wp-cli\\wp.cmd';
const BROWSER = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';

const results = [];
const check = ( label, ok, detail ) => {
	results.push( !! ok );
	console.log( ( ok ? 'PASS ' : 'FAIL ' ) + label + ( ! ok && detail !== undefined ? ' — ' + JSON.stringify( detail ) : '' ) );
};
const wp = ( ...args ) => execFileSync( 'cmd', [ '/c', WP, ...args ], { cwd: ROOT, encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'ignore' ] } );
const last = ( s ) => s.trim().split( '\n' ).pop();

async function publicHome( browser ) {
	const ctx = await browser.createBrowserContext();
	const p = await ctx.newPage();
	await p.goto( BASE + '/', { waitUntil: 'domcontentloaded' } );
	const data = await p.evaluate( () => ( {
		heading: document.querySelector( '.cic-home-treats h2' ).textContent.trim(),
		image: document.querySelector( '.cic-home-hero__main img' ).getAttribute( 'src' ),
		cta: document.querySelector( '.cic-search__submit' ).textContent.trim(),
	} ) );
	await ctx.close();
	return data;
}

let browser;
( async () => {
	const snapshot = path.join( OUT, 'state-snapshot-fields.json' );
	wp( 'eval-file', 'dev/tests/phase04/state.php', 'snapshot', snapshot );
	const front = parseInt( last( wp( 'eval-file', 'dev/tests/phase04/state.php', 'front' ) ), 10 );
	const newImage = parseInt( last( wp( 'eval', 'echo CIC\\Content\\Media::id("dots-hero");' ) ), 10 );
	const auth = JSON.parse( last( wp( 'eval-file', 'dev/tests/phase02/auth-cookies.php', 'create' ) ) );
	browser = await puppeteer.launch( { executablePath: BROWSER, headless: true, acceptInsecureCerts: true } );
	try {
		const before = await publicHome( browser );
		const ctx = await browser.createBrowserContext();
		const page = await ctx.newPage();
		page.on( 'dialog', ( d ) => d.accept() );
		await page.setCookie( ...auth.cookies.map( ( c ) => ( { name: c.name, value: c.value, domain: 'catericecream.test', path: '/', secure: true, httpOnly: true } ) ) );
		await page.setViewport( { width: 1440, height: 1100 } );
		await page.goto( BASE + '/wp-admin/post.php?post=' + front + '&action=edit', { waitUntil: 'domcontentloaded', timeout: 60000 } );
		await page.waitForFunction( () => window.wp && window.wp.data && window.wp.data.select( 'core/editor' ) && document.querySelector( '#acf-group_cic_home' ), { timeout: 60000 } );
		await page.evaluate( () => {
			try {
				window.wp.data.dispatch( 'core/preferences' ).set( 'core/edit-post', 'welcomeGuide', false );
			} catch ( e ) {}
		} );
		// Expand the meta box area so the field panel is fully visible.
		await page.evaluate( () => {
			const toggle = document.querySelector( '.edit-post-meta-boxes-main__presenter button, .edit-post-layout__metaboxes button[aria-expanded="false"]' );
			if ( toggle && 'false' === toggle.getAttribute( 'aria-expanded' ) ) {
				toggle.click();
			}
			document.querySelector( '#acf-group_cic_home' ).scrollIntoView();
		} );
		await new Promise( ( r ) => setTimeout( r, 800 ) );
		const tabs = await page.$$eval( '#acf-group_cic_home .acf-tab-button', ( t ) => t.map( ( x ) => x.textContent.trim() ) );
		check( 'F1 the Homepage composition panel lists its section tabs', [ 'Hero and search', 'Value points', 'Treats', 'Events', 'How it works', 'FAQ', 'Service areas', 'Closing call to action', 'Sections' ].every( ( t ) => tabs.includes( t ) ), tabs );
		// Evidence only: let the editor's meta-box pane grow so the whole field panel can be captured.
		await page.addStyleTag( { content: '.edit-post-meta-boxes-main, .edit-post-layout__metaboxes, .interface-interface-skeleton__content { height: auto !important; max-height: none !important; overflow: visible !important; } .interface-interface-skeleton { position: static !important; } .interface-interface-skeleton__body { overflow: visible !important; }' } );
		await page.setViewport( { width: 1440, height: 2600 } );
		const box = await page.$( '#acf-group_cic_home' );
		await box.screenshot( { path: path.join( OUT, 'admin-fields-hero-tab.png' ) } );

		// 1. CTA label (Hero and search tab).
		await page.evaluate( () => {
			const i = document.querySelector( '[name="acf[field_cic_search_cta]"]' );
			i.value = 'Plan My Celebration';
			i.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		} );

		// 2. Image through the Media Library modal.
		await page.evaluate( () => {
			const field = document.querySelector( '.acf-field[data-name="cic_home_image_main"]' );
			field.querySelector( 'a[data-name="remove"]' ).click();
		} );
		await page.evaluate( () => document.querySelector( '.acf-field[data-name="cic_home_image_main"] a[data-name="add"]' ).click() );
		// The modal may open on "Upload files"; switch to the Media Library tab like a person would.
		await page.waitForSelector( '.media-modal', { visible: true, timeout: 30000 } );
		await page.evaluate( () => {
			const tab = Array.from( document.querySelectorAll( '.media-modal .media-router button, .media-modal .media-router a' ) ).find( ( b ) => /Media Library/i.test( b.textContent ) );
			if ( tab ) {
				tab.click();
			}
		} );
		await page.waitForSelector( '.media-modal .attachments .attachment', { visible: true, timeout: 30000 } );
		await page.waitForSelector( '.media-modal .attachment[data-id="' + newImage + '"]', { visible: true, timeout: 30000 } );
		await page.click( '.media-modal .attachment[data-id="' + newImage + '"]' );
		await page.screenshot( { path: path.join( OUT, 'admin-media-modal.png' ) } );
		await page.click( '.media-modal .media-button-select' );
		await page.waitForFunction( ( id ) => String( id ) === document.querySelector( '.acf-field[data-name="cic_home_image_main"] input[type=hidden]' ).value, { timeout: 15000 }, newImage );
		check( 'F2 a different photo was chosen in the Media Library modal', true );

		// 3. Heading (Treats tab).
		await page.evaluate( () => Array.from( document.querySelectorAll( '#acf-group_cic_home .acf-tab-button' ) ).find( ( t ) => 'Treats' === t.textContent.trim() ).click() );
		await page.waitForSelector( '[name="acf[field_cic_treats_heading]"]', { visible: true } );
		await page.evaluate( () => {
			const i = document.querySelector( '[name="acf[field_cic_treats_heading]"]' );
			i.value = 'Admin-edited favourites. <em>Saved from the field panel.</em>';
			i.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		} );
		await box.screenshot( { path: path.join( OUT, 'admin-fields-treats-tab.png' ) } );

		await page.evaluate( () => {
			window.wp.data.dispatch( 'core/editor' ).editPost( { meta: {} } );
			window.wp.data.dispatch( 'core/editor' ).savePost();
		} );
		await page.waitForFunction( () => {
			const ed = window.wp.data.select( 'core/editor' );
			const mb = window.wp.data.select( 'core/edit-post' );
			return ! ed.isSavingPost() && ! ( mb.isSavingMetaBoxes && mb.isSavingMetaBoxes() );
		}, { timeout: 60000 } );
		await new Promise( ( r ) => setTimeout( r, 1500 ) );
		await ctx.close();

		const after = await publicHome( browser );
		check( 'F3 heading saved through admin appears on the homepage (accent markup kept)', 'Admin-edited favourites. Saved from the field panel.' === after.heading, after.heading );
		check( 'F4 image chosen in admin replaces the hero photo on the homepage', after.image.includes( 'dots-hero' ) && ! after.image.includes( 'service-garden' ), after.image );
		check( 'F5 CTA label saved through admin appears on the homepage', after.cta.startsWith( 'Plan My Celebration' ), after.cta );

		wp( 'eval-file', 'dev/tests/phase04/state.php', 'restore', snapshot );
		const restored = await publicHome( browser );
		check( 'F6 original heading, image and CTA are back after restore', restored.heading === before.heading && restored.image === before.image && restored.cta === before.cta, { before, restored } );
	} finally {
		try {
			wp( 'eval-file', 'dev/tests/phase04/state.php', 'restore', snapshot );
		} catch ( e ) {
			console.log( 'RESTORE FAILED ' + e.message );
		}
		try {
			wp( 'eval-file', 'dev/tests/phase02/auth-cookies.php', 'destroy', auth.token );
		} catch ( e ) {}
		await browser.close();
	}
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
	process.exit( 2 );
} );
