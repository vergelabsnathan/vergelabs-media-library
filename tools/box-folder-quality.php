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

/*
 *  The 2026-09-16 verdict on the C.1 dry sheet (seed 133, no fill; Nathan):
 *  sure 22/30, likely 7/30. Each id with its word, its mark and the folder
 *  the C.1 engine picked, so a later dry sheet can carry the mark where the
 *  pick is the same folder and re-read the 60 by folder path.
 */
$bfq_dry = array(
    105876 => array( 'sure', 'right', 'Hardware / Phones' ),
    106552 => array( 'sure', 'broad', 'Space' ),
    105861 => array( 'sure', 'wrong', 'Hardware / Components' ),
    105815 => array( 'sure', 'right', 'Data centres / Server racks' ),
    106798 => array( 'sure', 'wrong', 'Space / Satellites' ),
    106644 => array( 'sure', 'right', 'Energy / Batteries' ),
    106452 => array( 'sure', 'right', 'Hardware / Components' ),
    106624 => array( 'sure', 'right', 'Energy / Wind' ),
    106099 => array( 'sure', 'wrong', 'Hardware / Components' ),
    106130 => array( 'sure', 'right', 'Hardware / Components' ),
    105924 => array( 'sure', 'right', 'Hardware / Laptops' ),
    106161 => array( 'sure', 'right', 'Hardware / Laptops' ),
    106405 => array( 'sure', 'broad', 'People' ),
    105843 => array( 'sure', 'right', 'Data centres / Server racks' ),
    106301 => array( 'sure', 'right', 'Robotics' ),
    106516 => array( 'sure', 'broad', 'Space / Satellites' ),
    105919 => array( 'sure', 'right', 'Hardware / Laptops' ),
    105882 => array( 'sure', 'right', 'Hardware / Phones' ),
    106124 => array( 'sure', 'right', 'Hardware / Components' ),
    106285 => array( 'sure', 'right', 'Robotics' ),
    105899 => array( 'sure', 'right', 'Hardware / Phones' ),
    106525 => array( 'sure', 'right', 'Space / Satellites' ),
    106021 => array( 'sure', 'right', 'Hardware / Components' ),
    106220 => array( 'sure', 'right', 'People' ),
    106588 => array( 'sure', 'right', 'Energy / Solar' ),
    105842 => array( 'sure', 'right', 'Data centres / Server racks' ),
    106675 => array( 'sure', 'wrong', 'Space / Satellites' ),
    106074 => array( 'sure', 'broad', 'Hardware / Components' ),
    106656 => array( 'sure', 'right', 'Energy / Batteries' ),
    106127 => array( 'sure', 'right', 'Hardware / Components' ),
    106158 => array( 'likely', 'wrong', 'Space' ),
    105893 => array( 'likely', 'broad', 'Hardware' ),
    106029 => array( 'likely', 'broad', 'Hardware' ),
    106367 => array( 'likely', 'wrong', 'Hardware / Phones' ),
    106422 => array( 'likely', 'wrong', 'Energy / Batteries' ),
    106265 => array( 'likely', 'right', 'Robotics' ),
    106360 => array( 'likely', 'wrong', 'Space / Satellites' ),
    106349 => array( 'likely', 'broad', 'Hardware' ),
    105948 => array( 'likely', 'broad', 'Hardware' ),
    105892 => array( 'likely', 'right', 'Hardware / Phones' ),
    106068 => array( 'likely', 'broad', 'Hardware / Components' ),
    106022 => array( 'likely', 'broad', 'Hardware' ),
    106267 => array( 'likely', 'right', 'Robotics' ),
    106676 => array( 'likely', 'wrong', 'Data centres / Cooling' ),
    105906 => array( 'likely', 'broad', 'Hardware' ),
    106018 => array( 'likely', 'broad', 'Hardware' ),
    106079 => array( 'likely', 'wrong', 'Data centres / Cooling' ),
    105985 => array( 'likely', 'broad', 'Hardware' ),
    105911 => array( 'likely', 'broad', 'Hardware' ),
    106036 => array( 'likely', 'broad', 'Hardware' ),
    106704 => array( 'likely', 'wrong', 'Space / Satellites' ),
    106182 => array( 'likely', 'right', 'People / Conference talks' ),
    106696 => array( 'likely', 'wrong', 'Energy' ),
    106065 => array( 'likely', 'broad', 'Hardware / Components' ),
    106054 => array( 'likely', 'broad', 'Hardware / Components' ),
    105990 => array( 'likely', 'right', 'Hardware / Components' ),
    105886 => array( 'likely', 'wrong', 'Energy / Batteries' ),
    105944 => array( 'likely', 'broad', 'Hardware' ),
    106012 => array( 'likely', 'right', 'Hardware / Components' ),
    106200 => array( 'likely', 'right', 'People / Conference talks' ),
);

/*
 *  The 2026-09-16 verdict on the C.4 dry sheet (seed 133, no fill; Nathan):
 *  sure 26/30, likely 17/30. His notes: the smartwatches have no folder and
 *  land in Phones (the four likely wrong there); motherboards and the like sit
 *  in Hardware where Components is right; cables are too broad; the shuttle
 *  launch in Space belongs in Launches.
 */
$bfq_c4 = array(
    105996 => array( 'sure', 'right', 'Hardware / Components' ),
    106452 => array( 'sure', 'right', 'Hardware / Components' ),
    106660 => array( 'sure', 'right', 'Energy / Batteries' ),
    105834 => array( 'sure', 'right', 'Data centres / Server racks' ),
    105926 => array( 'sure', 'right', 'Hardware / Laptops' ),
    106051 => array( 'sure', 'right', 'Hardware / Components' ),
    106729 => array( 'sure', 'right', 'Hardware / Components' ),
    106720 => array( 'sure', 'wrong', 'Energy / Batteries' ),
    105828 => array( 'sure', 'right', 'Data centres / Server racks' ),
    106109 => array( 'sure', 'right', 'Hardware / Components' ),
    106621 => array( 'sure', 'right', 'Energy / Wind' ),
    106608 => array( 'sure', 'right', 'Energy / Solar' ),
    106684 => array( 'sure', 'right', 'Robotics' ),
    106637 => array( 'sure', 'right', 'Energy / Batteries' ),
    105924 => array( 'sure', 'right', 'Hardware / Laptops' ),
    106549 => array( 'sure', 'broad', 'Space' ),
    106451 => array( 'sure', 'right', 'Hardware / Components' ),
    106489 => array( 'sure', 'broad', 'Hardware / Components' ),
    105915 => array( 'sure', 'right', 'Hardware / Laptops' ),
    105887 => array( 'sure', 'right', 'Hardware / Phones' ),
    106307 => array( 'sure', 'right', 'Robotics' ),
    105883 => array( 'sure', 'right', 'Hardware / Phones' ),
    106513 => array( 'sure', 'right', 'Hardware / Components' ),
    106074 => array( 'sure', 'right', 'Hardware / Components' ),
    106282 => array( 'sure', 'right', 'Robotics' ),
    106010 => array( 'sure', 'right', 'Hardware / Components' ),
    105904 => array( 'sure', 'right', 'Hardware / Laptops' ),
    106492 => array( 'sure', 'broad', 'Hardware / Components' ),
    106731 => array( 'sure', 'right', 'Energy / Batteries' ),
    106025 => array( 'sure', 'right', 'Hardware / Components' ),
    106497 => array( 'likely', 'right', 'Hardware / Components' ),
    105999 => array( 'likely', 'right', 'Hardware' ),
    106217 => array( 'likely', 'right', 'People / Conference talks' ),
    106018 => array( 'likely', 'broad', 'Hardware' ),
    106041 => array( 'likely', 'broad', 'Hardware' ),
    106182 => array( 'likely', 'right', 'People / Conference talks' ),
    106374 => array( 'likely', 'wrong', 'Hardware / Phones' ),
    106082 => array( 'likely', 'broad', 'Hardware / Components' ),
    106666 => array( 'likely', 'broad', 'Hardware' ),
    106231 => array( 'likely', 'right', 'People / Conference talks' ),
    106366 => array( 'likely', 'wrong', 'Hardware / Phones' ),
    106784 => array( 'likely', 'wrong', 'Space' ),
    105880 => array( 'likely', 'right', 'Hardware' ),
    106368 => array( 'likely', 'wrong', 'Hardware / Phones' ),
    106031 => array( 'likely', 'right', 'Hardware' ),
    106477 => array( 'likely', 'broad', 'Energy' ),
    106027 => array( 'likely', 'right', 'Hardware' ),
    105957 => array( 'likely', 'wrong', 'Space' ),
    105993 => array( 'likely', 'right', 'Hardware' ),
    105973 => array( 'likely', 'right', 'Hardware' ),
    105991 => array( 'likely', 'right', 'Hardware' ),
    106457 => array( 'likely', 'right', 'Hardware' ),
    106200 => array( 'likely', 'right', 'People / Conference talks' ),
    106081 => array( 'likely', 'right', 'Hardware / Components' ),
    105893 => array( 'likely', 'broad', 'Hardware' ),
    106390 => array( 'likely', 'wrong', 'Hardware / Phones' ),
    106696 => array( 'likely', 'wrong', 'Energy' ),
    105916 => array( 'likely', 'right', 'Hardware / Laptops' ),
    106120 => array( 'likely', 'right', 'Hardware' ),
    105983 => array( 'likely', 'right', 'Hardware' ),
);

/*
 *  The 2026-09-16 verdict on the shop library's fill sheet (C.5, batch 1;
 *  Nathan): sure 25/30, likely 11/30 with 11 too broad. His reading held
 *  by the handoff: six likely wrongs in Garden (planner profile "power
 *  tool, architecture"), three laptops in Electronics / Computers by the
 *  siblings rule. The site is /var/www/ms2 (VGML_SITE=shop); ids are its own.
 */
$bfq_shop = array(
    367 => array( 'sure', 'right', 'Electronics / Cameras / Tripods' ),
    375 => array( 'sure', 'right', 'Electronics / Cameras / Tripods' ),
    114 => array( 'sure', 'right', 'Clothing / Accessories / Scarves' ),
    382 => array( 'sure', 'right', 'Electronics / Gaming / Controllers' ),
    372 => array( 'sure', 'right', 'Electronics / Cameras' ),
    353 => array( 'sure', 'right', 'Electronics / Cameras / Digital cameras' ),
    321 => array( 'sure', 'right', 'Electronics / Audio' ),
    45 => array( 'sure', 'right', 'Clothing / Women / Heels' ),
    53 => array( 'sure', 'right', 'Clothing / Men / Sandals' ),
    516 => array( 'sure', 'right', 'Kitchen / Appliances / Kettles' ),
    432 => array( 'sure', 'wrong', 'Books & Music / Books' ),
    239 => array( 'sure', 'wrong', 'Beauty / Fragrance / Perfume' ),
    413 => array( 'sure', 'right', 'Home / Furniture / Dining tables' ),
    143 => array( 'sure', 'right', 'Bags & Luggage / Handbags' ),
    102 => array( 'sure', 'right', 'Clothing' ),
    510 => array( 'sure', 'right', 'Kitchen / Knives & Boards / Cutting boards' ),
    323 => array( 'sure', 'right', 'Cars & Bikes / Motorbikes' ),
    244 => array( 'sure', 'right', 'Beauty / Make-up / Lipstick' ),
    509 => array( 'sure', 'right', 'Electronics / Gaming / Controllers' ),
    566 => array( 'sure', 'right', 'Sports & Outdoors / Water sports / Surfboards' ),
    99 => array( 'sure', 'right', 'Clothing / Accessories / Hats' ),
    270 => array( 'sure', 'right', 'Bags & Luggage' ),
    345 => array( 'sure', 'right', 'Electronics / Cameras / Digital cameras' ),
    30 => array( 'sure', 'broad', 'Shoes' ),
    128 => array( 'sure', 'right', 'Clothing / Accessories / Sunglasses' ),
    348 => array( 'sure', 'broad', 'Electronics / Cameras / Digital cameras' ),
    229 => array( 'sure', 'wrong', 'Jewellery & Watches / Earrings' ),
    513 => array( 'sure', 'right', 'Kitchen / Appliances / Coffee machines' ),
    309 => array( 'sure', 'right', 'Electronics / Computers / Keyboards' ),
    335 => array( 'sure', 'right', 'Electronics / Audio' ),
    178 => array( 'likely', 'wrong', 'Home / Decoration / Wall art' ),
    473 => array( 'likely', 'wrong', 'Garden' ),
    521 => array( 'likely', 'broad', 'Kitchen / Tableware' ),
    242 => array( 'likely', 'wrong', 'Beauty / Fragrance / Perfume' ),
    181 => array( 'likely', 'right', 'Jewellery & Watches / Watches' ),
    442 => array( 'likely', 'wrong', 'Garden' ),
    107 => array( 'likely', 'broad', 'Clothing / Accessories' ),
    58 => array( 'likely', 'broad', 'Garden' ),
    478 => array( 'likely', 'wrong', 'Garden' ),
    185 => array( 'likely', 'wrong', 'Jewellery & Watches / Watches / Smart watches' ),
    106 => array( 'likely', 'right', 'Clothing / Accessories / Ties' ),
    357 => array( 'likely', 'broad', 'Electronics / Cameras' ),
    212 => array( 'likely', 'broad', 'Home / Decoration / Wall art' ),
    291 => array( 'likely', 'broad', 'Electronics / Computers' ),
    248 => array( 'likely', 'right', 'Beauty / Make-up' ),
    476 => array( 'likely', 'wrong', 'Garden' ),
    89 => array( 'likely', 'broad', 'Clothing / Women / Sweaters' ),
    290 => array( 'likely', 'broad', 'Electronics / Computers' ),
    539 => array( 'likely', 'broad', 'Food & Drink' ),
    188 => array( 'likely', 'right', 'Jewellery & Watches / Watches' ),
    288 => array( 'likely', 'broad', 'Electronics / Computers' ),
    187 => array( 'likely', 'right', 'Jewellery & Watches / Watches' ),
    184 => array( 'likely', 'right', 'Jewellery & Watches / Watches' ),
    318 => array( 'likely', 'wrong', 'Garden' ),
    526 => array( 'likely', 'right', 'Food & Drink / Wine & Spirits' ),
    527 => array( 'likely', 'right', 'Food & Drink / Wine & Spirits' ),
    260 => array( 'likely', 'right', 'Beauty' ),
    277 => array( 'likely', 'right', 'Electronics / Phones' ),
    523 => array( 'likely', 'broad', 'Food & Drink' ),
    157 => array( 'likely', 'right', 'Bags & Luggage' ),
);

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
    $words    = vergeml_filing_words_sql( 'i' ); // The picture's file, title and alt (S10.9), as the fill reads them.
    $index    = $wpdb->get_results( "SELECT i.attachment_id, i.embedding, i.kind, i.filing, {$words['select']} FROM {$wpdb->vergeml_ai_index} i {$words['join']} WHERE i.error = '' AND i.embedding IS NOT NULL ORDER BY i.attachment_id", ARRAY_A );
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

    // The C.1 dry sheet's 60 under this engine, by folder path: the pair of numbers beside 73 % / 23 %.
    $dsum   = array( 'sure' => array( 'right kept' => 0, 'wrong dropped' => 0, 'right lost' => 0, 'wrong kept' => 0, 'broad kept' => 0, 'moved' => 0 ), 'likely' => array( 'right kept' => 0, 'wrong dropped' => 0, 'right lost' => 0, 'wrong kept' => 0, 'broad kept' => 0, 'moved' => 0 ) );
    $dlines = array();
    foreach ( $bfq_dry as $id => $d ) {
        list( $word, $mark, $path ) = $d;
        $pick = isset( $picks[ $id ] ) ? $picks[ $id ] : null;
        $new  = $pick ? (int) $pick['term_id'] : 0;
        $now  = $new ? bfq_path( $new, $tax ) : '';
        if ( $new && $now === $path ) {
            $fate = 'right' === $mark ? 'right kept' : ( 'broad' === $mark ? 'broad kept' : 'wrong kept' );
        } elseif ( ! $new ) {
            $fate = 'right' === $mark ? 'right lost' : 'wrong dropped';
        } else {
            $fate = 'moved';
        }
        $dsum[ $word ][ $fate ]++;
        $dlines[] = sprintf( '%-6s %6d  %-5s  was %-32s  now %-48s  %s', $word, $id, $mark, $path, $new ? sprintf( '%s (%s %.2f %s)', $now, $pick['why'], $pick['score'], 'siblings' === $pick['why'] ? 'likely' : $pick['confidence'] ) : sprintf( 'nothing (%s %.2f)', $pick ? $pick['why'] : '-', $pick ? $pick['score'] : 0 ), $fate );
    }
    $reread .= "the C.1 dry sheet's 60 (seed 133, marked 2026-09-16: sure 22/30, likely 7/30) under this engine, by folder path:\n";
    foreach ( $dsum as $word => $s ) {
        $reread .= sprintf( "  %-6s  %s\n", $word, implode( ' · ', array_map( function ( $k, $n ) { return $k . ' ' . $n; }, array_keys( $s ), $s ) ) );
    }
    $reread .= implode( "\n", $dlines ) . "\n";
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
        // Same folder as the fill Nathan marked, or as the C.1 dry sheet he marked: that mark stands. Anything else waits for one.
        if ( $dry && isset( $bfq_mark[ $id ], $was[ $id ] ) && (int) $was[ $id ]['term_id'] === (int) $speaks[ $id ]['term_id'] ) {
            $prefill[ count( $sample ) ] = $bfq_mark[ $id ];
        } elseif ( $dry && isset( $bfq_c4[ $id ] ) && $bfq_c4[ $id ][2] === bfq_path( (int) $speaks[ $id ]['term_id'], $tax ) ) {
            $prefill[ count( $sample ) ] = $bfq_c4[ $id ][1];
        } elseif ( $dry && isset( $bfq_dry[ $id ] ) && $bfq_dry[ $id ][2] === bfq_path( (int) $speaks[ $id ]['term_id'], $tax ) ) {
            $prefill[ count( $sample ) ] = $bfq_dry[ $id ][1];
        } elseif ( $dry && isset( $bfq_shop[ $id ] ) && $bfq_shop[ $id ][2] === bfq_path( (int) $speaks[ $id ]['term_id'], $tax ) ) {
            $prefill[ count( $sample ) ] = $bfq_shop[ $id ][1];
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
    // The second library (C.5): the leaf the seed fetched the picture for, the shop's own answer beside the engine's.
    $leaf = (string) get_post_meta( $s['id'], '_vergeml_seed_leaf', true );
    if ( '' !== $leaf ) {
        $wasline = sprintf( '<small class="was %s">%s</small>', str_replace( ' > ', ' / ', $leaf ) === bfq_path( (int) $r['term_id'], $tax ) ? 'same' : 'diff', esc_html( 'fetched for: ' . str_replace( ' > ', ' / ', $leaf ) ) );
    }
    if ( $dry ) {
        $old     = isset( $was[ $s['id'] ] ) ? (int) $was[ $s['id'] ]['term_id'] : 0;
        $same    = $old && $old === (int) $r['term_id'];
        $wasline .= sprintf( '<small class="was %s">%s</small>', $same ? 'same' : 'diff', esc_html( $old ? ( $same ? 'same folder as the last fill' : 'last fill: ' . bfq_path( $old, $tax ) ) : 'last fill: not placed' ) );
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
