<?php
/**
 *  The words on the screen, against the list of words that are not allowed on it.
 *
 *      wp eval-file tests/copy/voice.php --allow-root
 *
 *  docs/voice.md has had a banned-words table since it was written, and on the
 *  day this suite was added the plugin shipped a button labelled "Propose a
 *  tree" -- three of the banned words in three words of button. A rule nothing
 *  checks is a rule that documents an intention.
 *
 *  So this reads every translatable string in the plugin and fails on the ones
 *  the table forbids. It reads source rather than rendered output because a
 *  string only reachable on one screen state is still a string somebody gets,
 *  and no browser walk visits every state.
 *
 *  It is deliberately narrow. Only words with a plain replacement are listed:
 *  a suite that argues about tone produces a list nobody reads, and the point
 *  is to catch the specific words this product keeps reaching for.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$GLOBALS['vc_pass'] = 0;
$GLOBALS['vc_fail'] = 0;
$GLOBALS['vc_log']  = '';

function vc_say( $line ) {
    $GLOBALS['vc_log'] .= $line;
    echo $line; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

function vc_check( $label, $ok, $note = '' ) {
    if ( $ok ) {
        $GLOBALS['vc_pass']++;
    } else {
        $GLOBALS['vc_fail']++;
    }
    vc_say( sprintf( "  %s  %s%s\n", $ok ? 'ok  ' : 'FAIL', $label, '' === $note ? '' : '  -- ' . $note ) );
}

/*
 *  The table from docs/voice.md, as patterns.
 *
 *  Word boundaries throughout: "index" is banned as a noun somebody reads and
 *  "indexed" with it, but "reindex" inside a function name never reaches a
 *  screen and this only looks at translatable strings anyway.
 */
$vc_banned = array(
    'tree'          => 'folders',
    'branch'        => 'folder',
    'branches'      => 'folders',
    'proposal'      => 'the folders we want to make',
    'propose'       => 'work out',
    'proposes'      => 'works out',
    'proposed'      => 'the ones we would make',
    'scheme'        => 'way to sort',
    'schemes'       => 'ways to sort',
    'taxonomy'      => 'folder, or category',
    'taxonomies'    => 'folders, or categories',
    'attachment'    => 'file, or picture',
    'attachments'   => 'files, or pictures',
    'unindexed'     => 'not looked at yet',
    'quarantine'    => 'set aside',
    'librarian'     => 'sort into folders',
    'ladder'        => 'steps',
    'rung'          => 'step',
);

/*
 *  Where a banned word is the right word.
 *
 *  Three kinds, and each one is a place the reader is a developer rather than
 *  a customer: the wp-admin page slugs, which are URLs; the words in this
 *  suite and in docs/voice.md, which are the rule itself; and any string whose
 *  only reader is another programme.
 */
$vc_skip_files = array(
    'tests/copy/voice.php',
);

/*
 *  Files this rule does not reach yet, each for a stated reason.
 *
 *  Not a baseline of "whatever was failing when the suite was written" -- that
 *  rots into permission to add more. Two lists, both by file, both with a
 *  reason somebody can argue with.
 */
$vc_developer_only = array(
    // Validation errors on REST arguments. The only way to see one is to send
    // a request by hand with a taxonomy that does not exist, so the reader is
    // whoever wrote that request.
    'core/rest-folders.php',
    'core/rest-tree.php',
    'core/import.php',
    'core/folder-tools.php',
);

$vc_backlog = array(
    // Enhanced Media Library's own settings prose, inherited at the fork and
    // not yet rewritten. Roughly fifty strings across the settings screens,
    // and rewriting them is its own job rather than a thing to do halfway.
    'core/options-pages.php',
);

// Run from the checkout the root is two levels up; shipped to /tmp on the box
// by tools/verify.mjs it is not, and the plugin's own directory is the source.
$vc_root = dirname( dirname( __DIR__ ) );
if ( ! is_dir( $vc_root . '/core' ) ) {
    $vc_root = WP_PLUGIN_DIR . '/vergelabs-media-library';
}
$vc_files = array();

foreach ( array( 'core', 'pro/includes' ) as $vc_dir ) {

    $vc_path = $vc_root . '/' . $vc_dir;

    if ( ! is_dir( $vc_path ) ) {
        continue;
    }

    foreach ( glob( $vc_path . '/*.php' ) as $vc_file ) {
        $vc_files[] = $vc_file;
    }
}

$vc_files[] = $vc_root . '/vergelabs-media-library.php';


vc_say( "\nthe words on the screen\n\n" );
vc_say( sprintf( "A  %d files, every translatable string in them\n", count( $vc_files ) ) );

$vc_hits      = array();
$vc_strings   = 0;
$vc_excused_n = 0;

foreach ( $vc_files as $vc_file ) {

    $vc_short = str_replace( '\\', '/', substr( $vc_file, strlen( $vc_root ) + 1 ) );

    if ( in_array( $vc_short, $vc_skip_files, true ) ) {
        continue;
    }

    $vc_excused = in_array( $vc_short, $vc_developer_only, true ) || in_array( $vc_short, $vc_backlog, true );

    $vc_src = file_get_contents( $vc_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents

    if ( false === $vc_src ) {
        continue;
    }

    /*
     *  Single-quoted translatable strings, which is what this codebase writes.
     *  Escaped quotes inside are allowed for -- "WordPress\'s scheduler" is a
     *  real string in core/librarian.php.
     */
    if ( ! preg_match_all( "/\b(?:__|esc_html__|esc_attr__|_e|esc_html_e|esc_attr_e|_x|_n)\(\s*'((?:[^'\\\\]|\\\\.)*)'/", $vc_src, $vc_found, PREG_OFFSET_CAPTURE ) ) {
        continue;
    }

    foreach ( $vc_found[1] as $vc_match ) {

        $vc_strings++;

        $vc_text = $vc_match[0];
        $vc_line = substr_count( substr( $vc_src, 0, $vc_match[1] ), "\n" ) + 1;

        foreach ( $vc_banned as $vc_word => $vc_instead ) {

            if ( ! preg_match( '/\b' . preg_quote( $vc_word, '/' ) . '\b/i', $vc_text ) ) {
                continue;
            }

            if ( $vc_excused ) {
                $vc_excused_n++;
                break;
            }

            $vc_hits[] = array(
                'file'    => $vc_short,
                'line'    => $vc_line,
                'word'    => $vc_word,
                'instead' => $vc_instead,
                'text'    => strlen( $vc_text ) > 88 ? substr( $vc_text, 0, 85 ) . '…' : $vc_text,
            );

            break; // one report per string is enough to go and fix it
        }
    }
}

vc_say( sprintf( "   %d strings read\n\n", $vc_strings ) );

vc_check( 'there are strings to check at all', $vc_strings > 50, $vc_strings . ' found' );

vc_say( "\nB  none of them uses a word the voice forbids\n" );

if ( $vc_hits ) {

    foreach ( $vc_hits as $vc_hit ) {
        vc_say( sprintf(
            "        %s:%d  \"%s\"  → say %s\n",
            $vc_hit['file'],
            $vc_hit['line'],
            $vc_hit['text'],
            $vc_hit['instead']
        ) );
    }
}

vc_check(
    'no banned words in anything a person reads',
    0 === count( $vc_hits ),
    count( $vc_hits ) . ' strings to fix'
);

vc_say( sprintf(
    "
   %d more in the files named at the top of this suite: %s, and the settings
   prose inherited from Enhanced Media Library. Both are excused by name, not
   by having been failing when this was written.
",
    $vc_excused_n,
    implode( ', ', $vc_developer_only )
) );

vc_say( sprintf( "\n%d/%d passed\n", $GLOBALS['vc_pass'], $GLOBALS['vc_pass'] + $GLOBALS['vc_fail'] ) );

@file_put_contents( __DIR__ . '/voice-last-run.txt', $GLOBALS['vc_log'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

if ( $GLOBALS['vc_fail'] > 0 ) {
    exit( 1 );
}
