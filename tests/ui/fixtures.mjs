import { test as base, expect } from '@playwright/test';

/*
 *  Signed in once, then reused.
 *
 *  Logging in per spec is three requests each and a fair share of the total
 *  runtime, and a login page failure would then fail every spec for the same
 *  reason -- which hides whatever the spec was actually about.
 */

const USER = process.env.UI_USER ?? 'admin';
const PASS = process.env.UI_PASS ?? 'password';

/** The plugin's own screens, by the slug the menu uses. */
export const SCREEN = {
	dashboard: 'vergelabs-media',
	folders: 'media-librarian',
	ai: 'media-ai',
	guide: 'media-guide',
	duplicates: 'media-health',
	taxonomies: 'media-taxonomies',
};

let cookies = null;

export const test = base.extend( {
	page: async ( { page, baseURL }, use ) => {
		if ( cookies === null ) {
			/*
			 *  Twice, if need be. The login page's own script focuses the
			 *  username box and selects what is in it a moment after load
			 *  (wp_attempt_focus), and on the first login of a run that moment
			 *  landed between the two fills: the password went into the username
			 *  box and the form sat there until the test timed out. Seen twice on
			 *  2026-09-06 as the first test of a run. The fields are read back
			 *  before the form is sent, and a login that did not land is done
			 *  once more.
			 */
			for ( let attempt = 1; attempt <= 2; attempt++ ) {
				await page.goto( '/wp-login.php', { waitUntil: 'domcontentloaded' } );
				await page.waitForTimeout( 400 );
				await page.fill( '#user_login', USER );
				await page.fill( '#user_pass', PASS );
				if ( ( await page.inputValue( '#user_login' ) ) !== USER || ( await page.inputValue( '#user_pass' ) ) !== PASS ) {
					await page.fill( '#user_login', USER );
					await page.fill( '#user_pass', PASS );
				}

				/*
				 *  Jetpack's brute-force protection, left without an API key, falls
				 *  back to a sum on the login form ("Prove your humanity: 2 + 2 =")
				 *  and answers 401 to any login that does not carry it. The box has
				 *  Jetpack for the WooCommerce integrations, so the sum is solved
				 *  when it is on the form and ignored when it is not. Found on
				 *  2026-09-05, when seventeen specs failed at the door in a row.
				 */
				const puzzle = page.locator( 'input[name="jetpack_protect_num"]' );
				if ( await puzzle.count() ) {
					const asked = await page.locator( 'label[for="jetpack_protect_answer"]' ).innerText();
					const sum = /(\d+)\D+(\d+)/.exec( asked.replace( / /g, ' ' ) );
					if ( sum ) {
						await puzzle.fill( String( Number( sum[ 1 ] ) + Number( sum[ 2 ] ) ) );
					}
				}

				await Promise.all( [
					page.waitForNavigation( { waitUntil: 'domcontentloaded', timeout: 30000 } ).catch( () => null ),
					page.click( '#wp-submit' ),
				] );

				if ( ! /wp-login/.test( page.url() ) ) {
					break;
				}
			}

			if ( /wp-login/.test( page.url() ) ) {
				throw new Error(
					`Could not sign in to ${ baseURL } as "${ USER }". Set UI_USER and UI_PASS.`
				);
			}
			cookies = await page.context().cookies();
		} else {
			await page.context().addCookies( cookies );
		}
		await use( page );
	},
} );

/** Open one of the plugin's screens and fail loudly if PHP fell over. */
export async function open( page, screen ) {
	await page.goto( `/wp-admin/admin.php?page=${ screen }`, { waitUntil: 'domcontentloaded' } );

	const body = await page.content();
	const fatal = /Fatal error|Parse error|There has been a critical error/i.exec( body );

	if ( fatal !== null ) {
		throw new Error( `${ screen } raised "${ fatal[ 0 ] }" -- the screen did not render.` );
	}

	// A screen that redirected to the login page is not a screen we tested.
	expect( page.url(), 'should be on an admin screen' ).toContain( 'admin.php' );
}

/** Every number the screen prints, as integers, for the checks that compare them. */
export async function numbersOn( page ) {
	return page.evaluate( () =>
		Array.from( document.body.innerText.matchAll( /\d[\d,.]*/g ) )
			.map( ( m ) => Number( m[ 0 ].replace( /[,.]/g, '' ) ) )
			.filter( ( n ) => Number.isFinite( n ) )
	);
}

export { expect };
