import { test, expect, open, SCREEN } from './fixtures.mjs';

/*
 *  The shell in normal flow.
 *
 *  The rail was sticky, pinned to the height of the viewport with a scrollbar
 *  of its own, and it slid against WordPress's menu beside it. Nathan walked
 *  the box and could not use it. Spec section 3: the page scrolls as one and
 *  nothing in the shell is sticky or fixed.
 *
 *  One exception, taken on 2026-09-05: the Duplicates screen's bulk bar
 *  (.vgml-health-bulk) keeps its sticky, because the list it acts on runs to
 *  two hundred sets and a bulk control that scrolls away from what it controls
 *  is not used twice. It is named here so it reads as a decision.
 */

const SLUGS = {
	dashboard: SCREEN.dashboard,
	folders: SCREEN.folders,
	ai: SCREEN.ai,
	duplicates: SCREEN.duplicates,
	import: 'media-import-folders',
	licence: 'media-licence',
	taxonomies: SCREEN.taxonomies,
	library: 'media-library',
	filetypes: 'mime-types',
};

/** The one sticky the spec allows, and the only screen it may appear on. */
const ALLOWED = { screen: 'duplicates', selector: 'vgml-health-bulk' };

/*
 *  The Folders screen opens the conversation by itself on an empty session,
 *  and that is a planner call -- about twenty credits of the box's licence.
 *  A suite that runs on every push must not spend one, so an empty session is
 *  given a turn first. Copied from tests/ui/shots.spec.mjs.
 */
async function plantATurn( page, slug ) {
	await open( page, slug );

	const session = await page.evaluate( () => wp.apiFetch( { path: '/vergeml/v1/guide/session' } ) );

	if ( ( session.session.turns || [] ).length ) {
		return;
	}

	await page.evaluate( () =>
		wp
			.apiFetch( { path: '/vergeml/v1/guide/session', method: 'POST', data: { reset: true } } )
			.then( () =>
				wp.apiFetch( {
					path: '/vergeml/v1/guide/turn',
					method: 'POST',
					data: {
						said: { kind: 'said', text: 'Folders by subject.' },
						say: { text: [ 'In the draft:', '- Nothing yet', 'Folders by subject, by use, or both?' ].join( '\n' ) },
					},
				} )
			)
	);
}

for ( const [ name, slug ] of Object.entries( SLUGS ) ) {
	test.describe( `the shell on ${ name }`, () => {

		test( 'scrolls as one page, with nothing pinned', async ( { page } ) => {
			const problems = [];
			page.on( 'pageerror', ( e ) => problems.push( `javascript: ${ e.message }` ) );
			await page.setViewportSize( { width: 1440, height: 900 } );

			if ( name === 'folders' ) {
				await plantATurn( page, slug );
			}

			await open( page, slug );
			await expect( page.locator( '.vgml-shell-content' ) ).toBeVisible();

			// (a) Nothing under the shell is sticky or fixed, bar the one exception.
			const pinned = await page.evaluate( () => {
				// An element's name, in a form a failure message can be read.
				const nameOf = ( el ) => {
					const cls = typeof el.className === 'string' ? el.className : ( el.className || {} ).baseVal || '';
					return (
						el.tagName.toLowerCase() +
						( el.id ? '#' + el.id : '' ) +
						( cls ? '.' + cls.trim().split( /\s+/ ).join( '.' ) : '' )
					);
				};

				const root = document.querySelector( '.vgml-shell' );

				if ( ! root ) {
					return [ 'there is no .vgml-shell on this screen' ];
				}

				return [ root, ...root.querySelectorAll( '*' ) ]
					.filter( ( el ) => {
						const p = getComputedStyle( el ).position;
						return p === 'sticky' || p === 'fixed';
					} )
					.map( ( el ) => `${ nameOf( el ) } is ${ getComputedStyle( el ).position }` );
			} );

			const expected =
				name === ALLOWED.screen ? pinned.filter( ( line ) => line.includes( ALLOWED.selector ) ) : [];

			expect( pinned, `pinned in .vgml-shell on ${ name }:\n${ pinned.join( '\n' ) }` ).toEqual( expected );

			/*
			 *  The bulk bar is built by js/vergeml-health.js and exists only
			 *  once there are groups to act on, so a resting screen has none
			 *  in the DOM. The exception is proved on the rule instead: it is
			 *  still granted, and only to this one selector.
			 */
			if ( name === ALLOWED.screen ) {
				const granted = await page.evaluate( ( selector ) => {
					for ( const sheet of document.styleSheets ) {
						let rules;

						try {
							rules = sheet.cssRules;
						} catch ( e ) {
							continue; // a stylesheet from another origin
						}

						for ( const rule of rules ) {
							if ( rule.selectorText === `.${ selector }` ) {
								return rule.style.position;
							}
						}
					}

					return null;
				}, ALLOWED.selector );

				expect( granted, 'the bulk bar keeps the one sticky the spec allows' ).toBe( 'sticky' );
			}

			// (b) The rail is a column in flow, not a pane with a scrollbar of its own.
			const rail = await page.evaluate( () => {
				const el = document.querySelector( '.vgml-shell-nav' );

				if ( ! el ) {
					return null;
				}

				const css = getComputedStyle( el );
				return { position: css.position, overflowY: css.overflowY, scrollable: el.scrollHeight - el.clientHeight };
			} );

			expect( rail, 'every shell screen has a rail' ).not.toBeNull();
			expect( rail.position, 'the rail is in normal flow' ).toBe( 'static' );
			expect( rail.scrollable, 'the rail does not scroll on its own' ).toBeLessThanOrEqual( 1 );

			// (c) Scrolled to the bottom, the rail has travelled with the page.
			const scroll = await page.evaluate( async () => {
				const rail = document.querySelector( '.vgml-shell-nav' );
				const top = rail.getBoundingClientRect().top;
				const from = window.scrollY;

				window.scrollTo( 0, document.scrollingElement.scrollHeight );
				await new Promise( ( done ) => requestAnimationFrame( () => requestAnimationFrame( done ) ) );

				return {
					taller: document.scrollingElement.scrollHeight > window.innerHeight + 1,
					scrolled: window.scrollY - from,
					railMoved: top - rail.getBoundingClientRect().top,
				};
			} );

			if ( name === 'filetypes' ) {
				expect( scroll.taller, 'File types is longer than the viewport, so it can prove this' ).toBe( true );
			}

			if ( scroll.scrolled > 100 ) {
				expect(
					Math.abs( scroll.railMoved - scroll.scrolled ),
					`the page scrolled ${ scroll.scrolled }px and the rail moved ${ scroll.railMoved }px`
				).toBeLessThanOrEqual( 2 );
			}

			// (d) A save bar is the last thing in its form.
			const trailing = await page.evaluate( () => {
				const nameOf = ( el ) => {
					const cls = typeof el.className === 'string' ? el.className : ( el.className || {} ).baseVal || '';
					return (
						el.tagName.toLowerCase() +
						( el.id ? '#' + el.id : '' ) +
						( cls ? '.' + cls.trim().split( /\s+/ ).join( '.' ) : '' )
					);
				};

				const bars = document.querySelectorAll(
					'.vgml-shell .vgml-savebar, .vgml-shell .vgml-ds-savebar, .vgml-shell .vgml-pg-actions'
				);

				return Array.from( bars )
					.map( ( bar ) => {
						const form = bar.closest( 'form' );

						if ( ! form ) {
							return null;
						}

						const all = Array.from( form.querySelectorAll( '*' ) );
						const after = all
							.slice( all.indexOf( bar ) + 1 )
							.filter( ( el ) => ! bar.contains( el ) && el.getClientRects().length > 0 );

						return after.length ? `${ nameOf( bar ) } is followed by ${ nameOf( after[ 0 ] ) }` : null;
					} )
					.filter( Boolean );
			} );

			expect( trailing, trailing.join( '\n' ) ).toEqual( [] );

			expect( problems, problems.join( '\n' ) ).toEqual( [] );
		} );
	} );
}

/*
 *  The settings screens arrive closed.
 *
 *  Six sections of open form was 3,500 pixels and nobody read past the second.
 *  Every section is printed closed by the server, so the screen is short even
 *  if the script never loads; what a person opens is theirs and comes back
 *  (js/vergeml-settings.js, localStorage). Nothing closes a section they
 *  opened -- a settings screen is where two things get changed at once.
 */
const SETTINGS = {
	'Library settings': SLUGS.library,
	'Folders and categories': SLUGS.taxonomies,
	'File types': SLUGS.filetypes,
};

for ( const [ name, slug ] of Object.entries( SETTINGS ) ) {
	test.describe( `${ name }, collapsed`, () => {

		test( 'opens closed, and remembers what was opened', async ( { page } ) => {
			await page.setViewportSize( { width: 1440, height: 900 } );

			// A first visit: nothing this browser has ever opened here.
			await open( page, slug );
			await page.evaluate( () => window.localStorage.clear() );
			await open( page, slug );

			const items = page.locator( '.vgml-acc-item' );
			await expect( items, 'the screen has sections' ).not.toHaveCount( 0 );
			await expect( page.locator( '.vgml-acc-item[open]' ), 'closed by default' ).toHaveCount( 0 );

			// Every section says what it is set to while it is shut.
			const summaries = await page.locator( '.vgml-acc-item > summary small' ).allInnerTexts();
			expect( summaries.length, 'every section carries its values' ).toBe( await items.count() );
			expect( summaries.every( ( line ) => line.trim().length > 0 ) ).toBe( true );

			// Two sections open, and the second does not close the first.
			await page.locator( '.vgml-acc-item > summary' ).nth( 0 ).click();
			await page.locator( '.vgml-acc-item > summary' ).nth( 1 ).click();
			await expect( page.locator( '.vgml-acc-item[open]' ), 'both stay open' ).toHaveCount( 2 );

			const opened = await page.locator( '.vgml-acc-item[open]' ).evaluateAll( ( els ) =>
				els.map( ( el ) => el.getAttribute( 'data-section' ) )
			);

			// Come back: those two, and no others.
			await open( page, slug );
			await expect( page.locator( '.vgml-acc-item[open]' ), 'remembered' ).toHaveCount( 2 );

			const back = await page.locator( '.vgml-acc-item[open]' ).evaluateAll( ( els ) =>
				els.map( ( el ) => el.getAttribute( 'data-section' ) )
			);

			expect( back.sort() ).toEqual( opened.sort() );

			// Close them again and the screen goes back to arriving shut.
			await page.locator( '.vgml-acc-item[open] > summary' ).nth( 1 ).click();
			await page.locator( '.vgml-acc-item[open] > summary' ).nth( 0 ).click();
			await expect( page.locator( '.vgml-acc-item[open]' ) ).toHaveCount( 0 );

			await open( page, slug );
			await expect( page.locator( '.vgml-acc-item[open]' ), 'nothing remembered' ).toHaveCount( 0 );

			// The kind filter has to open the section its rows are in.
			if ( slug === 'mime-types' ) {
				await page.locator( '.vgml-ft-kinds button[data-kind="video"]' ).click();
				await expect( page.locator( '.vgml-acc-item[open]' ) ).toHaveCount( 1 );
				await expect( page.locator( '.vgml-acc-item[open]' ) ).toHaveAttribute( 'data-section', 'video' );
			}

			// The save bar is never shut inside a section.
			const inside = await page.evaluate( () =>
				document.querySelectorAll( '.vgml-acc-item .vgml-savebar' ).length
			);
			expect( inside, 'the save bar is outside the accordion' ).toBe( 0 );
		} );
	} );
}
