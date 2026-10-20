/**
 * Phase 09 interaction checks not covered by earlier suites (local only; one browser, closed in
 * finally). Automated checks — they support, but do not replace, manual and assistive-technology
 * testing.
 *   I1 no sticky/fixed element covers a focused form field (quote, contact, home search) at 390/768/1440
 *   I2 every element reached by Tab shows a visible focus indicator (home, quote, contact, an event page)
 *   I3 reduced motion: no running CSS animations/transitions and smooth scrolling off
 *   I4 FAQ and county accordions open/close with the keyboard and expose their state
 *   I5 image delivery: srcset/sizes on content images; flags images far larger than rendered
 *
 *   NODE_PATH=<dir>/node_modules node dev/tests/phase09/interaction-test.cjs
 */
'use strict';

const puppeteer = require( 'puppeteer-core' );
const fs = require( 'fs' );
const path = require( 'path' );

const BASE = 'https://catericecream.test';
const OUT = path.resolve( __dirname, '../../output/phase09' );
const BROWSER = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
fs.mkdirSync( OUT, { recursive: true } );
const results = [];
const check = ( label, ok, detail ) => {
	results.push( !! ok );
	console.log( ( ok ? 'PASS ' : 'FAIL ' ) + label + ( ! ok && detail !== undefined ? ' — ' + JSON.stringify( detail ).slice( 0, 900 ) : '' ) );
};

let browser;
( async () => {
	try {
		browser = await puppeteer.launch( { executablePath: BROWSER, headless: true, acceptInsecureCerts: true } );
		const page = await browser.newPage();

		// I1 sticky/fixed elements never cover a focused field.
		const covered = [];
		for ( const w of [ 390, 768, 1440 ] ) {
			await page.setViewport( { width: w, height: 390 === w ? 844 : 900 } );
			for ( const url of [ '/get-a-quote/', '/contact/', '/' ] ) {
				await page.goto( BASE + url, { waitUntil: 'networkidle2' } );
				await page.evaluate( () => window.scrollTo( 0, 400 ) );
				for ( let i = 0; i < 40; i++ ) {
					await page.keyboard.press( 'Tab' );
					const r = await page.evaluate( () => {
						const a = document.activeElement;
						if ( ! a || ! a.matches( 'input:not([type=hidden]),select,textarea' ) ) {
							return null;
						}
						const b = a.getBoundingClientRect();
						const x = Math.min( window.innerWidth - 2, Math.max( 1, b.left + Math.min( 20, b.width / 2 ) ) );
						const y = b.top + b.height / 2;
						const hit = document.elementFromPoint( x, y );
						const visibleBox = a.type === 'radio' || a.type === 'checkbox' ? a.closest( 'label' ) || a : a;
						const ok = hit && ( hit === a || visibleBox.contains( hit ) || a.contains( hit ) || ( a.labels && Array.from( a.labels ).some( ( l ) => l.contains( hit ) ) ) || hit.closest( '.wpcf7-form-control-wrap' ) === a.closest( '.wpcf7-form-control-wrap' ) );
						return { name: a.name || a.id, ok: !! ok || y < 0 || y > window.innerHeight, by: hit ? hit.nodeName + '.' + String( hit.className ).split( ' ' )[ 0 ] : 'none' };
					} );
					if ( r && ! r.ok ) {
						covered.push( `${ url }@${ w } ${ r.name } covered by ${ r.by }` );
					}
				}
			}
		}
		check( 'I1 no sticky or fixed element covers a focused form field (quote, contact, home search; 390/768/1440)', ! covered.length, covered );

		// I2 visible focus on every tab stop.
		const noRing = [];
		await page.setViewport( { width: 1440, height: 900 } );
		for ( const url of [ '/', '/get-a-quote/', '/contact/', '/events/birthday/', '/treats/dippin-dots/', '/service-areas/california/pasadena/' ] ) {
			await page.goto( BASE + url, { waitUntil: 'networkidle2' } );
			for ( let i = 0; i < 45; i++ ) {
				await page.keyboard.press( 'Tab' );
				const f = await page.evaluate( () => {
					const a = document.activeElement;
					// Date/time inputs: Tab moves between their inner segments, which makes this automated
					// reading unreliable. Their focus ring was verified by manual inspection (Phase 09 log).
					if ( ! a || a === document.body || ( 'INPUT' === a.nodeName && /^(date|time)$/.test( a.type ) ) ) {
						return null;
					}
					// The ring may be drawn on the control, its visual twin (custom tiles/cards) or a wrapper (:focus-within).
					const has = ( el ) => { const s = getComputedStyle( el ); return ( 'none' !== s.outlineStyle && parseFloat( s.outlineWidth ) > 0 ) || ( 'none' !== s.boxShadow && /\d/.test( s.boxShadow ) ); };
					const cands = [ a ];
					if ( a.nextElementSibling && a.matches( '.cic-tile__input,.cic-treat__input,.cic-unsure__input' ) ) {
						cands.push( a.nextElementSibling );
					}
					for ( let p = a.parentElement, n = 0; p && n < 4; p = p.parentElement, n++ ) {
						cands.push( p );
					}
					const ring = cands.some( has );
					return { ring, el: a.nodeName + '.' + String( a.className ).split( ' ' ).join( '.' ) + ' name=' + ( a.name || '' ) + ( a.textContent ? ' "' + a.textContent.trim().slice( 0, 20 ) + '"' : '' ) };
				} );
				if ( f && ! f.ring && noRing.length < 15 ) {
					noRing.push( url + ' ' + f.el );
				}
			}
		}
		check( 'I2 every element reached by Tab shows a visible focus indicator (6 page types, first 45 stops each)', ! noRing.length, noRing );

		// I3 reduced motion.
		await page.emulateMediaFeatures( [ { name: 'prefers-reduced-motion', value: 'reduce' } ] );
		const motion = [];
		for ( const url of [ '/', '/events/birthday/', '/events/wedding/', '/treats/dippin-dots/', '/get-a-quote/' ] ) {
			await page.goto( BASE + url, { waitUntil: 'networkidle2' } );
			await page.evaluate( () => window.scrollTo( 0, 600 ) );
			await new Promise( ( r ) => setTimeout( r, 300 ) );
			const m = await page.evaluate( () => ( {
				anims: document.getAnimations().filter( ( a ) => 'running' === a.playState && a.effect && a.effect.getTiming().duration > 50 ).map( ( a ) => ( a.animationName || a.transitionProperty || 'anim' ) + ' on ' + ( a.effect.target ? a.effect.target.className : '' ) ),
				smooth: getComputedStyle( document.documentElement ).scrollBehavior,
			} ) );
			if ( m.anims.length || 'smooth' === m.smooth ) {
				motion.push( url + ' ' + JSON.stringify( m ) );
			}
		}
		await page.emulateMediaFeatures( [] );
		check( 'I3 with reduced motion: no running animations or smooth scrolling (home, Birthday, Wedding, a treat, quote)', ! motion.length, motion );
		const endless = [];
		for ( const url of [ '/', '/events/birthday/', '/events/wedding/', '/events/anniversary/', '/events/corporate/', '/treats/dippin-dots/' ] ) {
			await page.goto( BASE + url, { waitUntil: 'networkidle2' } );
			const e = await page.evaluate( () => document.getAnimations().filter( ( a ) => { const t = a.effect && a.effect.getComputedTiming(); return t && ( t.endTime === Infinity || t.endTime > 5000 ); } ).map( ( a ) => ( a.animationName || 'anim' ) + ' ' + Math.round( a.effect.getComputedTiming().endTime ) ) );
			if ( e.length ) {
				endless.push( url + ' ' + e.join( ',' ) );
			}
		}
		check( 'I3b with motion allowed, no animation runs longer than 5 s (no endless decorative motion)', ! endless.length, endless );

		// I4 accordions with the keyboard.
		await page.setViewport( { width: 390, height: 844 } );
		const acc = [];
		for ( const [ url, sel ] of [ [ '/contact/', '.cic-accordion__item summary' ], [ '/events/birthday/', 'details summary' ], [ '/service-areas/', '.cic-county__toggle summary, .cic-county-card__toggle summary' ] ] ) {
			await page.goto( BASE + url, { waitUntil: 'networkidle2' } );
			const has = await page.$( sel );
			if ( ! has ) {
				acc.push( url + ' no accordion' );
				continue;
			}
			const before = await page.$eval( sel, ( s ) => s.parentElement.open );
			await page.focus( sel );
			await page.keyboard.press( 'Enter' );
			const after = await page.$eval( sel, ( s ) => s.parentElement.open );
			await page.keyboard.press( 'Space' );
			const back = await page.$eval( sel, ( s ) => s.parentElement.open );
			if ( before === after || after === back ) {
				acc.push( url + ` ${ before }/${ after }/${ back }` );
			}
		}
		check( 'I4 FAQ and county accordions toggle with Enter and Space (native details/summary exposes expanded state)', ! acc.length, acc );

		// I5 image delivery.
		const heavy = [];
		const noSrcset = [];
		for ( const w of [ 390, 1440 ] ) {
			await page.setViewport( { width: w, height: 900 } );
			for ( const url of [ '/', '/events/', '/events/birthday/', '/treats/', '/treats/frozen-yogurt/', '/service-areas/california/pasadena/', '/about/' ] ) {
				await page.goto( BASE + url, { waitUntil: 'networkidle2' } );
				await page.evaluate( async () => {
					for ( let y = 0; y < document.body.scrollHeight; y += 700 ) {
						window.scrollTo( 0, y );
						await new Promise( ( r ) => setTimeout( r, 80 ) );
					}
				} );
				await new Promise( ( r ) => setTimeout( r, 600 ) );
				const r = await page.evaluate( () => {
					const res = Object.fromEntries( performance.getEntriesByType( 'resource' ).map( ( e ) => [ e.name, e.transferSize || e.encodedBodySize ] ) );
					return Array.from( document.images ).filter( ( i ) => i.offsetWidth > 40 ).map( ( i ) => ( { src: ( i.currentSrc || i.src ).split( '/' ).pop(), shown: i.offsetWidth, natural: i.naturalWidth, srcset: !! i.srcset, kb: Math.round( ( res[ i.currentSrc ] || 0 ) / 1024 ) } ) );
				} );
				r.forEach( ( i ) => {
					if ( i.natural > i.shown * 2.6 && i.kb > 60 ) {
						heavy.push( `${ url }@${ w } ${ i.src } ${ i.natural }px for ${ i.shown }px (${ i.kb } KB)` );
					}
					if ( ! i.srcset && i.natural > 400 && ! /logo|mascot/.test( i.src ) ) {
						noSrcset.push( `${ url } ${ i.src }` );
					}
				} );
			}
		}
		check( 'I5a content images use srcset/sizes', ! noSrcset.length, Array.from( new Set( noSrcset ) ) );
		check( 'I5b no image is downloaded at more than ~2.6× its displayed width (over 60 KB)', ! heavy.length, heavy );
	} catch ( e ) {
		check( 'Unexpected error: ' + e.message, false );
	} finally {
		if ( browser ) {
			await browser.close().catch( () => {} );
		}
		const passed = results.filter( Boolean ).length;
		console.log( `Phase 09 interaction checks: ${ passed }/${ results.length } passed` );
		process.exitCode = passed === results.length ? 0 : 1;
	}
} )();
