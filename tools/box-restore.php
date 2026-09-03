<?php
/*
 *  Put the index back to how it was before the failed run.
 *
 *  The run stamped vergeml_ai_service_error on 183 rows without touching their
 *  caption, alt or embedding. Clearing the flag on exactly those rows -- the
 *  ones whose description survived -- returns folders and search to the
 *  baseline. Rows that genuinely lost their embedding are listed, not touched.
 */
global $wpdb; $t = $wpdb->prefix . 'vergeml_ai_index';
$before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE error = '' AND embedding IS NOT NULL AND model <> 'mock'" );
$lost   = $wpdb->get_col( "SELECT attachment_id FROM {$t} WHERE error = 'vergeml_ai_service_error' AND ( caption = '' OR embedding IS NULL )" );
$n      = (int) $wpdb->query( "UPDATE {$t} SET error = '' WHERE error = 'vergeml_ai_service_error' AND caption <> '' AND embedding IS NOT NULL" );
$after  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE error = '' AND embedding IS NOT NULL AND model <> 'mock'" );
printf( "searchable before: %d\nflags cleared:     %d\nsearchable after:  %d\nnot restorable:    %s\n", $before, $n, $after, $lost ? implode( ', ', $lost ) : 'none' );
