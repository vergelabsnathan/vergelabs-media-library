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
    $img  = wp_get_attachment_image_src( $id, 'medium' );
    $cards[] = array(
        'id'      => $id,
        'src'     => $img ? (string) $img[0] : (string) wp_get_attachment_url( $id ),
        'says'    => implode( '; ', (array) $f['classes'] ),
        'caption' => (string) $r['caption'],
        'pick'    => (int) $pick['term_id'],
        'word'    => $pick['term_id'] ? ( 'siblings' === $pick['why'] ? 'likely' : (string) $pick['confidence'] ) : '',
    );
}

$json = wp_json_encode( array( 'folders' => $paths, 'cards' => $cards, 'seed' => $seed, 'taken' => gmdate( 'c' ), 'site' => home_url() ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
?>
<!doctype html>
<meta charset="utf-8">
<title>Tech library — the truth, <?php echo esc_html( count( $cards ) ); ?> pictures</title>
<style>
:root { --ink: #101d40; --muted: #6f7891; --rule: #d5dbe9; --pane: #fff; --ground: #f7f9fc; --accent: #2b46d8; --accent-100: #e9edfc; --accent-700: #1e2f9d; --n200: #e8ecf5; --hi: #ffd84d; }
html, body { margin: 0; background: var(--ground); color: var(--ink); font-family: Inter, system-ui, sans-serif; }
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
	<button id="save" type="button">Save the truth</button>
</div>
<p class="how">Each picture shows the folder the fill would choose today. Change the ones that are wrong; leave the right ones. <b>No folder</b> means the picture belongs in none of these; <b>Wrong picture</b> keeps a picture that should not be in this library out of the score. Save gives you a file — drop it in the repo as <code>tests/tree/truth-tech.json</code>. Your choices are kept in this browser until you save.</p>

<div class="grid" id="grid"></div>

<script id="data" type="application/json"><?php echo str_replace( '</', '<\/', $json ); ?></script>
<script>
( function () {
	var data = JSON.parse( document.getElementById( 'data' ).textContent );
	var KEY = 'vgml-truth-' + data.seed;
	var kept = {};
	try { kept = JSON.parse( localStorage.getItem( KEY ) || '{}' ); } catch ( e ) { kept = {}; }
	var grid = document.getElementById( 'grid' );
	var folders = Object.keys( data.folders ).map( function ( id ) { return { id: Number( id ), path: data.folders[ id ] }; } );

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

	data.cards.forEach( function ( c ) {
		var card = document.createElement( 'div' );
		card.className = 'card';
		var img = document.createElement( 'img' );
		img.loading = 'lazy';
		img.src = c.src;
		img.alt = '';
		card.appendChild( img );
		var says = document.createElement( 'div' );
		says.className = 'says';
		says.innerHTML = '<b></b><br>';
		says.querySelector( 'b' ).textContent = c.says;
		says.appendChild( document.createTextNode( c.caption ) );
		card.appendChild( says );
		var row = document.createElement( 'div' );
		row.className = 'row';
		var sel = document.createElement( 'select' );
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
		var v = kept[ c.id ] !== undefined ? String( kept[ c.id ] ) : String( c.pick );
		sel.value = v;
		sel.addEventListener( 'change', function () {
			kept[ c.id ] = sel.value === 'skip' ? 'skip' : Number( sel.value );
			try { localStorage.setItem( KEY, JSON.stringify( kept ) ); } catch ( e ) {}
			card.classList.toggle( 'is-changed', sel.value !== 'skip' && sel.value !== String( c.pick ) );
			card.classList.toggle( 'is-skipped', sel.value === 'skip' );
			tally();
		} );
		card.classList.toggle( 'is-changed', v !== 'skip' && v !== String( c.pick ) );
		card.classList.toggle( 'is-skipped', v === 'skip' );
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
		grid.appendChild( card );
	} );
	tally();

	document.getElementById( 'save' ).addEventListener( 'click', function () {
		var out = {};
		data.cards.forEach( function ( c ) {
			var v = kept[ c.id ] !== undefined ? kept[ c.id ] : c.pick;
			if ( v === 'skip' ) { return; }
			out[ c.id ] = v ? ( data.folders[ v ] || '' ) : '';
		} );
		var blob = new Blob( [ JSON.stringify( out, null, 1 ) ], { type: 'application/json' } );
		var a = document.createElement( 'a' );
		a.href = URL.createObjectURL( blob );
		a.download = 'truth-tech.json';
		a.click();
	} );
} )();
</script>
