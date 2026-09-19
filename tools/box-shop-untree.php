<?php
/**
 *  The shop's tree, frozen and taken away, so the way in can be walked again.
 *
 *      node tools/box-eval.mjs tools/box-shop-untree.php --site realshop --env VGML_ACTION=freeze
 *      node tools/box-eval.mjs tools/box-shop-untree.php --site realshop --env VGML_ACTION=clear
 *      node tools/box-eval.mjs tools/box-shop-untree.php --site realshop --env VGML_ACTION=restore
 *
 *  The categories button is only on the card while the tree is empty, so a
 *  second walk needs the first one's tree gone: the folders, what is in them,
 *  the "placed by" mark the fill left on each picture, and the conversation.
 *  Nothing else -- the attachments and the describe index stay, because the 33
 *  descriptions are last session's spend and re-describing them is money.
 *
 *  freeze writes the tree to a file with literal SQL and reads nothing through
 *  the plugin (fixtures-never-read-through-the-code-under-test: a restore list
 *  computed by the code under test deleted 100 real alts once). restore puts
 *  it back from that file by name and path, ids being the site's to give.
 *
 *  Lives in tools/, which is export-ignored.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

$action = (string) getenv( 'VGML_ACTION' );
$file   = getenv( 'VGML_FILE' ) ? (string) getenv( 'VGML_FILE' ) : '/var/www/ms2/wp-content/vgml-realshop-tree-s21.json';
$tax    = vergeml_librarian_taxonomy();

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared

/** Every folder, by literal SQL: id, name, slug, parent. */
function su_terms( $tax ) {
    global $wpdb;
    return (array) $wpdb->get_results( $wpdb->prepare( "SELECT t.term_id, t.name, t.slug, tt.parent, tt.description FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE tt.taxonomy = %s ORDER BY tt.parent ASC, t.term_id ASC", $tax ), ARRAY_A );
}

/** What sits in them, by literal SQL. */
function su_rels( $tax ) {
    global $wpdb;
    return (array) $wpdb->get_results( $wpdb->prepare( "SELECT tr.object_id, tt.term_id FROM {$wpdb->term_relationships} tr JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id WHERE tt.taxonomy = %s", $tax ), ARRAY_A );
}

/** The mark the fill left on each picture, by literal SQL. */
function su_placed() {
    global $wpdb;
    return (array) $wpdb->get_results( $wpdb->prepare( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s", VERGEML_FILING_PLACED_BY ), ARRAY_A );
}

/** A term's path, root to leaf, from the frozen rows alone. */
function su_path( $id, $by_id ) {
    $path  = array();
    $guard = 0;
    while ( isset( $by_id[ $id ] ) && $guard++ < 64 ) {
        array_unshift( $path, (string) $by_id[ $id ]['name'] );
        $id = (int) $by_id[ $id ]['parent'];
    }
    return implode( ' > ', $path );
}

if ( 'freeze' === $action ) {
    $out = array( 'taxonomy' => $tax, 'when' => gmdate( 'c' ), 'terms' => su_terms( $tax ), 'rels' => su_rels( $tax ), 'placed' => su_placed() );
    file_put_contents( $file, wp_json_encode( $out, JSON_PRETTY_PRINT ) );
    printf( "frozen to %s\n  %d folders, %d pictures in them, %d marked placed\n", $file, count( $out['terms'] ), count( $out['rels'] ), count( $out['placed'] ) );
    return;
}

if ( 'clear' === $action ) {
    if ( ! file_exists( $file ) ) {
        printf( "no freeze at %s -- freeze first\n", $file );
        return;
    }
    $terms = su_terms( $tax );
    // Children before parents: deleting a parent re-parents its children rather than removing them.
    usort( $terms, function ( $a, $b ) { return (int) $b['parent'] <=> (int) $a['parent']; } );
    foreach ( $terms as $t ) {
        wp_delete_term( (int) $t['term_id'], $tax );
    }
    $marks = su_placed();
    foreach ( $marks as $m ) {
        delete_post_meta( (int) $m['post_id'], VERGEML_FILING_PLACED_BY );
    }
    foreach ( array( 'vergeml_guide_session', 'vergeml_talk_state', 'vergeml_talk_undo' ) as $option ) {
        delete_option( $option );
    }
    foreach ( array( 'VERGEML_TALK_STATE', 'VERGEML_TALK_UNDO', 'VERGEML_GUIDE_OPTION' ) as $constant ) {
        if ( defined( $constant ) ) {
            delete_option( constant( $constant ) );
        }
    }
    printf( "cleared\n  %d folders deleted, %d marks removed, the conversation gone\n  %d folders left, %d pictures still in one\n", count( $terms ), count( $marks ), count( su_terms( $tax ) ), count( su_rels( $tax ) ) );
    return;
}

if ( 'restore' === $action ) {
    if ( ! file_exists( $file ) ) {
        printf( "no freeze at %s\n", $file );
        return;
    }
    $was   = json_decode( (string) file_get_contents( $file ), true );
    $by_id = array();
    foreach ( (array) $was['terms'] as $t ) {
        $by_id[ (int) $t['term_id'] ] = $t;
    }
    // Parents first, so a child has one to be made under.
    $order = (array) $was['terms'];
    usort( $order, function ( $a, $b ) { return (int) $a['parent'] <=> (int) $b['parent']; } );
    $now = array(); // old id => new id
    foreach ( $order as $t ) {
        $parent = (int) $t['parent'];
        $made   = wp_insert_term( (string) $t['name'], $tax, array( 'parent' => $parent && isset( $now[ $parent ] ) ? $now[ $parent ] : 0 ) );
        if ( is_wp_error( $made ) ) {
            $have = get_term_by( 'name', (string) $t['name'], $tax );
            $now[ (int) $t['term_id'] ] = $have instanceof WP_Term ? (int) $have->term_id : 0;
            continue;
        }
        $now[ (int) $t['term_id'] ] = (int) $made['term_id'];
    }
    $into = array();
    foreach ( (array) $was['rels'] as $r ) {
        if ( ! empty( $now[ (int) $r['term_id'] ] ) ) {
            $into[ (int) $r['object_id'] ][] = (int) $now[ (int) $r['term_id'] ];
        }
    }
    foreach ( $into as $object => $ids ) {
        wp_set_object_terms( $object, $ids, $tax, false );
    }
    foreach ( (array) $was['placed'] as $m ) {
        update_post_meta( (int) $m['post_id'], VERGEML_FILING_PLACED_BY, (string) $m['meta_value'] );
    }
    printf( "restored\n  %d folders, %d pictures put back, %d marks\n", count( $now ), count( $into ), count( (array) $was['placed'] ) );
    foreach ( (array) $was['terms'] as $t ) {
        printf( "  %s\n", su_path( (int) $t['term_id'], $by_id ) );
    }
    return;
}

printf( "VGML_ACTION is freeze, clear or restore. The tree now: %d folders, %d pictures in them, %d marked placed.\n", count( su_terms( $tax ) ), count( su_rels( $tax ) ), count( su_placed() ) );
// phpcs:enable
