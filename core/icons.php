<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 *  The icon set.
 *
 *  Drawn here rather than pulled from a library, because this plugin has no
 *  build step and will not gain one for a hundred lines of path data. Every
 *  icon shares the same geometry so the set reads as a set:
 *
 *      24x24 box, 1.6 stroke, round caps and joins, no fills, currentColor.
 *
 *  currentColor is what lets one definition sit in a nav item, a button and a
 *  panel heading and take the right colour in each without a second copy.
 *
 *  They are deliberately plain. An icon beside a label is a landmark for
 *  somebody scanning a menu they have seen fifty times, not decoration, and an
 *  illustrated one competes with the word it is meant to help.
 */


function vergeml_icon( $name, $size = 20 ) {

    $paths = vergeml_icon_paths();

    if ( ! isset( $paths[ $name ] ) ) {
        return '';
    }

    return sprintf(
        '<svg class="vgml-icon-svg" width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%2$s</svg>',
        (int) $size,
        $paths[ $name ] // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal path data from the array below.
    );
}


function vergeml_icon_paths() {

    return array(

        // A library: three volumes on a shelf, one leaning. Not a house --
        // this is not a site's home, it is this plugin's.
        'dashboard' => '<path d="M4 20V7m4 13V4m4 16V9"/><path d="M15.5 20 19 6.6l2.2.6L18 20.5"/><path d="M3 20h18"/>',

        // The Librarian: a stack being sorted, the top one lifted out.
        'librarian' => '<rect x="3" y="14" width="18" height="6" rx="1.5"/><rect x="5" y="9" width="14" height="4" rx="1.2"/><path d="m9 6 3-3 3 3"/>',

        // AI: a spark. Four points rather than a star, so it does not read as
        // a rating.
        'ai' => '<path d="M12 3v4M12 17v4M3 12h4M17 12h4"/><path d="M12 8.5 13.4 11l2.6 1-2.6 1-1.4 2.5L10.6 13 8 12l2.6-1z"/>',

        // Duplicates: the same square twice, offset.
        'duplicates' => '<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M15 5.5A2.5 2.5 0 0 0 12.5 3h-7A2.5 2.5 0 0 0 3 5.5v7A2.5 2.5 0 0 0 5.5 15"/>',

        // Import: into a folder, from outside.
        'import' => '<path d="M3 8.5V18a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V9.5a2 2 0 0 0-2-2h-6.5l-2-2H5a2 2 0 0 0-2 2z"/><path d="M12 17v-5m0 0-2 2m2-2 2 2"/>',

        // Folders and taxonomies: a tree, because that is the shape of it.
        'folders' => '<path d="M4 4h5l1.5 2H4z" /><path d="M4 6v12"/><path d="M4 11h5m-5 7h5"/><rect x="9" y="9" width="11" height="4" rx="1.2"/><rect x="9" y="16" width="11" height="4" rx="1.2"/>',

        // Behaviour: sliders, the universal "how it works" mark.
        'sliders' => '<path d="M5 5v6m0 2v6M12 5v2m0 2v10M19 5v10m0 2v2"/><circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="8" r="1.6"/><circle cx="19" cy="16" r="1.6"/>',

        // File types: a page with a folded corner.
        'file' => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/>',
        'key'  => '<circle cx="8" cy="15" r="4"/><path d="m10.8 12.2 8.7-8.7M15 7l2.5 2.5M18 4l2 2"/>',

        /* --------------------------------------------------- the quick rail */

        'play' => '<path d="M6 4.5v15l13-7.5z"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'download' => '<path d="M12 3v12m0 0-4-4m4 4 4-4"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/>',
        'alt' => '<path d="M4 6h16M4 12h10M4 18h7"/><path d="m17 15 2.5 5 2.5-5"/>',
        'check' => '<path d="m4 12.5 5 5L20 6"/>',
    );
}
