/**
 * Phase 02 browser verification (local only). Drives the real admin forms with a headless local
 * Chromium browser and checks computed styles on the frontend and in the block editor.
 *
 * Needs puppeteer-core (not a project dependency) resolvable through NODE_PATH, and a local
 * Edge/Chrome. Run from the project root:
 *   NODE_PATH=<dir>/node_modules node dev/tests/phase02/browser-test.cjs
 *
 * Screenshots: dev/tests/output/phase02/ (git-ignored).
 */
'use strict';

const puppeteer = require( 'puppeteer-core' );
const { execFileSync } = require( 'child_process' );
const fs = require( 'fs' );
const path = require( 'path' );

const BASE = process.env.CIC_BASE || 'https://catericecream.test';
const ROOT = path.resolve( __dirname, '../../..' );
const OUT = path.join( ROOT, 'dev/tests/output/phase02' );
const WP = process.env.CIC_WP || 'C:\\laragon\\bin\\wp-cli\\wp.cmd';
const BROWSER = process.env.CIC_BROWSER || 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
fs.mkdirSync( OUT, { recursive: true } );

const RGB = {
	pink: 'rgb(217, 35, 115)',
	blue: 'rgb(31, 111, 178)',
	teal: 'rgb(31, 111, 139)',
	plum: 'rgb(80, 53, 100)',
	indigo: 'rgb(58, 42, 110)',
	green: 'rgb(46, 125, 50)',
	raspberry: 'rgb(222, 40, 121)',
	sky: 'rgb(99, 201, 235)',
	lavender: 'rgb(185, 160, 221)',
	mint: 'rgb(140, 220, 191)',
	strawberry: 'rgb(243, 162, 189)',
	white: 'rgb(255, 255, 255)',
};

const results = [];
const check = ( label, ok, detail ) => {
	results.push( { label, ok: !! ok } );
	console.log( ( ok ? 'PASS ' : 'FAIL ' ) + label + ( ! ok && detail !== undefined ? ' — ' + JSON.stringify( detail ) : '' ) );
};
const wp = ( ...args ) => execFileSync( 'cmd', [ '/c', WP, ...args ], { cwd: ROOT, encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'ignore' ] } );

const css = ( page, sel, prop ) => page.$eval( sel, ( el, p ) => getComputedStyle( el ).getPropertyValue( p ).trim(), prop );
const cssAll = ( page, sel, prop ) => page.$$eval( sel, ( els, p ) => els.map( ( el ) => getComputedStyle( el ).getPropertyValue( p ).trim() ), prop );
const allEqual = ( list, value ) => list.length > 0 && list.every( ( v ) => v === value );

async function go( page, url, width, waitUntil ) {
	if ( width ) {
		await page.setViewport( { width, height: 900 } );
	}
	await page.goto( url.startsWith( 'http' ) ? url : BASE + url, { waitUntil: waitUntil || 'networkidle0' } );
	await page.evaluate( () => document.fonts.ready );
}

/* Hosts contacted by a page other than this site (http/https only). */
function trackExternal( page ) {
	const hosts = [];
	page.on( 'request', ( r ) => {
		const u = new URL( r.url() );
		if ( /^https?:$/.test( u.protocol ) && 'catericecream.test' !== u.hostname ) {
			hosts.push( u.hostname );
		}
	} );
	return hosts;
}

async function setField( page, name, value ) {
	await page.evaluate( ( n, v ) => {
		const el = document.querySelector( '[name="' + n + '"]' );
		if ( ! el ) {
			throw new Error( 'Missing field ' + n );
		}
		if ( 'checkbox' === el.type ) {
			el.checked = !! v;
		} else {
			if ( 'SELECT' === el.tagName && ! Array.prototype.some.call( el.options, ( o ) => o.value === v ) ) {
				const o = document.createElement( 'option' );
				o.value = v;
				o.textContent = v;
				el.appendChild( o ); // Tampered value, to prove the server rejects it.
			}
			el.value = v;
		}
		el.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		el.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	}, name, value );
}

async function clickReset( page, name ) {
	await page.evaluate( ( n ) => {
		document.querySelector( '[name="' + n + '"]' ).closest( '[data-cic-row]' ).querySelector( '[data-cic-reset]' ).click();
	}, name );
}

async function submit( page, formSelector ) {
	await Promise.all( [
		page.waitForNavigation( { waitUntil: 'networkidle0' } ),
		page.evaluate( ( s ) => document.querySelector( s ).querySelector( '[type=submit]' ).click(), formSelector ),
	] );
}

async function saveDesign( page, fields, resets ) {
	await go( page, '/wp-admin/admin.php?page=cic-design-system', 1440 );
	for ( const [ name, value ] of Object.entries( fields || {} ) ) {
		await setField( page, 'cic_design_settings' + name, value );
	}
	for ( const name of resets || [] ) {
		await clickReset( page, 'cic_design_settings' + name );
	}
	await submit( page, '.cic-design-form' );
	return page.$$eval( '.notice, .settings-error', ( n ) => n.map( ( x ) => x.textContent.replace( /\s+/g, ' ' ).trim() ) );
}

async function saveLayout( page, fields ) {
	await go( page, '/wp-admin/admin.php?page=cic-header-footer', 1440 );
	for ( const [ name, value ] of Object.entries( fields ) ) {
		await setField( page, name, value );
	}
	await submit( page, '#cic-header-footer form' );
}

async function shot( page, name, fullPage ) {
	await page.screenshot( { path: path.join( OUT, name + '.png' ), fullPage: !! fullPage } );
}

( async () => {
	const fixtures = JSON.parse( wp( 'eval-file', 'dev/tests/phase02/fixtures.php', 'create' ).trim().split( '\n' ).pop() );
	const snapshotFile = path.join( OUT, 'options-snapshot.json' );
	wp( 'eval-file', 'dev/tests/phase02/options.php', 'snapshot', snapshotFile );
	const auth = JSON.parse( wp( 'eval-file', 'dev/tests/phase02/auth-cookies.php', 'create' ).trim().split( '\n' ).pop() );
	const browser = await puppeteer.launch( { executablePath: BROWSER, headless: true, acceptInsecureCerts: true, args: [ '--no-first-run', '--disable-extensions' ] } );
	const cookieList = auth.cookies.map( ( c ) => ( { name: c.name, value: c.value, domain: 'catericecream.test', path: '/', secure: true, httpOnly: true } ) );
	let tempMenu = null;

	try {
		const page = await browser.newPage();
		await page.setCookie( ...cookieList );
		page.on( 'dialog', ( d ) => d.accept() );
		const external = trackExternal( page );

		/* ---------- T1 defaults, fonts, presets ---------- */
		console.log( '== T1 approved defaults on the style preview' );
		await go( page, '/?cic_style_preview=1', 1440 );
		const fonts = await page.evaluate( () => ( {
			heading: getComputedStyle( document.querySelector( '.cic-h1' ) ).fontFamily,
			body: getComputedStyle( document.querySelector( '[data-cic-sample="body"]' ) ).fontFamily,
			fraunces: document.fonts.check( '700 40px Fraunces' ),
			frauncesItalic: document.fonts.check( 'italic 600 40px Fraunces' ),
			dmsans: document.fonts.check( '400 16px "DM Sans"' ),
			loaded: Array.from( document.fonts ).filter( ( f ) => 'loaded' === f.status ).map( ( f ) => f.family.replace( /"/g, '' ) + ' ' + f.style + ' ' + f.weight ),
		} ) );
		check( 'T1.1 headings use Fraunces, body uses DM Sans', fonts.heading.startsWith( 'Fraunces' ) && fonts.body.startsWith( '"DM Sans"' ), fonts );
		check( 'T1.2 local Fraunces and DM Sans files load (roman + Wedding italic)', fonts.fraunces && fonts.dmsans && fonts.loaded.some( ( f ) => f.startsWith( 'Fraunces italic' ) ), fonts.loaded );
		// The only expected outside host for a logged-in admin is the admin-bar avatar (Gravatar, WordPress core).
		check( 'T1.3 no external font/style/script requests (only the core admin-bar avatar for logged-in admins)', external.every( ( h ) => 'secure.gravatar.com' === h ), external );
		const buttons = await cssAll( page, '[data-cic-sample="button"], [data-cic-sample="brand-button"]', 'background-color' );
		check( 'T1.4 brand + all 8 preset buttons use the approved action pink', 9 === buttons.length && allEqual( buttons, RGB.pink ), buttons );
		check( 'T1.5 Wedding preset: heading weight 600, italic accent words', '600' === await css( page, '[data-cic-preview="wedding"] h2', 'font-weight' ) && 'italic' === await css( page, '[data-cic-preview="wedding"] h2 .cic-accent', 'font-style' ) );
		check( 'T1.6 Birthday accents: sky and mint from central tokens', RGB.sky === await css( page, '[data-cic-preview="birthday"] .cic-decor-dot', 'background-color' ) && RGB.mint === await css( page, '[data-cic-preview="birthday"] .cic-decor-dot--2', 'background-color' ) );
		check( 'T1.7 Brand heading accent is raspberry', RGB.raspberry === await css( page, '.cic-h1 .cic-accent', 'color' ) );
		await shot( page, '01-preview-1440-defaults', true );
		await go( page, '/?cic_style_preview=1', 768 );
		await shot( page, '02-preview-768-defaults', true );
		await go( page, '/?cic_style_preview=1', 390 );
		await shot( page, '03-preview-390-defaults', true );

		/* ---------- T2 responsive scale ---------- */
		console.log( '== T2 responsive type scale' );
		const sizes = {};
		for ( const w of [ 390, 768, 1440 ] ) {
			await go( page, '/?cic_style_preview=1', w );
			sizes[ w ] = {
				h1: await css( page, '.cic-h1', 'font-size' ),
				h1lh: await css( page, '.cic-h1', 'line-height' ),
				h2: await css( page, '.cic-h2', 'font-size' ),
				body: await css( page, '[data-cic-sample="body"]', 'font-size' ),
				bodylh: await css( page, '[data-cic-sample="body"]', 'line-height' ),
			};
		}
		// Expected sizes come from the saved Design System (the owner may tune the scale centrally).
		const ty = JSON.parse( wp( 'eval', 'echo wp_json_encode( CIC\\Design\\Settings::resolved()["type"] );' ).trim().split( '\n' ).pop() );
		const px = ( n ) => n + 'px';
		check( 'T2.1 390px: H1, H2 and body use the saved mobile scale', px( ty.h1.min ) === sizes[ 390 ].h1 && px( ty.h1.lh_min ) === sizes[ 390 ].h1lh && px( ty.h2.min ) === sizes[ 390 ].h2 && px( ty.body.min ) === sizes[ 390 ].body && px( ty.body.lh_min ) === sizes[ 390 ].bodylh, { sizes: sizes[ 390 ], ty } );
		check( 'T2.2 1440px: H1, H2 and body use the saved desktop scale', px( ty.h1.max ) === sizes[ 1440 ].h1 && px( ty.h1.lh_max ) === sizes[ 1440 ].h1lh && px( ty.h2.max ) === sizes[ 1440 ].h2 && px( ty.body.max ) === sizes[ 1440 ].body && px( ty.body.lh_max ) === sizes[ 1440 ].bodylh, { sizes: sizes[ 1440 ], ty } );
		const mid = ty.h1.min + ( ty.h1.max - ty.h1.min ) * ( 768 - 390 ) / 1050;
		check( 'T2.3 768px interpolates fluidly between the saved H1 sizes', Math.abs( parseFloat( sizes[ 768 ].h1 ) - mid ) < 0.1, { got: sizes[ 768 ].h1, mid } );

		/* ---------- T3 global action colour change through the admin form ---------- */
		console.log( '== T3 global action colour change propagates; local override stays local' );
		let notices = await saveDesign( page, { '[colors][action_primary]': '#1F6FB2' } );
		check( 'T3.1 admin form saved (Settings API + nonce)', notices.some( ( n ) => /Settings saved/i.test( n ) ), notices );
		await go( page, '/?cic_style_preview=1', 1440 );
		const blue = await cssAll( page, '[data-cic-sample="button"], [data-cic-sample="brand-button"]', 'background-color' );
		check( 'T3.2 every preset button now uses the new global action colour', allEqual( blue, RGB.blue ), blue );
		check( 'T3.3 header quote button and call panel follow the global token', RGB.blue === await css( page, '.cic-header__quote', 'background-color' ) && RGB.blue === await css( page, '.cic-call-panel', 'background-color' ) );
		await shot( page, '04-preview-1440-action-changed' );
		await go( page, fixtures.urlA, 1440 );
		check( 'T3.4 fixture: inheriting button and Wedding-section button turn blue', RGB.blue === await css( page, '.fx-button-inherit .wp-block-button__link', 'background-color' ) && RGB.blue === await css( page, '.fx-wedding-button .wp-block-button__link', 'background-color' ) );
		check( 'T3.5 fixture: explicit button override stays teal (local)', RGB.teal === await css( page, '.fx-button-override .wp-block-button__link', 'background-color' ) );
		await shot( page, '05-fixture-overrides-1440-action-changed', true );

		/* ---------- T4 editor parity ---------- */
		console.log( '== T4 block editor parity and override panel' );
		await go( page, '/wp-admin/post.php?post=' + fixtures.a + '&action=edit', 1440, 'domcontentloaded' );
		await page.evaluate( () => {
			try {
				window.wp.data.dispatch( 'core/preferences' ).set( 'core/edit-post', 'welcomeGuide', false );
			} catch ( e ) {}
		} );
		await page.waitForSelector( 'iframe[name="editor-canvas"]', { timeout: 30000 } );
		const frame = await ( await page.$( 'iframe[name="editor-canvas"]' ) ).contentFrame();
		await frame.waitForSelector( '.fx-override-heading', { timeout: 30000 } );
		await new Promise( ( r ) => setTimeout( r, 1500 ) );
		const editor = await frame.evaluate( () => {
			const g = ( s, p ) => getComputedStyle( document.querySelector( s ) ).getPropertyValue( p ).trim();
			return {
				overrideColor: g( '.fx-override-heading', 'color' ),
				neighbourColor: g( '.fx-neighbour-heading', 'color' ),
				inheritButton: g( '.fx-button-inherit .wp-block-button__link', 'background-color' ),
				overrideButton: g( '.fx-button-override .wp-block-button__link', 'background-color' ),
				weddingWeight: g( '.fx-wedding-heading', 'font-weight' ),
				headingFont: g( '.fx-neighbour-heading', 'font-family' ),
				token: getComputedStyle( document.body ).getPropertyValue( '--wp--preset--color--action-primary' ).trim(),
			};
		} );
		const validity = await page.evaluate( () => {
			const flat = ( blocks ) => blocks.reduce( ( a, b ) => a.concat( [ b ], flat( b.innerBlocks ) ), [] );
			return flat( window.wp.data.select( 'core/block-editor' ).getBlocks() ).map( ( b ) => b.name + ':' + b.isValid );
		} );
		check( 'T4.1 fixture blocks are valid in the editor (server-side injection does not alter saved markup)', validity.every( ( v ) => v.endsWith( ':true' ) ), validity );
		check( 'T4.2 editor canvas receives the saved global token (#1F6FB2)', '#1F6FB2' === editor.token.toUpperCase() && RGB.blue === editor.inheritButton, editor );
		check( 'T4.3 editor preview shows local overrides and preset (teal button, override heading, Wedding 600)', RGB.teal === editor.overrideButton && RGB.teal === editor.overrideColor && RGB.plum === editor.neighbourColor && '600' === editor.weddingWeight, editor );
		check( 'T4.4 editor headings use Fraunces like the frontend', editor.headingFont.startsWith( 'Fraunces' ), editor.headingFont );
		const clientId = await page.evaluate( () => {
			const flat = ( blocks ) => blocks.reduce( ( a, b ) => a.concat( [ b ], flat( b.innerBlocks ) ), [] );
			const block = flat( window.wp.data.select( 'core/block-editor' ).getBlocks() ).find( ( b ) => b.attributes.cicStyle && b.attributes.cicStyle.heading );
			window.wp.data.dispatch( 'core/block-editor' ).selectBlock( block.clientId );
			try {
				window.wp.data.dispatch( 'core/edit-post' ).openGeneralSidebar( 'edit-post/block' );
			} catch ( e ) {}
			return block.clientId;
		} );
		await page.waitForFunction( () => document.body.innerText.includes( 'Cater style overrides' ), { timeout: 15000 } );
		const panelText = await page.evaluate( () => document.body.innerText );
		check( 'T4.5 inspector shows the override panel with "Override active" and reset', /Cater style overrides \(3\)/.test( panelText ) && panelText.includes( 'Override active' ) && panelText.includes( 'Reset to inherited' ) );
		await shot( page, '06-editor-override-panel' );
		await page.evaluate( () => {
			// The block inspector panel (not the page meta box further down, which has its own reset buttons).
			const btn = Array.from( document.querySelectorAll( '.cic-ov-field.is-active button' ) ).find( ( b ) => 'Reset to inherited' === b.textContent.trim() );
			btn.click();
		} );
		await new Promise( ( r ) => setTimeout( r, 500 ) );
		const afterReset = await frame.$eval( '.fx-override-heading', ( el ) => getComputedStyle( el ).color );
		const attr = await page.evaluate( ( id ) => window.wp.data.select( 'core/block-editor' ).getBlockAttributes( id ).cicStyle, clientId );
		check( 'T4.6 editor Reset removes only that field and the heading previews the inherited colour', RGB.plum === afterReset && attr && ! attr.heading && 30 === attr.size_min, { afterReset, attr } );
		await page.goto( BASE + '/wp-admin/', { waitUntil: 'networkidle0' } ); // Leave without saving.

		/* ---------- T5 inherited value after reset includes later global changes ---------- */
		console.log( '== T5 reset returns current inherited values, including later global changes' );
		await saveDesign( page, { '[colors][text_primary]': '#3A2A6E' } );
		await go( page, fixtures.urlA, 1440 );
		check( 'T5.1 global heading colour changed: neighbour follows, override stays local', RGB.indigo === await css( page, '.fx-neighbour-heading', 'color' ) && RGB.teal === await css( page, '.fx-override-heading', 'color' ) );
		wp( 'eval-file', 'dev/tests/phase02/fixtures.php', 'reset-override' );
		await go( page, fixtures.urlA, 1440 );
		check( 'T5.2 after reset the heading shows the CURRENT global colour (made after the override)', RGB.indigo === await css( page, '.fx-override-heading', 'color' ) );
		wp( 'eval-file', 'dev/tests/phase02/fixtures.php', 'restore-override' );

		/* ---------- T6 global body font ---------- */
		console.log( '== T6 global body font change' );
		await saveDesign( page, { '[scalars][font_body]': 'system-sans' } );
		await go( page, '/?cic_style_preview=1', 1440 );
		const bodies = await cssAll( page, '.cic-preview-strip p:not(.cic-eyebrow), [data-cic-sample="body"]', 'font-family' );
		check( 'T6.1 body text in every preset switches to the new global body font', bodies.length > 8 && bodies.every( ( f ) => f.startsWith( 'system-ui' ) ), bodies.slice( 0, 3 ) );
		const heads = await cssAll( page, '.cic-preview-strip h2, .cic-h1', 'font-family' );
		check( 'T6.2 headings keep Fraunces (heading font unchanged)', 9 === heads.length && heads.every( ( f ) => f.startsWith( 'Fraunces' ) ), heads );
		check( 'T6.3 navigation and buttons follow the body/interface font', ( await css( page, '.cic-nav--primary a', 'font-family' ) ).startsWith( 'system-ui' ) && ( await css( page, '.cic-header__quote', 'font-family' ) ).startsWith( 'system-ui' ) );

		/* ---------- T7 reset globals through the Reset buttons ---------- */
		console.log( '== T7 reset globals with the admin Reset buttons' );
		await saveDesign( page, {}, [ '[colors][action_primary]', '[colors][text_primary]', '[scalars][font_body]' ] );
		const saved = JSON.parse( wp( 'option', 'get', 'cic_design_settings', '--format=json' ) );
		check( 'T7.1 reset fields are removed from storage (sparse, back to defaults)', ! saved.colors || ( ! saved.colors.action_primary && ! saved.colors.text_primary ), saved );
		await go( page, '/?cic_style_preview=1', 1440 );
		check( 'T7.2 approved pink, plum and DM Sans return everywhere', allEqual( await cssAll( page, '[data-cic-sample="button"], [data-cic-sample="brand-button"]', 'background-color' ), RGB.pink ) && RGB.plum === await css( page, '.cic-h2', 'color' ) && ( await css( page, '[data-cic-sample="body"]', 'font-family' ) ).startsWith( '"DM Sans"' ) );
		await go( page, fixtures.urlA, 1440 );
		check( 'T7.3 local overrides were untouched by global resets', RGB.teal === await css( page, '.fx-button-override .wp-block-button__link', 'background-color' ) && RGB.teal === await css( page, '.fx-override-heading', 'color' ) );

		/* ---------- T8 responsive size change ---------- */
		console.log( '== T8 mobile/desktop size changes and local size override' );
		await saveDesign( page, { '[type][h1][min]': '36', '[type][h1][lh_min]': '40', '[type][h1][max]': '72', '[type][h1][lh_max]': '78' } );
		await go( page, fixtures.urlA, 390 );
		const m = { h1: await css( page, '.fx-h1', 'font-size' ), ov: await css( page, '.fx-override-heading', 'font-size' ), nb: await css( page, '.fx-neighbour-heading', 'font-size' ) };
		await shot( page, '07-fixture-390-sizes', true );
		await go( page, fixtures.urlA, 1440 );
		const d = { h1: await css( page, '.fx-h1', 'font-size' ), ov: await css( page, '.fx-override-heading', 'font-size' ), nb: await css( page, '.fx-neighbour-heading', 'font-size' ) };
		check( 'T8.1 new global H1 sizes apply: 36px mobile, 72px desktop', '36px' === m.h1 && '72px' === d.h1, { m, d } );
		check( 'T8.2 local heading size override stays 30px/50px; neighbour H2 keeps the saved H2 scale', '30px' === m.ov && '50px' === d.ov && px( ty.h2.min ) === m.nb && px( ty.h2.max ) === d.nb, { m, d } );
		await go( page, fixtures.urlB, 390 );
		const pb = { size: await css( page, '.fx-h1', 'font-size' ), accent: await css( page, '.fx-h1 em', 'color' ) };
		await go( page, fixtures.urlA, 390 );
		const pa = { accent: await css( page, '.fx-h1 em', 'color' ) };
		check( 'T8.3 page-scope override (page B) applies only to that page', '36px' === pb.size && RGB.green === pb.accent && RGB.raspberry === pa.accent, { pb, pa } );
		await saveDesign( page, {}, [ '[type][h1][min]' ] );
		await go( page, fixtures.urlA, 1440 );
		check( 'T8.4 resetting the scale row restores H1 64px desktop', '64px' === await css( page, '.fx-h1', 'font-size' ) );

		/* ---------- T9 persistence through reload and cache flush ---------- */
		console.log( '== T9 persistence after reload and cache clear' );
		await saveDesign( page, { '[colors][lavender]': '#A58BD6' } );
		wp( 'cache', 'flush' );
		wp( 'transient', 'delete', '--all' );
		const fresh = await browser.createBrowserContext();
		const p2 = await fresh.newPage();
		await p2.setCookie( ...cookieList );
		await go( p2, '/?cic_style_preview=1', 1440 );
		check( 'T9.1 saved token persists in a fresh browser session after object-cache and transient flush', 'rgb(165, 139, 214)' === await css( p2, '[data-cic-preview="corporate"] .cic-decor-dot', 'background-color' ) );
		const anonContext = await browser.createBrowserContext();
		const anon = await anonContext.newPage();
		const anonExternal = trackExternal( anon );
		await anon.setViewport( { width: 1440, height: 900 } );
		await anon.goto( BASE + '/', { waitUntil: 'networkidle0' } );
		check( 'T9.2 anonymous visitors receive the updated token in the public stylesheet', ( await anon.content() ).includes( '--wp--preset--color--lavender: #A58BD6' ) );
		check( 'T9.3 anonymous page makes no requests to any external host (fonts self-hosted)', 0 === anonExternal.length, anonExternal );
		await anonContext.close();
		await fresh.close();
		await saveDesign( page, {}, [ '[colors][lavender]' ] );

		/* ---------- T10 contrast warning ---------- */
		console.log( '== T10 contrast warnings without silent changes' );
		notices = await saveDesign( page, { '[colors][action_primary]': '#F3A2BD' } );
		check( 'T10.1 admin warns about failing pairs after save', notices.some( ( n ) => /Contrast warning/.test( n ) ), notices );
		check( 'T10.2 the saved value was NOT changed automatically', '#F3A2BD' === JSON.parse( wp( 'option', 'get', 'cic_design_settings', '--format=json' ) ).colors.action_primary );
		await page.evaluate( () => { location.hash = 'contrast'; } );
		await page.click( '[data-cic-tab="contrast"]' );
		await shot( page, '08-admin-contrast-warning' );
		await go( page, '/?cic_style_preview=1', 1440 );
		check( 'T10.3 style preview lists the failing pairs', 0 < parseInt( await page.$eval( '[data-cic-contrast-failures]', ( el ) => el.getAttribute( 'data-cic-contrast-failures' ) ), 10 ) );
		await saveDesign( page, {}, [ '[colors][action_primary]' ] );

		/* ---------- T11 validation through the real form ---------- */
		console.log( '== T11 invalid input is rejected by the server' );
		notices = await saveDesign( page, { '[colors][action_primary]': 'red;}body{display:none}', '[type][h1][min]': '500', '[scalars][font_heading]': 'comic-sans' } );
		const after = JSON.parse( wp( 'option', 'get', 'cic_design_settings', '--format=json' ) );
		check( 'T11.1 injected colour, out-of-range size and tampered font each produce an error', notices.filter( ( n ) => /not a valid #RRGGBB|must be a whole number|is not allowed/.test( n ) ).length >= 1 && /RRGGBB/.test( notices.join( ' ' ) ) && /whole number/.test( notices.join( ' ' ) ) && /Heading font/.test( notices.join( ' ' ) ), notices );
		check( 'T11.2 nothing invalid was stored', ! ( after.colors && after.colors.action_primary ) && ! ( after.type && after.type.h1 ) && ! ( after.scalars && after.scalars.font_heading ), after );
		const forged = await page.evaluate( async ( base ) => {
			const body = new URLSearchParams( { option_page: 'cic_design', action: 'update', 'cic_design_settings[colors][action_primary]': '#000000' } );
			const r = await fetch( base + '/wp-admin/options.php', { method: 'POST', body, credentials: 'include' } );
			return r.status;
		}, BASE );
		check( 'T11.3 a POST without a valid nonce is refused', 403 === forged && ! ( JSON.parse( wp( 'option', 'get', 'cic_design_settings', '--format=json' ) ).colors || {} ).action_primary, forged );

		/* ---------- T12 preset edit stays in its preset ---------- */
		console.log( '== T12 editing one preset leaves the others alone' );
		await saveDesign( page, { '[contexts][birthday][roles][accent]': 'lavender' } );
		await go( page, '/?cic_style_preview=1', 1440 );
		check( 'T12.1 Birthday accent changed; Wedding, Yogurt and Dots keep their own', RGB.lavender === await css( page, '[data-cic-preview="birthday"] .cic-decor-dot', 'background-color' ) && 'rgb(245, 231, 232)' === await css( page, '[data-cic-preview="wedding"] .cic-decor-dot', 'background-color' ) && RGB.mint === await css( page, '[data-cic-preview="yogurt"] .cic-decor-dot', 'background-color' ) && RGB.sky === await css( page, '[data-cic-preview="dots"] .cic-decor-dot', 'background-color' ) );
		await go( page, '/wp-admin/admin.php?page=cic-design-system#presets', 1440 );
		await page.evaluate( () => { document.querySelectorAll( '.cic-preset' ).forEach( ( d, i ) => { d.open = i < 2; } ); } );
		await shot( page, '09-admin-presets' );
		await saveDesign( page, {}, [ '[contexts][birthday][roles][accent]' ] );
		await go( page, '/?cic_style_preview=1', 1440 );
		check( 'T12.2 preset reset restores the seeded sky accent', RGB.sky === await css( page, '[data-cic-preview="birthday"] .cic-decor-dot', 'background-color' ) );

		/* ---------- T13 header rows, footer and contact ---------- */
		console.log( '== T13 header rows, mobile navigation and footer controls' );
		tempMenu = wp( 'menu', 'create', 'Phase 02 test secondary', '--porcelain' ).trim();
		wp( 'menu', 'item', 'add-custom', tempMenu, 'Test secondary link', BASE + '/#secondary-test' );
		wp( 'menu', 'location', 'assign', tempMenu, 'secondary' );
		await go( page, '/', 1440 );
		check( 'T13.1 defaults: top row on, bottom row off, sticky header', 1 === ( await page.$$( '.cic-header__top' ) ).length && 0 === ( await page.$$( '.cic-header__bottom' ) ).length && 'sticky' === await css( page, '.cic-header', 'position' ) );
		await saveLayout( page, {
			'cic_site_layout[top][enabled]': false,
			'cic_site_layout[bottom][enabled]': true,
			'cic_site_layout[bottom][show_mobile]': false,
			'cic_site_layout[main][sticky]': false,
			'cic_site_layout[main][show_call]': true,
			'cic_site_layout[footer][layout]': 'brand-contact',
			'cic_site_layout[footer][show_email]': true,
			'cic_core_settings[business_phone]': '(818) 555-0142',
		} );
		await go( page, '/?cic_style_preview=1', 1440 );
		const telLinks = await page.$$eval( 'a[href^="tel:"]', ( as ) => as.map( ( a ) => a.getAttribute( 'href' ) ) );
		check( 'T13.2 top row hidden, bottom row shown, header no longer sticky', 0 === ( await page.$$( '.cic-header__top' ) ).length && 1 === ( await page.$$( '.cic-header__bottom' ) ).length && 'relative' === await css( page, '.cic-header', 'position' ) );
		check( 'T13.3 phone change updates every tel: link (header, drawer, call panel, footer)', telLinks.length >= 4 && telLinks.every( ( h ) => 'tel:+18185550142' === h ), telLinks );
		check( 'T13.4 footer layout "logo · contact" drops the menu column and shows email', 0 === ( await page.$$( '.cic-footer__menu' ) ).length && 1 === ( await page.$$( '.cic-footer a[href^="mailto:"]' ) ).length );
		await shot( page, '10-header-footer-changed-1440' );
		await go( page, '/?cic_style_preview=1', 390 );
		check( 'T13.5 bottom row "hidden on mobile" is hidden at 390px', 'none' === await css( page, '.cic-header__bottom', 'display' ) );
		await page.goto( BASE + '/wp-admin/admin.php?page=cic-header-footer#main', { waitUntil: 'networkidle0' } );
		await page.setViewport( { width: 1440, height: 1000 } );
		await shot( page, '11-admin-header-footer' );
		wp( 'eval-file', 'dev/tests/phase02/options.php', 'restore', snapshotFile );
		wp( 'menu', 'delete', tempMenu );
		tempMenu = null;

		/* ---------- T14 mobile drawer and keyboard ---------- */
		console.log( '== T14 mobile drawer accessibility' );
		await go( page, '/?cic_style_preview=1', 390 );
		check( 'T14.1 at 390px the primary nav collapses and the menu button shows', 'none' === await css( page, '.cic-nav--primary', 'display' ) && 'none' !== await css( page, '.cic-menu-toggle', 'display' ) );
		await page.click( '.cic-menu-toggle' );
		const opened = await page.evaluate( () => ( {
			hidden: document.querySelector( '#cic-drawer' ).hidden,
			expanded: document.querySelector( '.cic-menu-toggle' ).getAttribute( 'aria-expanded' ),
			focus: document.activeElement.className,
			locked: document.body.classList.contains( 'cic-drawer-open' ),
			dialog: document.querySelector( '#cic-drawer' ).getAttribute( 'role' ) + '/' + document.querySelector( '#cic-drawer' ).getAttribute( 'aria-modal' ),
		} ) );
		check( 'T14.2 drawer opens as a modal dialog, focus moves to Close, page scroll locked', false === opened.hidden && 'true' === opened.expanded && opened.focus.includes( 'cic-drawer__close' ) && opened.locked && 'dialog/true' === opened.dialog, opened );
		await page.$eval( '#cic-drawer .cic-submenu-toggle', ( b ) => b.click() );
		check( 'T14.3 submenu disclosure button expands "Our Treats"', 'true' === await page.$eval( '#cic-drawer .cic-submenu-toggle', ( b ) => b.getAttribute( 'aria-expanded' ) ) && 'block' === await css( page, '#cic-drawer .menu-item-has-children .sub-menu', 'display' ) );
		await new Promise( ( r ) => setTimeout( r, 400 ) ); // Let the slide-in animation finish.
		await shot( page, '12-drawer-open-390' );
		for ( let i = 0; i < 40; i++ ) {
			await page.keyboard.press( 'Tab' );
		}
		check( 'T14.4 focus stays trapped inside the drawer while tabbing', await page.evaluate( () => document.querySelector( '#cic-drawer' ).contains( document.activeElement ) ) );
		await page.keyboard.press( 'Escape' );
		const closed = await page.evaluate( () => ( { hidden: document.querySelector( '#cic-drawer' ).hidden, focus: document.activeElement.className, expanded: document.querySelector( '.cic-menu-toggle' ).getAttribute( 'aria-expanded' ), locked: document.body.classList.contains( 'cic-drawer-open' ) } ) );
		check( 'T14.5 Escape closes the drawer and returns focus to the menu button', closed.hidden && closed.focus.includes( 'cic-menu-toggle' ) && 'false' === closed.expanded && ! closed.locked, closed );
		const touch = await page.$$eval( '.cic-menu-toggle, #cic-drawer .menu a, .cic-drawer__close', ( els ) => els.map( ( e ) => { e.closest( '#cic-drawer' ) && ( e.closest( '#cic-drawer' ).hidden = false ); const r = e.getBoundingClientRect(); return Math.round( Math.min( r.width, r.height ) ); } ) );
		const visibleTouch = touch.filter( ( s ) => s > 0 ); // Links inside a collapsed submenu are not rendered.
		check( 'T14.6 menu button, visible drawer links and Close are at least 44px', visibleTouch.length >= 8 && visibleTouch.every( ( s ) => s >= 44 ), touch );
		await go( page, '/?cic_style_preview=1', 1440 );
		await page.focus( '.cic-nav--primary .menu-item-has-children > a' );
		check( 'T14.7 desktop submenu opens on keyboard focus (no hover needed)', 'block' === await css( page, '.cic-nav--primary .menu-item-has-children .sub-menu', 'display' ) && 'none' === await css( page, '.cic-menu-toggle', 'display' ) );
		const offset = await page.evaluate( () => ( { pad: getComputedStyle( document.documentElement ).scrollPaddingTop, header: document.querySelector( '.cic-header' ).offsetHeight, bar: document.getElementById( 'wpadminbar' ) ? document.getElementById( 'wpadminbar' ).offsetHeight : 0, top: getComputedStyle( document.querySelector( '.cic-header' ) ).top } ) );
		check( 'T14.8 sticky header sits below the admin bar and anchors get a matching scroll offset', parseFloat( offset.pad ) === offset.header + offset.bar + 8 && parseFloat( offset.top ) === offset.bar, offset );
		await go( page, '/wp-admin/admin.php?page=cic-design-system', 1440 );
		await shot( page, '13-admin-design-system' );
	} finally {
		try {
			wp( 'eval-file', 'dev/tests/phase02/options.php', 'restore', snapshotFile );
		} catch ( e ) {}
		if ( tempMenu ) {
			try {
				wp( 'menu', 'delete', tempMenu );
			} catch ( e ) {}
		}
		try {
			wp( 'eval-file', 'dev/tests/phase02/auth-cookies.php', 'destroy', auth.token );
		} catch ( e ) {}
		try {
			wp( 'eval-file', 'dev/tests/phase02/fixtures.php', 'delete' );
		} catch ( e ) {}
		await browser.close();
	}
	const restoredFile = path.join( OUT, 'options-after.json' );
	wp( 'eval-file', 'dev/tests/phase02/options.php', 'snapshot', restoredFile );
	check( 'T15 all Phase 02 options restored exactly to their pre-test state', fs.readFileSync( restoredFile, 'utf8' ) === fs.readFileSync( snapshotFile, 'utf8' ) );
	const failed = results.filter( ( r ) => ! r.ok ).length;
	console.log( '== RESULT: ' + ( results.length - failed ) + ' passed, ' + failed + ' failed' );
	process.exitCode = failed ? 1 : 0;
} )().catch( ( e ) => {
	console.error( e );
	process.exitCode = 2;
} );
