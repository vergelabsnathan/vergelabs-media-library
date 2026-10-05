/**
 * Opens the plugin's grid on upload.php in place of core's manage frame.
 *
 * Core's wp-admin/js/media.js stays on the page and does everything else it
 * does there: the attach dialog (findPosts), the bulk actions, copy URL. It
 * opens its manage frame on DOM ready if #wp-media-grid exists. This file
 * loads after it (it depends on `media`), so while the page is still loading
 * it holds the container back under another id; core's ready callback finds
 * no container and opens nothing, and this file's ready callback, which runs
 * after core's, gives the id back and opens the plugin's frame there. One
 * frame, one uploader, and core's own script untouched.
 */

/* global _wpMediaGridSettings */

( function( $ ) {

    var container = document.getElementById( 'wp-media-grid' );

    if ( ! container ) {
        return;
    }

    container.id = 'vergeml-media-grid-pending';

    $( function() {

        var $mediaGridWrap = $( container ).attr( 'id', 'wp-media-grid' ),
            frame = new window.wp.media.view.MediaFrame.emlGrid({
                frame: 'eml-grid',
                container: $mediaGridWrap,
                library: _wpMediaGridSettings.queryVars
            }).open();

        // Fire a global ready event, as core does for its own frame.
        $mediaGridWrap.trigger( 'wp-media-grid-ready', frame );
    });

})( jQuery );
