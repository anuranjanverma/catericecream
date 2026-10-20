/**
 * Phase 09 automated audit (local only; one Edge instance, sequential, always closed).
 * For every public page type at 390, 768 and 1440 px: full-page screenshot, axe-core
 * (WCAG 2.x A/AA rules — automated checks only, not a compliance claim), horizontal overflow,
 * layout shift (CLS incl. scrolling), LCP element/time and its loading attribute, broken images,
 * missing alt, heading structure, duplicate ids, touch targets (390 px), page weight by type,
 * commerce scripts/controls. Then every internal link found is requested once.
 * Conditions: local Laragon server, no network throttling, headless Edge — timings are indicative
 * only, not field performance.
 *
 *   NODE_PATH=<dir>/node_modules node dev/tests/phase09/audit.cjs [width,width,...]
 * Writes dev/tests/output/phase09/audit.json and screenshots.
 */
'use strict';

const puppeteer = require( 'puppeteer-core' );
const https = require( 'https' );
const fs = require( 'fs' );
const path = require( 'path' );

const BASE = 'https://catericecream.test';
const ROOT = path.resolve( __dirname, '../../..' );
const OUT = path.join( ROOT, 'dev/tests/output/phase09' );
const BROWSER = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const AXE = require.resolve( 'axe-core/axe.min.js' );
fs.mkdirSync( OUT, { recursive: true } );

const PAGES = [
	[ 'home', '/' ],
	[ 'events', '/events/' ],
	[ 'event-birthday', '/events/birthday/' ],
	[ 'event-wedding', '/events/wedding/' ],
	[ 'event-anniversary', '/events/anniversary/' ],
	[ 'event-corporate', '/events/corporate/' ],
	[ 'treats', '/treats/' ],
	[ 'treat-dots', '/treats/dippin-dots/' ],
	[ 'treat-bananas', '/treats/dipn-bananas/' ],
	[ 'treat-classic', '/treats/classic-ice-cream/' ],
	[ 'treat-yogurt', '/treats/frozen-yogurt/' ],
	[ 'service-areas', '/service-areas/' ],
	[ 'state', '/service-areas/california/' ],
	[ 'city', '/service-areas/california/pasadena/' ],
	[ 'about', '/about/' ],
	[ 'contact', '/contact/' ],
	[ 'quote', '/get-a-quote/' ],
	[ '404', '/no-such-page-phase09/' ],
];
const WIDTHS = ( process.argv[ 2 ] || '390,768,1440' ).split( ',' ).map( Number );

const head = ( url ) => new Promise( ( resolve ) => {
	https.get( url, { rejectUnauthorized: false, agent: false, headers: { 'User-Agent': 'Mozilla/5.0 CIC-Phase09' } }, ( res ) => {
		res.resume();
		resolve( res.statusCode );
	} ).on( 'error', () => resolve( 0 ) );
} );

( async () => {
	const report = { conditions: 'local Laragon (Apache + PHP 8.5.1), no throttling, headless Edge, cache disabled per page', pages: {}, links: {} };
	const links = new Set();
	const browser = await puppeteer.launch( { executablePath: BROWSER, headless: true, acceptInsecureCerts: true } );
	try {
		const page = await browser.newPage();
		await page.setCacheEnabled( false );
		await page.evaluateOnNewDocument( () => {
			window.__cls = 0;
			window.__shifts = [];
			window.__lcp = null;
			new PerformanceObserver( ( l ) => {
				l.getEntries().forEach( ( e ) => {
					if ( ! e.hadRecentInput ) {
						window.__cls += e.value;
						if ( e.value > 0.01 ) {
							window.__shifts.push( { value: +e.value.toFixed( 3 ), nodes: ( e.sources || [] ).map( ( s ) => s.node && s.node.nodeName + ( s.node.className && 'string' === typeof s.node.className ? '.' + s.node.className.split( ' ' )[ 0 ] : '' ) ) } );
						}
					}
				} );
			} ).observe( { type: 'layout-shift', buffered: true } );
			new PerformanceObserver( ( l ) => {
				const e = l.getEntries().pop();
				if ( e ) {
					const el = e.element;
					window.__lcp = { time: Math.round( e.startTime ), tag: el ? el.nodeName : '', loading: el && el.getAttribute ? el.getAttribute( 'loading' ) : '', fetchpriority: el && el.getAttribute ? el.getAttribute( 'fetchpriority' ) : '', src: el && el.currentSrc ? el.currentSrc.split( '/' ).pop() : '' };
				}
			} ).observe( { type: 'largest-contentful-paint', buffered: true } );
		} );
		// Warm-up: the first navigation in a fresh browser pays ~1.2 s of connection/TLS start-up
		// (measured), which would otherwise be attributed to whichever page is first.
		await page.goto( BASE + '/wp-json/', { waitUntil: 'load' } ).catch( () => {} );
		for ( const [ slug, url ] of PAGES ) {
			for ( const w of WIDTHS ) {
				await page.setViewport( { width: w, height: 390 === w ? 844 : ( 768 === w ? 1024 : 900 ) } );
				const resp = await page.goto( BASE + url, { waitUntil: 'networkidle2', timeout: 60000 } );
				await new Promise( ( r ) => setTimeout( r, 400 ) );
				const lcp = await page.evaluate( () => window.__lcp );
				// Scroll through the page so lazy images load (CLS from late images counts).
				await page.evaluate( async () => {
					for ( let y = 0; y < document.body.scrollHeight; y += Math.round( window.innerHeight * 0.8 ) ) {
						window.scrollTo( 0, y );
						await new Promise( ( r ) => setTimeout( r, 120 ) );
					}
					window.scrollTo( 0, 0 );
				} );
				await page.waitForNetworkIdle( { idleTime: 400, timeout: 15000 } ).catch( () => {} );
				await page.screenshot( { path: path.join( OUT, `${ slug }-${ w }.png` ), fullPage: true } );
				const data = await page.evaluate( ( mobile ) => {
					const vis = ( e ) => { const r = e.getBoundingClientRect(); const s = getComputedStyle( e ); return r.width > 0 && r.height > 0 && 'hidden' !== s.visibility && 'none' !== s.display; };
					const sel = ( e ) => e.nodeName.toLowerCase() + ( e.id ? '#' + e.id : '' ) + ( e.className && 'string' === typeof e.className ? '.' + e.className.trim().split( /\s+/ ).slice( 0, 2 ).join( '.' ) : '' );
					const W = document.documentElement.clientWidth;
					const overflow = [];
					document.querySelectorAll( 'body *' ).forEach( ( e ) => {
						if ( ! vis( e ) ) {
							return;
						}
						const r = e.getBoundingClientRect();
						let p = e.parentElement;
						let clipped = false;
						while ( p && p !== document.body ) {
							const ox = getComputedStyle( p ).overflowX;
							if ( 'hidden' === ox || 'auto' === ox || 'scroll' === ox || 'clip' === ox ) {
								clipped = true;
								break;
							}
							p = p.parentElement;
						}
						if ( ! clipped && r.right > W + 1 && overflow.length < 6 ) {
							overflow.push( sel( e ) + ' right=' + Math.round( r.right ) );
						}
					} );
					const imgs = Array.from( document.images );
					const ids = {};
					document.querySelectorAll( '[id]' ).forEach( ( e ) => { ids[ e.id ] = ( ids[ e.id ] || 0 ) + 1; } );
					const hs = Array.from( document.querySelectorAll( 'h1,h2,h3,h4,h5,h6' ) ).filter( vis ).map( ( h ) => +h.nodeName[ 1 ] );
					const skips = hs.filter( ( n, i ) => i > 0 && n - hs[ i - 1 ] > 1 ).length;
					const small = [];
					if ( mobile ) {
						document.querySelectorAll( 'a[href],button,input:not([type=hidden]),select,textarea,summary,[role=button]' ).forEach( ( e ) => {
							if ( ! vis( e ) || e.closest( '.cic-hp,.screen-reader-text,.skip-link' ) ) {
								return;
							}
							const r = e.getBoundingClientRect();
							const inline = 'A' === e.nodeName && 'inline' === getComputedStyle( e ).display && e.closest( 'p,li,dd,figcaption' ) && ( e.closest( 'p,li,dd,figcaption' ).textContent.trim().length > e.textContent.trim().length + 15 );
							// A stretched link (::after covering its card) is as large as the card.
							if ( 'A' === e.nodeName && 'absolute' === getComputedStyle( e, '::after' ).position ) {
								return;
							}
							const control = 'INPUT' === e.nodeName && /checkbox|radio/.test( e.type );
							const labelled = control && e.closest( 'label' ) && e.closest( 'label' ).getBoundingClientRect().height >= 44;
							if ( ! inline && ! labelled && ( r.height < 44 || r.width < 24 ) && small.length < 12 ) {
								small.push( sel( e ) + ' ' + Math.round( r.width ) + 'x' + Math.round( r.height ) );
							}
						} );
					}
					const res = performance.getEntriesByType( 'resource' );
					const nav = performance.getEntriesByType( 'navigation' )[ 0 ];
					const bytes = { html: nav ? nav.transferSize : 0, css: 0, js: 0, font: 0, img: 0, other: 0 };
					const fonts = [];
					res.forEach( ( r ) => {
						const u = r.name.split( '?' )[ 0 ];
						const t = /\.css$/.test( u ) ? 'css' : /\.js$/.test( u ) ? 'js' : /\.(woff2?|ttf|otf)$/.test( u ) ? 'font' : /\.(webp|png|jpe?g|svg|gif|avif)$/.test( u ) ? 'img' : 'other';
						bytes[ t ] += r.transferSize || r.encodedBodySize || 0;
						if ( 'font' === t ) {
							fonts.push( u.split( '/' ).pop() );
						}
					} );
					const scripts = Array.from( document.scripts ).map( ( s ) => s.src ).filter( Boolean );
					return {
						title: document.title,
						h1: document.querySelectorAll( 'h1' ).length,
						headingSkips: skips,
						overflow,
						scrollWidth: document.documentElement.scrollWidth,
						width: W,
						brokenImages: imgs.filter( ( i ) => i.complete && 0 === i.naturalWidth && i.getAttribute( 'src' ) ).map( ( i ) => i.getAttribute( 'src' ).split( '/' ).pop() ),
						noAlt: imgs.filter( ( i ) => ! i.hasAttribute( 'alt' ) ).map( ( i ) => ( i.getAttribute( 'src' ) || '' ).split( '/' ).pop() ),
						noDims: imgs.filter( ( i ) => ! i.getAttribute( 'width' ) || ! i.getAttribute( 'height' ) ).map( ( i ) => ( i.getAttribute( 'src' ) || '' ).split( '/' ).pop() ).slice( 0, 6 ),
						lazyInFirstViewport: imgs.filter( ( i ) => 'lazy' === i.getAttribute( 'loading' ) && i.getBoundingClientRect().top < window.innerHeight && i.getBoundingClientRect().bottom > 0 && vis( i ) ).map( ( i ) => ( i.currentSrc || '' ).split( '/' ).pop() ),
						dupIds: Object.keys( ids ).filter( ( k ) => ids[ k ] > 1 ),
						smallTargets: small,
						cls: +window.__cls.toFixed( 4 ),
						shifts: window.__shifts.slice( 0, 5 ),
						bytes,
						fonts: Array.from( new Set( fonts ) ),
						commerceScripts: scripts.filter( ( s ) => /woocommerce|wc-|cart-fragments|wc_/.test( s ) ).map( ( s ) => s.split( '/' ).pop().split( '?' )[ 0 ] ),
						commerceControls: document.querySelectorAll( '.price, .add_to_cart_button, form.cart, [name="add-to-cart"], .woocommerce-mini-cart, .cart-contents, a[href*="/cart"], a[href*="/checkout"]' ).length,
						cf7Scripts: scripts.filter( ( s ) => /contact-form-7|swv/.test( s ) ).length,
						links: Array.from( document.querySelectorAll( 'a[href]' ) ).map( ( a ) => a.href ),
						skipLink: !! document.querySelector( 'a[href="#main"], a.skip-link, a[href="#content"]' ),
						lang: document.documentElement.lang,
					};
				}, 390 === w );
				data.links.forEach( ( l ) => {
					if ( l.startsWith( BASE ) && ! l.includes( '/wp-admin' ) && ! l.includes( '/wp-login' ) ) {
						links.add( l.split( '#' )[ 0 ] );
					}
				} );
				delete data.links;
				await page.addScriptTag( { path: AXE } );
				const axe = await page.evaluate( async () => {
					const r = await window.axe.run( document, { runOnly: { type: 'tag', values: [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa' ] }, resultTypes: [ 'violations' ] } );
					return r.violations.map( ( v ) => ( { id: v.id, impact: v.impact, count: v.nodes.length, sample: v.nodes.slice( 0, 3 ).map( ( n ) => n.target.join( ' ' ) + ( n.any[ 0 ] && n.any[ 0 ].data && n.any[ 0 ].data.contrastRatio ? ' (' + n.any[ 0 ].data.contrastRatio + ':1 ' + n.any[ 0 ].data.fgColor + ' on ' + n.any[ 0 ].data.bgColor + ')' : '' ) ) } ) );
				} );
				data.status = resp ? resp.status() : 0;
				data.lcp = lcp;
				data.axe = axe;
				report.pages[ `${ slug }@${ w }` ] = data;
				const flags = [];
				if ( data.overflow.length || data.scrollWidth > data.width + 1 ) {
					flags.push( 'overflow' );
				}
				if ( axe.length ) {
					flags.push( 'axe:' + axe.map( ( a ) => a.id + '×' + a.count ).join( ',' ) );
				}
				if ( data.cls > 0.1 ) {
					flags.push( 'cls=' + data.cls );
				}
				if ( data.lcp && 'lazy' === data.lcp.loading ) {
					flags.push( 'lcp-lazy' );
				}
				if ( data.brokenImages.length || data.noAlt.length ) {
					flags.push( 'img' );
				}
				if ( 1 !== data.h1 ) {
					flags.push( 'h1=' + data.h1 );
				}
				if ( data.dupIds.length ) {
					flags.push( 'dupid:' + data.dupIds.join( ',' ) );
				}
				if ( data.smallTargets.length ) {
					flags.push( 'targets=' + data.smallTargets.length );
				}
				if ( data.commerceScripts.length || data.commerceControls ) {
					flags.push( 'commerce' );
				}
				console.log( `${ slug }@${ w } ${ data.status } lcp=${ data.lcp ? data.lcp.time + 'ms ' + data.lcp.tag : '-' } cls=${ data.cls } kb=${ Math.round( Object.values( data.bytes ).reduce( ( a, b ) => a + b, 0 ) / 1024 ) } ${ flags.join( ' ' ) || 'ok' }` );
			}
		}
	} finally {
		await browser.close();
	}
	for ( const l of links ) {
		report.links[ l ] = await head( l );
	}
	const bad = Object.entries( report.links ).filter( ( [ , c ] ) => c >= 400 || 0 === c );
	console.log( `links checked: ${ links.size }, failing: ${ bad.length } ${ bad.map( ( [ u, c ] ) => c + ' ' + u ).join( ' | ' ) }` );
	fs.writeFileSync( path.join( OUT, 'audit' + ( process.argv[ 2 ] ? '-' + WIDTHS.join( '-' ) : '' ) + '.json' ), JSON.stringify( report, null, 1 ) );
} )();
