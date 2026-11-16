window.vergeml = window.vergeml || { l10n: {} };


( function( $, _ ) {

    _.extend( vergeml.l10n, vergeml_mimetype_options_l10n_data );



    /*
     *  Only types WordPress knows can be listed here (4.1.0), so a row can be
     *  removed from the settings but no longer added by hand.
     */

    // remove mime type
    $( document ).on( 'click', 'tr .vergeml-button-remove', function() {

        $(this).closest('tr').hide( 300, function() {
            $(this).remove();
        });

        return false;
    });

    // mime types restoration warning
    $( document ).on( 'click', '#eml-restore-mime-types-settings', function( event ) {

        var name = this.name,
            value = $(this).val();


        event.preventDefault();

        vergemlConfirmDialog( vergeml.l10n.mime_restoring_confirm_title, vergeml.l10n.mime_restoring_confirm_text, vergeml.l10n.mime_restoring_yes, vergeml.l10n.cancel, 'button button-primary eml-warning-button' )
        .done( function() {

            vergemlFullscreenSpinnerStart( vergeml.l10n.in_progress_restoring_text );

            $('<input type="hidden"/>').attr( 'name', name )
                .val( value )
                .appendTo( $('#vergeml-form-mimetypes') );

            $('#vergeml-form-mimetypes').submit();

        })
        .fail(function() {
            return false;
        });
    });

})( jQuery, _ );
