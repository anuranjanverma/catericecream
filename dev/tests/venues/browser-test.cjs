/**
 * "Popular Event Venues" browser checks (2026-11-21): on a city page (Pasadena) and the Wedding page
 * at 390 / 768 / 1440 px — names per row (2 / 3 / 4), no horizontal scroll, touch-free plain text,
 * section order, axe on the section, and screenshots in dev/tests/output/venues. Read-only: it
 * changes no data (local drafts are visible by design). The browser is closed in `finally`.
 *
 * Usage: node dev/tests/venues/browser-test.cjs
 */
const puppeteer = require( 'puppeteer-core' );
const { execFileSync } = require( 'child_process' );
const fs = require( 'fs' );
const path = require( 'path' );

const ROOT = path.resolve( __dirname, '../../..' );
const OUT = path.join( ROOT, 'dev/tests/output/venues' );
const WP = 'C:\\laragon\\bin\\wp-cli\\wp.cmd';
const BROWSER = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const AXE = require.resolve( 'axe-core/axe.min.js' );
fs.mkdirSync( OUT, { recursive: true } );

const results = [];
const check = ( label, ok, detail ) => {
	results.push( !! ok );
	console.log( ( ok ? 'PASS ' : 'FAIL ' ) + label + ( ! ok && detail !== undefined ? ' — ' + JSON.stringify( detail ).slice( 0, 600 ) : '' ) );
};
const wp = ( ...args ) => execFileSync( 'cmd', [ '/c', WP, ...args ], { cwd: ROOT, encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'ignore' ], timeout: 120000 } );
const last = ( s ) => s.trim().split( '\n' ).pop();

let browser;
( async () => {
	try {
		const urls = JSON.parse( last( wp( 'eval', '$c = ( new CIC\\Locations\\DatabaseRepository() )->find_city( "pasadena-ca" ); echo wp_json_encode( array( "city" => CIC\\Locations\\DatabaseRepository::url( $c["state_slug"], $c["slug"] ), "wedding" => get_permalink( CIC\\Content\\Catalog::find_by_key( "cic_event", "wedding" ) ) ) );' ) ) );
		browser = await puppeteer.launch( { executablePath: BROWSER, headless: true, acceptInsecureCerts: true } );
		const page = await browser.newPage();
		const pages = [ [ 'city', urls.city, 12, '.cic-city-nearby' ], [ 'wedding', urls.wedding, 32, null ] ];
		const widths = [ [ 390, 2 ], [ 768, 3 ], [ 1440, 4 ] ];
		for ( const [ name, url, max, after ] of pages ) {
			for ( const [ w, cols ] of widths ) {
				await page.setViewport( { width: w, height: 900 } );
				await page.goto( url, { waitUntil: 'networkidle2', timeout: 60000 } );
				const info = await page.evaluate( ( afterSel ) => {
					const sec = document.querySelector( 'section.cic-venues' );
					if ( ! sec ) {
						return null;
					}
					const items = [ ...sec.querySelectorAll( '.cic-venues__item' ) ];
					const top = items.length ? Math.round( items[ 0 ].getBoundingClientRect().top ) : 0;
					const next = afterSel ? document.querySelector( afterSel ) : null;
					return {
						heading: sec.querySelector( 'h2' )?.textContent.trim(),
						count: items.length,
						firstRow: items.filter( ( i ) => Math.abs( Math.round( i.getBoundingClientRect().top ) - top ) < 2 ).length,
						links: sec.querySelectorAll( 'a' ).length,
						overflow: document.documentElement.scrollWidth > window.innerWidth + 1,
						clipped: items.some( ( i ) => i.scrollWidth > i.clientWidth + 1 ),
						beforeNext: next ? !! ( sec.compareDocumentPosition( next ) & Node.DOCUMENT_POSITION_FOLLOWING ) : true,
					};
				}, after );
				const tag = `${ name } @${ w }`;
				if ( ! info ) {
					check( `${ tag }: section present`, false );
					continue;
				}
				check( `${ tag }: heading "Popular Event Venues", 1–${ max } names, no links`, 'Popular Event Venues' === info.heading && info.count >= 1 && info.count <= max && 0 === info.links, info );
				check( `${ tag }: ${ cols } names per row`, info.firstRow === Math.min( cols, info.count ), info.firstRow );
				check( `${ tag }: no horizontal scroll, no clipped names`, ! info.overflow && ! info.clipped, info );
				if ( after ) {
					check( `${ tag }: sits directly above "Explore nearby areas"`, info.beforeNext );
				}
				const el = await page.$( 'section.cic-venues' );
				await el.screenshot( { path: path.join( OUT, `${ name }-${ w }.png` ) } );
				if ( 1440 === w ) {
					await page.addScriptTag( { path: AXE } );
					const axe = await page.evaluate( async () => ( await window.axe.run( 'section.cic-venues', { runOnly: [ 'wcag2a', 'wcag2aa' ] } ) ).violations.map( ( v ) => v.id ) );
					check( `${ tag }: axe (WCAG A/AA) finds no violations in the section`, 0 === axe.length, axe );
				}
			}
		}
	} catch ( e ) {
		check( 'run completed without an exception', false, String( e && e.message ) );
	} finally {
		if ( browser ) {
			await browser.close();
		}
		console.log( `Venue browser checks: ${ results.filter( Boolean ).length }/${ results.length } passed` );
		process.exitCode = results.every( Boolean ) ? 0 : 1;
	}
} )();
