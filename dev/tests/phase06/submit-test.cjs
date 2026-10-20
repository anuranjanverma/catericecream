/**
 * Phase 06 integration checks through the real CF7 transport (REST feedback endpoint and the
 * no-JavaScript page POST): storage before notification, retries, concurrency, validation,
 * coverage, spam, expired tokens, mail failure + recovery, Contact, dependency failure, rate
 * limits, confirmation receipts and public leakage. Synthetic @example.test addresses only; mail is
 * captured by Mailpit. Only test-created records are removed (state.php restore + Mailpit cleanup).
 *
 *   node dev/tests/phase06/submit-test.cjs
 */
'use strict';

const { execFileSync } = require( 'child_process' );
const https = require( 'https' );
const http = require( 'http' );
const fs = require( 'fs' );
const path = require( 'path' );
const crypto = require( 'crypto' );

const BASE = 'https://catericecream.test';
const ROOT = path.resolve( __dirname, '../../..' );
const OUT = path.join( ROOT, 'dev/tests/output/phase06' );
const WP = 'C:\\laragon\\bin\\wp-cli\\wp.cmd';
fs.mkdirSync( OUT, { recursive: true } );

const results = [];
const check = ( label, ok, detail ) => {
	results.push( !! ok );
	console.log( ( ok ? 'PASS ' : 'FAIL ' ) + label + ( ! ok && detail !== undefined ? ' — ' + JSON.stringify( detail ).slice( 0, 600 ) : '' ) );
};
const wp = ( ...args ) => execFileSync( 'cmd', [ '/c', WP, ...args ], { cwd: ROOT, encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'ignore' ], timeout: 120000 } );
const last = ( s ) => s.trim().split( '\n' ).pop();
const state = ( ...a ) => last( wp( 'eval-file', 'dev/tests/phase06/state.php', ...a ) );
const json = ( ...a ) => JSON.parse( state( ...a ) );
const sleep = ( ms ) => new Promise( ( r ) => setTimeout( r, ms ) );

const request = ( url, { method = 'GET', body = '', headers = {} } = {} ) => new Promise( ( resolve ) => {
	const u = new URL( url );
	const lib = 'http:' === u.protocol ? http : https;
	// CF7 treats a missing User-Agent as spam; send what a browser would.
	const h = Object.assign( { 'Cache-Control': 'no-cache', 'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) CIC-Phase06-Test' }, headers );
	if ( body ) {
		h[ 'Content-Type' ] = h[ 'Content-Type' ] || 'application/x-www-form-urlencoded';
		h[ 'Content-Length' ] = Buffer.byteLength( body );
	}
	const req = lib.request( u, { method, rejectUnauthorized: false, headers: h, agent: false }, ( res ) => {
		let data = '';
		res.on( 'data', ( d ) => { data += d; } );
		res.on( 'end', () => resolve( { code: res.statusCode, location: res.headers.location || '', body: data } ) );
	} );
	req.on( 'error', ( e ) => resolve( { code: 0, body: e.message } ) );
	if ( body ) {
		req.write( body );
	}
	req.end();
} );

/** Mailpit: messages to an address (captured locally only). */
const mails = async ( to ) => {
	const r = await request( 'http://localhost:8025/api/v1/search?query=' + encodeURIComponent( 'to:"' + to + '"' ) + '&limit=50' );
	try {
		return JSON.parse( r.body ).messages || [];
	} catch ( e ) {
		return [];
	}
};
const createdMail = new Set();

let info;
const key = () => crypto.randomBytes( 24 ).toString( 'base64url' );
const enc = ( obj ) => {
	const p = new URLSearchParams();
	Object.entries( obj ).forEach( ( [ k, v ] ) => {
		( Array.isArray( v ) ? v : [ v ] ).forEach( ( x ) => p.append( Array.isArray( v ) ? k + '[]' : k, x ) );
	} );
	return p.toString();
};
const quoteFields = ( over = {} ) => Object.assign( {
	event: 'birthday', event_other: '', event_date: info.future, guests: '40', location: info.covered, location_detail: '', venue: '', serve_time: '', serve_duration: '',
	treats: [ 'dippin-dots' ], flavours: '', party_theme: '', dietary: '', your_name: 'Phase Six Tester', your_email: 'p6-' + crypto.randomBytes( 4 ).toString( 'hex' ) + '@example.test',
	phone: '', contact_pref: 'email', notes: '',
}, over );
const cf7 = ( type, fields, opts = {} ) => {
	const id = info[ type ];
		const tags = info.tags[ type ];
	const guard = {
		_wpcf7: id, _wpcf7_version: info.version, _wpcf7_locale: 'en_US', _wpcf7_unit_tag: tags.unit, _wpcf7_container_post: tags.container,
		cic_guard: '1', cic_website: opts.honeypot || '', cic_ts: opts.ts || info.token, cic_key: opts.key || key(), cic_source: JSON.stringify( { first: { type: 'event', key: 'birthday' }, latest: { type: 'quote', key: '' } } ),
	};
	const all = Object.assign( guard, fields );
	if ( opts.nonajax ) {
		return request( BASE + ( 'quote' === type ? '/get-a-quote/' : '/contact/' ), { method: 'POST', body: enc( all ) } );
	}
	// CF7's REST endpoint accepts multipart/form-data only (as its own script sends).
	const boundary = '----cicp6' + crypto.randomBytes( 8 ).toString( 'hex' );
	let body = '';
	Object.entries( all ).forEach( ( [ k, v ] ) => {
		( Array.isArray( v ) ? v : [ v ] ).forEach( ( x ) => {
			body += `--${ boundary }\r\nContent-Disposition: form-data; name="${ Array.isArray( v ) ? k + '[]' : k }"\r\n\r\n${ x }\r\n`;
		} );
	} );
	body += `--${ boundary }--\r\n`;
	return request( `${ BASE }/wp-json/contact-form-7/v1/contact-forms/${ id }/feedback`, { method: 'POST', body, headers: { 'Content-Type': 'multipart/form-data; boundary=' + boundary } } ).then( ( r ) => {
		try {
			return Object.assign( { http: r.code }, JSON.parse( r.body ) );
		} catch ( e ) {
			return { http: r.code, raw: r.body.slice( 0, 300 ) };
		}
	} );
};

( async () => {
	const snapshot = path.join( OUT, 'state-submit.json' );
	const operator = 'operator-p6@example.test';
	wp( 'eval-file', 'dev/tests/phase06/state.php', 'snapshot', snapshot );
	try {
		info = json( 'info' );
		// Hidden CF7 fields exactly as the rendered pages carry them (what a browser posts back).
		info.tags = {};
		for ( const [ type, url ] of [ [ 'quote', '/get-a-quote/' ], [ 'contact', '/contact/' ] ] ) {
			const html = ( await request( BASE + url ) ).body;
			info.tags[ type ] = { unit: ( html.match( /name="_wpcf7_unit_tag" value="([^"]+)"/ ) || [] )[ 1 ], container: ( html.match( /name="_wpcf7_container_post" value="([^"]+)"/ ) || [] )[ 1 ] };
		}
		info.token = state( 'token', '20' );
		state( 'recipient', operator );
		state( 'observer', 'on' );
		state( 'reset-limits' );
		check( 'S0 test setup (forms, fixture coverage, synthetic operator recipient, observer fixture)', info.quote && info.contact && info.covered && info.unknown, info );

		// S1 accepted quote: stored once, then notified.
		const f1 = quoteFields();
		const k1 = key();
		const r1 = await cf7( 'quote', f1, { key: k1 } );
		await sleep( 500 );
		const e1 = json( 'enquiry', f1.your_email );
		const obs = json( 'observe' ).filter( ( o ) => o.id === e1.id );
		const m1 = await mails( f1.your_email );
		const op1 = ( await mails( operator ) ).filter( ( m ) => ( m.Subject || '' ).includes( '#' + e1.id ) );
		m1.concat( op1 ).forEach( ( m ) => createdMail.add( m.ID ) );
		check( 'S1 accepted quote → one Flamingo enquiry, status New, receipt returned', 'mail_sent' === r1.status && r1.cic && r1.cic.accepted && /^[a-f0-9]{32}$/.test( r1.cic.receipt ) && 1 === e1.count && 'quote' === e1.type && 'new' === e1.wf, { r1, e1 } );
		check( 'S2 notifications ran only after storage (observer: enquiry already stored at every send)', obs.length >= 2 && obs.every( ( o ) => o.stored ), obs );
		check( 'S3 exactly one operator notification and one customer acknowledgement (Mailpit), states recorded as sent', 1 === op1.length && 1 === m1.length && 'sent' === e1.row.operator_status && 'sent' === e1.row.customer_status, { op: op1.length, cust: m1.length, row: e1.row } );
		check( 'S4 no guard/key/source/IP/user-agent stored with the enquiry; server-resolved labels kept', ! e1.fields.some( ( f ) => /^cic_/.test( f ) ) && ! e1.meta.includes( 'remote_ip' ) && ! e1.meta.includes( 'user_agent' ) && 'Birthday' === e1.context.event_label && e1.context.coverage.fixture === true && 'event' === e1.source.first.type, e1 );
		check( 'S5 success wording means received, not booked', /received/i.test( r1.message ) && ! /book(ed|ing) (is )?confirmed|reserved/i.test( r1.message ), r1.message );

		// S6 retry with the same key (lost response / double click after save).
		const r2 = await cf7( 'quote', f1, { key: k1 } );
		await sleep( 300 );
		const e2 = json( 'enquiry', f1.your_email );
		check( 'S6 retry with the same submission key → same receipt, no second enquiry, no new email', r2.cic && r2.cic.accepted && r2.cic.duplicate && r2.cic.receipt === r1.cic.receipt && 1 === e2.count && 1 === ( await mails( f1.your_email ) ).length, { r2, count: e2.count } );

		// S7 concurrent retries.
		state( 'reset-limits' );
		const f3 = quoteFields();
		const k3 = key();
		const burst = await Promise.all( Array.from( { length: 6 }, () => cf7( 'quote', f3, { key: k3 } ) ) );
		await sleep( 800 );
		const e3 = json( 'enquiry', f3.your_email );
		const m3 = await mails( f3.your_email );
		const op3 = ( await mails( operator ) ).filter( ( m ) => ( m.Subject || '' ).includes( '#' + e3.id ) );
		m3.concat( op3 ).forEach( ( m ) => createdMail.add( m.ID ) );
		check( 'S7 six concurrent submissions with one key → one enquiry, one operator mail, one acknowledgement', 1 === e3.count && 1 === m3.length && 1 === op3.length && burst.some( ( b ) => b.cic && b.cic.accepted ) && burst.every( ( b ) => ( b.cic && ( b.cic.accepted || b.cic.pending ) ) ), { count: e3.count, mails: m3.length, op: op3.length, statuses: burst.map( ( b ) => b.status + ':' + JSON.stringify( b.cic ) ) } );
		const r4 = await cf7( 'quote', Object.assign( {}, f3 ) );
		check( 'S8 a new request from the same email is a separate enquiry (never deduplicated by email)', r4.cic && r4.cic.accepted && 2 === json( 'enquiry', f3.your_email ).count );
		( await mails( f3.your_email ) ).forEach( ( m ) => createdMail.add( m.ID ) );

		// S9 validation failures.
		state( 'reset-limits' );
		const bad = quoteFields( { event: '', guests: '0', your_name: '', your_email: 'nope', event_date: '' } );
		const r5 = await cf7( 'quote', bad );
		const fields5 = ( r5.invalid_fields || [] ).map( ( f ) => f.field );
		check( 'S9 invalid submission → validation_failed listing each field; nothing stored or sent', 'validation_failed' === r5.status && [ 'event', 'guests', 'your_name', 'your_email', 'event_date' ].every( ( f ) => fields5.includes( f ) ) && 0 === json( 'enquiry', bad.your_email ).count, r5 );
		const r6 = await cf7( 'quote', quoteFields( { event: '<script>', treats: [ '../x', 'dippin-dots' ], serve_duration: 'forever' } ) );
		const fields6 = ( r6.invalid_fields || [] ).map( ( f ) => f.field );
		check( 'S10 malformed identifiers rejected server-side (event, treats, duration)', [ 'event', 'treats', 'serve_duration' ].every( ( f ) => fields6.includes( f ) ), fields6 );

		// S11 coverage outcomes (fixtures).
		const r7 = await cf7( 'quote', quoteFields( { location: 'Compton' } ) );
		const r8 = await cf7( 'quote', quoteFields( { location: '90220' } ) );
		const loc7 = ( r7.invalid_fields || [] ).find( ( f ) => 'location' === f.field );
		check( 'S11 excluded location blocks submission with the service-area message', loc7 && /do not currently serve/i.test( loc7.message ), r7.invalid_fields );
		check( 'S12 shared ZIP asks for the venue city (clarification) instead of accepting', ( r8.invalid_fields || [] ).some( ( f ) => 'location_detail' === f.field ), r8.invalid_fields );
		const f9 = quoteFields( { location: info.unknown, location_detail: 'Testville' } );
		const r9 = await cf7( 'quote', f9 );
		const e9 = json( 'enquiry', f9.your_email );
		( await mails( f9.your_email ) ).forEach( ( m ) => createdMail.add( m.ID ) );
		check( 'S13 unknown ZIP with venue city → accepted and recorded as "unknown" for the team (never "covered")', r9.cic && r9.cic.accepted && 'unknown' === e9.context.coverage.status, e9.context );

		// S14 spam paths.
		state( 'reset-limits' );
		const fs1 = quoteFields();
		const s1 = await cf7( 'quote', fs1, { honeypot: 'http://spam' } );
		const s2 = await cf7( 'quote', quoteFields(), { ts: state( 'token', '0' ) } );
		const s3 = await cf7( 'quote', quoteFields(), { ts: '1700000000.aaaaaaaaaaaaaaaaaaaaaaaa' } );
		check( 'S14 honeypot, too-fast and forged-token submissions are spam: not stored, not acknowledged', 'spam' === s1.status && 'spam' === s2.status && 'spam' === s3.status && 0 === json( 'enquiry', fs1.your_email ).count && 0 === ( await mails( fs1.your_email ) ).length, [ s1.status || s1, s2.status, s3.status ] );

		// S15 expired token.
		const r10 = await cf7( 'quote', quoteFields(), { ts: state( 'token', String( 12 * 3600 + 60 ) ) } );
		check( 'S15 expired form token → "press Send again" (values kept client-side) with a fresh token', 'validation_failed' === r10.status && ( r10.invalid_fields || [] ).some( ( f ) => 'cic_guard' === f.field ) && /^\d{10}\.[a-f0-9]{24}$/.test( ( r10.cic || {} ).token || '' ), r10 );

		// S16 mail failure and recovery.
		state( 'reset-limits' );
		state( 'fail', 'on' );
		const f11 = quoteFields();
		const r11 = await cf7( 'quote', f11 );
		const e11 = json( 'enquiry', f11.your_email );
		check( 'S16 mail failure: the enquiry is still saved once and reported as received; delivery marked failed', r11.cic && r11.cic.accepted && 1 === e11.count && 'failed' === e11.row.operator_status && 'failed' === e11.row.customer_status && 0 === ( await mails( f11.your_email ) ).length, { r11: r11.status, row: e11.row } );
		state( 'fail', 'off' );
		const retry = json( 'retry', String( e11.id ) );
		const again = json( 'retry', String( e11.id ) );
		await sleep( 400 );
		const m11 = await mails( f11.your_email );
		const op11 = ( await mails( operator ) ).filter( ( m ) => ( m.Subject || '' ).includes( '#' + e11.id ) );
		m11.concat( op11 ).forEach( ( m ) => createdMail.add( m.ID ) );
		check( 'S17 retry recovers delivery; a second retry sends nothing more; still one enquiry', 'sent' === retry.operator && 'sent' === retry.customer && 'sent' === again.operator && 1 === m11.length && 1 === op11.length && 1 === json( 'enquiry', f11.your_email ).count, { retry, again, cust: m11.length, op: op11.length } );

		// S18 contact form.
		state( 'reset-limits' );
		const fc = { topic: 'general', your_name: 'Contact Tester', your_email: 'p6-contact-' + crypto.randomBytes( 3 ).toString( 'hex' ) + '@example.test', phone: '', message: 'Do you serve Ojai?' };
		const rc = await cf7( 'contact', fc );
		await sleep( 400 );
		const ec = json( 'enquiry', fc.your_email );
		const mc = await mails( fc.your_email );
		mc.forEach( ( m ) => createdMail.add( m.ID ) );
		( await mails( operator ) ).filter( ( m ) => ( m.Subject || '' ).includes( '#' + ec.id ) ).forEach( ( m ) => createdMail.add( m.ID ) );
		check( 'S18 Contact form saves through the same foundation and notifies once', rc.cic && rc.cic.accepted && 'contact' === ec.type && 1 === ec.count && 1 === mc.length && 'sent' === ec.row.operator_status, { rc: rc.status, ec } );
		const rcBad = await cf7( 'contact', { topic: 'x', your_name: '', your_email: 'bad', message: '' } );
		check( 'S19 Contact validation failures are reported per field', 'validation_failed' === rcBad.status && 4 === ( rcBad.invalid_fields || [] ).length, rcBad.invalid_fields );

		// S20 receipts.
		const conf = await request( BASE + '/get-a-quote/?received=' + r1.cic.receipt );
		const confBad = await request( BASE + '/get-a-quote/?received=' + 'a'.repeat( 32 ) );
		const visible = /id="confirmation"[^>]*data-cic-received(?! hidden)/.test( conf.body ) || /data-cic-received>/.test( conf.body );
		check( 'S20 refreshing the confirmation URL shows the same non-personal confirmation; unknown receipts show none', visible && conf.body.includes( 'Your quote request is received.' ) && ! conf.body.includes( f1.your_email ) && ! conf.body.includes( 'Phase Six Tester' ) && /data-cic-received hidden/.test( confBad.body ), conf.code );

		// S21 no-JS submissions.
		state( 'reset-limits' );
		const fn = quoteFields();
		const nj = await cf7( 'quote', fn, { nonajax: true } );
		const njBad = await cf7( 'quote', quoteFields( { your_email: 'bad', your_name: 'Kept Value Name' } ), { nonajax: true } );
		( await mails( fn.your_email ) ).forEach( ( m ) => createdMail.add( m.ID ) );
		check( 'S21 no-JS: a valid POST stores once and redirects (303) to the confirmation receipt', 303 === nj.code && /\/get-a-quote\/\?received=[a-f0-9]{32}#confirmation$/.test( nj.location ) && 1 === json( 'enquiry', fn.your_email ).count, { code: nj.code, location: nj.location } );
		check( 'S22 no-JS: an invalid POST re-renders with inline errors and keeps the entered values', 200 === njBad.code && njBad.body.includes( 'value="Kept Value Name"' ) && njBad.body.includes( 'wpcf7-not-valid-tip' ), { code: njBad.code, kept: njBad.body.includes( 'Kept Value Name' ), tip: njBad.body.includes( 'not-valid-tip' ), excerpt: ( njBad.body.match( /name="your_name"[^>]*>/ ) || [ '' ] )[ 0 ] } );

		// S23 rate limit.
		state( 'reset-limits' );
		const statuses = [];
		let firstRaw = null;
		for ( let i = 0; i < 6; i++ ) {
			const f = quoteFields();
			const rr = await cf7( 'quote', f );
			firstRaw = firstRaw || rr;
			statuses.push( rr.status );
			( await mails( f.your_email ) ).forEach( ( m ) => createdMail.add( m.ID ) );
		}
		check( 'S23 more than five accepted requests from one address in ten minutes are refused as spam', 5 === statuses.filter( ( s ) => 'mail_sent' === s ).length && 'spam' === statuses[ 5 ], statuses );

		// S24 public leakage.
		const restNo = await request( BASE + '/wp-json/wp/v2/flamingo_inbound' );
		const search = await request( BASE + '/?s=' + encodeURIComponent( 'Phase Six Tester' ) );
		const map = await request( BASE + '/wp-sitemap.xml' );
		check( 'S24 no enquiry data in public REST, site search or sitemaps', 404 === restNo.code && ! search.body.includes( f1.your_email ) && ! /flamingo/i.test( map.body ), { rest: restNo.code } );

		// S25 dependency failure.
		state( 'reset-limits' );
		state( 'plugin', 'deactivate' );
		const page = await request( BASE + '/get-a-quote/' );
		const fd = quoteFields();
		const rd = await cf7( 'quote', fd );
		state( 'plugin', 'activate' );
		check( 'S25 Flamingo missing → form replaced by call/email fallback; a direct submission is not accepted or sent', ! page.body.includes( 'class="wpcf7-form' ) && page.body.includes( 'cic-quote-unavailable' ) && ! ( rd.cic && rd.cic.accepted ) && 0 === json( 'enquiry', fd.your_email ).count && 0 === ( await mails( fd.your_email ) ).length, { rd: rd.status, cic: rd.cic } );
	} catch ( e ) {
		check( 'Unexpected error: ' + e.message, false );
	} finally {
		try {
			state( 'plugin', 'activate' );
			state( 'observer', 'off' );
			// Mailpit: delete only the messages this test created (synthetic addresses).
			( await mails( operator ) ).forEach( ( m ) => createdMail.add( m.ID ) );
			if ( createdMail.size ) {
				await request( 'http://localhost:8025/api/v1/messages', { method: 'DELETE', body: JSON.stringify( { IDs: Array.from( createdMail ) } ), headers: { 'Content-Type': 'application/json' } } );
			}
			const restored = last( wp( 'eval-file', 'dev/tests/phase06/state.php', 'restore', snapshot ) );
			const left = ( await mails( operator ) ).length;
			check( 'Z1 test records, settings, fixture and test mail removed', /Restored/.test( restored ) && 0 === left, { restored, left } );
		} catch ( e ) {
			check( 'Z1 restore failed: ' + e.message, false );
		}
		fs.rmSync( snapshot, { force: true } );
		const passed = results.filter( Boolean ).length;
		console.log( `Phase 06 submission checks: ${ passed }/${ results.length } passed` );
		process.exitCode = passed === results.length ? 0 : 1;
	}
} )();
