<?php
/**
 *  What the folders get called.
 *
 *  The claim worth defending is narrow: **a folder's name is a word that most
 *  of the files in it actually carry**, and it is one name rather than two
 *  words stapled together.
 *
 *      wp eval-file tests/organize/naming.php --allow-root
 *
 *  This exists because of what shipped before it. A real library came back
 *  with folders called "Account Basket", "Anna Catalogue", "Boots Cover",
 *  "Audience Conf" and "Autumn Hanger" -- names that read as somebody's
 *  surname and described nothing, because the score was share x (1/spread)
 *  with no floor. A tag one file in thirty-four carried won the folder purely
 *  by being rare, and then a second, equally arbitrary tag was appended.
 *
 *  So the assertions here are mostly about what a name may NOT be. Case A is
 *  the regression itself and is the one to run after touching the scoring.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'vergeml_organize_label' ) ) {
    echo "core/organize.php is not loaded -- plugin inactive, or safe mode?\n";
    exit( 1 );
}

$GLOBALS['nm_pass'] = 0;
$GLOBALS['nm_fail'] = 0;
$GLOBALS['nm_log']  = '';

function nm_say( $line ) {
    $GLOBALS['nm_log'] .= $line;
    echo $line; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

function nm_check( $label, $ok, $note = '' ) {
    if ( $ok ) {
        $GLOBALS['nm_pass']++;
    } else {
        $GLOBALS['nm_fail']++;
    }
    nm_say( sprintf( "  %s  %s%s\n", $ok ? 'ok  ' : 'FAIL', $label, '' === $note ? '' : '  -- ' . $note ) );
}

/**
 *  A library from a list of tag lists, in the shape the clusterer hands the
 *  namer. Written out rather than fetched, so every case below is reachable
 *  without owning a library that happens to contain it.
 */
function nm_library( $files ) {

    $points = array();

    foreach ( $files as $i => $tags ) {
        $points[ $i ] = array( 'tags' => $tags, 'kind' => 'photo' );
    }

    return array(
        'points'  => $points,
        'global'  => vergeml_organize_global_tags( $points ),
        'members' => array_keys( $points ),
    );
}

function nm_name( $files, $members = null ) {
    $lib = nm_library( $files );
    return vergeml_organize_label( null === $members ? $lib['members'] : $members, $lib['global'], $lib['points'] );
}


nm_say( "\nwhat the folders get called\n\n" );


/* --------------------------------------------------- the regression itself */

nm_say( "A  a word almost nobody carries cannot be the name\n" );

/*
 *  Ten files. Nine of them are pictures of a desk; one of them, and nothing
 *  else in the library, is tagged "basket". Under the old scoring "basket"
 *  scored 0.1 x 1/1 = 0.1 and "desk" scored 0.9 x 1/9 = 0.1 -- and the
 *  tiebreak is alphabetical, so the folder was called "Basket".
 */
$nm_rare = array_fill( 0, 9, array( 'desk', 'workspace' ) );
$nm_rare[] = array( 'desk', 'basket' );

$nm_got = nm_name( $nm_rare );

nm_check(
    'the odd one out does not name the folder',
    false === stripos( $nm_got, 'basket' ),
    $nm_got
);

nm_check(
    'what nine in ten of them are does',
    'Desk' === $nm_got,
    $nm_got
);

/*
 *  And the floor is a floor rather than a preference: exactly half is in,
 *  anything under it is out. Nothing here reaches half except the two, so if
 *  the floor let neither through the answer would be the fallback "Photo".
 */
$nm_half = array_fill( 0, 5, array( 'poster' ) );
foreach ( array_fill( 0, 5, array( 'sticker' ) ) as $nm_f ) {
    $nm_half[] = $nm_f;
}

nm_check(
    'a tag exactly half of them carry can still name the folder',
    in_array( nm_name( $nm_half ), array( 'Poster', 'Sticker' ), true ),
    nm_name( $nm_half ) . '  (poster 5/10, sticker 5/10)'
);

$nm_under = array_fill( 0, 6, array( 'print' ) );
$nm_under[] = array( 'print', 'poster' );
$nm_under[] = array( 'print', 'poster' );

nm_check(
    'and one carried by under half is not',
    false === stripos( nm_name( $nm_under ), 'poster' ),
    nm_name( $nm_under ) . '  (poster 2/8)'
);


/* -------------------------------------------------------------- the shape */

nm_say( "\nB  a name, not two words in a trenchcoat\n" );

$nm_two = array_fill( 0, 6, array( 'skincare', 'cosmetics', 'amber bottles' ) );
$nm_got = nm_name( $nm_two );

nm_check(
    'three shared tags still produce one name',
    in_array( $nm_got, array( 'Skincare', 'Cosmetics', 'Amber bottles' ), true ),
    $nm_got
);

nm_check(
    'it is sentence case, not Title Case',
    'Amber Bottles' !== $nm_got && 'Skincare Cosmetics' !== $nm_got,
    $nm_got
);

/*
 *  The tags the model returns are lowercase phrases. Capitalising every word
 *  turned "beauty products" into "Beauty Products", which reads as a company
 *  rather than a shelf.
 */
nm_check(
    'a two-word tag keeps its second word lowercase',
    'Beauty products' === vergeml_organize_name_case( 'beauty products' ),
    vergeml_organize_name_case( 'beauty products' )
);

nm_check(
    'an acronym is left as the model wrote it',
    'PDF scans' === vergeml_organize_name_case( 'PDF scans' ),
    vergeml_organize_name_case( 'PDF scans' )
);


/* ------------------------------------------------------- rarity still works */

nm_say( "\nC  rare-elsewhere still breaks the tie, it just cannot overrule\n" );

/*
 *  Both tags are on every file in the branch, so share cannot separate them.
 *  "photo" is on the whole library and names nothing; "synthesizer" is on
 *  these four only. Without the rarity nudge every folder is called "Photo".
 */
$nm_tie = array();
foreach ( range( 1, 4 ) as $nm_i ) {
    $nm_tie[] = array( 'photo', 'synthesizer' );
}
foreach ( range( 1, 20 ) as $nm_i ) {
    $nm_tie[] = array( 'photo', 'landscape' );
}

$nm_lib = nm_library( $nm_tie );
$nm_got = vergeml_organize_label( array( 0, 1, 2, 3 ), $nm_lib['global'], $nm_lib['points'] );

nm_check(
    'the tag that is theirs alone wins over the one everything has',
    'Synthesizer' === $nm_got,
    $nm_got
);


/* ------------------------------------------------------------- nothing held */

nm_say( "\nD  when they share nothing, say what they are\n" );

$nm_none = array( array( 'aardvark' ), array( 'bicycle' ), array( 'cathedral' ), array( 'dentist' ) );
$nm_got  = nm_name( $nm_none );

nm_check(
    'no invented name from four unrelated files',
    'Photo' === $nm_got,
    $nm_got
);

/*
 *  And the case on the other side of that line, which is why the floor is not
 *  simply "the top tag wins".
 *
 *  Thirteen of eighty-three photographs tagged "workspace" is a weak name and
 *  a true one. Refusing it sent the folder to the file kind, and on a library
 *  of photographs that produced six folders called some variation of "Photo".
 */
$nm_weak = array();

foreach ( range( 1, 13 ) as $nm_i ) {
    $nm_weak[] = array( 'workspace', 'desk' );
}

foreach ( range( 1, 70 ) as $nm_i ) {
    $nm_weak[] = array( 'thing' . $nm_i );
}

$nm_got = nm_name( $nm_weak );

nm_check(
    'a weak but real shared word beats falling back to the file kind',
    'Photo' !== $nm_got && '' !== $nm_got,
    $nm_got . '  (13 of 83 share it)'
);


/* ----------------------------------------------------------- the siblings */

nm_say( "\nE  no two siblings with the same name\n" );

/*
 *  Twenty files sharing the same two tags, cut in two. Nothing tells the
 *  halves apart, so both score the same name -- six folders called "Boats
 *  Harbour" is what this guards, and the split is real even when the tags
 *  cannot explain it.
 */
$nm_sib = array();
foreach ( range( 0, 19 ) as $nm_i ) {
    $nm_sib[] = array( 'boats', 'harbour' );
}

$nm_lib    = nm_library( $nm_sib );
$nm_labels = vergeml_organize_distinct_labels(
    array( range( 0, 9 ), range( 10, 19 ) ),
    $nm_lib['global'],
    $nm_lib['points']
);

nm_check(
    'the two halves get two names',
    $nm_labels[0] !== $nm_labels[1],
    implode( ' / ', $nm_labels )
);

nm_check(
    'and the second is joined with "and", not stapled',
    'Boats and harbour' === $nm_labels[1],
    $nm_labels[1]
);

/*
 *  A child must not score its parent's name. The tree grew "Body Canvas /
 *  Body Canvas" -- a folder inside a folder of the same name.
 */
$nm_child = vergeml_organize_distinct_labels(
    array( range( 0, 9 ) ),
    $nm_lib['global'],
    $nm_lib['points'],
    array( vergeml_organize_label( range( 0, 19 ), $nm_lib['global'], $nm_lib['points'] ) )
);

nm_check(
    'a child is never named after its parent',
    'Boats' !== $nm_child[0],
    $nm_child[0]
);


/* -------------------------------------------------- against the real library */

nm_say( "\nF  against the library on this box\n" );

$nm_rows = $GLOBALS['wpdb']->get_results(
    "SELECT attachment_id, tags FROM {$GLOBALS['wpdb']->prefix}vergeml_ai_index WHERE error = '' AND tags <> ''",
    ARRAY_A
);

if ( ! $nm_rows ) {

    nm_say( "  skipped -- nothing described here yet\n" );

} else {

    $nm_points = array();

    foreach ( $nm_rows as $nm_row ) {
        $nm_points[ (int) $nm_row['attachment_id'] ] = array(
            'tags' => vergeml_index_tags_out( $nm_row['tags'] ),
            'kind' => 'photo',
        );
    }

    $nm_global = vergeml_organize_global_tags( $nm_points );
    $nm_all    = array_keys( $nm_points );
    $nm_got    = vergeml_organize_label( $nm_all, $nm_global, $nm_points );

    nm_say( sprintf( "  %d described files, whole library named: %s\n", count( $nm_all ), $nm_got ) );

    /*
     *  Whatever it is called, most of the files have to carry it -- or it is
     *  the honest fallback, which names no tag at all.
     */
    $nm_needle = strtolower( $nm_got );
    $nm_have   = 0;

    foreach ( $nm_points as $nm_p ) {
        foreach ( $nm_p['tags'] as $nm_t ) {
            if ( strtolower( $nm_t ) === $nm_needle ) {
                $nm_have++;
                break;
            }
        }
    }

    $nm_fallback = in_array( $nm_got, array( 'Photo', 'Unsorted' ), true ) || false !== strpos( $nm_got, ' and ' );

    nm_check(
        'the name describes most of what is in it',
        $nm_fallback || $nm_have / count( $nm_all ) >= VERGEML_ORGANIZE_NAME_SHARE,
        sprintf( '%d of %d files carry "%s"', $nm_have, count( $nm_all ), $nm_got )
    );

    nm_check(
        'and it is not two tags with a space between them',
        $nm_fallback || isset( $nm_global[ $nm_needle ] ),
        $nm_got . ' is ' . ( isset( $nm_global[ $nm_needle ] ) ? 'a real tag' : 'not a tag any file has' )
    );
}

nm_say( sprintf( "\n%d/%d passed\n", $GLOBALS['nm_pass'], $GLOBALS['nm_pass'] + $GLOBALS['nm_fail'] ) );

@file_put_contents( __DIR__ . '/naming-last-run.txt', $GLOBALS['nm_log'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

if ( $GLOBALS['nm_fail'] > 0 ) {
    exit( 1 );
}
