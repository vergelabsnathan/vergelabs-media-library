<?php
/**
 *  What a confirm would ask the planner about on this site, under S10.1's
 *  rule, as if every folder had to be profiled afresh: the folders whose
 *  name is one of the library's own words stay home; the rest go, in
 *  batches of sixty, at the credits the button would say.
 *
 *      node tools/box-eval.mjs tools/box-ask-split.php --site shop
 *
 *  Read-only: nothing is confirmed, nothing is asked, nothing is spent.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$taxonomy = vergeml_librarian_taxonomy();
$nodes    = vergeml_folders_nodes( $taxonomy );
$vocab    = vergeml_filing_vocabulary( 0 );
$folders  = array();
$names    = array();
foreach ( $nodes as $n ) {
    $folders[] = array( 'key' => 't' . (int) $n['id'], 'name' => (string) $n['name'], 'parent' => $n['parent'] ? 't' . (int) $n['parent'] : '' );
    $names[ 't' . (int) $n['id'] ] = (string) $n['name'];
}
$goes  = vergeml_filing_ask_split( $folders, $vocab, '1' === (string) getenv( 'VGML_FOREIGN' ) || vergeml_filing_tree_is_foreign() ); // VGML_FOREIGN=1: as a site in another language (S15)
$batch = VERGEML_FILING_PROFILE_BATCH;
printf( "folders %d · vocabulary %d words · stay home %d · go to the planner %d · batches %d · credits %d\n", count( $nodes ), count( $vocab ), count( $nodes ) - count( $goes ), count( $goes ), (int) ceil( count( $goes ) / $batch ), vergeml_filing_profile_credits( count( $goes ) ) );
printf( "go: %s\n", implode( ', ', array_map( function ( $k ) use ( $names ) { return $names[ $k ]; }, $goes ) ) );

// What the live session's confirm would ask right now (stored plans and asked flags honoured).
$s = vergeml_guide_session();
if ( is_array( $s['draft'] ) && ! empty( $s['draft']['folders'] ) ) {
    $facts = vergeml_guide_profile_facts( $s['draft'] );
    printf( "the session's draft: %d folders would be asked, %d credits\n", $facts['folders'], $facts['credits'] );
} else {
    echo "the session holds no draft\n";
}
