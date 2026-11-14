/**
 * Phase 07 content-editing verification (local only, one browser, sequential, always closed).
 * Through the real admin screens: Wedding (block editor + ACF panel) ribbon and story heading;
 * Classic Ice Cream (product screen) FAQ question and theme title. Each change is confirmed on the
 * public page, then everything is restored (state.php) and the restore confirmed.
 *
 *   NODE_PATH=<dir>/node_modules node dev/tests/phase07/admin-details-test.cjs
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
fs.mkdirSync( OUT, { recursive: true } );

const results = [];
const check = ( label, ok, detail ) => {
	results.push( !! ok );
	console.log( ( ok ? 'PASS ' : 'FAIL ' ) + label + ( ! ok && detail !== undefined ? ' — ' + JSON.stringify( detail ) : '' ) );
};
const wp = ( ...args ) => execFileSync( 'cmd', [ '/c', WP, ...args ], { cwd: ROOT, encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'ignore' ] } );
const last = ( s ) => s.trim().split( '\n' ).pop();

async function publicText( browser, url ) {
	const ctx = await browser.createBrowserContext();
	const p = await ctx.newPage();
	await p.goto( BASE + url, { waitUntil: 'domcontentloaded' } );
	const html = await p.content();
	await ctx.close();
	return html;
}

let browser;
( async () => {
	const snapshot = path.join( OUT, 'state-admin.json' );
	let auth = null;
	wp( 'eval-file', 'dev/tests/phase07/state.php', 'snapshot', snapshot );
	try {
		const ids = JSON.parse( last( wp( 'eval-file', 'dev/tests/phase07/state.php', 'ids' ) ) );
		auth = JSON.parse( last( wp( 'eval-file', 'dev/tests/phase02/auth-cookies.php', 'create' ) ) );
		browser = await puppeteer.launch( { executablePath: BROWSER, headless: true, acceptInsecureCerts: true } );
		const page = await browser.newPage();
		await page.setCookie( ...auth.cookies.map( ( c ) => ( { name: c.name, value: c.value, domain: 'catericecream.test', path: '/', secure: true, httpOnly: true } ) ) );
		await page.setViewport( { width: 1440, height: 1100 } );

		// Wedding: block editor + ACF panel.
		await page.goto( BASE + '/wp-admin/post.php?post=' + ids.wedding + '&action=edit', { waitUntil: 'domcontentloaded', timeout: 60000 } );
		await page.waitForFunction( () => window.wp && window.wp.data && window.wp.data.select( 'core/editor' ) && document.querySelector( '[name="acf[field_cic_ev_ribbon]"]' ), { timeout: 60000 } );
		const panel = await page.evaluate( () => ( {
			layout: document.querySelector( '[name="acf[field_cic_layout]"]' ).value,
			tabs: Array.from( document.querySelectorAll( '#acf-group_cic_event .acf-tab-button' ) ).map( ( t ) => t.textContent.trim() ),
		} ) );
		check( 'A1 Wedding editor shows its composition and the new tabs (ribbon/values, story with photo, wide photo band)', 'wedding' === panel.layout && panel.tabs.includes( 'Ribbon and values' ) && panel.tabs.includes( 'Story with photo' ) && panel.tabs.includes( 'Wide photo band' ), panel );
		await page.evaluate( () => {
			try {
				window.wp.data.dispatch( 'core/preferences' ).set( 'core/edit-post', 'welcomeGuide', false );
			} catch ( e ) {}
			const toggle = document.querySelector( '.edit-post-meta-boxes-main__presenter button, .edit-post-layout__metaboxes button[aria-expanded="false"]' );
			if ( toggle && 'false' === toggle.getAttribute( 'aria-expanded' ) ) {
				toggle.click();
			}
			const tab = Array.from( document.querySelectorAll( '#acf-group_cic_event .acf-tab-button' ) ).find( ( t ) => 'Story with photo' === t.textContent.trim() );
			if ( tab ) {
				tab.click();
			}
			document.querySelector( '#acf-group_cic_event' ).scrollIntoView();
		} );
		await new Promise( ( r ) => setTimeout( r, 600 ) );
		await page.addStyleTag( { content: '.edit-post-meta-boxes-main, .edit-post-layout__metaboxes { max-height: none !important; height: auto !important; }' } );
		const box = await page.$( '#acf-group_cic_event' );
		await box.screenshot( { path: path.join( OUT, 'admin-wedding-fields.png' ) } );
		await page.evaluate( () => {
			const set = ( name, value ) => {
				const el = document.querySelector( '[name="acf[' + name + ']"]' );
				el.value = value;
				el.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			};
			set( 'field_cic_ev_ribbon', 'Edited in admin\nSecond phrase\nThird phrase.' );
			set( 'field_cic_ev_feature_heading', 'For the edited\n<em>admin moments.</em>' );
			window.wp.data.dispatch( 'core/editor' ).editPost( { excerpt: window.wp.data.select( 'core/editor' ).getEditedPostAttribute( 'excerpt' ) + ' ' } );
		} );
		await page.evaluate( () => window.wp.data.dispatch( 'core/editor' ).savePost() );
		await page.waitForFunction( () => {
			const ed = window.wp.data.select( 'core/editor' );
			const mb = window.wp.data.select( 'core/edit-post' );
			return ! ed.isSavingPost() && ! ( mb.isSavingMetaBoxes && mb.isSavingMetaBoxes() );
		}, { timeout: 60000 } );
		await new Promise( ( r ) => setTimeout( r, 1500 ) );
		const wed = await publicText( browser, '/events/wedding-ice-cream-catering/' );
		check( 'A2 ribbon phrases and story heading saved in the block editor appear on the Wedding page', wed.includes( '<li>Edited in admin</li>' ) && wed.includes( 'For the edited</span> <span class="cic-line"><em>admin moments.</em>' ) );
		const order = ( wed.match( /<section class="cic-comp cic-event__([a-z]+)/g ) || [] ).map( ( s ) => s.split( '__' )[ 1 ] ).join( ',' );
		check( 'A3 after an admin save the Wedding sections keep their approved order (empty order fields do not freeze a generic order)', 'hero,ribbon,feature,treats,gallery,planning,quote' === order, order );

		// Classic Ice Cream: product screen.
		await page.goto( BASE + '/wp-admin/post.php?post=' + ids[ 'classic-ice-cream' ] + '&action=edit', { waitUntil: 'domcontentloaded', timeout: 60000 } );
		await page.waitForSelector( '[name="acf[field_cic_treat_layout]"]', { timeout: 60000 } );
		await page.waitForSelector( '#publish:not(.disabled)', { timeout: 60000 } );
		const treatPanel = await page.evaluate( () => ( {
			layout: document.querySelector( '[name="acf[field_cic_treat_layout]"]' ).value,
			image2: !! document.querySelector( '[data-name="cic_hero_image_2"]' ),
			pairings: document.querySelectorAll( '#cic-pairings tbody tr, .cic-pairing-row' ).length,
		} ) );
		check( 'A4 Classic product screen shows its composition, the extra hero photos and its four pairings', 'classic' === treatPanel.layout && treatPanel.image2, treatPanel );
		await page.evaluate( () => {
			document.querySelector( '[name="acf[field_cic_faq_1_q]"]' ).value = 'Edited question from the admin?';
			document.querySelector( '[name="acf[field_cic_theme_2_title]"]' ).value = 'Edited garden theme';
		} );
		await page.screenshot( { path: path.join( OUT, 'admin-classic-fields.png' ) } );
		await Promise.all( [ page.waitForNavigation( { waitUntil: 'domcontentloaded', timeout: 60000 } ), page.click( '#publish' ) ] );
		const classic = await publicText( browser, '/treats/classic-ice-cream-catering/' );
		check( 'A5 FAQ question and theme title saved on the product screen appear on the Classic page', classic.includes( 'Edited question from the admin?' ) && classic.includes( 'Edited garden theme' ) );
		const corder = ( classic.match( /<section class="cic-comp cic-treat__([a-z]+)/g ) || [] ).map( ( s ) => s.split( '__' )[ 1 ] ).join( ',' );
		check( 'A6 after an admin save the Classic sections keep their approved order', 'hero,feelings,occasions,themes,detail,quote' === corder, corder );

		wp( 'eval-file', 'dev/tests/phase07/state.php', 'restore', snapshot );
		const wed2 = await publicText( browser, '/events/wedding-ice-cream-catering/' );
		const classic2 = await publicText( browser, '/treats/classic-ice-cream-catering/' );
		check( 'A7 restore brings back the approved copy on both pages', wed2.includes( '<li>Favorite treats</li>' ) && ! wed2.includes( 'Edited in admin' ) && classic2.includes( 'Which options can we choose?' ) && ! classic2.includes( 'Edited garden theme' ) );
	} finally {
		try {
			wp( 'eval-file', 'dev/tests/phase07/state.php', 'restore', snapshot );
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
