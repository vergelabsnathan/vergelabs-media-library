<?php
/*
 *  Take the FileBird fixture back out. Both tables are emptied, which is right
 *  only because the box held nothing in them before the fixture went in --
 *  the probe on 2026-09-06 read rows=0 in both.
 */

global $wpdb;

$folders_table = $wpdb->prefix . 'fbv';
$links_table   = $wpdb->prefix . 'fbv_attachment_folder';

$folders = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$folders_table}" );
$links   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$links_table}" );

$wpdb->query( "DELETE FROM {$links_table}" );
$wpdb->query( "DELETE FROM {$folders_table}" );

echo 'removed folders: ' . $folders . "\n";
echo 'removed links:   ' . $links . "\n";
echo 'folders now:     ' . (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$folders_table}" ) . "\n";
echo 'links now:       ' . (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$links_table}" ) . "\n";
