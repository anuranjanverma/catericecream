/**
 * "Cities We Serve" prefooter, browser checks (2026-10-22): layout at 390/768/1440 (two columns on
 * phones, natural wrapping on desktop), keyboard focus, text contrast, axe-core on the section,
 * and the footer (content, logo, geometry) identical with the section on and off. The layout
 * option is restored in `finally`. One Edge instance, always closed.
 *
 *   NODE_PATH=<dir>/node_modules node dev/tests/cities/browser-test.cjs
 */
'use strict';

const puppeteer = require( 'puppeteer-core' );
const { execFileSync } = require( 'child_process' );
const fs = require( 'fs' );
const path = require( 'path' );

const BASE = 'https://catericecream.test';
const ROOT = path.resolve( __dirname, '../../..' );
const OUT = path.join( ROOT, 'dev/tests/output/cities' );
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
const setEnabled = ( on ) => wp( 'eval', `$o = get_option( CIC\\Site\\Layout::OPTION, array() ); $o = is_array( $o ) ? $o : array(); $o['cities'] = array_merge( CIC\\Site\\Layout::get()['cities'], array( 'enabled' => ${ on ? 'true' : 'false' } ) ); update_option( CIC\\Site\\Layout::OPTION, $o );` );

let browser;
( async () => {
	const saved = last( wp( 'eval', 'echo wp_json_encode( get_option( CIC\\Site\\Layout::OPTION, "__absent__" ) );' ) );
	try {
		setEnabled( true );
		browser = await puppeteer.launch( { executablePath: BROWSER, headless: true, acceptInsecureCerts: true } );
		const page = await browser.newPage();
		const errors = [];
		page.on( 'pageerror', ( e ) => errors.push( e.message ) );
		const footerGeo = () => page.evaluate( () => {
			const f = document.querySelector( '.cic-footer' );
			const logo = f.querySelector( '.cic-footer__logo img' );
			const r = ( el ) => { const b = el.getBoundingClientRect(); return [ Math.round( b.width ), Math.round( b.height ) ]; };
			return { html: f.innerHTML.length, text: f.innerText, footer: r( f ), logo: r( logo ), logoSrc: logo.getAttribute( 'src' ), bg: getComputedStyle( f ).backgroundColor };
		} );
		const on = {};
		for ( const width of [ 1440, 768, 390 ] ) {
			await page.setViewport( { width, height: 900 } );
			await page.goto( BASE + '/about/', { waitUntil: 'networkidle2' } );
			await page.evaluate( () => document.querySelector( '.cic-prefooter' ).scrollIntoView() );
			await new Promise( ( r ) => setTimeout( r, 300 ) );
			const info = await page.evaluate( () => {
				const lis = Array.from( document.querySelectorAll( '.cic-prefooter__list li' ) );
				const xs = [ ...new Set( lis.map( ( li ) => Math.round( li.getBoundingClientRect().left ) ) ) ];
				const ys = [ ...new Set( lis.map( ( li ) => Math.round( li.getBoundingClientRect().top ) ) ) ];
				const sec = document.querySelector( '.cic-prefooter' );
				const inner = sec.querySelector( '.cic-prefooter__inner' ).getBoundingClientRect();
				const grid = document.querySelector( '.cic-footer__grid' ).getBoundingClientRect();
				return {
					count: lis.length,
					columns: xs.length,
					rows: ys.length,
					overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
					minTarget: Math.min( ...Array.from( sec.querySelectorAll( 'a' ) ).map( ( a ) => a.getBoundingClientRect().height ) ),
					sameWidth: Math.abs( inner.width - grid.width ) <= 1 && Math.abs( inner.left - grid.left ) <= 1,
					border: getComputedStyle( sec.querySelector( '.cic-prefooter__inner' ) ).borderBottomStyle,
					bgSame: getComputedStyle( sec ).backgroundColor === getComputedStyle( document.querySelector( '.cic-footer' ) ).backgroundColor,
					images: sec.querySelectorAll( 'img, picture, svg, iframe' ).length,
					animated: Array.from( sec.querySelectorAll( '*' ) ).some( ( e ) => 'none' !== getComputedStyle( e ).animationName ),
				};
			} );
			check( `L1 ${ width } px: 20 links, no overflow, ≥44 px targets, footer width and background, divider, no images or animation`, 20 === info.count && 0 === info.overflow && info.minTarget >= 44 && info.sameWidth && info.bgSame && 'solid' === info.border && 0 === info.images && ! info.animated, info );
			if ( 390 === width ) {
				check( 'L2 390 px: two readable columns', 2 === info.columns, info );
			} else {
				check( `L2 ${ width } px: natural wrapping (several links per row, few rows)`, info.columns > 2 && info.rows < 10, info );
			}
			await ( await page.$( '.cic-prefooter' ) ).screenshot( { path: path.join( OUT, `prefooter-${ width }.png` ) } );
			on[ width ] = await footerGeo();
		}

		/* Keyboard focus, contrast, axe. */
		await page.setViewport( { width: 1440, height: 900 } );
		await page.goto( BASE + '/about/', { waitUntil: 'networkidle2' } );
		await page.focus( '.cic-prefooter__list a' );
		await page.keyboard.press( 'Tab' );
		await page.keyboard.down( 'Shift' );
		await page.keyboard.press( 'Tab' );
		await page.keyboard.up( 'Shift' );
		const focus = await page.evaluate( () => {
			const a = document.activeElement;
			const s = getComputedStyle( a );
			return { inList: !! a.closest( '.cic-prefooter__list' ), visible: a.matches( ':focus-visible' ), outline: s.outlineStyle + ' ' + s.outlineWidth, underline: s.textDecorationLine };
		} );
		check( 'K1 keyboard focus on a city link is clearly visible (outline + underline)', focus.inList && focus.visible && focus.outline.startsWith( 'solid' ) && 'underline' === focus.underline, focus );
		await ( await page.$( '.cic-prefooter' ) ).screenshot( { path: path.join( OUT, 'prefooter-focus-1440.png' ) } );
		const contrast = await page.evaluate( () => {
			const rgb = ( c ) => c.match( /[\d.]+/g ).map( Number );
			const lum = ( [ r, g, b ] ) => [ r, g, b ].map( ( v ) => { v /= 255; return v <= 0.03928 ? v / 12.92 : ( ( v + 0.055 ) / 1.055 ) ** 2.4; } ).reduce( ( s, v, i ) => s + v * [ 0.2126, 0.7152, 0.0722 ][ i ], 0 );
			const a = document.querySelector( '.cic-prefooter__list a' );
			const bg = rgb( getComputedStyle( document.querySelector( '.cic-prefooter' ) ).backgroundColor );
			const fgc = rgb( getComputedStyle( a ).color );
			const op = Number( getComputedStyle( a ).opacity );
			const fg = fgc.slice( 0, 3 ).map( ( v, i ) => v * op + bg[ i ] * ( 1 - op ) );
			const [ l1, l2 ] = [ lum( fg ), lum( bg ) ].sort( ( x, y ) => y - x );
			return Math.round( ( ( l1 + 0.05 ) / ( l2 + 0.05 ) ) * 100 ) / 100;
		} );
		check( 'K2 city link text contrast ≥ 4.5:1 against the prefooter background (' + contrast + ':1)', contrast >= 4.5, contrast );
		await page.addScriptTag( { path: AXE } );
		const axe = await page.evaluate( async () => ( await window.axe.run( '.cic-prefooter', { runOnly: [ 'wcag2a', 'wcag2aa', 'wcag21aa', 'best-practice' ] } ) ).violations.map( ( v ) => v.id ) );
		check( 'K3 axe-core: no violations in the section', 0 === axe.length, axe );

		/* Footer unchanged with the section on vs off. */
		setEnabled( false );
		for ( const width of [ 1440, 768, 390 ] ) {
			await page.setViewport( { width, height: 900 } );
			await page.goto( BASE + '/about/', { waitUntil: 'networkidle2' } );
			const none = await page.$( '.cic-prefooter' );
			const off = await footerGeo();
			check( `F1 ${ width } px: the footer (content, logo file, logo and footer size, background) is identical with the section on and off`, null === none && JSON.stringify( off ) === JSON.stringify( on[ width ] ), { on: on[ width ], off } );
		}
		check( 'X1 no JavaScript errors', 0 === errors.length, errors );
	} catch ( e ) {
		check( 'run completed without an exception', false, e.stack );
	} finally {
		if ( browser ) {
			await browser.close().catch( () => {} );
		}
		const file = path.join( OUT, 'layout-saved.json' );
		fs.writeFileSync( file, saved );
		wp( 'eval', `$v = json_decode( file_get_contents( ${ JSON.stringify( file ) } ), true ); "__absent__" === $v ? delete_option( CIC\\Site\\Layout::OPTION ) : update_option( CIC\\Site\\Layout::OPTION, $v );` );
		fs.unlinkSync( file );
		const now = last( wp( 'eval', 'echo wp_json_encode( get_option( CIC\\Site\\Layout::OPTION, "__absent__" ) );' ) );
		check( 'Z1 layout settings restored', now === saved );
		console.log( `\nCities We Serve browser checks: ${ results.filter( Boolean ).length }/${ results.length } passed` );
	}
} )();
