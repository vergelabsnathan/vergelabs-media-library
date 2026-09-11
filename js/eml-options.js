window.vergeml = window.vergeml || { l10n: {} };


( function( $ ) {

    _.extend( vergeml.l10n, vergeml_options_l10n_data );




    $( document ).on('click', '#eml-submit-settings-cleanup', function( event ) {

        event.preventDefault();

        vergemlConfirmDialog( vergeml.l10n.cleanup_warning_title, vergeml.l10n.cleanup_warning_text_p1+vergeml.l10n.cleanup_warning_text_p2, vergeml.l10n.cleanup_warning_yes, vergeml.l10n.cancel, 'button button-primary eml-warning-button' )
        .done( function() {

            vergemlFullscreenSpinnerStart( vergeml.l10n.in_progress_cleanup_text );

            $('#eml-form-cleanup').submit();

        })
        .fail(function() {
            return false;
        });
    });


    $( document ).on( 'click', '.vergeml-apply-settings-to-network', function( event ) {

        var settings =  $( event.target ).attr( 'data-settings' ),
            applying_settings_text;


        event.preventDefault();

        switch ( settings ) {

            case 'media-library':
                applying_settings_text = vergeml.l10n.applying_media_library_settings_text;
                break;
            case 'media-taxonomies':
                applying_settings_text = vergeml.l10n.applying_media_taxonomies_settings_text;
                break;
            case 'mime-types':
                applying_settings_text = vergeml.l10n.applying_mime_types_settings_text;
                break;
            default:
                applying_settings_text = '';
        }

        vergemlConfirmDialog( vergeml.l10n.applying_settings_title, applying_settings_text + ' ' + vergeml.l10n.cleanup_warning_text_p2, vergeml.l10n.applying_settings_yes, vergeml.l10n.cancel, 'button button-primary eml-warning-button' )
        .done( function() {

            vergemlFullscreenSpinnerStart( vergeml.l10n.in_progress_apply_setings_text );

            $.post( ajaxurl, {
                nonce: vergeml.l10n.apply_to_network_nonce,
                action: 'vergeml-apply-settings-to-network',
                settings: settings
            },function( response ) {
                vergemlFullscreenSpinnerStop();
            });
        })
        .fail(function() {
            return false;
        });
    });

})( jQuery );
