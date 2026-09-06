window.vergeml = window.vergeml || { l10n: {} };


( function( $, _ ) {

    _.extend( vergeml.l10n, vergeml_mimetype_options_l10n_data );



    /*
     *  Create a new mime type.
     *
     *  The screen is five tables now, one per kind under its own chevron. A
     *  new row cannot be sorted into a kind before it has a MIME type, so it
     *  goes into Other -- .vgml-ft-new -- and that section is opened, because
     *  a row added inside a closed section is a button that appears to do
     *  nothing. prependTo() against the old selector would have put a copy in
     *  all five.
     */
    $( document ).on( 'click', '.vergeml-button-create-mime', function() {

        var section = document.querySelector( '.vgml-ft-new' );

        if ( section && section.closest( '.vgml-acc-item' ) ) {
            section.closest( '.vgml-acc-item' ).open = true;
        }

        $('.vergeml-mime-type-list').find('.vergeml-clone').first().clone().attr('class','vergeml-clone-mime').prependTo('.vgml-ft-new tbody').show(300).find('input').first().focus();

        return false;
    });

    // remove mime type
    $( document ).on( 'click', 'tr .vergeml-button-remove', function() {

        $(this).closest('tr').hide( 300, function() {
            $(this).remove();
        });

        return false;
    });

    // on change of an extension during creation
    $( document ).on( 'blur', '.vergeml-clone-mime .vergeml-type', function() {

        var extension = $(this).val().toLowerCase(),
            mime_type_tr = $(this).closest('tr');

        $(this).val(extension);

        mime_type_tr.find('.vergeml-mime').attr('name','vergeml_mimes['+extension+'][mime]');
        mime_type_tr.find('.vergeml-singular').attr('name','vergeml_mimes['+extension+'][singular]');
        mime_type_tr.find('.vergeml-plural').attr('name','vergeml_mimes['+extension+'][plural]');
        mime_type_tr.find('.vergeml-filter').attr('name','vergeml_mimes['+extension+'][filter]');
        mime_type_tr.find('.vergeml-upload').attr('name','vergeml_mimes['+extension+'][upload]');
    });


    // on change of a mime type during creation
    $( document ).on( 'blur', '.vergeml-clone-mime .vergeml-mime', function() {

        var mime_type = $(this).val().toLowerCase(),
            mime_type_tr = $(this).closest('tr');

        $(this).val(mime_type);
    });

    // mime types restoration warning
    $( document ).on( 'click', '#eml-restore-mime-types-settings', function( event ) {

        var name = this.name,
            value = $(this).val();


        event.preventDefault();

        emlConfirmDialog( vergeml.l10n.mime_restoring_confirm_title, vergeml.l10n.mime_restoring_confirm_text, vergeml.l10n.mime_restoring_yes, vergeml.l10n.cancel, 'button button-primary eml-warning-button' )
        .done( function() {

            emlFullscreenSpinnerStart( vergeml.l10n.in_progress_restoring_text );

            $('<input type="hidden"/>').attr( 'name', name )
                .val( value )
                .appendTo( $('#vergeml-form-mimetypes') );

            $('#vergeml-form-mimetypes').submit();

        })
        .fail(function() {
            return false;
        });
    });

    // on mime types form submit
    $( '#vergeml-form-mimetypes' ).on( 'submit', function( event ) {

        var submit_it = true,
            alert_title = vergeml.l10n.mime_error_cannot_save_title,
            alert_text = '';

        $('.vergeml-clone-mime').each( function( index ) {

            if ( $('[id="'+$('.vergeml-type',this).val()+'"]').length > 0 ||
                      $('.vergeml-mime[value="'+$('.vergeml-mime',this).val()+'"]').length > 0 ) {

                submit_it = false;
                alert_text = '<p>' + vergeml.l10n.mime_error_duplicate + '</p>';
            }
            else if ( ! $('.vergeml-type',this).val() || $('.vergeml-type',this).val() == '' ||
                 ! $('.vergeml-mime',this).val() || $('.vergeml-mime',this).val() == '' ) {

                submit_it = false;
                alert_text = '<p>' + vergeml.l10n.mime_error_empty_fields + '</p>';
            }


            if ( ! $('.vergeml-singular',this).val() || $('.vergeml-singular',this).val() == '' ||
                 ! $('.vergeml-plural',this).val() || $('.vergeml-plural',this).val() == '' ) {

                $('.vergeml-singular',this).val($('.vergeml-mime',this).val());
                $('.vergeml-plural',this).val($('.vergeml-mime',this).val());
            }
        });

        if ( ! submit_it ) {

            emlAlertDialog( alert_title, alert_text, vergeml.l10n.okay, 'button button-primary' )
            .done( function() {
                return false;
            });
        }

        return submit_it;
    });

})( jQuery, _ );
