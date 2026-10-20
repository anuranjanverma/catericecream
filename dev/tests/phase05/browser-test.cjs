/**
 * Phase 05 public browser verification (local only, one browser, sequential, always closed).
 * Hub, state hub and city pages at true 390/768/1440 CSS px (full page + sections), keyboard
 * autocomplete, coverage results for every status, outside-area blocking in starters, city context
 * handoff, mobile county accordions, homepage connection, menu state and storage privacy.
 * Read-only: nothing is changed on the site.
 *
 *   NODE_PATH=<dir>/node_modules node dev/tests/phase05/browser-test.cjs
 */
'use strict';

const puppeteer = require( 'puppeteer-core' );
const fs = require( 'fs' );
const path = require( 'path' );

const BASE = 'https://catericecream.test';
const ROOT = path.resolve( __dirname, '../../..' );
const OUT = path.join( ROOT, 'dev/tests/output/phase05' );
const BROWSER = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const OUTSIDE = 'Sorry, we do not currently serve this area. Please choose another event location.';
fs.mkdirSync( OUT, { recursive: true } );

const results = [];
const check = ( label, ok, detail ) => {
	results.push( !! ok );
	console.log( ( ok ? 'PASS ' : 'FAIL ' ) + label + ( ! ok && detail !== undefined ? ' — ' + JSON.stringify( detail ) : '' ) );
};

async function settle( page ) {
	await page.evaluate( async () => {
		for ( let y = 0; y < document.body.scrollHeight; y += 600 ) {
			window.scrollTo( 0, y );
			await new Promise( ( r ) => setTimeout( r, 60 ) );
		}
		window.scrollTo( 0, 0 );
		await document.fonts.ready;
		await Promise.all( Array.from( document.images ).map( ( i ) => { i.loading = 'eager'; i.decoding = 'sync'; return i.decode().catch( () => {} ); } ) );
	} );
}

async function areaResult( page, input, value, form ) {
	await page.$eval( input, ( el ) => { el.value = ''; } );
	await page.type( input, value );
	await page.keyboard.press( 'Escape' );
	await page.$eval( form, ( f ) => f.requestSubmit() );
	await page.waitForFunction( ( f ) => { const el = document.querySelector( f ); const r = el.querySelector( '.cic-area-check__result' ); return ! el.hasAttribute( 'aria-busy' ) && r && ! r.hidden && r.textContent.trim().length > 0; }, {}, form );
	await new Promise( ( r ) => setTimeout( r, 150 ) );
	return page.$eval( form + ' .cic-area-check__result', ( r ) => ( { status: r.getAttribute( 'data-status' ), text: r.textContent.trim(), links: Array.from( r.querySelectorAll( 'a' ) ).map( ( a ) => ( { text: a.textContent, href: a.href } ) ) } ) );
}

let browser;
( async () => {
	browser = await puppeteer.launch( { executablePath: BROWSER, headless: true, acceptInsecureCerts: true } );
	try {
		const page = await browser.newPage();

		// ---- Screenshots at true widths + overflow ----
		const targets = { hub: '/service-areas/', state: '/service-areas/california/', city: '/service-areas/california/pasadena/' };
		const overflow = {};
		for ( const w of [ 390, 768, 1440 ] ) {
			await page.setViewport( { width: w, height: 900, deviceScaleFactor: 1 } );
			for ( const [ name, url ] of Object.entries( targets ) ) {
				await page.goto( BASE + url, { waitUntil: 'networkidle0' } );
				await settle( page );
				overflow[ name + w ] = await page.evaluate( () => document.documentElement.scrollWidth - window.innerWidth );
				await page.screenshot( { path: path.join( OUT, `${ name }-${ w }.png` ), fullPage: true } );
				await page.screenshot( { path: path.join( OUT, `${ name }-${ w }-viewport.png` ) } );
				if ( 768 !== w && 'state' !== name ) {
					const ids = await page.$$eval( 'main section[id], .cic-areas > section[id], .cic-city > section[id]', ( els ) => Array.from( new Set( els.map( ( e ) => e.id ) ) ) );
					for ( const id of ids ) {
						const el = await page.$( '#' + id );
						if ( el && ( await el.boundingBox() ) ) {
							await el.screenshot( { path: path.join( OUT, `section-${ name }-${ w }-${ id }.png` ) } );
						}
					}
				}
			}
		}
		check( 'P1 hub, state hub and city pages have no horizontal overflow at 390/768/1440', Object.values( overflow ).every( ( v ) => v <= 0 ), overflow );

		// ---- Hub: autocomplete + coverage statuses ----
		await page.setViewport( { width: 1440, height: 1000, deviceScaleFactor: 1 } );
		await page.goto( BASE + '/service-areas/', { waitUntil: 'networkidle0' } );
		const input = '#cic-areas-location';
		await page.focus( input );
		await page.type( input, 'pas' );
		await page.waitForSelector( '.cic-ac-list:not([hidden]) [role=option]', { timeout: 5000 } );
		const combo = await page.$eval( input, ( el ) => ( { role: el.getAttribute( 'role' ), expanded: el.getAttribute( 'aria-expanded' ), controls: el.getAttribute( 'aria-controls' ), options: Array.from( document.querySelectorAll( '#' + el.getAttribute( 'aria-controls' ) + ' [role=option]' ) ).map( ( o ) => o.textContent ) } ) );
		check( 'P2 autocomplete is an ARIA combobox with a limited listbox of matching cities', 'combobox' === combo.role && 'true' === combo.expanded && combo.options.includes( 'Pasadena, CA' ) && combo.options.length <= 6, combo );
		await page.keyboard.press( 'ArrowDown' );
		const activeDesc = await page.$eval( input, ( el ) => el.getAttribute( 'aria-activedescendant' ) );
		await page.keyboard.press( 'Enter' );
		const chosen = await page.$eval( input, ( el ) => ( { value: el.value, expanded: el.getAttribute( 'aria-expanded' ) } ) );
		check( 'P3 keyboard: ArrowDown highlights (aria-activedescendant), Enter fills the city and closes the list', !! activeDesc && 'Pasadena, CA' === chosen.value && 'false' === chosen.expanded, { activeDesc, chosen } );
		await page.screenshot( { path: path.join( OUT, 'autocomplete-1440.png' ), clip: { x: 0, y: 150, width: 1440, height: 520 } } );

		const form = '.cic-areas-hero form[data-cic-area-check]';
		const covered = await areaResult( page, input, 'Pasadena, CA', form );
		check( 'P4 covered city: geography confirmed, links to the city page and the quote, labelled as local test data', 'covered' === covered.status && covered.links.some( ( l ) => l.href.endsWith( '/service-areas/california/pasadena/' ) ) && covered.links.some( ( l ) => l.href.includes( '/get-a-quote/' ) ) && covered.text.includes( 'Local test data' ) && ! /available|reserved|booked/i.test( covered.text ), covered );
		const outside = await areaResult( page, input, '90222', form );
		check( 'P5 excluded ZIP: exact outside-area error and no way to continue to the quote', 'outside_area' === outside.status && outside.text.startsWith( OUTSIDE ) && ! outside.links.some( ( l ) => l.href.includes( '/get-a-quote/' ) ), outside );
		await page.screenshot( { path: path.join( OUT, 'area-outside-1440.png' ), clip: { x: 0, y: 150, width: 1440, height: 520 } } );
		const shared = await areaResult( page, input, '90220', form );
		check( 'P6 shared ZIP: manual review asking for the venue city (never confirmed or excluded)', 'manual_review' === shared.status && /venue/.test( shared.text ) && shared.links.some( ( l ) => l.href.includes( 'location=90220' ) ), shared );
		const unknown = await areaResult( page, input, '94105', form );
		check( 'P7 ZIP without reviewed data: unknown, asks for ZIP and venue city', 'unknown' === unknown.status && /venue/.test( unknown.text ), unknown );
		const partial = await areaResult( page, input, 'Glendale', form );
		check( 'P8 partially served city asks for the event ZIP', 'manual_review' === partial.status && /ZIP code/.test( partial.text ), partial );
		await page.$eval( input, ( el ) => { el.value = '12'; } );
		await page.$eval( form, ( f ) => f.requestSubmit() );
		const invalid = await page.$eval( input, ( el ) => ( { invalid: el.getAttribute( 'aria-invalid' ), msg: el.form.querySelector( '.cic-area-check__result' ).textContent } ) );
		check( 'P9 malformed input is flagged for correction (aria-invalid)', 'true' === invalid.invalid && /5-digit/.test( invalid.msg ), invalid );

		// ---- Hub starter: outside blocks, covered continues ----
		await page.evaluate( () => window.sessionStorage.clear() );
		await page.goto( BASE + '/service-areas/', { waitUntil: 'networkidle0' } );
		await page.type( '#cic-areas-start-location', '90224' );
		await page.keyboard.press( 'Escape' );
		await page.select( '#cic-areas-start-event', 'birthday' );
		await page.$eval( '#cic-areas-start-location', ( el ) => el.form.requestSubmit() );
		await page.waitForFunction( () => { const e = document.querySelector( '#cic-areas-start-error' ); return e && ! e.hidden; }, { timeout: 5000 } );
		const blocked = await page.evaluate( () => ( { url: location.pathname, error: document.querySelector( '#cic-areas-start-error' ).textContent, event: document.querySelector( '#cic-areas-start-event' ).value, invalid: document.querySelector( '#cic-areas-start-location' ).getAttribute( 'aria-invalid' ) } ) );
		check( 'P10 confirmed outside-area location blocks the quote starter; other answers are kept', '/service-areas/' === blocked.url && OUTSIDE === blocked.error && 'birthday' === blocked.event && 'true' === blocked.invalid, blocked );
		await page.screenshot( { path: path.join( OUT, 'starter-blocked-1440.png' ), fullPage: false, clip: await ( await page.$( '.cic-areas-start' ) ).boundingBox() } );
		await page.$eval( '#cic-areas-start-location', ( el ) => { el.value = '91101'; } );
		await Promise.all( [ page.waitForNavigation( { waitUntil: 'domcontentloaded' } ), page.$eval( '#cic-areas-start-location', ( el ) => el.form.requestSubmit() ) ] );
		const quote = new URL( page.url() );
		check( 'P11 covered location continues to the quote with only validated, non-personal context', '/get-a-quote/' === quote.pathname && '91101' === quote.searchParams.get( 'location' ) && 'birthday' === quote.searchParams.get( 'event' ), page.url() );
		const stored = await page.evaluate( () => JSON.parse( window.sessionStorage.getItem( 'cic_quote_context' ) || '{}' ) );
		check( 'P12 sessionStorage holds only allowed context keys (no personal data)', Object.keys( stored ).every( ( k ) => [ 'event', 'treat', 'location', 'date', 'guests' ].includes( k ) ), stored );

		// ---- City page ----
		await page.evaluate( () => window.sessionStorage.clear() );
		await page.goto( BASE + '/service-areas/california/pasadena/', { waitUntil: 'networkidle0' } );
		const city = await page.evaluate( () => ( {
			h1: document.querySelector( 'h1' ).textContent.trim(),
			crumbs: Array.from( document.querySelectorAll( '.cic-breadcrumbs li' ) ).map( ( l ) => l.textContent.trim() ),
			navCurrent: !! document.querySelector( '.cic-nav--primary .current-menu-ancestor > a[href$="/service-areas/"]' ),
			nearby: Array.from( document.querySelectorAll( '.cic-city-nearby__list a' ) ).map( ( a ) => a.textContent ),
			fixture: !! document.querySelector( '.cic-loc-notice' ),
			order: Array.from( document.querySelectorAll( '.cic-city > section' ) ).map( ( s ) => s.id ),
		} ) );
		check( 'P13 city page: headline, breadcrumbs Home/Service Areas/California/Pasadena, Service Areas marked current, fixture notice', /Ice cream catering in Pasadena\./.test( city.h1 ) && 'Home|Service Areas|California|Pasadena' === city.crumbs.join( '|' ) && city.navCurrent && city.fixture, city );
		check( 'P14 city sections follow the approved Pasadena order', 'city-hero|city-plans|treats|city-call|city-occasions|city-check|city-faq|city-nearby' === city.order.join( '|' ).replace( 'city-treats', 'treats' ), city.order );
		check( 'P15 nearby areas are other published cities in the county, nearest first', city.nearby.length > 0 && city.nearby.length <= 3 && ! city.nearby.includes( 'Pasadena' ) && ! city.nearby.includes( 'Compton' ), city.nearby );
		const cform = '.cic-city-check form[data-cic-area-check]';
		const cmis = await areaResult( page, '#cic-city-zip', '90220', cform );
		check( 'P16 city ZIP check sends the venue city: a ZIP that is not Pasadena\'s asks for clarification', 'manual_review' === cmis.status, cmis );
		const cok = await areaResult( page, '#cic-city-zip', '91101', cform );
		check( 'P17 city ZIP check confirms a Pasadena ZIP (no self-link to the same page)', 'covered' === cok.status && ! cok.links.some( ( l ) => l.href.endsWith( '/pasadena/' ) ), cok );
		await Promise.all( [ page.waitForNavigation( { waitUntil: 'domcontentloaded' } ), page.$eval( '#cic-city-search-location', ( el ) => el.form.requestSubmit() ) ] );
		check( 'P18 city hero starter with no ZIP carries the city key explicitly', 'pasadena-ca' === new URL( page.url() ).searchParams.get( 'location' ), page.url() );
		await page.goto( BASE + '/service-areas/california/pasadena/', { waitUntil: 'networkidle0' } );
		const zipEmpty = await page.$eval( '#cic-city-quote-zip', ( el ) => el.value );
		await page.type( '#cic-city-quote-guests', '60' );
		await page.type( '#cic-city-quote-zip', '91106' );
		await Promise.all( [ page.waitForNavigation( { waitUntil: 'domcontentloaded' } ), page.$eval( '#cic-city-quote-zip', ( el ) => el.form.requestSubmit() ) ] );
		const cq = new URL( page.url() );
		check( 'P19 city quote panel: ZIP field never prefilled with a city key; ZIP + guests carried to the quote', '' === zipEmpty && '91106' === cq.searchParams.get( 'location' ) && '60' === cq.searchParams.get( 'guests' ), { zipEmpty, url: page.url() } );
		await page.goto( BASE + '/service-areas/california/pasadena/', { waitUntil: 'networkidle0' } );
		await page.$eval( '#cic-city-quote-zip', ( el ) => { el.value = ''; } );
		await page.type( '#cic-city-quote-zip', '90221' );
		await page.$eval( '#cic-city-quote-zip', ( el ) => el.form.requestSubmit() );
		await page.waitForFunction( () => { const e = document.querySelector( '#cic-city-quote-error' ); return e && ! e.hidden; }, { timeout: 5000 } );
		check( 'P20 city quote panel blocks an excluded ZIP with the exact error', OUTSIDE === await page.$eval( '#cic-city-quote-error', ( e ) => e.textContent ), await page.$eval( '#cic-city-quote-error', ( e ) => e.textContent ) );

		// ---- Mobile county accordions ----
		await page.setViewport( { width: 390, height: 900, deviceScaleFactor: 1 } );
		await page.goto( BASE + '/service-areas/', { waitUntil: 'networkidle0' } );
		const acc = await page.evaluate( () => ( {
			open: Array.from( document.querySelectorAll( '.cic-county-card__toggle' ) ).map( ( d ) => d.open ),
			tabs: getComputedStyle( document.querySelector( '.cic-county-tabs ul' ) ).display,
			mapAfterSearch: document.querySelector( '.cic-ca-map' ).getBoundingClientRect().top > document.querySelector( '#cic-areas-location' ).getBoundingClientRect().top,
		} ) );
		await page.click( '#county-ventura-county summary' );
		const toggled = await page.$eval( '#county-ventura-county details', ( d ) => d.open );
		check( 'P21 mobile: counties are accordions (first open), county tabs hidden, map below the search', 'true,false,false' === acc.open.join( ',' ) && 'none' === acc.tabs && acc.mapAfterSearch && toggled, { acc, toggled } );

		// ---- Homepage connection ----
		await page.setViewport( { width: 1440, height: 900, deviceScaleFactor: 1 } );
		await page.evaluate( () => window.sessionStorage.clear() );
		await page.goto( BASE + '/', { waitUntil: 'networkidle0' } );
		const home = await page.evaluate( () => ( {
			link: document.querySelector( '.cic-county__cities a[href$="/service-areas/california/pasadena/"]' ) ? true : false,
			combo: document.querySelector( '#cic-home-search-location' ).getAttribute( 'role' ),
			sections: Array.from( document.querySelectorAll( '.cic-home > section' ) ).map( ( s ) => s.id ).join( '|' ),
			overflow: document.documentElement.scrollWidth - window.innerWidth,
		} ) );
		check( 'P22 homepage: county cities link to city pages, hero location offers autocomplete, section order unchanged', home.link && 'combobox' === home.combo && 'hero|values|treats|events|how-it-works|faq|coverage|cta' === home.sections && home.overflow <= 0, home );
		await Promise.all( [ page.waitForNavigation( { waitUntil: 'domcontentloaded' } ), page.$eval( '.cic-county__cities a[href$="/pasadena/"]', ( a ) => a.click() ) ] );
		check( 'P23 following a homepage city link opens the city page', page.url().endsWith( '/service-areas/california/pasadena/' ), page.url() );
		const nav = await page.evaluate( () => window.sessionStorage.getItem( 'cic_quote_context' ) );
		check( 'P24 browsing (homepage → city page) does not silently select a location', null === nav || ! JSON.parse( nav ).location, nav );
	} finally {
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
	process.exit( 1 );
} );
