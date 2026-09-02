import { test, expect, open, SCREEN } from './fixtures.mjs';

/*
 *  Every screen, opened.
 *
 *  The cheapest test in this suite and probably the most valuable: a PHP
 *  warning printed into a page, a string that never got translated, an
 *  unresolved placeholder. None of these stop the code running, so nothing
 *  else notices them, and all of them are visible to the first customer who
 *  opens the screen.
 *
 *  It runs on a box with WooCommerce, Elementor, WP Rocket and Packlink
 *  installed, all of which emit deprecation notices of their own on PHP 8.5 --
 *  so this is careful to only fail on notices pointing at our own files.
 */

const SCREENS = Object.entries( SCREEN );

for ( const [ name, slug ] of SCREENS ) {

	test( `the ${ name } screen opens cleanly`, async ( { page } ) => {
		const problems = [];

		page.on( 'pageerror', ( e ) => problems.push( `javascript: ${ e.message }` ) );
		page.on( 'console', ( m ) => {
			if ( m.type() === 'error' && ! /favicon|net::ERR/i.test( m.text() ) ) {
				problems.push( `console: ${ m.text().slice( 0, 160 ) }` );
			}
		} );

		await open( page, slug );

		const html = await page.content();

		/*
		 *  Somebody else's deprecation notice is their business. One pointing
		 *  at a file of ours is ours, and on a screen it is a bug a customer
		 *  sees before we do.
		 */
		const ours = Array.from(
			html.matchAll( /(Warning|Notice|Deprecated|Fatal error)[^<\n]{0,400}vergelabs-media-library[^<\n]{0,120}/gi )
		).map( ( m ) => m[ 0 ].slice( 0, 180 ) );

		expect( ours, `PHP complained about our own code on ${ name }` ).toEqual( [] );

		const text = await page.evaluate( () => document.body.innerText );

		expect( text, `an unresolved placeholder is showing on ${ name }` )
			.not.toMatch( /\[Company\]|\[Address\]|\[Country\]|\{\{\s*\w/ );

		// A printf that never got its argument reaches the screen as %s.
		expect( text, `an unfilled format string is showing on ${ name }` )
			.not.toMatch( /(^|\s)%[sd](\s|$)/ );

		expect( problems, `the browser reported errors on ${ name }` ).toEqual( [] );
	} );
}

test( 'the plugin does not leak notices into the media library itself', async ( { page } ) => {
	await page.goto( '/wp-admin/upload.php', { waitUntil: 'domcontentloaded' } );

	const html = await page.content();
	const ours = Array.from(
		html.matchAll( /(Warning|Notice|Deprecated|Fatal error)[^<\n]{0,400}vergelabs-media-library[^<\n]{0,120}/gi )
	).map( ( m ) => m[ 0 ].slice( 0, 180 ) );

	expect( ours, 'PHP complained about our code on the media screen' ).toEqual( [] );
} );
