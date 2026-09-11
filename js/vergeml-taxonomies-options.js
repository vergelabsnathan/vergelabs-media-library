/*
 *  Media Taxonomies settings screen.
 *
 *  Recovered from eml-taxonomies-options.min.js, which upstream shipped
 *  minified with no readable counterpart. Formatting restored with Prettier,
 *  outer-closure identifiers renamed through a scope-aware pass. No behaviour
 *  changed in the recovery.
 *
 *  This file only touches its own settings screen; it does not patch any core
 *  view.
 */

((window.vergeml = window.vergeml || { l10n: {} }),
    (function ($, _) {
        function n(e) {
            return !!new RegExp('^(?=.{3,32}$)[a-z0-9_]*[a-z][a-z0-9_]*$').test(e);
        }
        function m(e) {
            const t = {
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
            return (e = e
                .split('')
                .map((e) => t[e] || e)
                .join(''));
        }
        function s(t, n) {
            (n.attr('id', t),
                (('' !== t &&
                    $(
                        '.vergeml-clone-taxonomy[id=' +
                            t +
                            '], .vergeml-taxonomy[id=' +
                            t +
                            '], .wpuxss-non-eml-taxonomy[id=' +
                            t +
                            ']',
                    ).length > 1) ||
                    -1 !==
                        $.inArray(t, [
                            'link_category',
                            'post_format',
                            'wp_theme',
                            'wp_pattern_category',
                            'wp_template_part_area',
                        ])) &&
                    (n.attr('id', ''),
                    vergemlAlertDialog(
                        vergeml.l10n.tax_error_duplicate_title,
                        vergeml.l10n.tax_error_duplicate_text,
                        vergeml.l10n.okay,
                        'button button-primary',
                    ).done(function () {
                        return !1;
                    })));
        }
        function o(t, n) {
            ($.each(
                {
                    assigned: '.vergeml-assigned',
                    eml_media: '.vergeml-eml_media',
                    create_taxonomy: '.vergeml-create_taxonomy',
                },
                function (e, m) {
                    n.find(m).attr('name', 'vergeml_taxonomies[' + t + '][' + e + ']');
                },
            ),
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
                    function (m, s) {
                        m === s
                            ? n
                                  .find('.vergeml-' + s)
                                  .attr('name', 'vergeml_taxonomies[' + t + '][' + s + ']')
                            : $.each(s, function (e, s) {
                                  n.find('.vergeml-' + s).attr(
                                      'name',
                                      'vergeml_taxonomies[' + t + '][' + m + '][' + s + ']',
                                  );
                              });
                    },
                ));
        }
        function l(t, n) {
            var m = {
                edit: '.vergeml-edit_item',
                view: '.vergeml-view_item',
                update: '.vergeml-update_item',
                add_new: '.vergeml-add_new_item',
                new: '.vergeml-new_item_name',
                parent: '.vergeml-parent_item',
            };
            '' !== t
                ? $.each(m, function (e, m) {
                      n.find(m).val(vergeml.l10n[e] + ' ' + t);
                  })
                : $.each(m, function (e, t) {
                      n.find(t).val('');
                  });
        }
        (_.extend(vergeml.l10n, vergeml_taxonomies_options_l10n_data),
            $(document).on('click', 'li .vergeml-button-remove', function () {
                var t = $(this).parent();
                return (
                    t.hasClass('vergeml-clone-taxonomy')
                        ? t.hide(300, function () {
                              $(this).remove();
                          })
                        : vergemlConfirmDialog(
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
                                  t.hide(300, function () {
                                      $(this).remove();
                                  });
                              })
                              .fail(function () {
                                  return !1;
                              }),
                    !1
                );
            }),
            $(document).on('click', '.vergeml-button-create-taxonomy', function () {
                return (
                    $('.vergeml-media-taxonomy-list')
                        .find('.vergeml-clone')
                        .clone()
                        .attr('class', 'vergeml-clone-taxonomy')
                        .appendTo('.vergeml-media-taxonomy-list')
                        .show(300),
                    !1
                );
            }),
            $(document).on('click', '.vergeml-button-edit', function () {
                return (
                    $(this).parent().find('.vergeml-taxonomy-edit').toggle(300),
                    $(this).html(function (e, t) {
                        return t == vergeml.l10n.edit + ' ↓'
                            ? vergeml.l10n.close + ' ↑'
                            : vergeml.l10n.edit + ' ↓';
                    }),
                    !1
                );
            }),
            $(document).on('blur', '.vergeml-clone-taxonomy .vergeml-taxonomy-name', function () {
                var t = $(this).val().toLowerCase(),
                    l = $(this).parents('.vergeml-clone-taxonomy'),
                    i = l.find('.vergeml-slug').val();
                return '' === t
                    ? (s('', l), void o('', l))
                    : ('' !== (t = t.replace(/[^a-z0-9_]/g, '')) &&
                          'year' == (t = m(t)) &&
                          (t = 'media_year'),
                      $(this).val(t),
                      '' === i && l.find('.vergeml-slug').val(t),
                      n(t)
                          ? (s(t, l), void o(t, l))
                          : (s('', l),
                            o('', l),
                            void vergemlAlertDialog(
                                vergeml.l10n.tax_error_wrong_taxname_title,
                                vergeml.l10n.tax_error_wrong_taxname,
                                vergeml.l10n.okay,
                                'button button-primary',
                            ).done(function () {
                                return !1;
                            })));
            }),
            $(document).on('blur', '.vergeml-slug', function () {
                var t = $(this).val().toLowerCase(),
                    n = $(this)
                        .parents('.vergeml-taxonomy-edit')
                        .find('.vergeml-taxonomy-name')
                        .val()
                        .toLowerCase();
                '' !== (t = t || n) &&
                    ((t = t.replace(/^[_-]+|[^a-z0-9_-]|[_-]+$/g, '').replace(/([_-])+/g, '$1')),
                    $(this).val(t),
                    '' === t &&
                        ($(this).val(n),
                        vergemlAlertDialog(
                            vergeml.l10n.tax_error_wrong_slug_title,
                            vergeml.l10n.tax_error_wrong_slug,
                            vergeml.l10n.okay,
                            'button button-primary',
                        ).done(function () {
                            return !1;
                        })));
            }),
            $(document).on('blur', '.vergeml-clone-taxonomy .vergeml-singular_name', function () {
                var t,
                    i = $(this)
                        .val()
                        .trim()
                        .replace(/(<([^>]+)>)/g, ''),
                    u = $(this).closest('.vergeml-clone-taxonomy'),
                    a = $(this).parents('.vergeml-taxonomy-edit'),
                    r = a.find('.vergeml-taxonomy-name'),
                    p = a.find('.vergeml-slug').val();
                if (($(this).val(i), '' !== i)) {
                    if (
                        (l(i, a),
                        (t = m(
                            (t = i
                                .toLowerCase()
                                .replace(/[ &-]+/g, '_')
                                .replace(/[^a-z0-9_]/g, '')),
                        )),
                        r.val(t),
                        '' === p &&
                            ((p = t
                                .replace(/^[_-]+|[^a-z0-9_-]|[_-]+$/g, '')
                                .replace(/([_-])+/g, '$1')),
                            a.find('.vergeml-slug').val(p)),
                        n(t))
                    )
                        return (s(t, u), void o(t, u));
                    (s('', u), o('', u));
                } else l('', a);
            }),
            $(document).on('blur', '.vergeml-taxonomy .vergeml-singular_name', function () {
                var t = $(this)
                        .val()
                        .trim()
                        .replace(/(<([^>]+)>)/g, ''),
                    n = $(this).parents('.vergeml-taxonomy-edit');
                ($(this).val(t), l(t, n));
            }),
            $(document).on(
                'blur',
                '.vergeml-clone-taxonomy .vergeml-name, .vergeml-taxonomy .vergeml-name',
                function () {
                    var t = $(this)
                            .val()
                            .trim()
                            .replace(/(<([^>]+)>)/g, ''),
                        n = $(this).parents('.vergeml-taxonomy-edit'),
                        m = $(this)
                            .closest('.vergeml-clone-taxonomy')
                            .find('.vergeml-taxonomy-label span');
                    ($(this).val(t),
                        (function (t, n, m) {
                            var s = { all: '.vergeml-all_items', search: '.vergeml-search_items' };
                            '' !== t
                                ? (n.find('.vergeml-menu_name').val(t),
                                  m.text(t),
                                  $.each(s, function (e, m) {
                                      n.find(m).val(vergeml.l10n[e] + ' ' + t);
                                  }))
                                : (n.find('.vergeml-menu_name').val(''),
                                  m.text(vergeml.l10n.tax_new),
                                  $.each(s, function (e, t) {
                                      n.find(t).val('');
                                  }));
                        })(t, n, m));
                },
            ),
            $('#vergeml-form-taxonomies').on('submit', function (t) {
                var n,
                    m,
                    s,
                    o,
                    l = [
                        'link_category',
                        'post_format',
                        'wp_theme',
                        'wp_pattern_category',
                        'wp_template_part_area',
                    ],
                    i = !0,
                    u = vergeml.l10n.tax_error_empty_fileds_title,
                    a = '';
                return (
                    $('.vergeml-clone-taxonomy, .vergeml-taxonomy').each(function (t) {
                        ((n = $(this).attr('id')),
                            (m = $('.vergeml-singular_name', this).val()),
                            (s = $('.vergeml-name', this).val()),
                            (o = $('.vergeml-slug', this).val()),
                            n
                                ? o
                                    ? m || s
                                        ? m
                                            ? s
                                                ? ($(
                                                      '.vergeml-clone-taxonomy[id=' +
                                                          n +
                                                          '], .vergeml-taxonomy[id=' +
                                                          n +
                                                          '], .wpuxss-non-eml-taxonomy[id=' +
                                                          n +
                                                          ']',
                                                  ).length > 1 ||
                                                      -1 !== $.inArray(n, l)) &&
                                                  ((i = !1),
                                                  (u = vergeml.l10n.tax_error_duplicate_title),
                                                  (a = vergeml.l10n.tax_error_duplicate_text))
                                                : ((i = !1),
                                                  (a = vergeml.l10n.tax_error_empty_plural))
                                            : ((i = !1),
                                              (a = vergeml.l10n.tax_error_empty_singular))
                                        : ((i = !1), (a = vergeml.l10n.tax_error_empty_both))
                                    : $('.vergeml-slug', this).val(n)
                                : ((i = !1),
                                  (a =
                                      '<p>' +
                                      vergeml.l10n.tax_error_empty_taxname +
                                      '</p><p>' +
                                      vergeml.l10n.tax_error_wrong_taxname +
                                      '</p>')));
                    }),
                    i ||
                        vergemlAlertDialog(u, a, vergeml.l10n.okay, 'button button-primary').done(
                            function () {
                                return (
                                    $('.vergeml-clone-taxonomy, .vergeml-taxonomy-name').trigger(
                                        'focus',
                                    ),
                                    !1
                                );
                            },
                        ),
                    i
                );
            }),
            $(document).on('click', '.eml-button-synchronize-terms', function (t) {
                var n, m, s;
                if ((n = $(t.target)).hasClass('disabled')) return (t.preventDefault(), !1);
                vergemlConfirmDialog(
                    vergeml.l10n.sync_warning_title,
                    vergeml.l10n.sync_warning_text,
                    vergeml.l10n.sync_warning_yes,
                    vergeml.l10n.sync_warning_no,
                    'button button-primary',
                )
                    .done(function () {
                        ((m = n.attr('data-post-type')),
                            (s = n.attr('data-taxonomy')),
                            vergemlFullscreenSpinnerStart(vergeml.l10n.in_progress_sync_text),
                            $.post(
                                ajaxurl,
                                {
                                    nonce: vergeml.l10n.bulk_edit_nonce,
                                    action: 'vergeml-synchronize-terms',
                                    post_type: m,
                                    taxonomy: s,
                                },
                                function (e) {
                                    vergemlFullscreenSpinnerStop();
                                },
                            ));
                    })
                    .fail(function () {
                        return !1;
                    });
            }),
            $(document).on(
                'click',
                'input[readonly], .disabled .submit input.button',
                function (e) {
                    e.preventDefault();
                },
            ));
    })(jQuery, _));
