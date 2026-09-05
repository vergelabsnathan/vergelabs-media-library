<?php
/*
 *  Every number the screens show, from the box, side by side -- so the ones
 *  that disagree can be seen disagreeing. Read-only.
 */
global $wpdb;
$t = $wpdb->prefix . 'vergeml_ai_index';

$say = function ( $label, $value ) {
    printf( "%-46s %s\n", $label, is_scalar( $value ) || null === $value ? var_export( $value, true ) : wp_json_encode( $value ) );
};

echo "== files ==\n";
$say( 'attachments (all)', (int) wp_count_posts( 'attachment' )->inherit );
$say( 'attachments image/*', (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='attachment' AND post_status='inherit' AND post_mime_type LIKE 'image/%'" ) );
$say( 'index rows', (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t}" ) );
$say( 'index rows described (error=\'\', embedding)', (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE error = '' AND embedding IS NOT NULL" ) );
$say( 'index rows with error', (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE error <> ''" ) );
$say( 'index errors by kind', $wpdb->get_results( "SELECT error, COUNT(*) n FROM {$t} WHERE error <> '' GROUP BY error", ARRAY_A ) );
$say( 'images with empty alt', (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id=p.ID AND m.meta_key='_wp_attachment_image_alt' WHERE p.post_type='attachment' AND p.post_status='inherit' AND p.post_mime_type LIKE 'image/%' AND (m.meta_value IS NULL OR m.meta_value='')" ) );

echo "\n== dashboard facts (vergeml_journey_facts) ==\n";
if ( function_exists( 'vergeml_journey_touch' ) ) { vergeml_journey_touch(); }
$f = function_exists( 'vergeml_journey_facts' ) ? vergeml_journey_facts() : array();
foreach ( array( 'files', 'images', 'described', 'undescribed', 'no_alt', 'folders', 'unfiled', 'stale', 'unused', 'credits' ) as $k ) {
    $say( 'facts.' . $k, isset( $f[ $k ] ) ? $f[ $k ] : null );
}
$say( 'alt pending (vergeml_ai_alt_pending)', function_exists( 'vergeml_ai_alt_pending' ) ? count( vergeml_ai_alt_pending() ) : null );
$say( 'ai pending unindexed (vergeml_ai_pending_count)', function_exists( 'vergeml_ai_pending_count' ) ? vergeml_ai_pending_count( 'unindexed' ) : null );
$say( 'ai pending missing-alt', function_exists( 'vergeml_ai_pending_count' ) ? vergeml_ai_pending_count( 'missing-alt' ) : null );
$say( 'progress', function_exists( 'vergeml_journey_progress' ) ? vergeml_journey_progress() : null );

echo "\n== folders ==\n";
$tax = function_exists( 'vergeml_librarian_taxonomy' ) ? vergeml_librarian_taxonomy() : 'media_category';
$say( 'librarian taxonomy', $tax );
$say( 'terms in it', (int) wp_count_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) ) );
$say( 'top-level terms', (int) wp_count_terms( array( 'taxonomy' => $tax, 'hide_empty' => false, 'parent' => 0 ) ) );
$say( 'attachments in no term of it', (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} p WHERE p.post_type='attachment' AND p.post_status='inherit' AND NOT EXISTS (SELECT 1 FROM {$wpdb->term_relationships} tr JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id=tr.term_taxonomy_id WHERE tr.object_id=p.ID AND tt.taxonomy=%s)", $tax ) ) );
$say( 'described pictures in no term of it', (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} i WHERE i.error='' AND i.embedding IS NOT NULL AND NOT EXISTS (SELECT 1 FROM {$wpdb->term_relationships} tr JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id=tr.term_taxonomy_id WHERE tr.object_id=i.attachment_id AND tt.taxonomy=%s)", $tax ) ) );
$say( 'tree taxonomies', function_exists( 'vergeml_tree_taxonomies' ) ? vergeml_tree_taxonomies() : null );

echo "\n== guide summary (what the Sort screen is handed) ==\n";
if ( function_exists( 'vergeml_guide_summary' ) ) {
    $t0  = microtime( true );
    $sum = vergeml_guide_summary();
    $say( 'summary keys', array_keys( (array) $sum ) );
    $say( 'summary total / unfiled / folders / groups', array( $sum['total'] ?? null, $sum['unfiled'] ?? null, count( (array) ( $sum['folders'] ?? array() ) ), count( (array) ( $sum['groups'] ?? array() ) ) ) );
    $say( 'summary seconds', round( microtime( true ) - $t0, 1 ) );
}

echo "\n== sort surface (guide session) ==\n";
$s = get_option( 'vergeml_guide_session' );
if ( is_array( $s ) ) {
    $say( 'state', $s['state'] );
    $say( 'draft folders', count( (array) $s['draft']['folders'] ) );
    $say( 'draft aside', count( array_filter( (array) $s['draft']['folders'], function ( $x ) { return ! empty( $x['aside'] ); } ) ) );
    $say( 'draft names', array_map( function ( $x ) { return $x['name'] . '(' . $x['count'] . ')'; }, (array) $s['draft']['folders'] ) );
    $say( 'proposals', count( (array) $s['proposals'] ) );
    $say( 'assistant turns', $s['assistant_turns'] );
    $say( 'updated_at', gmdate( 'c', (int) $s['updated_at'] ) );
} else {
    $say( 'session', null );
}
$talk = get_option( 'vergeml_talk_refile' );
$say( 'refile job', is_array( $talk ) ? array( 'active' => $talk['active'], 'seen' => $talk['seen'], 'total' => $talk['total'], 'moved' => $talk['moved'], 'skipped' => $talk['skipped'], 'started' => gmdate( 'c', (int) $talk['started'] ) ) : null );
$undo = get_option( 'vergeml_talk_undo' );
$say( 'undo record', is_array( $undo ) ? array( 'terms' => count( (array) $undo['terms'] ), 'files' => count( (array) $undo['files'] ) ) : null );

echo "\n== ai run ==\n";
$say( 'run payload', function_exists( 'vergeml_ai_run_payload' ) ? vergeml_ai_run_payload() : null );
$say( 'run state raw', function_exists( 'vergeml_ai_run_state' ) ? vergeml_ai_run_state() : null );
$say( 'cron next', wp_next_scheduled( defined( 'VERGEML_AI_RUN_HOOK' ) ? VERGEML_AI_RUN_HOOK : 'vergeml_ai_run' ) );
$say( 'DISABLE_WP_CRON', defined( 'DISABLE_WP_CRON' ) ? DISABLE_WP_CRON : false );
$say( 'run lock', get_transient( 'vergeml_ai_run_lock' ) );

echo "\n== duplicates ==\n";
if ( function_exists( 'vergeml_health_state' ) ) {
    $h = vergeml_health_state();
    $say( 'health_state keys', array_keys( (array) $h ) );
    $say( 'health_state finished', isset( $h['finished'] ) ? $h['finished'] : null );
}
if ( function_exists( 'vergeml_health_report' ) ) {
    $r = vergeml_health_report();
    $say( 'health_report keys', array_keys( (array) $r ) );
    foreach ( array( 'duplicates', 'related' ) as $k ) {
        $v = isset( $r[ $k ] ) ? $r[ $k ] : null;
        $say( 'health_report.' . $k, is_array( $v ) ? ( isset( $v['groups'] ) ? array( 'groups' => count( (array) $v['groups'] ), 'more' => $v['more'] ?? null, 'wasted' => $v['wasted'] ?? null ) : array( 'list' => count( $v ) ) ) : $v );
    }
    $say( 'health_report.wasted', isset( $r['wasted'] ) ? $r['wasted'] : null );
}

echo "\n== credits ==\n";
$say( 'vergeml_ai_credits', get_option( 'vergeml_ai_credits' ) );
