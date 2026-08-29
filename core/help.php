<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 *  A question mark beside every option.
 *
 *  Settings screens inherit a habit from the plugins they grew out of: a label
 *  that names the setting, and a description that explains the mechanism. Read
 *  quickly, "Include children" and "Show media items of child media categories
 *  as a result of filtering" tell you almost nothing about whether you want it
 *  on -- which is the only question anybody is actually asking.
 *
 *  So each option gets a `?`. One or two sentences, plain words, and where it
 *  helps, the sentence that matters most: what happens if you leave it alone.
 *
 *  ## Why the copy lives here and not beside each field
 *
 *  Twenty-odd explanations spread through 2,900 lines of markup cannot be read
 *  as a set, and reading them as a set is the only way to notice that two of
 *  them contradict each other or that one is written for a different audience.
 *  Here they are one list, in one file, and anybody can read the lot in a
 *  minute and fix the tone.
 *
 *  The text is deliberately NOT the existing `.description` restated. The
 *  description says what the option does to the software; this says what it
 *  does for you.
 */


/**
 *  vergeml_help_texts
 *
 *  key => the concise explanation. Keys are the option names, so a field and
 *  its help cannot drift apart without somebody renaming one of them.
 */
function vergeml_help_texts() {

    $texts = array(

        /* ---------------------------------------------------- the library */

        'force_filters' => __( 'Adds the media filters to popups that other plugins and themes open. Turn this on if the filters are missing somewhere you expected them.', 'vergelabs-media-library' ),

        'filters_to_show' => __( 'Which dropdowns appear above the media library. Fewer filters means a tidier toolbar; each one you leave on is one more way to narrow a large library.', 'vergelabs-media-library' ),

        'show_count' => __( 'Puts the number of files beside each category in the filter dropdowns. It costs one database query per category, so turn it off if the admin feels slow on a big library.', 'vergelabs-media-library' ),

        'include_children' => __( 'When you filter by a folder, also show what is inside its sub-folders. Off, picking a folder shows only what is filed directly in it.', 'vergelabs-media-library' ),

        'filter_uploaded' => __( 'When you add an image to a post, start by showing only the files uploaded to that post. Useful when posts have their own images; unhelpful when everyone draws from one shared library.', 'vergelabs-media-library' ),

        'infinite_scrolling' => __( 'Load more files as you scroll instead of paging through them. Easier for browsing, harder for finding your place again.', 'vergelabs-media-library' ),

        'loads_per_page' => __( 'How many files to fetch at a time. Lower is faster on a slow server; higher means less waiting as you scroll.', 'vergelabs-media-library' ),

        'search_in' => __( 'Which fields the media search looks at. WordPress searches titles, captions and descriptions on its own — the rest are added by this plugin, and each one makes search find more.', 'vergelabs-media-library' ),

        'search_on_enter' => __( 'Wait until you press Enter before searching, instead of searching as you type. Worth having on a large library, where searching on every keystroke is slow.', 'vergelabs-media-library' ),

        'search_auto' => __( 'Search while you type, which is what WordPress does by default. Turn it off if results appear before you have finished the word.', 'vergelabs-media-library' ),

        'media_orderby' => __( 'What decides the order files appear in. Pick Custom Order if you want to arrange them yourself by dragging.', 'vergelabs-media-library' ),

        'media_order' => __( 'Which end of that order comes first. Newest first is Descending.', 'vergelabs-media-library' ),

        'grid_sidebar_width' => __( 'How wide the details panel is on the right of the media library. Only applies on screens wider than 900px.', 'vergelabs-media-library' ),

        'ideal_column_width' => __( 'Roughly how big each thumbnail should be. WordPress fits as many as it can per row, so a smaller number means more files on screen at once.', 'vergelabs-media-library' ),

        'grid_show_caption' => __( 'Print a line of text under each thumbnail. Helpful when file names are meaningless; noisy when they are not.', 'vergelabs-media-library' ),

        'enhance_media_shortcodes' => __( 'Lets WordPress gallery shortcodes select by folder, tag or upload date instead of a fixed list of files. Only affects shortcodes you write yourself.', 'vergelabs-media-library' ),

        /* -------------------------------------------------- the taxonomies */

        'tax_archives' => __( 'Gives every media category its own public page on your site, the way category pages work for posts. Most sites do not want this. Re-save your permalinks after changing it.', 'vergelabs-media-library' ),

        'edit_all_as_hierarchical' => __( 'Show tag-style taxonomies with tick boxes instead of a text field when you edit a file. Easier to pick from a known list, worse for inventing new tags as you go.', 'vergelabs-media-library' ),

        'one_folder_per_file' => __( 'On, a file lives in exactly one folder and dragging moves it — the way a folder tree normally behaves. Off, a file can be in several at once and dragging adds it to another. Nothing already filed changes either way.', 'vergelabs-media-library' ),

        /* ----------------------------------------------------- the network */

        'media_settings' => __( 'Let each site in the network change its own media settings. Off, only a network administrator can.', 'vergelabs-media-library' ),

        'utilities' => __( 'Let each site in the network import, export and reset this plugin\'s settings. Off, only a network administrator can.', 'vergelabs-media-library' ),

        /* ---------------------------------------------------------- the AI */

        'license_key' => __( 'The key from your purchase email. It connects this site to the AI service and is stored encrypted — nothing is sent anywhere until you enter one and start a run.', 'vergelabs-media-library' ),

        'credits' => __( 'One credit describes one image. The number shown is whatever was left after the last run, so it updates when you next describe something.', 'vergelabs-media-library' ),

        'enrich_search' => __( 'Lets the media search match what the AI saw in a picture, not only what you typed about it. A search for “beach” then finds the photo of a beach whose file name says DSC_0431.', 'vergelabs-media-library' ),

        'site_profile' => __( 'What you sell or publish, in a sentence or two. The model is told never to guess a brand or product name, so this is where you give it the ones it should know — it makes descriptions more exact, not longer.', 'vergelabs-media-library' ),

        'mock' => __( 'Invents descriptions on this server from file names, so you can see what the folders and the Librarian would do before paying for anything. Nothing is sent anywhere and no credits are spent. The captions are not real.', 'vergelabs-media-library' ),
    );

    /**
     *  Filter the option help texts.
     *
     *  Copy only -- it reaches the screen escaped, and it decides no behaviour.
     *
     *  @since 3.9.0
     *
     *  @param array $texts  option key => explanation.
     */
    return apply_filters( 'vergeml_help_texts', $texts );
}


/**
 *  vergeml_help
 *
 *  The button itself. Prints nothing at all for a key with no text, so a new
 *  option cannot ship with an empty bubble that looks broken -- it simply has
 *  no question mark until somebody writes one.
 */
function vergeml_help( $key ) {

    $texts = vergeml_help_texts();

    if ( empty( $texts[ $key ] ) ) {
        return;
    }

    printf(
        ' <button type="button" class="vgml-help" data-help="%1$s" aria-label="%2$s" aria-expanded="false">?</button>',
        esc_attr( $texts[ $key ] ),
        esc_attr__( 'What does this do?', 'vergelabs-media-library' )
    );
}


add_action( 'admin_enqueue_scripts', 'vergeml_help_assets', 21 );

function vergeml_help_assets() {

    if ( ! function_exists( 'vergeml_shell_current' ) || '' === vergeml_shell_current() ) {
        return;
    }

    wp_enqueue_script(
        'vergeml-help',
        plugins_url( 'js/vergeml-help.js', VERGEML_FILE ),
        array(),
        vergeml_asset_ver( 'js/vergeml-help.js' ),
        true
    );

    wp_localize_script( 'vergeml-help', 'vergemlHelp', array(
        'close' => __( 'Close', 'vergelabs-media-library' ),
    ) );
}
