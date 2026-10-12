/*
 *  Media Taxonomies settings screen.
 *
 *  Handles the taxonomy list on the settings screen: adding and removing
 *  taxonomies, deriving a taxonomy name, slug and labels from what the user
 *  types, validating the name against the allowed format and the reserved
 *  names, and the "synchronize terms" button.
 *
 *  This file only touches its own settings screen; it does not patch any core
 *  view.
 */

window.vergeml = window.vergeml || { l10n: {} };

(function ($, _) {
    function isValidTaxonomyName(name) {
        return new RegExp('^(?=.{3,32}$)[a-z0-9_]*[a-z][a-z0-9_]*$').test(name);
    }

    function transliterate(text) {
        const transliterationMap = {
            а: 'a',
            б: 'b',
            в: 'v',
            г: 'g',
            ґ: 'g',
            д: 'd',
            е: 'e',
            є: 'ie',
            ж: 'zh',
            з: 'z',
            и: 'y',
            і: 'i',
            ї: 'i',
            й: 'i',
            к: 'k',
            л: 'l',
            м: 'm',
            н: 'n',
            о: 'o',
            п: 'p',
            р: 'r',
            с: 's',
            т: 't',
            у: 'u',
            ф: 'f',
            х: 'kh',
            ц: 'ts',
            ч: 'ch',
            ш: 'sh',
            щ: 'shch',
            ь: '',
            ю: 'iu',
            я: 'ia',
            ё: 'e',
            ы: 'y',
            ъ: '',
            э: 'e',
            ä: 'a',
            á: 'a',
            à: 'a',
            â: 'a',
            ã: 'a',
            å: 'a',
            æ: 'ae',
            ç: 'c',
            è: 'e',
            é: 'e',
            ê: 'e',
            ë: 'e',
            ì: 'i',
            í: 'i',
            î: 'i',
            ï: 'i',
            ı: 'i',
            ğ: 'g',
            ð: 'd',
            ñ: 'n',
            ò: 'o',
            ó: 'o',
            ô: 'o',
            õ: 'o',
            ö: 'o',
            ő: 'o',
            ø: 'o',
            ş: 's',
            ù: 'u',
            ú: 'u',
            û: 'u',
            ü: 'u',
            ű: 'u',
            ý: 'y',
            þ: 'th',
            ÿ: 'y',
            α: 'a',
            β: 'b',
            γ: 'g',
            δ: 'd',
            ε: 'e',
            ζ: 'z',
            η: 'h',
            θ: '8',
            ι: 'i',
            κ: 'k',
            λ: 'l',
            μ: 'm',
            ν: 'n',
            ξ: '3',
            ο: 'o',
            π: 'p',
            ρ: 'r',
            σ: 's',
            τ: 't',
            υ: 'y',
            φ: 'f',
            χ: 'x',
            ψ: 'ps',
            ω: 'w',
            ά: 'a',
            έ: 'e',
            ί: 'i',
            ό: 'o',
            ύ: 'y',
            ή: 'h',
            ώ: 'w',
            ς: 's',
            ϊ: 'i',
            ΰ: 'y',
            ϋ: 'y',
            ΐ: 'i',
            č: 'c',
            ď: 'd',
            ě: 'e',
            ň: 'n',
            ř: 'r',
            š: 's',
            ť: 't',
            ů: 'u',
            ž: 'z',
            ą: 'a',
            ć: 'c',
            ę: 'e',
            ł: 'l',
            ń: 'n',
            ś: 's',
            ź: 'z',
            ż: 'z',
            ā: 'a',
            ē: 'e',
            ģ: 'g',
            ī: 'i',
            ķ: 'k',
            ļ: 'l',
            ņ: 'n',
            ū: 'u',
        };
        return text
            .split('')
            .map((character) => transliterationMap[character] || character)
            .join('');
    }

    // Sets the row's id to the taxonomy name, or clears it and warns when the
    // name is already used or reserved.
    function setTaxonomyId(taxonomyId, $row) {
        $row.attr('id', taxonomyId);

        var sameIdSelector =
            '.vergeml-clone-taxonomy[id=' +
            taxonomyId +
            '], .vergeml-taxonomy[id=' +
            taxonomyId +
            '], .wpuxss-non-eml-taxonomy[id=' +
            taxonomyId +
            ']';

        if (
            ('' !== taxonomyId && $(sameIdSelector).length > 1) ||
            -1 !==
                $.inArray(taxonomyId, [
                    'link_category',
                    'post_format',
                    'wp_theme',
                    'wp_pattern_category',
                    'wp_template_part_area',
                ])
        ) {
            $row.attr('id', '');
            vergemlAlertDialog(
                vergeml.l10n.tax_error_duplicate_title,
                vergeml.l10n.tax_error_duplicate_text,
                vergeml.l10n.okay,
                'button button-primary',
            ).done(function () {
                return false;
            });
        }
    }

    // Points the row's form fields at vergeml_taxonomies[<taxonomyId>][...].
    function setFieldNames(taxonomyId, $row) {
        $.each(
            {
                assigned: '.vergeml-assigned',
                eml_media: '.vergeml-eml_media',
                create_taxonomy: '.vergeml-create_taxonomy',
            },
            function (fieldKey, selector) {
                $row.find(selector).attr(
                    'name',
                    'vergeml_taxonomies[' + taxonomyId + '][' + fieldKey + ']',
                );
            },
        );

        $.each(
            {
                labels: [
                    'singular_name',
                    'name',
                    'menu_name',
                    'all_items',
                    'edit_item',
                    'view_item',
                    'update_item',
                    'add_new_item',
                    'new_item_name',
                    'parent_item',
                    'search_items',
                ],
                hierarchical: 'hierarchical',
                show_admin_column: 'show_admin_column',
                admin_filter: 'admin_filter',
                media_uploader_filter: 'media_uploader_filter',
                media_popup_taxonomy_edit: 'media_popup_taxonomy_edit',
                sort: 'sort',
                show_in_rest: 'show_in_rest',
                rewrite: ['slug', 'with_front'],
            },
            function (groupKey, fieldNames) {
                if (groupKey === fieldNames) {
                    $row.find('.vergeml-' + fieldNames).attr(
                        'name',
                        'vergeml_taxonomies[' + taxonomyId + '][' + fieldNames + ']',
                    );
                } else {
                    $.each(fieldNames, function (index, fieldName) {
                        $row.find('.vergeml-' + fieldName).attr(
                            'name',
                            'vergeml_taxonomies[' +
                                taxonomyId +
                                '][' +
                                groupKey +
                                '][' +
                                fieldName +
                                ']',
                        );
                    });
                }
            },
        );
    }

    // Fills the singular-name based labels (edit, view, update, ...) in the edit panel.
    function fillItemLabels(singularName, $editPanel) {
        var labelSelectors = {
            edit: '.vergeml-edit_item',
            view: '.vergeml-view_item',
            update: '.vergeml-update_item',
            add_new: '.vergeml-add_new_item',
            new: '.vergeml-new_item_name',
            parent: '.vergeml-parent_item',
        };

        if ('' !== singularName) {
            $.each(labelSelectors, function (l10nKey, selector) {
                $editPanel.find(selector).val(vergeml.l10n[l10nKey] + ' ' + singularName);
            });
        } else {
            $.each(labelSelectors, function (l10nKey, selector) {
                $editPanel.find(selector).val('');
            });
        }
    }

    _.extend(vergeml.l10n, vergeml_taxonomies_options_l10n_data);

    $(document).on('click', 'li .vergeml-button-remove', function () {
        var $item = $(this).parent();

        if ($item.hasClass('vergeml-clone-taxonomy')) {
            $item.hide(300, function () {
                $(this).remove();
            });
        } else {
            vergemlConfirmDialog(
                vergeml.l10n.tax_deletion_confirm_title,
                vergeml.l10n.tax_deletion_confirm_text_p1 +
                    vergeml.l10n.tax_deletion_confirm_text_p2 +
                    vergeml.l10n.tax_deletion_confirm_text_p3 +
                    vergeml.l10n.tax_deletion_confirm_text_p4,
                vergeml.l10n.tax_deletion_yes,
                vergeml.l10n.cancel,
                'button button-primary eml-warning-button',
            )
                .done(function () {
                    $item.hide(300, function () {
                        $(this).remove();
                    });
                })
                .fail(function () {
                    return false;
                });
        }

        return false;
    });

    $(document).on('click', '.vergeml-button-create-taxonomy', function () {
        $('.vergeml-media-taxonomy-list')
            .find('.vergeml-clone')
            .clone()
            .attr('class', 'vergeml-clone-taxonomy')
            .appendTo('.vergeml-media-taxonomy-list')
            .show(300);

        return false;
    });

    $(document).on('click', '.vergeml-button-edit', function () {
        $(this).parent().find('.vergeml-taxonomy-edit').toggle(300);
        $(this).html(function (index, currentHtml) {
            return currentHtml == vergeml.l10n.edit + ' ↓'
                ? vergeml.l10n.close + ' ↑'
                : vergeml.l10n.edit + ' ↓';
        });

        return false;
    });

    $(document).on('blur', '.vergeml-clone-taxonomy .vergeml-taxonomy-name', function () {
        var taxonomyName = $(this).val().toLowerCase();
        var $row = $(this).parents('.vergeml-clone-taxonomy');
        var slug = $row.find('.vergeml-slug').val();

        if ('' === taxonomyName) {
            setTaxonomyId('', $row);
            setFieldNames('', $row);
            return;
        }

        taxonomyName = taxonomyName.replace(/[^a-z0-9_]/g, '');
        if ('' !== taxonomyName) {
            taxonomyName = transliterate(taxonomyName);
            if ('year' == taxonomyName) {
                taxonomyName = 'media_year';
            }
        }

        $(this).val(taxonomyName);

        if ('' === slug) {
            $row.find('.vergeml-slug').val(taxonomyName);
        }

        if (isValidTaxonomyName(taxonomyName)) {
            setTaxonomyId(taxonomyName, $row);
            setFieldNames(taxonomyName, $row);
        } else {
            setTaxonomyId('', $row);
            setFieldNames('', $row);
            vergemlAlertDialog(
                vergeml.l10n.tax_error_wrong_taxname_title,
                vergeml.l10n.tax_error_wrong_taxname,
                vergeml.l10n.okay,
                'button button-primary',
            ).done(function () {
                return false;
            });
        }
    });

    $(document).on('blur', '.vergeml-slug', function () {
        var slug = $(this).val().toLowerCase();
        var taxonomyName = $(this)
            .parents('.vergeml-taxonomy-edit')
            .find('.vergeml-taxonomy-name')
            .val()
            .toLowerCase();

        slug = slug || taxonomyName;

        if ('' !== slug) {
            slug = slug.replace(/^[_-]+|[^a-z0-9_-]|[_-]+$/g, '').replace(/([_-])+/g, '$1');
            $(this).val(slug);

            if ('' === slug) {
                $(this).val(taxonomyName);
                vergemlAlertDialog(
                    vergeml.l10n.tax_error_wrong_slug_title,
                    vergeml.l10n.tax_error_wrong_slug,
                    vergeml.l10n.okay,
                    'button button-primary',
                ).done(function () {
                    return false;
                });
            }
        }
    });

    $(document).on('blur', '.vergeml-clone-taxonomy .vergeml-singular_name', function () {
        var taxonomyName;
        var singularName = $(this)
            .val()
            .trim()
            .replace(/(<([^>]+)>)/g, '');
        var $row = $(this).closest('.vergeml-clone-taxonomy');
        var $editPanel = $(this).parents('.vergeml-taxonomy-edit');
        var $taxonomyNameField = $editPanel.find('.vergeml-taxonomy-name');
        var slug = $editPanel.find('.vergeml-slug').val();

        $(this).val(singularName);

        if ('' !== singularName) {
            fillItemLabels(singularName, $editPanel);

            taxonomyName = transliterate(
                singularName
                    .toLowerCase()
                    .replace(/[ &-]+/g, '_')
                    .replace(/[^a-z0-9_]/g, ''),
            );
            $taxonomyNameField.val(taxonomyName);

            if ('' === slug) {
                slug = taxonomyName
                    .replace(/^[_-]+|[^a-z0-9_-]|[_-]+$/g, '')
                    .replace(/([_-])+/g, '$1');
                $editPanel.find('.vergeml-slug').val(slug);
            }

            if (isValidTaxonomyName(taxonomyName)) {
                setTaxonomyId(taxonomyName, $row);
                setFieldNames(taxonomyName, $row);
            } else {
                setTaxonomyId('', $row);
                setFieldNames('', $row);
            }
        } else {
            fillItemLabels('', $editPanel);
        }
    });

    $(document).on('blur', '.vergeml-taxonomy .vergeml-singular_name', function () {
        var singularName = $(this)
            .val()
            .trim()
            .replace(/(<([^>]+)>)/g, '');
        var $editPanel = $(this).parents('.vergeml-taxonomy-edit');

        $(this).val(singularName);
        fillItemLabels(singularName, $editPanel);
    });

    $(document).on(
        'blur',
        '.vergeml-clone-taxonomy .vergeml-name, .vergeml-taxonomy .vergeml-name',
        function () {
            var pluralName = $(this)
                .val()
                .trim()
                .replace(/(<([^>]+)>)/g, '');
            var $editPanel = $(this).parents('.vergeml-taxonomy-edit');
            var $labelText = $(this)
                .closest('.vergeml-clone-taxonomy')
                .find('.vergeml-taxonomy-label span');

            $(this).val(pluralName);

            var pluralLabelSelectors = {
                all: '.vergeml-all_items',
                search: '.vergeml-search_items',
            };

            if ('' !== pluralName) {
                $editPanel.find('.vergeml-menu_name').val(pluralName);
                $labelText.text(pluralName);
                $.each(pluralLabelSelectors, function (l10nKey, selector) {
                    $editPanel.find(selector).val(vergeml.l10n[l10nKey] + ' ' + pluralName);
                });
            } else {
                $editPanel.find('.vergeml-menu_name').val('');
                $labelText.text(vergeml.l10n.tax_new);
                $.each(pluralLabelSelectors, function (l10nKey, selector) {
                    $editPanel.find(selector).val('');
                });
            }
        },
    );

    $('#vergeml-form-taxonomies').on('submit', function () {
        var reservedNames = [
            'link_category',
            'post_format',
            'wp_theme',
            'wp_pattern_category',
            'wp_template_part_area',
        ];
        var isValid = true;
        var errorTitle = vergeml.l10n.tax_error_empty_fileds_title;
        var errorText = '';

        $('.vergeml-clone-taxonomy, .vergeml-taxonomy').each(function () {
            var taxonomyId = $(this).attr('id');
            var singularName = $('.vergeml-singular_name', this).val();
            var pluralName = $('.vergeml-name', this).val();
            var slug = $('.vergeml-slug', this).val();

            if (!taxonomyId) {
                isValid = false;
                errorText =
                    '<p>' +
                    vergeml.l10n.tax_error_empty_taxname +
                    '</p><p>' +
                    vergeml.l10n.tax_error_wrong_taxname +
                    '</p>';
            } else if (!slug) {
                $('.vergeml-slug', this).val(taxonomyId);
            } else if (!(singularName || pluralName)) {
                isValid = false;
                errorText = vergeml.l10n.tax_error_empty_both;
            } else if (!singularName) {
                isValid = false;
                errorText = vergeml.l10n.tax_error_empty_singular;
            } else if (!pluralName) {
                isValid = false;
                errorText = vergeml.l10n.tax_error_empty_plural;
            } else {
                var sameIdSelector =
                    '.vergeml-clone-taxonomy[id=' +
                    taxonomyId +
                    '], .vergeml-taxonomy[id=' +
                    taxonomyId +
                    '], .wpuxss-non-eml-taxonomy[id=' +
                    taxonomyId +
                    ']';

                if ($(sameIdSelector).length > 1 || -1 !== $.inArray(taxonomyId, reservedNames)) {
                    isValid = false;
                    errorTitle = vergeml.l10n.tax_error_duplicate_title;
                    errorText = vergeml.l10n.tax_error_duplicate_text;
                }
            }
        });

        if (!isValid) {
            vergemlAlertDialog(
                errorTitle,
                errorText,
                vergeml.l10n.okay,
                'button button-primary',
            ).done(function () {
                $('.vergeml-clone-taxonomy, .vergeml-taxonomy-name').trigger('focus');
                return false;
            });
        }

        return isValid;
    });

    $(document).on('click', '.eml-button-synchronize-terms', function (event) {
        var $button = $(event.target);

        if ($button.hasClass('disabled')) {
            event.preventDefault();
            return false;
        }

        vergemlConfirmDialog(
            vergeml.l10n.sync_warning_title,
            vergeml.l10n.sync_warning_text,
            vergeml.l10n.sync_warning_yes,
            vergeml.l10n.sync_warning_no,
            'button button-primary',
        )
            .done(function () {
                var postType = $button.attr('data-post-type');
                var taxonomy = $button.attr('data-taxonomy');

                vergemlFullscreenSpinnerStart(vergeml.l10n.in_progress_sync_text);
                $.post(
                    ajaxurl,
                    {
                        nonce: vergeml.l10n.bulk_edit_nonce,
                        action: 'vergeml-synchronize-terms',
                        post_type: postType,
                        taxonomy: taxonomy,
                    },
                    function () {
                        vergemlFullscreenSpinnerStop();
                    },
                );
            })
            .fail(function () {
                return false;
            });
    });

    $(document).on('click', 'input[readonly], .disabled .submit input.button', function (event) {
        event.preventDefault();
    });
})(jQuery, _);
