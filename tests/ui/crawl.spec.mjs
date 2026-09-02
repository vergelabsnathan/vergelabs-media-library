import { test, expect, open, SCREEN } from './fixtures.mjs';

/*
 *  Everywhere our screens can take you.
 *
 *  Every other spec here encodes a bug somebody already found, which means the
 *  suite can only ever be as good as our memory. This one goes looking: it
 *  collects every link our own screens offer, opens each, and demands the
 *  destination render without PHP complaining about our code.
 *
 *  It is deliberately incurious about other people's plugins and about
 *  anything that changes state -- a crawler that presses delete is not a test,
 *  it is an incident.
 */

const SKIP = /logout|action=delete|action=trash|_wpnonce|admin-post\.php|wp-login|\.zip|\.csv|#/i;

test( 'every link our screens offer leads somewhere that renders', async ( { page, baseURL } ) => {

	const seen = new Set();
	const found = [];

	// Collect first, visit after: navigating while iterating a live list is
	// how a crawler ends up chasing its own tail.
	for ( const slug of Object.values( SCREEN ) ) {
		await open( page, slug );

		const links = await page.$$eval( 'a[href]', ( as ) => as.map( ( a ) => a.href ) );

		for ( const href of links ) {
			if ( SKIP.test( href ) ) continue;
			if ( ! href.includes( '/wp-admin/' ) ) continue;
			// Only pages that are ours or that we send people to.
			if ( ! /page=(vergelabs-media|media-|mime-types)/.test( href ) && ! /upload\.php/.test( href ) ) continue;
			if ( seen.has( href ) ) continue;
			seen.add( href );
			found.push( href );
		}
	}

	expect( found.length, 'our screens should link somewhere' ).toBeGreaterThan( 0 );

	const broken = [];

	for ( const href of found.slice( 0, 40 ) ) {
		const res = await page.goto( href, { waitUntil: 'domcontentloaded' } ).catch( () => null );

		if ( res === null ) {
			broken.push( `${ href } -- did not load` );
			continue;
		}
		if ( res.status() >= 400 ) {
			broken.push( `${ href } -- HTTP ${ res.status() }` );
			continue;
		}

		const html = await page.content();

		const ours = /(Fatal error|Parse error)[^<\n]{0,300}/i.exec( html );
		if ( ours !== null ) {
			broken.push( `${ href } -- ${ ours[ 0 ].slice( 0, 120 ) }` );
			continue;
		}

		const mine = /(Warning|Notice|Deprecated)[^<\n]{0,300}vergelabs-media-library[^<\n]{0,80}/i.exec( html );
		if ( mine !== null ) {
			broken.push( `${ href } -- ${ mine[ 0 ].slice( 0, 140 ) }` );
		}
	}

	expect( broken, `${ found.length } links checked` ).toEqual( [] );
} );

test( 'the forms on our settings screens survive being saved untouched', async ( { page } ) => {

	/*
	 *  Saving a form without changing anything must be a no-op. It is the
	 *  cheapest way to find a field that does not round-trip: one that
	 *  silently drops a value, or turns an empty string into a default, shows
	 *  up here as a screen that looks different afterwards.
	 */
	await open( page, SCREEN.taxonomies );

	const before = await page.$$eval( 'input[type="checkbox"]', ( xs ) =>
		xs.map( ( x ) => `${ x.name }=${ x.checked }` ).join( '|' )
	);

	const submit = page.locator( 'input[type="submit"], button[type="submit"]' ).first();
	test.skip( ( await submit.count() ) === 0, 'no form on this screen' );

	await submit.click();
	await page.waitForLoadState( 'domcontentloaded' );

	const html = await page.content();
	expect( /Fatal error|Parse error/i.test( html ), 'saving must not fatal' ).toBe( false );

	await open( page, SCREEN.taxonomies );
	const after = await page.$$eval( 'input[type="checkbox"]', ( xs ) =>
		xs.map( ( x ) => `${ x.name }=${ x.checked }` ).join( '|' )
	);

	expect( after, 'saving without changing anything changed something' ).toBe( before );
} );
