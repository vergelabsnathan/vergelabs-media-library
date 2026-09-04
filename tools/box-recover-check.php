<?php
/* What is left on the box to rebuild the folder tree from. Read-only. */
global $wpdb;
echo "== stored planner proposals ==\n";
foreach ( $wpdb->get_results( "SELECT option_name, LENGTH(option_value) len FROM {$wpdb->options} WHERE option_name LIKE 'vergeml_probe_plan_%' OR option_name LIKE 'vergeml_%plan%' OR option_name LIKE 'vergeml_%propos%'", ARRAY_A ) as $o ) {
    $v = get_option( $o['option_name'] );
    $n = is_array( $v ) && isset( $v['folders'] ) ? count( $v['folders'] ) : ( is_array( $v ) ? count( $v ) : -1 );
    printf( "%s  %d bytes  folders=%d\n", $o['option_name'], $o['len'], $n );
    if ( is_array( $v ) && isset( $v['folders'] ) ) {
        foreach ( $v['folders'] as $f ) { printf( "   %s%s\n", $f['parent'] ? $f['parent'] . ' / ' : '', $f['name'] ); }
    }
}
echo "\n== librarian batches / moves ==\n";
$b = $wpdb->vergeml_librarian_batches; $m = $wpdb->vergeml_librarian_moves;
print_r( $wpdb->get_results( "SELECT * FROM {$b} ORDER BY batch_id DESC LIMIT 5", ARRAY_A ) );
print_r( $wpdb->get_row( "SELECT COUNT(*) n, COUNT(DISTINCT term_id) terms, MIN(batch_id) b0, MAX(batch_id) b1 FROM {$m}", ARRAY_A ) );
echo "\n== term meta on deleted term ids (profiles survive?) ==\n";
print_r( $wpdb->get_row( "SELECT COUNT(*) n FROM {$wpdb->termmeta} tm LEFT JOIN {$wpdb->terms} t ON t.term_id = tm.term_id WHERE t.term_id IS NULL", ARRAY_A ) );
echo "\n== relationships pointing at missing term_taxonomy rows ==\n";
print_r( $wpdb->get_row( "SELECT COUNT(*) n FROM {$wpdb->term_relationships} tr LEFT JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id WHERE tt.term_taxonomy_id IS NULL", ARRAY_A ) );
echo "\n== mysql binlog ==\n";
print_r( $wpdb->get_results( "SHOW VARIABLES LIKE 'log_bin%'", ARRAY_A ) );
echo "\n== files on disk ==\n";
foreach ( array( '/var/backups', '/root', '/var/www', '/var/lib/mysql' ) as $d ) {
    $l = @scandir( $d );
    if ( $l ) { echo $d, ': ', implode( ' ', array_filter( $l, function ( $x ) { return preg_match( '/sql|bak|dump|snap|bin\\.|tar|gz/i', $x ); } ) ), "\n"; }
}
