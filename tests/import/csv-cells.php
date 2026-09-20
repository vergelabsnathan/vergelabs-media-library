<?php
/*
 *  The CSV export cannot execute in a spreadsheet (story 4.1, FR12).
 *
 *  Local: no WordPress, no database, no box. The suite stands in for the
 *  helpers core/import-csv.php reaches -- the two hooks at load, get_terms and
 *  friends inside the export, WP_Error and the i18n pair inside the parser --
 *  and drives the real vergeml_csv_export_rows(), vergeml_csv_path(),
 *  vergeml_csv_line() and vergeml_csv_parse() over a tree whose names are
 *  OWASP's CSV-injection triggers.
 *
 *      node tools/verify.mjs csv-local
 *
 *  Three questions, in order: what does a spreadsheet see (a cell that begins
 *  with an apostrophe, never with a formula), does the pair invert (out then
 *  in is the name), and does the whole file survive the round trip through
 *  the parser. Then the things the story must not change: the BOM, the
 *  delimiter, the header, the error codes.
 *
 *  Mutation: vergeml_csv_cell_out() returning its input -> section A goes red
 *  on every trigger row, and the '=x round trip in C goes red.
 */

define( 'ABSPATH', '/' );

function add_action() {}
function add_filter() {}
function __( $t, $d = '' ) {
    return $t;
}
function _n( $one, $many, $n, $d = '' ) {
    return 1 === (int) $n ? $one : $many;
}
function number_format_i18n( $n ) {
    return (string) $n;
}

class WP_Error {
    public $code;
    public $message;
    public function __construct( $code = '', $message = '', $data = null ) {
        $this->code    = $code;
        $this->message = $message;
    }
    public function get_error_code() {
        return $this->code;
    }
    public function get_error_message() {
        return $this->message;
    }
}
function is_wp_error( $x ) {
    return $x instanceof WP_Error;
}

// The tree, as get_terms() would hand it over. Names are the point, and the
// trigger names sit at the top level: a nested path begins with its parent's
// name, so only a top-level folder can put a trigger at the start of a cell.
$GLOBALS['cc_terms'] = array(
    1  => array( '=HYPERLINK("http://x","x")', 0 ),
    2  => array( '+1', 0 ),
    3  => array( '-1', 0 ),
    4  => array( '@x', 0 ),
    5  => array( '-5', 0 ),
    6  => array( "'=x", 0 ),
    7  => array( "'Quoted", 0 ),
    8  => array( 'Acme', 0 ),
    9  => array( "\tname", 0 ),
    10 => array( "\rname", 0 ),
    11 => array( 'Root', 0 ),
    12 => array( '=child', 11 ),
);

function get_terms( $args ) {
    $out = array();
    foreach ( $GLOBALS['cc_terms'] as $id => $t ) {
        $term          = new stdClass();
        $term->term_id = $id;
        $term->name    = $t[0];
        $term->parent  = $t[1];
        $out[]         = $term;
    }
    return $out;
}
function get_objects_in_term( $ids, $taxonomy ) {
    // One file, in Acme, so the id column is in the round trip.
    return array( 8 ) === array_map( 'intval', $ids ) ? array( 12 ) : array();
}
function get_post_type( $id ) {
    return 'attachment';
}
function get_attached_file( $id ) {
    return '/uploads/x.jpg';
}
function wp_basename( $p ) {
    return basename( $p );
}

// vergeml_csv_reject_unknown() asks the database which ids are attachments.
class cc_wpdb {
    public $posts = 'wp_posts';
    public function prepare( $q, $args ) {
        return $q;
    }
    public function get_col( $q ) {
        return array( 12 );
    }
}
$wpdb = new cc_wpdb();

require dirname( __DIR__, 2 ) . '/core/import-csv.php';

$GLOBALS['cc_pass'] = 0;
$GLOBALS['cc_fail'] = 0;

function cc_check( $label, $ok, $note = '' ) {
    if ( $ok ) {
        $GLOBALS['cc_pass']++;
    } else {
        $GLOBALS['cc_fail']++;
    }
    printf( "  %s  %s%s\n", $ok ? 'ok  ' : 'FAIL', $label, '' === $note ? '' : '  -- ' . $note );
}

function cc_show( $s ) {
    return str_replace( array( "\t", "\r", "\n" ), array( '\t', '\r', '\n' ), (string) $s );
}

/** The first cell of a written line, as a CSV reader hands it to a spreadsheet. */
function cc_first_cell( $line ) {
    $cells = str_getcsv( rtrim( $line, "\r\n" ), ',', '"', '' );
    return isset( $cells[0] ) ? $cells[0] : null;
}

$cc_triggers = array(
    '=HYPERLINK("http://x","x")',
    '+1',
    '-1',
    '@x',
    '-5',
    "\tname",
    "\rname",
);

$cc_plain = array( 'Acme', "'Quoted", '12', 'A, B', 'x"y', 'http://x' );


echo "\nthe csv export cannot execute in a spreadsheet\n\n";

/* ---------------------------------------------- A  what a spreadsheet sees */

echo "A  what a spreadsheet sees\n";

foreach ( $cc_triggers as $cc_name ) {
    $cc_line = vergeml_csv_line( array( $cc_name, '', '' ) );
    $cc_cell = cc_first_cell( $cc_line );
    cc_check(
        'a cell starting ' . cc_show( substr( $cc_name, 0, 1 ) ) . ' reaches the spreadsheet as text: ' . cc_show( $cc_name ),
        "'" . $cc_name === $cc_cell,
        cc_show( $cc_cell )
    );
    cc_check(
        '  and is written inside quotes',
        0 === strpos( $cc_line, '"\'' ),
        cc_show( $cc_line )
    );
}

$cc_line = vergeml_csv_line( array( "'=x", '', '' ) );
cc_check( "a name that already begins '= gets a second apostrophe", "''=x" === cc_first_cell( $cc_line ), cc_show( $cc_line ) );

foreach ( $cc_plain as $cc_name ) {
    cc_check( 'a plain cell is unchanged: ' . cc_show( $cc_name ), $cc_name === cc_first_cell( vergeml_csv_line( array( $cc_name, '', '' ) ) ) );
}

cc_check( 'the id column is unchanged', "Acme,12,x.jpg\r\n" === vergeml_csv_line( array( 'Acme', '12', 'x.jpg' ) ) );


/* ------------------------------------------------------ B  the pair inverts */

echo "\nB  out, then in, is the name\n";

foreach ( array_merge( $cc_triggers, array( "'=x", "''=x" ), $cc_plain ) as $cc_name ) {
    cc_check( 'cell_in( cell_out( x ) ) is x: ' . cc_show( $cc_name ), $cc_name === vergeml_csv_cell_in( vergeml_csv_cell_out( $cc_name ) ) );
}

cc_check( "cell_in leaves a bare ' before an ordinary character alone", "'Quoted" === vergeml_csv_cell_in( "'Quoted" ) );
cc_check( 'cell_in strips exactly one apostrophe', "'=x" === vergeml_csv_cell_in( "''=x" ) );


/* ------------------------------------------------ C  the whole round trip */

echo "\nC  the export, parsed back\n";

$cc_rows = vergeml_csv_export_rows( 'media_category' );

cc_check( 'the export runs on the fixture tree', is_array( $cc_rows ) && 13 === count( $cc_rows ), is_array( $cc_rows ) ? count( $cc_rows ) . ' rows' : 'error' );
cc_check( 'the header row is first and unchanged', array( 'folder', 'attachment_id', 'filename' ) === $cc_rows[0] );

$cc_text = "\xEF\xBB\xBF";
foreach ( $cc_rows as $cc_row ) {
    $cc_text .= vergeml_csv_line( $cc_row );
}

cc_check( 'the file carries the HYPERLINK cell prefixed', false !== strpos( $cc_text, "\"'=HYPERLINK(" ), cc_show( substr( $cc_text, 3, 80 ) ) );
cc_check( 'and no line begins with a trigger', ! preg_match( '/^[=+\-@\t]/m', substr( $cc_text, 3 ) ) );
cc_check( 'a nested formula name begins with its parent, and is left alone', false !== strpos( $cc_text, "\r\nRoot/=child,,\r\n" ) );

$cc_parsed = vergeml_csv_parse( $cc_text );

cc_check( 'the parser accepts it', ! is_wp_error( $cc_parsed ), is_wp_error( $cc_parsed ) ? $cc_parsed->get_error_code() : '' );

if ( ! is_wp_error( $cc_parsed ) ) {

    cc_check( 'with nothing reported', 0 === (int) $cc_parsed['problem_count'], implode( ' ', $cc_parsed['problems'] ) );

    $cc_top   = array();
    $cc_child = array();

    foreach ( $cc_parsed['folders'] as $cc_id => $cc_f ) {
        if ( 0 === $cc_f['parent'] ) {
            $cc_top[ $cc_f['name'] ] = $cc_id;
        }
    }
    foreach ( $cc_parsed['folders'] as $cc_id => $cc_f ) {
        if ( isset( $cc_top['Root'] ) && $cc_top['Root'] === $cc_f['parent'] ) {
            $cc_child[ $cc_f['name'] ] = $cc_id;
        }
    }

    cc_check( 'ten top-level folders came back', 10 === count( $cc_top ), json_encode( array_map( 'cc_show', array_keys( $cc_top ) ) ) );

    // What each name is once the path walker has had it (/ becomes -) and the
    // importer's trim has had it (leading whitespace goes). The trim is the
    // importer's own, pre-existing rule; the story's pair is exact.
    $cc_expect = array(
        '=HYPERLINK("http:--x","x")' => 'the HYPERLINK folder is a folder, not a formula',
        '+1'                         => '+1 came back as +1',
        '-1'                         => '-1 came back as -1',
        '@x'                         => '@x came back as @x',
        '-5'                         => '-5 came back as -5, a name and not a number',
        "'=x"                        => "'=x came back with its own apostrophe",
        "'Quoted"                    => "'Quoted came back untouched",
        'Acme'                       => 'Acme came back as Acme',
        'Root'                       => 'Root came back',
        'name'                       => 'the tab- and CR-first names came back trimmed (the importer\'s trim, unchanged)',
    );

    foreach ( $cc_expect as $cc_name => $cc_label ) {
        cc_check( $cc_label, isset( $cc_top[ $cc_name ] ) );
    }

    cc_check( 'the nested =child came back as =child under Root', array( '=child' ) === array_keys( $cc_child ), json_encode( array_keys( $cc_child ) ) );

    // The only name allowed to begin with an apostrophe-then-trigger is the
    // one that was named that way; a prefix left on by the import would
    // show up here as a second.
    $cc_stray = array_values( preg_grep( "/^'[=+\\-@]/", array_keys( $cc_top ) ) );
    cc_check( "no prefix survived the import (only '=x may begin that way)", array( "'=x" ) === $cc_stray, json_encode( array_map( 'cc_show', $cc_stray ) ) );

    cc_check(
        'the file in Acme is still in Acme',
        isset( $cc_top['Acme'] ) && isset( $cc_parsed['files'][ $cc_top['Acme'] ] ) && array( 12 ) === $cc_parsed['files'][ $cc_top['Acme'] ],
        isset( $cc_top['Acme'], $cc_parsed['files'][ $cc_top['Acme'] ] ) ? json_encode( $cc_parsed['files'][ $cc_top['Acme'] ] ) : 'absent'
    );
}


/* --------------------------------------------------- D  what did not change */

echo "\nD  what the story must not change\n";

cc_check( 'a plain line is comma-separated with CRLF', "a,b,c\r\n" === vergeml_csv_line( array( 'a', 'b', 'c' ) ) );

$cc_quoted = vergeml_csv_line( array( 'A, B', '7', 'x"y.jpg' ) );
cc_check( 'a comma in a name is quoted', false !== strpos( $cc_quoted, '"A, B"' ), trim( $cc_quoted ) );
cc_check( 'a quote in a name is doubled', false !== strpos( $cc_quoted, '"x""y.jpg"' ), trim( $cc_quoted ) );

$cc_bom = vergeml_csv_parse( "\xEF\xBB\xBFfolder,attachment_id,filename\r\nzz/One,,\r\n" );
cc_check( 'a BOM is still stripped on the way in', ! is_wp_error( $cc_bom ) && 2 === count( $cc_bom['folders'] ) );
cc_check( 'the header row is still not a folder', ! is_wp_error( $cc_bom ) && ! in_array( 'folder', array_column( $cc_bom['folders'], 'name' ), true ) );

$cc_empty = vergeml_csv_parse( "\n\n" );
cc_check( 'an empty file is refused with the same code', is_wp_error( $cc_empty ) && 'vergeml_csv_empty' === $cc_empty->get_error_code() );

$cc_none = vergeml_csv_parse( "folder,attachment_id,filename\n" );
cc_check( 'a header-only file is refused with the same code', is_wp_error( $cc_none ) && 'vergeml_csv_no_folders' === $cc_none->get_error_code() );

$cc_deep = vergeml_csv_parse( "folder,attachment_id\n" . implode( '/', array_fill( 0, VERGEML_CSV_MAX_DEPTH + 2, 'x' ) ) . ",\nzz/ok,\n" );
cc_check(
    'an over-deep path is still reported, not built',
    ! is_wp_error( $cc_deep ) && 1 === (int) $cc_deep['problem_count'] && array( 'zz', 'ok' ) === array_values( array_column( $cc_deep['folders'], 'name' ) ),
    is_wp_error( $cc_deep ) ? $cc_deep->get_error_code() : json_encode( array_column( $cc_deep['folders'], 'name' ) )
);

$cc_bad = vergeml_csv_parse( "zz/A,not-a-number,odd.jpg\n" );
cc_check( 'a value that is not an id is still reported the same way', ! is_wp_error( $cc_bad ) && 1 === (int) $cc_bad['problem_count'] && false !== strpos( $cc_bad['problems'][0], 'is not a file id' ), is_wp_error( $cc_bad ) ? $cc_bad->get_error_code() : json_encode( $cc_bad['problems'] ) );


printf( "\n%d/%d passed\n", $GLOBALS['cc_pass'], $GLOBALS['cc_pass'] + $GLOBALS['cc_fail'] );

if ( $GLOBALS['cc_fail'] > 0 ) {
    exit( 1 );
}
