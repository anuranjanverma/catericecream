/**
 * City landing pages: representative browser checks (2026-11-16).
 *
 * For each sample city (shared-ZIP locality, Ventura city, island, curated major city) at 390 and
 * 1440 px: no horizontal scroll; one visible H1 naming the city; FAQ shows 4 questions; a tel:
 * phone link; the ZIP check answers for a served ZIP; the hero search hands the city key, event
 * and date to Get a Quote, which shows the readable "City, CA" label and the chosen occasion.
 *
 * Usage: NODE_PATH=<dir with puppeteer-core> node dev/tests/city-seo/browser-test.cjs
 * Read-only (no quote is submitted). Closes the browser on failure.
 */
const puppeteer = require( 'puppeteer-core' );

const BASE    = 'https://catericecream.test';
const BROWSER = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const CITIES  = [
	{ slug: 'willowbrook', name: 'Willowbrook', zip: '90061' },
	{ slug: 'ojai', name: 'Ojai', zip: '93023' },
	{ slug: 'avalon', name: 'Avalon', zip: '90704' },
	{ slug: 'los-angeles', name: 'Los Angeles', zip: '90012' },
];
let pass = 0, fail = 0;
const check = ( label, ok, detail ) => {
	ok ? pass++ : fail++;
	console.log( ( ok ? 'PASS ' : 'FAIL ' ) + label + ( ! ok && detail !== undefined ? ' — ' + JSON.stringify( detail ).slice( 0, 300 ) : '' ) );
};

( async () => {
	const browser = await puppeteer.launch( { executablePath: BROWSER, headless: 'new', args: [ '--ignore-certificate-errors' ] } );
	try {
		const page = await browser.newPage();
		for ( const c of CITIES ) {
			const url = `${ BASE }/service-areas/california/${ c.slug }-ice-cream-catering/`;
			for ( const width of [ 390, 1440 ] ) {
				await page.setViewport( { width, height: 900 } );
				const res = await page.goto( url, { waitUntil: 'networkidle2', timeout: 60000 } );
				const info = await page.evaluate( ( name ) => {
					const h1 = [ ...document.querySelectorAll( 'h1' ) ];
					const faq = document.querySelectorAll( '.cic-faq details, .cic-faq summary, [class*="faq"] details' );
					return {
						overflow: document.documentElement.scrollWidth > window.innerWidth + 1,
						h1: h1.length,
						h1Text: h1[ 0 ] ? h1[ 0 ].textContent.trim() : '',
						h1Visible: h1[ 0 ] ? h1[ 0 ].getBoundingClientRect().height > 0 : false,
						faq: [ ...document.querySelectorAll( 'details' ) ].filter( ( d ) => d.closest( 'section' ) && /question/i.test( d.closest( 'section' ).textContent ) ).length || faq.length,
						tel: !! document.querySelector( 'a[href^="tel:"]' ),
					};
				}, c.name );
				check( `${ c.name } ${ width }px: 200, no horizontal scroll`, res.status() === 200 && ! info.overflow, { status: res.status(), overflow: info.overflow } );
				check( `${ c.name } ${ width }px: one visible H1 naming the city`, info.h1 === 1 && info.h1Visible && info.h1Text.includes( c.name ), info );
				check( `${ c.name } ${ width }px: FAQ shows 4 questions; tel: link present`, info.faq === 4 && info.tel, info );
			}

			// ZIP check (desktop), as a fresh visitor: the quote context deliberately restores an
			// earlier explicit choice from this browser session into the visible field, so clear it.
			await page.setViewport( { width: 1440, height: 900 } );
			await page.evaluate( () => window.sessionStorage.clear() );
			await page.goto( url, { waitUntil: 'networkidle2', timeout: 60000 } );
			const checked = await page.evaluate( async ( zip ) => {
				const btn = [ ...document.querySelectorAll( 'button' ) ].find( ( b ) => /check my location/i.test( b.textContent ) );
				if ( ! btn ) {
					return { error: 'no check button' };
				}
				const form = btn.closest( 'form' );
				const input = form && form.querySelector( 'input[type="text"], input:not([type="hidden"])' );
				if ( ! input ) {
					return { error: 'no input' };
				}
				input.focus();
				input.value = zip;
				input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
				btn.click();
				for ( let i = 0; i < 50; i++ ) {
					await new Promise( ( r ) => setTimeout( r, 200 ) );
					const live = form.parentElement.querySelector( '[aria-live], [role="status"]' );
					const text = live ? live.textContent.trim() : '';
					if ( text.length > 10 ) {
						return { text };
					}
				}
				return { error: 'no answer' };
			}, c.zip );
			check( `${ c.name }: ZIP check answers for served ZIP ${ c.zip }`, !! checked.text && ! /outside/i.test( checked.text ), checked );

			// Hero search → Get a Quote preselection.
			const nav = await page.evaluate( () => {
				const form = document.querySelector( 'form.cic-search' );
				if ( ! form ) {
					return null;
				}
				const sel = form.querySelector( 'select[name="event"]' );
				const opt = [ ...sel.options ].find( ( o ) => o.value === 'birthday' );
				if ( opt ) {
					sel.value = 'birthday';
					sel.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				}
				return true;
			} );
			check( `${ c.name }: hero search form present`, !! nav );
			await Promise.all( [ page.waitForNavigation( { waitUntil: 'networkidle2', timeout: 60000 } ), page.click( 'form.cic-search button[type="submit"]' ) ] );
			await new Promise( ( r ) => setTimeout( r, 800 ) );
			const quote = await page.evaluate( () => {
				const loc = document.querySelector( 'input[name="location"]:not([type="hidden"]), input[data-cic-context="location"]:not([type="hidden"])' );
				const ev = document.querySelector( 'input[name="event"]:checked, select[name="event"]' );
				return { url: location.pathname + location.search, location: loc ? loc.value : null, event: ev ? ev.value : null };
			} );
			check( `${ c.name }: Get a Quote opens with "${ c.name }, CA" and the birthday occasion`, quote.url.startsWith( '/get-a-quote/' ) && quote.location === `${ c.name }, CA` && quote.event === 'birthday', quote );
		}
	} catch ( e ) {
		fail++;
		console.log( 'FAIL exception — ' + e.message );
	} finally {
		await browser.close();
	}
	console.log( `City SEO browser checks: ${ pass } passed, ${ fail } failed; browser closed.` );
	process.exit( fail ? 1 : 0 );
} )();
