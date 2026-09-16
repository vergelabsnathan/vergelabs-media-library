<?php
/*
 *  The quality sample (every-picture-a-home A.5).
 *
 *  After a fill: 60 pictures the fill placed -- 30 the matcher called `sure`
 *  and 30 `likely` -- as one contact sheet, each with the folder it landed in,
 *  the word, the score and the runner-up. A person marks each right or wrong
 *  on the sheet; the sheet keeps the tally and copies the verdict as one line.
 *  The spec's bar (§5): sure right in >= 95 of 100, likely in >= 80.
 *
 *  The word is read the way the screen reads it -- vergeml_filing_confidence()
 *  over the newest live moves row that names a folder the picture is in now --
 *  so what the sheet says is what the list row and the modal say. The sample is
 *  seeded by that row's batch, so the same fill gives the same 60 every run.
 *
 *      bash tools/box-folder-quality.sh > docs/superpowers/mocks/shots/<date>-quality-sample.html
 *
 *  VGML_DRY=1 VGML_SEED=133: the sample the engine as deployed would give
 *  without a fill -- every picture picked fresh (a hand placement picked like
 *  any other, nothing written), the pools drawn from those picks on the given
 *  seed, each card carrying where the last fill put it. A card whose folder
 *  the new pick keeps carries the earlier verdict's mark (the table below,
 *  from the 2026-09-16 sheet); one that moved or is newly placed waits for a
 *  mark. Before the sheet, the earlier 60 are re-read under the new engine:
 *  right kept, wrong dropped, right lost, moved -- the honest pair of numbers
 *  for a re-take without a fill.
 *
 *  Read-only. Nothing is moved, no folder is made, no model is reached.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;
wp_set_current_user( 1 );

$tax   = vergeml_librarian_taxonomy();
$moves = $wpdb->vergeml_librarian_moves;
$each  = 30;
$dry   = '1' === (string) getenv( 'VGML_DRY' );

/*
 *  The 2026-09-16 verdict on batch 133's 60 (Nathan): sure 14/30, likely 12/30
 *  with 11 too broad. Every id below was on the sheet; the ones not named
 *  wrong or broad were marked right.
 */
$bfq_old = array(
    'sure'   => array( 106032, 106603, 106029, 106468, 106524, 106027, 106590, 106178, 106224, 106189, 106798, 106724, 106235, 106075, 105857, 106226, 106729, 106280, 106535, 106728, 106209, 106177, 106441, 106074, 106628, 106203, 106061, 105841, 106128, 105969 ),
    'likely' => array( 106238, 105871, 106467, 106079, 106278, 106102, 106006, 106085, 106631, 106540, 106609, 106461, 105826, 106622, 106611, 105883, 106627, 105932, 105831, 106633, 106003, 105941, 106002, 105875, 105888, 106623, 105846, 106533, 105836, 106614 ),
    'wrong'  => array( 106603, 106029, 106468, 106027, 106224, 106798, 106075, 106729, 106535, 106728, 106209, 106441, 106074, 106203, 106061, 105969, 106238, 106079, 106085, 105826, 105883, 105875, 105888 ),
    'broad'  => array( 106102, 106631, 106609, 106622, 106611, 106627, 105932, 106633, 106002, 106623, 106614 ),
);
$bfq_mark = array();
foreach ( array_merge( $bfq_old['sure'], $bfq_old['likely'] ) as $bfq_id ) {
    $bfq_mark[ $bfq_id ] = in_array( $bfq_id, $bfq_old['wrong'], true ) ? 'wrong' : ( in_array( $bfq_id, $bfq_old['broad'], true ) ? 'broad' : 'right' );
}

// The newest live row per picture that names a folder the picture is in now: the row that speaks for the placement.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$rows = $wpdb->get_results( $wpdb->prepare(
    "SELECT m.move_id, m.attachment_id, m.term_id, m.why, m.score, m.runner_up, m.runner_score, m.batch_id
       FROM {$moves} m
       JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = m.term_id AND tt.taxonomy = %s
       JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tr.object_id = m.attachment_id
      WHERE m.undone = 0 AND m.why IN ('ok', 'siblings')
      ORDER BY m.move_id DESC",
    $tax
), ARRAY_A );

$speaks = array();
foreach ( $rows as $r ) {
    $id = (int) $r['attachment_id'];
    if ( ! isset( $speaks[ $id ] ) ) {
        $speaks[ $id ] = $r;
    }
}

$pool = array( 'sure' => array(), 'likely' => array() );
$batch = 0;
foreach ( $speaks as $id => $r ) {
    $word = vergeml_filing_confidence( $id, $r );
    if ( isset( $pool[ $word ] ) ) {
        $pool[ $word ][] = $id;
        $batch = max( $batch, (int) $r['batch_id'] );
    }
}

$was     = $speaks; // The last fill's placement per picture, for the dry sheet's "was" line.
$reread  = '';
$prefill = array();

if ( $dry ) {
    $terms    = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) );
    $profiles = vergeml_filing_profiles( array_map( function ( $t ) { return (int) $t->term_id; }, $terms ), $tax );
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $index    = $wpdb->get_results( "SELECT attachment_id, embedding, kind, filing FROM {$wpdb->vergeml_ai_index} WHERE error = '' AND embedding IS NOT NULL ORDER BY attachment_id", ARRAY_A );
    $counted  = vergeml_filing_count( $profiles, $index );
    $picks    = $counted['picks'];
    $t        = $counted['counts'];
    $batch    = (int) ( getenv( 'VGML_SEED' ) ?: $batch );

    // The pools, from the picks: the word the fill would put on each picture.
    $pool   = array( 'sure' => array(), 'likely' => array() );
    $speaks = array();
    foreach ( $picks as $id => $pick ) {
        if ( ! $pick['term_id'] ) {
            continue;
        }
        $row = array( 'term_id' => $pick['term_id'], 'why' => $pick['why'], 'score' => $pick['score'], 'runner_up' => $pick['runner_up'], 'runner_score' => $pick['runner_score'], 'batch_id' => $batch );
        $word = 'siblings' === $pick['why'] ? 'likely' : $pick['confidence'];
        if ( isset( $pool[ $word ] ) ) {
            $pool[ $word ][] = (int) $id;
            $speaks[ (int) $id ] = $row;
        }
    }

    // The earlier 60 under this engine.
    $lines = array();
    $sum   = array();
    foreach ( array( 'sure', 'likely' ) as $word ) {
        $sum[ $word ] = array( 'right kept' => 0, 'wrong dropped' => 0, 'right lost' => 0, 'wrong kept' => 0, 'moved' => 0, 'broad kept' => 0 );
        foreach ( $bfq_old[ $word ] as $id ) {
            $mark = $bfq_mark[ $id ];
            $old  = isset( $was[ $id ] ) ? (int) $was[ $id ]['term_id'] : 0;
            $pick = isset( $picks[ $id ] ) ? $picks[ $id ] : null;
            $new  = $pick ? (int) $pick['term_id'] : 0;
            if ( $new && $new === $old ) {
                $fate = 'right' === $mark ? 'right kept' : ( 'broad' === $mark ? 'broad kept' : 'wrong kept' );
            } elseif ( ! $new ) {
                $fate = 'right' === $mark ? 'right lost' : 'wrong dropped';
            } else {
                $fate = 'moved';
            }
            $sum[ $word ][ $fate ]++;
            $lines[] = sprintf( '%-6s %6d  %-5s  was %-32s  now %-48s  %s', $word, $id, $mark, bfq_path( $old, $tax ), $new ? sprintf( '%s (%s %.2f)', bfq_path( $new, $tax ), $pick['why'], $pick['score'] ) : sprintf( 'nothing (%s %.2f)', $pick ? $pick['why'] : '-', $pick ? $pick['score'] : 0 ), $fate );
        }
    }
    $reread  = sprintf( "engine as deployed, fresh picks over %d: fits %d (sure %d, likely %d) + siblings %d + nothing %d (floor %d, margin %d, either %d, gated %d)\n", $t['looked'], $t['fits'], $t['sure'], $t['likely'], $t['siblings'], $t['nothing'], $t['why']['floor'], $t['why']['margin'], (int) $t['either'], $t['why']['gated'] );
    $reread .= "the earlier 60 (batch 133, marked 2026-09-16) under this engine:\n";
    foreach ( $sum as $word => $s ) {
        $reread .= sprintf( "  %-6s  %s\n", $word, implode( ' · ', array_map( function ( $k, $n ) { return $k . ' ' . $n; }, array_keys( $s ), $s ) ) );
    }
    $reread .= implode( "\n", $lines ) . "\n";
    echo "<!--\n", esc_html( $reread ), "-->\n";
}

if ( count( $pool['sure'] ) < $each || count( $pool['likely'] ) < $each ) {
    printf( "<!-- not enough to sample: %d sure, %d likely (need %d each) -->\n", count( $pool['sure'] ), count( $pool['likely'] ), $each );
}

mt_srand( $batch ?: 1 );
$sample = array();
foreach ( array( 'sure', 'likely' ) as $word ) {
    $ids = $pool[ $word ];
    sort( $ids );
    shuffle( $ids );
    foreach ( array_slice( $ids, 0, $each ) as $id ) {
        $sample[] = array( 'word' => $word, 'id' => $id, 'row' => $speaks[ $id ] );
        // Same folder as the fill Nathan marked: his mark stands. Anything else waits for one.
        if ( $dry && isset( $bfq_mark[ $id ], $was[ $id ] ) && (int) $was[ $id ]['term_id'] === (int) $speaks[ $id ]['term_id'] ) {
            $prefill[ count( $sample ) ] = $bfq_mark[ $id ];
        }
    }
}

function bfq_path( $term_id, $tax ) {
    $out = array();
    $t   = get_term( (int) $term_id, $tax );
    while ( $t instanceof WP_Term ) {
        array_unshift( $out, $t->name );
        $t = $t->parent ? get_term( (int) $t->parent, $tax ) : null;
    }
    return implode( ' / ', $out );
}

function bfq_thumb( $id ) {
    $file = get_attached_file( $id );
    $meta = wp_get_attachment_metadata( $id );
    $pick = $file;
    foreach ( array( 'medium', 'thumbnail' ) as $size ) {
        if ( isset( $meta['sizes'][ $size ]['file'] ) && file_exists( dirname( $file ) . '/' . $meta['sizes'][ $size ]['file'] ) ) {
            $pick = dirname( $file ) . '/' . $meta['sizes'][ $size ]['file'];
            break;
        }
    }
    if ( ! $pick || ! file_exists( $pick ) ) {
        return '';
    }
    $mime = wp_check_filetype( $pick );
    return 'data:' . ( $mime['type'] ?: 'image/jpeg' ) . ';base64,' . base64_encode( file_get_contents( $pick ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
}

$cards = array();
$n     = 0;
foreach ( $sample as $s ) {
    $n++;
    $r     = $s['row'];
    $title = get_the_title( $s['id'] );
    if ( '' === trim( (string) $title ) ) {
        $title = wp_basename( (string) get_attached_file( $s['id'] ) );
    }
    $runner  = (int) $r['runner_up'] ? bfq_path( (int) $r['runner_up'], $tax ) : '';
    $wasline = '';
    if ( $dry ) {
        $old     = isset( $was[ $s['id'] ] ) ? (int) $was[ $s['id'] ]['term_id'] : 0;
        $same    = $old && $old === (int) $r['term_id'];
        $wasline = sprintf( '<small class="was %s">%s</small>', $same ? 'same' : 'diff', esc_html( $old ? ( $same ? 'same folder as the last fill' : 'last fill: ' . bfq_path( $old, $tax ) ) : 'last fill: not placed' ) );
    }
    $cards[] = sprintf(
        '<figure class="c %1$s" data-n="%2$d" data-id="%3$d" data-word="%1$s"><img src="%4$s" alt="" loading="lazy"><figcaption><b>%5$s</b><span class="pill %1$s">%1$s</span><small>%6$s</small>%9$s<small class="t">#%2$d · <a href="%7$s" target="_blank" rel="noopener">%8$s</a></small></figcaption><div class="mark"><button data-v="right">right</button><button data-v="broad">too broad</button><button data-v="wrong">wrong</button></div></figure>',
        $s['word'],
        $n,
        $s['id'],
        esc_attr( bfq_thumb( $s['id'] ) ),
        esc_html( bfq_path( (int) $r['term_id'], $tax ) ),
        esc_html( sprintf( '%s %.2f%s', $r['why'], (float) $r['score'], '' !== $runner ? sprintf( ' · next %s %.2f', $runner, (float) $r['runner_score'] ) : '' ) ),
        esc_url( admin_url( 'upload.php?item=' . $s['id'] ) ),
        esc_html( mb_substr( (string) $title, 0, 40 ) ),
        $wasline
    );
}

$made = wp_date( 'Y-m-d H:i' );
$head = sprintf( '%d sure of %d · %d likely of %d · %s %d · %s%s', $each, count( $pool['sure'] ), $each, count( $pool['likely'] ), $dry ? 'dry, seed' : 'fill batch', $batch, $made, $dry ? sprintf( ' · %d marks carried over', count( $prefill ) ) : '' );
?>
<!doctype html>
<meta charset="utf-8">
<title>Quality sample — <?php echo esc_html( $made ); ?></title>
<style>
body{margin:0;padding:24px;font:14px/1.4 system-ui,sans-serif;background:#f6f6f4;color:#1a1a1a}
header{position:sticky;top:0;background:#f6f6f4;padding:8px 0 12px;border-bottom:1px solid #ddd;z-index:2}
h1{font-size:16px;margin:0 0 4px}
.tally{display:flex;gap:16px;align-items:center;flex-wrap:wrap}
.tally span{padding:3px 10px;border-radius:999px;background:#e8e8e4}
.tally .bad{background:#f7d6d6}.tally .good{background:#d7efd9}
button.copy{margin-left:auto;padding:6px 12px;border:1px solid #999;border-radius:6px;background:#fff;cursor:pointer}
h2{font-size:13px;text-transform:uppercase;letter-spacing:.08em;margin:24px 0 8px;color:#666}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:12px}
figure.c{margin:0;background:#fff;border:1px solid #ddd;border-radius:8px;overflow:hidden;display:flex;flex-direction:column}
figure.c img{width:100%;aspect-ratio:4/3;object-fit:contain;background:#eee;display:block}
figcaption{padding:8px 10px 4px;display:flex;flex-direction:column;gap:2px}
figcaption b{font-size:14px}
figcaption small{color:#555;font-size:12px}
figcaption .t a{color:#555}
.pill{align-self:flex-start;font-size:11px;padding:1px 8px;border-radius:999px;background:#e8e8e4}
.pill.sure{background:#d7efd9}.pill.likely{background:#fbeccb}
.mark{display:flex;border-top:1px solid #eee;margin-top:auto}
.mark button{flex:1;padding:8px;border:0;background:#fafafa;cursor:pointer;font:inherit}
.mark button+button{border-left:1px solid #eee}
figure.is-right .mark [data-v=right]{background:#cfe9d1;font-weight:600}
figure.is-wrong .mark [data-v=wrong]{background:#f3c5c5;font-weight:600}
figure.is-broad .mark [data-v=broad]{background:#fbeccb;font-weight:600}
figure.is-broad{outline:2px solid #d9a441}
figure.is-wrong{outline:2px solid #d66}
small.was.same{color:#3a7d44}small.was.diff{color:#8a5a00}
</style>
<header>
  <h1>Quality sample · <?php echo esc_html( $head ); ?></h1>
  <div class="tally"><span id="s">sure —</span><span id="l">likely —</span><span id="left">60 to mark</span><button class="copy" id="copy">Copy verdict</button></div>
</header>
<h2>Sure</h2>
<div class="grid"><?php echo implode( '', array_slice( $cards, 0, $each ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
<h2>Likely</h2>
<div class="grid"><?php echo implode( '', array_slice( $cards, $each ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
<script>
(function(){
  var KEY='vgml-quality-<?php echo $dry ? 'dry-' : ''; echo (int) $batch; ?>', marks={};
  try{marks=JSON.parse(localStorage.getItem(KEY)||'{}')}catch(e){}
  var carried=<?php echo wp_json_encode( (object) $prefill ); ?>;
  Object.keys(carried).forEach(function(n){if(!marks[n])marks[n]=carried[n]});
  var figs=[].slice.call(document.querySelectorAll('figure.c'));
  function paint(){
    var t={sure:{r:0,b:0,w:0},likely:{r:0,b:0,w:0}},left=0;
    figs.forEach(function(f){
      var v=marks[f.dataset.n];f.classList.toggle('is-right',v==='right');f.classList.toggle('is-wrong',v==='wrong');f.classList.toggle('is-broad',v==='broad');
      if(!v){left++}else{t[f.dataset.word][v==='right'?'r':(v==='broad'?'b':'w')]++}
    });
    function line(w,el,bar){var n=t[w].r+t[w].b+t[w].w,p=n?Math.round(100*t[w].r/n):0;el.textContent=w+' '+t[w].r+'/'+n+' right'+(t[w].b?' · '+t[w].b+' too broad':'')+(n?' · '+p+'%':'');el.className=n?(p>=bar?'good':'bad'):''}
    line('sure',document.getElementById('s'),95);line('likely',document.getElementById('l'),80);
    document.getElementById('left').textContent=left?left+' to mark':'all marked';
    try{localStorage.setItem(KEY,JSON.stringify(marks))}catch(e){}
  }
  document.addEventListener('click',function(e){
    var b=e.target.closest('.mark button');if(!b)return;
    var f=b.closest('figure');marks[f.dataset.n]=b.dataset.v;paint();
  });
  document.getElementById('copy').addEventListener('click',function(){
    var out=['sure','likely'].map(function(w){
      var wrong=figs.filter(function(f){return f.dataset.word===w&&marks[f.dataset.n]==='wrong'}).map(function(f){return '#'+f.dataset.n+' (id '+f.dataset.id+')'});var broad=figs.filter(function(f){return f.dataset.word===w&&marks[f.dataset.n]==='broad'}).map(function(f){return '#'+f.dataset.n+' (id '+f.dataset.id+')'});
      var n=figs.filter(function(f){return f.dataset.word===w&&marks[f.dataset.n]}).length;
      return w+': '+(n-wrong.length-broad.length)+'/'+n+' right'+(broad.length?' · too broad '+broad.join(', '):'')+(wrong.length?' · wrong '+wrong.join(', '):'');
    }).join('\n');
    navigator.clipboard.writeText(out).then(function(){document.getElementById('copy').textContent='Copied'});
  });
  paint();
})();
</script>
