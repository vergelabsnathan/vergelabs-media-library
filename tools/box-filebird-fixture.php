<?php
/*
 *  A FileBird fixture on the test box, so the migration card has a real
 *  detected state to be drawn from. Writes only to FileBird's own two tables,
 *  and only rows it creates. Remove with fixture-filebird-remove.php.
 *
 *  Nothing of ours is touched, and FileBird is read-only to our importer, so
 *  this changes what the importer would find and nothing else.
 */

global $wpdb;

$folders_table = $wpdb->prefix . 'fbv';
$links_table   = $wpdb->prefix . 'fbv_attachment_folder';

$existing = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$folders_table}" );

if ( $existing > 0 ) {
    echo "REFUSED: {$folders_table} already holds {$existing} rows. Not touching a tree that is not this fixture's.\n";
    return;
}

/*
 *  A tree the way a customer's FileBird actually looks: by site area and
 *  campaign, not by subject. Two names collide with ours at the top level
 *  (Workspace, Portrait) so the preview's merge count is a real one.
 *
 *  name, parent key, how many files
 */
$tree = array(
    'website'   => array( 'Website',     '',          0 ),
    'homepage'  => array( 'Homepage',    'website',  24 ),
    'blog'      => array( 'Blog posts',  'website',  96 ),
    'products'  => array( 'Products',    '',         62 ),
    'apparel'   => array( 'Apparel',     'products', 45 ),
    'lookbook'  => array( 'Lookbook',    'apparel',  17 ),
    'team'      => array( 'Team',        '',         18 ),
    'workspace' => array( 'Workspace',   '',         25 ),
    'portrait'  => array( 'Portrait',    '',         18 ),
    'campaigns' => array( 'Campaigns',   '',          0 ),
    'spring'    => array( 'Spring 2026', 'campaigns', 41 ),
    'autumn'    => array( 'Autumn 2025', 'campaigns', 33 ),
    'press'     => array( 'Press kit',   '',          9 ),
    'logos'     => array( 'Logos',       'press',     6 ),
);

$ids = $wpdb->get_col(
    "SELECT ID FROM {$wpdb->posts}
      WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'
      ORDER BY ID ASC"
);

echo 'images on the site: ' . count( $ids ) . "\n";

$made = array();
$ord  = 0;

foreach ( $tree as $key => $row ) {

    $parent = '' === $row[1] ? 0 : $made[ $row[1] ];

    $wpdb->insert(
        $folders_table,
        array(
            'name'       => $row[0],
            'parent'     => $parent,
            'type'       => 0,
            'ord'        => $ord ++,
            'created_by' => 0,
        ),
        array( '%s', '%d', '%d', '%d', '%d' )
    );

    $made[ $key ] = (int) $wpdb->insert_id;
}

$cursor = 0;
$filed  = 0;

foreach ( $tree as $key => $row ) {

    $want = (int) $row[2];

    for ( $i = 0; $i < $want; $i ++ ) {

        if ( ! isset( $ids[ $cursor ] ) ) {
            break;
        }

        $wpdb->insert(
            $links_table,
            array( 'folder_id' => $made[ $key ], 'attachment_id' => (int) $ids[ $cursor ] ),
            array( '%d', '%d' )
        );

        $cursor ++;
        $filed ++;
    }
}

echo 'folders made: ' . count( $made ) . "\n";
echo 'files filed:  ' . $filed . "\n";
echo 'folder ids:   ' . implode( ',', $made ) . "\n";
