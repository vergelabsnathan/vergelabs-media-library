<?php
/*
 *  The truth page (S17): a library with no answer key gets one, once.
 *
 *      node tools/box-eval.mjs tools/truth-page.php > docs/truth/2026-09-18-tech-truth.html
 *      node tools/box-eval.mjs tools/truth-page.php --env VGML_N=200 --env VGML_SEED=133
 *
 *  The shop library scores itself: every picture carries the catalogue path
 *  it was fetched for. The tech library (a newsroom's beats) carries
 *  nothing, so until now it was judged on sixty hand-marked cards -- which
 *  read "every rule helped" while the quality slid (2026-09-18). This prints
 *  one page: N pictures drawn at random (seeded, so the same page comes
 *  back), each with its thumbnail and a select of the tree's folders
 *  pre-set to the folder the fill would choose today; a person corrects the
 *  wrong ones, presses Save, and gets a JSON of attachment id => folder path
 *  ("" for a picture that belongs in no folder). tools/box-truth-score.php
 *  reads it with VGML_TRUTH, and from then on every rule is scored on this
 *  library too. Read-only: nothing moves, nothing is spent; the thumbnails
 *  are the site's own URLs.
 *
 *  The pre-set is a convenience, not a lead: the description is shown small
 *  under the picture so a wrong description can be seen for what it is, and
 *  "wrong picture" keeps a picture the seed fetched badly out of the truth.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
global $wpdb;
wp_set_current_user( 1 );
$tax  = vergeml_librarian_taxonomy();
$n    = max( 1, (int) ( getenv( 'VGML_N' ) ?: 200 ) );
$seed = (int) ( getenv( 'VGML_SEED' ) ?: 133 );

$terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) );
$by_id = array();
foreach ( $terms as $t ) {
    $by_id[ (int) $t->term_id ] = $t;
}
$path_of = function ( $tid ) use ( $by_id ) {
    $out = array();
    $g   = 0;
    while ( $tid && isset( $by_id[ $tid ] ) && $g++ < 32 ) {
        array_unshift( $out, vergeml_term_name( $by_id[ $tid ] ) );
        $tid = (int) $by_id[ $tid ]->parent;
    }
    return implode( ' > ', $out );
};
$paths = array();
foreach ( $by_id as $tid => $t ) {
    $paths[ $tid ] = $path_of( $tid );
}
asort( $paths );

$ids = array_map( 'intval', array_keys( $by_id ) );
sort( $ids );
$profiles = vergeml_filing_profiles( $ids, $tax );
$words    = vergeml_filing_words_sql( 'i' );
$rows     = (array) $wpdb->get_results( "SELECT i.attachment_id, i.embedding, i.kind, i.filing, i.caption, {$words['select']} FROM {$wpdb->vergeml_ai_index} i {$words['join']} WHERE i.error = '' AND i.embedding IS NOT NULL ORDER BY i.attachment_id ASC", ARRAY_A );
mt_srand( $seed );
$keys = array_keys( $rows );
shuffle( $keys ); // Seeded above: the same N every time this seed is asked.
$keys = array_slice( $keys, 0, $n );
sort( $keys );

$cards = array();
foreach ( $keys as $k ) {
    $r = $rows[ $k ];
    $r['placed_by'] = '';
    $id   = (int) $r['attachment_id'];
    $f    = vergeml_filing_facts( $r );
    $pick = vergeml_filing_pick( $f, $profiles );
    /*
     *  The thumbnail embedded (the medium size, a few tens of KB each): the
     *  page is opened as a claude.ai artifact, whose sandbox loads no image
     *  from the box, and works the same from a file on disk.
     */
    $src  = '';
    $size = image_get_intermediate_size( $id, 'medium' );
    $path = is_array( $size ) && ! empty( $size['path'] ) ? trailingslashit( wp_get_upload_dir()['basedir'] ) . $size['path'] : get_attached_file( $id );
    if ( '0' !== (string) getenv( 'VGML_EMBED' ) && $path && is_readable( $path ) && filesize( $path ) < 400000 ) { // VGML_EMBED=0: the data block without pictures, for tools/truth-model.mjs.
        $type = wp_check_filetype( $path )['type'];
        $src  = 'data:' . ( $type ? $type : 'image/jpeg' ) . ';base64,' . base64_encode( (string) file_get_contents( $path ) );
    }
    $cards[] = array(
        'id'      => $id,
        'src'     => $src,
        'says'    => implode( '; ', (array) $f['classes'] ),
        'caption' => (string) $r['caption'],
        'pick'    => (int) $pick['term_id'],
        'truth'   => (string) get_post_meta( $id, '_vergeml_seed_leaf', true ), // A seeded library's own answer (the shop); '' elsewhere.
        'word'    => $pick['term_id'] ? ( 'siblings' === $pick['why'] ? 'likely' : (string) $pick['confidence'] ) : '',
    );
}

$json = wp_json_encode( array( 'folders' => $paths, 'cards' => $cards, 'seed' => $seed, 'taken' => gmdate( 'c' ), 'site' => home_url() ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
?>
<title>Tech Library Truth</title>
<style>
:root { --ink: #101d40; --muted: #6f7891; --rule: #d5dbe9; --pane: #fff; --ground: #f7f9fc; --accent: #2b46d8; --accent-100: #e9edfc; --accent-700: #1e2f9d; --n200: #e8ecf5; --hi: #ffd84d; }
@media (prefers-color-scheme: dark) { :root:not([data-theme="light"]) { --ink: #e8ecf5; --muted: #98a2bd; --rule: #2a3350; --pane: #171d33; --ground: #0f1425; --accent: #7b8cff; --accent-100: #26305a; --accent-700: #c3caff; --n200: #232b47; --hi: #d9b400; } }
:root[data-theme="dark"] { --ink: #e8ecf5; --muted: #98a2bd; --rule: #2a3350; --pane: #171d33; --ground: #0f1425; --accent: #7b8cff; --accent-100: #26305a; --accent-700: #c3caff; --n200: #232b47; --hi: #d9b400; }
html, body { margin: 0; background: var(--ground); color: var(--ink); font-family: Inter, system-ui, sans-serif; }
.bar { top: env(safe-area-inset-top, 0px); }
.state { margin-inline-start: auto; font-size: 13px; color: var(--muted); white-space: nowrap; }
.state.is-saved { color: var(--accent-700); }
.state.is-off { color: #b3261e; }
@media (max-width: 600px) { .bar { flex-wrap: wrap; padding-inline: 16px; } .how, .grid { padding-inline: 16px; } .grid { grid-template-columns: 1fr; } }
.bar { position: sticky; top: 0; z-index: 2; display: flex; align-items: center; gap: 14px; padding: 12px 24px; background: var(--pane); border-bottom: 1px solid var(--rule); }
.bar h1 { margin: 0; font-size: 18px; letter-spacing: -.01em; }
.bar .n { font-variant-numeric: tabular-nums; color: var(--muted); font-size: 13px; }
.bar button { margin-inline-start: auto; border: 0; border-radius: 999px; padding: 9px 18px; background: var(--accent); color: #fff; font: inherit; font-size: 14px; font-weight: 600; cursor: pointer; }
.bar button:disabled { opacity: .5; cursor: default; }
.how { margin: 14px 24px 0; font-size: 13.5px; color: var(--muted); max-width: 860px; }
.grid { display: grid; grid-template-columns: repeat( auto-fill, minmax( 250px, 1fr ) ); gap: 14px; padding: 16px 24px 40px; }
.card { background: var(--pane); border: 1px solid var(--rule); border-radius: 10px; padding: 10px; display: flex; flex-direction: column; gap: 8px; }
.card.is-changed { border-color: var(--accent); box-shadow: 0 0 0 2px var(--accent-100); }
.card.is-skipped { opacity: .55; }
.card img { width: 100%; aspect-ratio: 4 / 3; object-fit: cover; border-radius: 6px; background: var(--n200); }
.says { font-size: 12.5px; color: var(--muted); line-height: 1.35; min-height: 2.7em; }
.says b { color: var(--ink); font-weight: 500; }
.row { display: flex; align-items: center; gap: 6px; }
select { flex: 1; min-width: 0; font: inherit; font-size: 13px; padding: 6px 8px; border: 1px solid var(--rule); border-radius: 6px; background: var(--pane); color: var(--ink); }
.pill { font-size: 11.5px; padding: 2px 8px; border-radius: 999px; background: var(--n200); color: var(--muted); white-space: nowrap; }
.pill.sure { background: var(--accent-100); color: var(--accent-700); }
.pill.likely { background: var(--hi); color: var(--ink); }
.id { font-size: 11px; color: var(--muted); }
</style>

<div class="bar">
	<h1>Tech library — the truth</h1>
	<span class="n"><b id="changed">0</b> corrected · <b id="skipped">0</b> skipped · <span id="total"><?php echo esc_html( count( $cards ) ); ?></span> pictures</span>
	<span class="state" id="state">Loading your marks…</span>
</div>
<p class="how">Each picture shows the folder the fill would choose today. Change the ones that are wrong; leave the right ones. <b>No folder</b> means the picture belongs in none of these; <b>Wrong picture</b> keeps a picture that should not be in this library out of the score. Every change is saved on this page as you go — close it whenever you like and come back.</p>

<div class="grid" id="grid"></div>

<script id="data" type="application/json"><?php echo str_replace( '</', '<\/', $json ); ?></script>
<script>
( function () {
	var data = JSON.parse( document.getElementById( 'data' ).textContent );
	var DOC = 'truth/tech-' + data.seed;
	var KEY = 'vgml-truth-' + data.seed;
	var kept = {};
	try { kept = JSON.parse( localStorage.getItem( KEY ) || '{}' ); } catch ( e ) { kept = {}; }
	var grid = document.getElementById( 'grid' );
	var stateEl = document.getElementById( 'state' );
	var folders = Object.keys( data.folders ).map( function ( id ) { return { id: Number( id ), path: data.folders[ id ] }; } );
	var selects = {};
	var ref = null;
	var writing = null;
	var pending = false;
	var timer = null;

	function say( text, cls ) {
		stateEl.textContent = text;
		stateEl.className = 'state' + ( cls ? ' ' + cls : '' );
	}

	function tally() {
		var changed = 0, skipped = 0;
		data.cards.forEach( function ( c ) {
			var v = kept[ c.id ];
			if ( v === undefined ) { return; }
			if ( v === 'skip' ) { skipped++; } else if ( String( v ) !== String( c.pick ) ) { changed++; }
		} );
		document.getElementById( 'changed' ).textContent = changed;
		document.getElementById( 'skipped' ).textContent = skipped;
	}

	function paint( c, card ) {
		var v = kept[ c.id ] !== undefined ? String( kept[ c.id ] ) : String( c.pick );
		selects[ c.id ].value = v;
		card.classList.toggle( 'is-changed', v !== 'skip' && v !== String( c.pick ) );
		card.classList.toggle( 'is-skipped', v === 'skip' );
	}

	// One write at a time, a burst of changes folded into one: the whole set of marks, never the DOM.
	function flush() {
		if ( ! ref || writing ) { pending = true; return; }
		pending = false;
		say( 'Saving…' );
		writing = ref.set( { seed: data.seed, marks: kept, updated: new Date().toISOString() } ).then( function () {
			writing = null;
			say( 'Saved on this page', 'is-saved' );
			if ( pending ) { flush(); }
		}, function ( e ) {
			writing = null;
			say( 'Not saved: ' + ( e && e.code ? e.code : 'no connection' ) + ' — kept in this browser', 'is-off' );
		} );
	}

	data.cards.forEach( function ( c ) {
		var card = document.createElement( 'div' );
		card.className = 'card';
		var img = document.createElement( 'img' );
		img.loading = 'lazy';
		if ( c.src ) { img.src = c.src; }
		img.alt = '';
		card.appendChild( img );
		var says = document.createElement( 'div' );
		says.className = 'says';
		var b = document.createElement( 'b' );
		b.textContent = c.says;
		says.appendChild( b );
		says.appendChild( document.createElement( 'br' ) );
		says.appendChild( document.createTextNode( c.caption ) );
		card.appendChild( says );
		var row = document.createElement( 'div' );
		row.className = 'row';
		var sel = document.createElement( 'select' );
		sel.id = 'pick-' + c.id;
		var none = document.createElement( 'option' );
		none.value = '0';
		none.textContent = '— No folder —';
		sel.appendChild( none );
		folders.forEach( function ( f ) {
			var o = document.createElement( 'option' );
			o.value = String( f.id );
			o.textContent = f.path;
			sel.appendChild( o );
		} );
		var skip = document.createElement( 'option' );
		skip.value = 'skip';
		skip.textContent = '✗ Wrong picture';
		sel.appendChild( skip );
		selects[ c.id ] = sel;
		sel.addEventListener( 'change', function () {
			kept[ c.id ] = sel.value === 'skip' ? 'skip' : Number( sel.value );
			try { localStorage.setItem( KEY, JSON.stringify( kept ) ); } catch ( e ) {}
			paint( c, card );
			tally();
			clearTimeout( timer );
			timer = setTimeout( flush, 700 );
		} );
		row.appendChild( sel );
		if ( c.word ) {
			var pill = document.createElement( 'span' );
			pill.className = 'pill ' + c.word;
			pill.textContent = c.word;
			row.appendChild( pill );
		}
		card.appendChild( row );
		var id = document.createElement( 'div' );
		id.className = 'id';
		id.textContent = '#' + c.id;
		card.appendChild( id );
		paint( c, card );
		grid.appendChild( card );
	} );
	tally();

	// The marks kept on the page (the db capability): loaded once, then written on every change.
	var use = window.claude && window.claude.use ? window.claude.use( 'db' ) : Promise.resolve( null );
	use.then( function ( db ) {
		if ( ! db ) {
			say( 'Marks kept in this browser only', 'is-off' );
			return;
		}
		ref = db.doc( DOC );
		return ref.get().then( function ( snap ) {
			var stored = snap && snap.exists && snap.data() && snap.data().marks;
			if ( stored && typeof stored === 'object' ) {
				Object.keys( stored ).forEach( function ( id ) { kept[ id ] = stored[ id ]; } );
				try { localStorage.setItem( KEY, JSON.stringify( kept ) ); } catch ( e ) {}
				data.cards.forEach( function ( c ) { paint( c, selects[ c.id ].closest( '.card' ) ); } );
				tally();
			}
			say( Object.keys( kept ).length ? 'Saved on this page' : 'Nothing marked yet', 'is-saved' );
			if ( pending ) { flush(); }
		} );
	} ).catch( function () {
		say( 'Marks kept in this browser only', 'is-off' );
	} );
} )();
</script>
