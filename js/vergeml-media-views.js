/*
 *  Media view extensions.
 *
 *  Readable source of the plugin's extensions to the core wp.media views,
 *  forked from Enhanced Media Library 2.9.4 (GPLv2 or later). This is the file
 *  that ships and the file to edit: there is no build step and no minified
 *  counterpart.
 *
 *  Structure worth knowing before editing: this file patches core wp.media
 *  views in three different ways, and they are not equally safe.
 *
 *    core.<Name>.<method>.apply(this, arguments)   wraps core, safe
 *    media.view.X = media.view.Y.extend({...})     adds a new view, safe
 *    _.extend(media.view.X.prototype, {...})       may REPLACE core outright
 *
 *  The replacements are the liability: they discard core's implementation, so
 *  they silently fall behind whenever WordPress changes it. That is what broke
 *  the toolbar in WordPress 7.0, and why createToolbar below no longer renders
 *  core's filters-heading. Prefer wrapping. See docs/architecture.md.
 */

window.wp = window.wp || {};
window.vergeml = window.vergeml || { l10n: {} };

(function ($, _) {
    var media = wp.media,
        l10n = media.view.l10n,
        mediaTrash = media.view.settings.mediaTrash,
        core = {},
        // the AttachmentFilters.All instance, once a browser toolbar exists
        allFilters = null;

    _.extend(vergeml.l10n, vergeml_mvln);
    _.defaults(vergeml.l10n, { media_orderby: 'date', media_order: 'DESC' });

    // ---- media library controller: refresh the grid around uploads ----
    core.controllerLibrary = { activate: media.controller.Library.prototype.activate };

    _.extend(media.controller.Library.prototype, {
        activate: function () {
            core.controllerLibrary.activate.apply(this, arguments);
            wp.Uploader.queue.on('add', this.beforeUpload, this);
            wp.Uploader.queue.on('reset', this.afterUpload, this);
        },
        beforeUpload: function () {
            if (1 == wp.Uploader.queue.length) {
                $('.attachment-filters:has(option[value!="all"]:selected)').val('all').change();
            }
        },
        afterUpload: function () {
            var library = this.get('library'),
                selection = this.get('selection');

            if ('menuOrder' === library.props.get('orderby')) {
                library.saveMenuOrder();
            }

            library.reset(library.models);
            selection.trigger('selection:unsingle', selection.model, selection);
            selection.trigger('selection:single', selection.model, selection);
        },
        uploading: function (attachment) {
            var content = this.frame.content,
                selection = this.get('selection');

            if ('upload' === content.mode()) {
                this.frame.content.mode('browse');
            }

            if (this.get('autoSelect')) {
                if (1 == wp.Uploader.queue.length && selection.length) {
                    selection.reset();
                }

                selection.add(attachment);
                selection.trigger('selection:unsingle', selection.model, selection);
                selection.trigger('selection:single', selection.model, selection);
            }
        },
    });

    _.extend(media.controller.Library.prototype.defaults, {
        idealColumnWidth: vergeml.l10n.ideal_column_width || 170,
    });

    _.extend(media.view.Attachments.prototype.defaults, {
        idealColumnWidth: $(window).width() < 640 ? 135 : vergeml.l10n.ideal_column_width || 150,
    });

    // ---- attachment compat fields (taxonomy checkboxes in the details panel) ----
    var compatEvents = { 'click input[type=checkbox]': 'preSave' };

    _.extend(compatEvents, media.view.AttachmentCompat.prototype.events);

    core.AttachmentCompat = { postSave: media.view.AttachmentCompat.prototype.postSave };

    _.extend(media.view.AttachmentCompat.prototype, {
        events: compatEvents,
        preSave: function () {
            var spinner,
                checkboxes = $('input[type=checkbox]', this.$el);

            if (this.controller.isModeActive('eml-grid')) {
                spinner = this.controller.browserView.toolbar.get('spinner');
            }

            checkboxes.prop('readonly', true);

            if (spinner) {
                spinner.show();
            }

            this.noRender = true;
            this.rendered = false;
            media.model.Query.cleanQueries();
        },
        postSave: function (succeeded) {
            var messageView,
                messageKey,
                spinner,
                frameToolbar,
                checkboxes = $('input[type=checkbox]', this.$el);

            core.AttachmentCompat.postSave.apply(this, arguments);

            if (this.controller.isModeActive('eml-grid')) {
                spinner = this.controller.browserView.toolbar.get('spinner');
            }

            checkboxes.prop('readonly', false);

            if (spinner) {
                spinner.hide();
            }

            if ('edit-attachment' !== this.controller._state) {
                frameToolbar = this.controller.toolbar.get();
            }

            if (frameToolbar) {
                messageKey = succeeded ? 'emlAttachmentSuccess' : 'emlAttachmentError';
                messageView = frameToolbar.get(messageKey);
                messageView.$el.fadeIn(200);
                setTimeout(function () {
                    messageView.$el.fadeOut(100);
                }, 800);
            }
        },
        render: function () {
            var compat = this.model.get('compat'),
                $element = this.$el,
                termCounts = this.model.get('tcount');

            if (compat && compat.item) {
                _.each(termCounts, function (count, termId) {
                    var option = $('.eml-taxonomy-filters option[value="' + termId + '"]'),
                        text = option.text();

                    text = text.replace(/\(.*?\)/, '(' + count + ')');
                    option.text(text);
                });

                if (this.noRender) {
                    return this;
                }

                this.views.detach();
                this.$el.html(compat.item);
                this.views.render();

                if (
                    this.controller.isModeActive('select') &&
                    'edit-attachment' !== this.controller._state
                ) {
                    $.each(vergeml.l10n.compat_taxonomies_to_hide, function (index, taxonomy) {
                        $element.find('.compat-field-' + taxonomy).remove();
                    });

                    if (!this.$el.find('.compat-attachment-fields tbody').children().length) {
                        this.$el.find('.media-types-required-info').hide();
                    }
                }

                $.each(vergeml.l10n.compat_taxonomies, function (index, taxonomy) {
                    $element.find('.compat-field-' + taxonomy + ' .label').addClass('eml-tax-label');
                    $element.find('.compat-field-' + taxonomy + ' .field').addClass('eml-tax-field');
                });

                return this;
            }
        },
    });

    // ---- attachment filters ----
    _.extend(media.view.AttachmentFilters.prototype, {
        change: function () {
            var filter = this.filters[this.el.value],
                selection = this.controller.state().get('selection'),
                resetButton = this.controller.content.get().toolbar.get('resetFilterButton'),
                filterCount = $('.attachment-filters').length,
                resetFilterCount = $('.attachment-filters')
                    .map(function () {
                        return this.value;
                    })
                    .get()
                    .filter(function (value) {
                        return 'all' === value;
                    }).length;

            if (filter) {
                this.model.set(filter.props);
            }

            if (filter && selection && selection.length && 1 !== wp.Uploader.queue.length) {
                selection.reset();
            }

            if (filter && mediaTrash && !_.isUndefined(this.controller.toolbar)) {
                this.controller.toolbar
                    .get()
                    .$('.media-selection')
                    .toggleClass('trash', 'trash' === filter.props.status);
            }

            if (!_.isUndefined(resetButton)) {
                resetButton.model.set('disabled', filterCount === resetFilterCount);
            }
        },
        select: function () {
            var model = this.model,
                selectedKey = 'all',
                modelProps = model.toJSON();

            modelProps = _.omit(modelProps, 'orderby', 'order');

            _.find(this.filters, function (filter, key) {
                var filterProps = _.omit(filter.props, 'orderby', 'order');

                if (
                    _.all(filterProps, function (value, prop) {
                        return value === (_.isUndefined(modelProps[prop]) ? null : modelProps[prop]);
                    })
                ) {
                    selectedKey = key;
                    return selectedKey;
                }
            });

            this.$el.val(selectedKey);
        },
    });

    core.AttachmentFilters = {
        All: { createFilters: media.view.AttachmentFilters.All.prototype.createFilters },
        Uploaded: {
            createFilters: media.view.AttachmentFilters.Uploaded.prototype.createFilters,
        },
    };

    _.extend(media.view.AttachmentFilters.All.prototype, {
        createFilters: function () {
            var uncategorizedProps,
                taxonomySlugs = _.intersection(
                    _.keys(vergeml.l10n.taxonomies),
                    vergeml.l10n.filter_taxonomies,
                );

            core.AttachmentFilters.All.createFilters.apply(this, arguments);

            _.each(this.filters, function (filter) {
                filter.props.uncategorized = null;
                filter.props.orderby = vergeml.l10n.media_orderby;
                filter.props.order = vergeml.l10n.media_order;
            });

            this.filters.uncategorized = {
                text: vergeml.l10n.uncategorized,
                props: {
                    uploadedTo: null,
                    uncategorized: true,
                    status: null,
                    type: null,
                    orderby: vergeml.l10n.media_orderby,
                    order: vergeml.l10n.media_order,
                },
                priority: 60,
            };

            uncategorizedProps = this.filters.uncategorized.props;

            _.each(taxonomySlugs, function (taxonomy) {
                uncategorizedProps[taxonomy] = null;
            });

            if (
                mediaTrash &&
                (this.controller.isModeActive('grid') || this.controller.isModeActive('eml-grid'))
            ) {
                this.filters.trash = {
                    text: l10n.trash,
                    props: {
                        uploadedTo: null,
                        status: 'trash',
                        type: null,
                        orderby: 'date',
                        order: 'DESC',
                    },
                    priority: 70,
                };
            }
        },
    });

    _.extend(media.view.AttachmentFilters.Uploaded.prototype, {
        createFilters: function () {
            core.AttachmentFilters.Uploaded.createFilters.apply(this, arguments);

            _.each(this.filters, function (filter) {
                filter.props.orderby = vergeml.l10n.media_orderby;
                filter.props.order = vergeml.l10n.media_order;
            });
        },
    });

    media.view.AttachmentFilters.Taxonomy = media.view.AttachmentFilters.extend({
        id: function () {
            return 'media-attachment-' + this.options.taxonomy + '-filters';
        },
        className: function () {
            return (
                'attachment-filters eml-taxonomy-filters attachment-' +
                this.options.taxonomy +
                '-filter'
            );
        },
        createFilters: function () {
            var filters = {},
                view = this;

            _.each(view.options.termList || {}, function (term, index) {
                var termId = term.term_id,
                    text = _.escape($('<div/>').html(term.term_row).text()); // the filter option is rendered as HTML: a name decoded here must not become markup

                filters[termId] = {
                    text: text,
                    props: {
                        uncategorized: null,
                        orderby: vergeml.l10n.media_orderby,
                        order: vergeml.l10n.media_order,
                    },
                    priority: index + 4,
                };
                filters[termId].props[view.options.taxonomy] = termId;
            });

            filters.all = {
                text: vergeml.l10n.filter_by + ' ' + view.options.pluralName,
                props: {
                    uncategorized: null,
                    orderby: vergeml.l10n.media_orderby,
                    order: vergeml.l10n.media_order,
                },
                priority: 1,
            };
            filters.all.props[view.options.taxonomy] = null;

            filters.in = {
                text: '&#8212; ' + vergeml.l10n.in + ' ' + view.options.pluralName + ' &#8212;',
                props: {
                    uncategorized: null,
                    orderby: vergeml.l10n.media_orderby,
                    order: vergeml.l10n.media_order,
                },
                priority: 2,
            };
            filters.in.props[view.options.taxonomy] = 'in';

            filters.not_in = {
                text: '&#8212; ' + vergeml.l10n.not_in + ' ' + view.options.pluralName + ' &#8212;',
                props: {
                    uncategorized: null,
                    orderby: vergeml.l10n.media_orderby,
                    order: vergeml.l10n.media_order,
                },
                priority: 3,
            };
            filters.not_in.props[view.options.taxonomy] = 'not_in';

            this.filters = filters;
        },
    });

    media.view.AttachmentFilters.Authors = media.view.AttachmentFilters.extend({
        id: 'author-filter',
        createFilters: function () {
            var filters = {};

            _.each(this.options.users || {}, function (user, index) {
                var userId = user.user_id,
                    userName = user.user_name;

                filters[userId] = {
                    text: userName,
                    props: {
                        author: userId,
                        orderby: vergeml.l10n.media_orderby,
                        order: vergeml.l10n.media_order,
                    },
                    priority: index + 2,
                };
            });

            filters.all = {
                text: vergeml.l10n.in + ' ' + vergeml.l10n.authors,
                props: {
                    author: null,
                    orderby: vergeml.l10n.media_orderby,
                    order: vergeml.l10n.media_order,
                },
                priority: 1,
            };

            this.filters = filters;
        },
    });

    media.view.Button.resetFilters = media.view.Button.extend({
        id: 'reset-all-filters',
        initialize: function () {
            media.view.Button.prototype.initialize.apply(this, arguments);
            this.controller.on(
                'select:activate select:deactivate',
                this.toogleResetFilters,
                this,
            );
        },
        click: function (event) {
            if ('#' === this.attributes.href) {
                event.preventDefault();
            }

            $('.attachment-filters:has(option[value!="all"]:selected)').each(function () {
                $(this).val('all').change();
            });
        },
        toogleResetFilters: function () {
            this.$el.toggleClass('hidden');
        },
    });

    media.view.emlAttachmentDetailsEditMessage = media.View.extend({
        tagName: 'div',
        id: 'eml-save-changes-message',
        initialize: function () {
            this.text = this.options.text;
            this.class = this.options.class;
        },
        render: function () {
            this.$el.addClass(this.class);
            this.$el.empty().append( $( '<p></p>' ).append( $( '<strong></strong>' ).text( this.text ) ) );
            return this;
        },
    });

    _.extend(media.view.Attachment.Details.prototype, {
        deleteAttachment: function (event) {
            event.preventDefault();

            if (window.confirm(l10n.warnDelete)) {
                this.model.destroy();

                if (this.controller.modal) {
                    this.controller.modal.focusManager.focus();
                }
            }
        },
    });

    // ---- attachments browser: toolbar, sidebar and layout ----
    core.AttachmentsBrowser = {
        initialize: media.view.AttachmentsBrowser.prototype.initialize,
        createToolbar: media.view.AttachmentsBrowser.prototype.createToolbar,
        createSidebar: media.view.AttachmentsBrowser.prototype.createSidebar,
        createSingle: media.view.AttachmentsBrowser.prototype.createSingle,
        disposeSingle: media.view.AttachmentsBrowser.prototype.disposeSingle,
    };

    _.extend(media.view.AttachmentsBrowser.prototype, {
        initialize: function () {
            var filters, uploaded;

            core.AttachmentsBrowser.initialize.apply(this, arguments);

            if (
                this.controller.isModeActive('select') ||
                this.controller.isModeActive('eml-grid')
            ) {
                if (this.controller.isModeActive('eml-grid')) {
                    this.sidebar.$el.width(vergeml.l10n.grid_sidebar_width);
                    this.on('ready', this.fixLayout, this);
                    $(document).on(
                        'click',
                        '.notice-dismiss',
                        _.debounce(_.bind(this.fixLayout, this), 250),
                    );
                }

                if (this.controller.isModeActive('select')) {
                    this.on('ready', this.fixLayout, this);
                    $(document).on('click', '.acf-expand-details', _.bind(this.fixLayout, this));

                    if (parseInt(vergeml.l10n.filter_uploaded) && 'post-php' === window.adminpage) {
                        filters = this.toolbar.get('filters');
                        uploaded = filters.filters.uploaded;
                        filters.model.set(uploaded.props);
                    }
                }

                this.$window = $(window);
                this.$window.on('resize', _.debounce(_.bind(this.fixLayout, this), 15));
                this.controller.on(
                    'sidebar:on',
                    _.debounce(
                        _.bind(this.scrollAttachmentIntoView, this, {
                            block: 'center',
                        }),
                        15,
                    ),
                );
            }
        },
        fixLayout: function () {
            if (
                this.controller.isModeActive('select') ||
                this.controller.isModeActive('eml-grid')
            ) {
                var attachmentsView = this.attachmentsWrapper || this.attachments,
                    toolbar = this.toolbar,
                    notices = $(
                        '.eml-media-css .updated:visible, .eml-media-css .error:visible, .eml-media-css .notice:visible, .eml-media-css .notice-error:visible, .eml-media-css .notice-warning:visible, .eml-media-css .notice-success:visible, .eml-media-css .notice-info:visible',
                    ),
                    updateNag = $('.eml-media-css .update-nag'),
                    noticesHeight = 0;

                this.sidebarWidth =
                    $(window).width() < 640 ? 0 : parseInt(this.sidebar.$el.outerWidth());

                if (updateNag.length) {
                    updateNag.css('margin-left', '15px');
                    this.$el.closest('.wrap').css('top', updateNag.outerHeight() + 25 + 'px');
                }

                if (this.controller.isModeActive('select')) {
                    attachmentsView.$el.css('top', toolbar.$el.height() + 10 + 'px');
                    attachmentsView.$el.css('right', this.sidebarWidth.toString() + 'px');
                    this.uploader.$el.css('top', toolbar.$el.height() + 10 + 'px');
                    this.$el.find('.eml-loader').css('top', toolbar.$el.height() + 10 + 'px');
                    toolbar.$el
                        .find('.media-toolbar-secondary')
                        .prepend(toolbar.$el.find('.instructions'));
                }

                if (this.controller.isModeActive('eml-grid')) {
                    if (!_.isUndefined(notices)) {
                        notices.each(function () {
                            if (!$(this).hasClass('update-nag')) {
                                noticesHeight += $(this).outerHeight(true);
                            }
                        });
                    }

                    this.$el.css('top', toolbar.$el.outerHeight() + noticesHeight + 15 + 'px');
                    toolbar.$el.css('top', -toolbar.$el.outerHeight() - 25 + 'px');
                    attachmentsView.$el.css('top', 0);
                }
            }
        },
        scrollAttachmentIntoView: function (scrollOptions) {
            // The selected attachment's tile is not always in the DOM:
            // a fresh upload lands selected before its tile renders, and
            // a filtered grid may not show it at all. Inherited code
            // assumed [0] exists and threw on every upload.
            var selection = this.controller.state().get('selection');

            if (1 == selection.length) {
                var el = $('li.attachment[data-id="' + selection.single().get('id') + '"]')[0];

                if (el && el.scrollIntoView) {
                    el.scrollIntoView(scrollOptions);
                }
            }
        },
        createToolbar: function () {
            var browser = this,
                order = 1,
                show = vergeml.l10n.filters_to_show || [],
                isEmlGrid = this.controller.isModeActive('eml-grid'),
                borrowedGridMode,
                frameToolbar,
                wantsFilters =
                    -1 !== $.inArray(this.options.filters, ['uploaded', 'all']) ||
                    (parseInt(vergeml.l10n.force_filters, 10) &&
                        !this.controller.isModeActive('eml-bulk-edit') &&
                        'gallery-edit' !== this.controller._state &&
                        'playlist-edit' !== this.controller._state &&
                        'video-playlist-edit' !== this.controller._state) ||
                    'customize' === vergeml.l10n.current_screen ||
                    'widgets' === vergeml.l10n.current_screen;

            /*
             *  Let core build the toolbar, then adjust what it produced.
             *
             *  Upstream forked this entire method instead. That is why the
             *  WordPress 7.0 grid change broke the layout, and why core's
             *  filters-heading was missing: anything core adds after the
             *  fork is taken never appears at all. Wrapping means core's
             *  additions arrive on their own.
             *
             *  This frame runs as 'eml-grid' rather than core's 'grid', so
             *  core's grid branch is skipped and its Bulk select and Delete
             *  selected buttons are never built. Claim 'grid' for the
             *  duration of the call so core builds them, with its own trash
             *  and delete handling, then hand the mode straight back.
             *
             *  Claiming it permanently instead pulls in core's grid
             *  stylesheet, which hides the individual filter labels behind
             *  the screen-reader heading. That is core's design but not this
             *  plugin's, and it collapses the filters into stacked columns.
             *  Borrowing the mode gets the buttons without the restyle.
             */
            borrowedGridMode = isEmlGrid && !this.controller.isModeActive('grid');

            if (borrowedGridMode) {
                this.controller.activateMode('grid');
            }

            core.AttachmentsBrowser.createToolbar.apply(this, arguments);

            if (borrowedGridMode) {
                this.controller.deactivateMode('grid');
            }

            if (!this.toolbar) {
                return;
            }

            allFilters = this.toolbar.get('filters');

            // core only dresses the toolbar for its own grid mode
            if (isEmlGrid) {
                this.toolbar.$el.addClass('media-toolbar wp-filter');

                if (!this.toolbar.get('libraryViewSwitcher')) {
                    this.toolbar.set(
                        'libraryViewSwitcher',
                        new (media.View.extend({
                            className: 'view-switch media-grid-view-switch',
                            template: media.template('media-library-view-switcher'),
                        }))({ controller: this.controller, priority: -90 }).render(),
                    );
                }
            }

            // ---- filter by type ----
            if (!wantsFilters || (isEmlGrid && -1 === $.inArray('types', show))) {
                this.toolbar.unset('filtersLabel');
                this.toolbar.unset('filters');
                allFilters = null;
            } else if (!allFilters) {
                // force_filters is on somewhere core declined to build them
                this.toolbar.set(
                    'filtersLabel',
                    new media.view.Label({
                        value: l10n.filterByType,
                        attributes: { for: 'media-attachment-filters' },
                        priority: -80,
                    }).render(),
                );
                allFilters =
                    'uploaded' === this.options.filters
                        ? new media.view.AttachmentFilters.Uploaded({
                              controller: this.controller,
                              model: this.collection.props,
                              priority: -80,
                          })
                        : new media.view.AttachmentFilters.All({
                              controller: this.controller,
                              model: this.collection.props,
                              priority: -80,
                          });
                this.toolbar.set('filters', allFilters.render());
            }

            /*
             *  ---- filter by date ----
             *
             *  In grid mode core files the label and the select under the
             *  same 'dateFilter' key, so the label is dropped and the date
             *  select renders unlabelled. Rebuild the pair, label first, so
             *  it reads in order and the two land in the same grid column.
             */
            this.toolbar.unset('dateFilterLabel');
            this.toolbar.unset('dateFilter');

            if (
                wantsFilters &&
                -1 !== $.inArray('dates', show) &&
                media.view.settings.months.length
            ) {
                this.toolbar.set(
                    'dateFilterLabel',
                    new media.view.Label({
                        value: l10n.filterByDate,
                        attributes: { for: 'media-attachment-date-filters' },
                        priority: -75,
                    }).render(),
                );
                this.toolbar.set(
                    'dateFilter',
                    new media.view.DateFilter({
                        controller: this.controller,
                        model: this.collection.props,
                        priority: -75,
                    }).render(),
                );
            }

            /*
             *  Our filters sit between core's date filter at -75 and core's
             *  Bulk select at -70, so they read as one run and the button
             *  lands after them rather than wedged between two dropdowns.
             *  Fractional steps because there are only four integers in
             *  that gap and a site can assign more taxonomies than that.
             */
            if (wantsFilters) {
                // ---- filter by author ----
                if (vergeml.l10n.users.length > 1 && -1 !== $.inArray('authors', show)) {
                    this.toolbar.set(
                        'authorFilterLabel',
                        new media.view.Label({
                            value: vergeml.l10n.filter_by + ' ' + vergeml.l10n.author,
                            attributes: { for: 'author-filter' },
                            priority: -75 + order++ * 0.1,
                        }).render(),
                    );
                    this.toolbar.set(
                        'author-filter',
                        new media.view.AttachmentFilters.Authors({
                            controller: this.controller,
                            model: this.collection.props,
                            priority: -75 + order++ * 0.1,
                            users: vergeml.l10n.users,
                        }).render(),
                    );
                }

                // ---- one filter per taxonomy assigned to the library ----
                if (-1 !== $.inArray('taxonomies', show)) {
                    $.each(vergeml.l10n.taxonomies, function (taxonomy, data) {
                        if (
                            -1 === _.indexOf(vergeml.l10n.filter_taxonomies, taxonomy) ||
                            !data.term_list.length
                        ) {
                            return;
                        }

                        browser.toolbar.set(
                            taxonomy + 'FilterLabel',
                            new media.view.Label({
                                value: vergeml.l10n.filter_by + ' ' + data.plural_name,
                                attributes: {
                                    for: 'media-attachment-' + taxonomy + '-filters',
                                },
                                priority: -75 + order++ * 0.1,
                            }).render(),
                        );
                        browser.toolbar.set(
                            taxonomy + '-filter',
                            new media.view.AttachmentFilters.Taxonomy({
                                controller: browser.controller,
                                model: browser.collection.props,
                                priority: -75 + order++ * 0.1,
                                taxonomy: taxonomy,
                                termList: data.term_list,
                                singularName: data.singular_name,
                                pluralName: data.plural_name,
                            }).render(),
                        );
                    });
                }

                // ---- reset, once there is more than one filter to reset ----
                if (this.toolbar.$el.find('.attachment-filters').length > 1) {
                    this.toolbar.set(
                        'resetFilterButton',
                        new media.view.Button.resetFilters({
                            controller: this.controller,
                            text: vergeml.l10n.reset_filters,
                            disabled: true,
                            priority: -75 + order++ * 0.1,
                        }).render(),
                    );
                }
            }

            /*
             *  Core files search under a positive priority, which puts it in
             *  the primary toolbar. This plugin keeps it beside the filters
             *  in the secondary one, so re-file it with a negative priority.
             */
            if (this.options.search && this.toolbar.get('search')) {
                this.toolbar.unset('searchLabel');
                this.toolbar.unset('search');
                this.toolbar.set(
                    'searchLabel',
                    new media.view.Label({
                        value: l10n.searchMediaLabel,
                        attributes: { for: 'media-search-input' },
                        priority: -30,
                    }).render(),
                );
                this.toolbar.set(
                    'search',
                    new media.view.Search({
                        controller: this.controller,
                        model: this.collection.props,
                        priority: -30,
                    }).render(),
                );
            }

            frameToolbar = this.controller.toolbar && this.controller.toolbar.get();

            // ---- bulk buttons live on the frame toolbar ----
            // (The original's "Select all", shown only to its paid edition, is gone:
            // a directory plugin may not hold features back. WordPress.org Guidelines 5-6.)
            if (isEmlGrid && frameToolbar) {
                frameToolbar.set(
                    'emlDeselectButton',
                    new media.view.emlDeselectButton({
                        filters: allFilters,
                        disabled: true,
                        text: vergeml.l10n.deselect,
                        controller: this.controller,
                        priority: -70,
                    }).render(),
                );
                frameToolbar.set(
                    'emlDeleteSelectedButton',
                    new media.view.emlDeleteSelectedButton({
                        filters: allFilters,
                        style: 'primary',
                        disabled: true,
                        text: mediaTrash ? l10n.trashSelected : l10n.deletePermanently,
                        controller: this.controller,
                        priority: -60,
                    }).render(),
                );

                if (mediaTrash) {
                    frameToolbar.set(
                        'emlDeleteSelectedPermanentlyButton',
                        new media.view.emlDeleteSelectedPermanentlyButton({
                            filters: allFilters,
                            style: 'primary',
                            disabled: true,
                            text: l10n.deletePermanently,
                            controller: this.controller,
                            priority: -50,
                        }).render(),
                    );
                }
            }

            // ---- save feedback, also on the frame toolbar ----
            if ('edit-attachment' !== this.controller._state && frameToolbar) {
                frameToolbar.set(
                    'emlAttachmentSuccess',
                    new media.view.emlAttachmentDetailsEditMessage({
                        text: vergeml.l10n.saveButton_success,
                        class: 'updated',
                        controller: this.controller,
                        priority: 200,
                    }).render(),
                );
                frameToolbar.set(
                    'emlAttachmentError',
                    new media.view.emlAttachmentDetailsEditMessage({
                        text: vergeml.l10n.saveButton_failure,
                        class: 'error',
                        controller: this.controller,
                        priority: 220,
                    }).render(),
                );
            }
        },
        createSidebar: function () {
            core.AttachmentsBrowser.createSidebar.apply(this, arguments);

            if (this.controller.isModeActive('eml-grid')) {
                this.toggleSidebar();
            }
        },
        toggleSidebar: function () {
            var selection = this.controller.state().get('selection'),
                attachmentsView = this.attachmentsWrapper || this.attachments,
                sidebarWidth = this.sidebarWidth || 0;

            if (selection.length) {
                this.sidebar.$el.removeClass('hidden');
                attachmentsView.$el.css('right', sidebarWidth.toString() + 'px');
                sidebarWidth += 10;
                this.uploader.$el.css('right', sidebarWidth.toString() + 'px');
                this.controller.trigger('sidebar:on');
            } else {
                this.sidebar.$el.addClass('hidden');
                attachmentsView.$el.css('right', 0);
                this.uploader.$el.css('right', '10px');
                this.controller.trigger('sidebar:off');
            }
        },
        createSingle: function () {
            core.AttachmentsBrowser.createSingle.apply(this, arguments);

            if (this.controller.isModeActive('eml-grid')) {
                var sidebar = this.sidebar,
                    attachment = this.options.selection.single();

                if ('trash' !== this.options.selection.at(0).get('status')) {
                    sidebar.set(
                        'details',
                        new wp.media.view.emlGridAttachmentDetails({
                            controller: this.controller,
                            model: attachment,
                            priority: 80,
                        }),
                    );
                }

                this.toggleSidebar();
            }
        },
        disposeSingle: function () {
            core.AttachmentsBrowser.disposeSingle.apply(this, arguments);

            if (this.controller.isModeActive('eml-grid')) {
                this.toggleSidebar();
            }
        },
        updateContent: function () {
            var emptyState,
                browser = this;

            emptyState =
                this.controller.isModeActive('grid') || this.controller.isModeActive('eml-grid')
                    ? browser.attachmentsNoResults
                    : browser.uploader;

            if (this.collection.length) {
                emptyState.$el.addClass('hidden');
                browser.toolbar.get('spinner').hide();
            } else {
                this.toolbar.get('spinner').show();
                this.dfd = this.collection.more().done(function () {
                    if (browser.collection.length) {
                        emptyState.$el.addClass('hidden');
                    } else {
                        emptyState.$el.removeClass('hidden');
                    }

                    browser.toolbar.get('spinner').hide();
                });
            }
        },
        createUploader: function () {
            this.uploader = new media.view.UploaderInline({
                controller: this.controller,
                status: false,
                message:
                    this.controller.isModeActive('grid') || this.controller.isModeActive('eml-grid')
                        ? ''
                        : l10n.noItemsFound,
                canClose:
                    this.controller.isModeActive('grid') || this.controller.isModeActive('eml-grid'),
            });
            this.uploader.$el.addClass('hidden');
            this.views.add(this.uploader);
        },
        createAttachments: function () {
            this.attachments = new media.view.Attachments({
                controller: this.controller,
                collection: this.collection,
                selection: this.options.selection,
                model: this.model,
                sortable: this.options.sortable,
                scrollElement: this.options.scrollElement,
                idealColumnWidth: this.options.idealColumnWidth,
                AttachmentView: this.options.AttachmentView,
            });
            this.controller.on(
                'attachment:keydown:arrow',
                _.bind(this.attachments.arrowEvent, this.attachments),
            );
            this.controller.on(
                'attachment:details:shift-tab',
                _.bind(this.attachments.restoreFocus, this.attachments),
            );

            if (this.attachmentsWrapper) {
                this.views.add('.attachments-wrapper', this.attachments);
            } else {
                this.views.add(this.attachments);
            }

            if (this.controller.isModeActive('grid') || this.controller.isModeActive('eml-grid')) {
                this.attachmentsNoResults = new media.View({
                    controller: this.controller,
                    tagName: 'p',
                });
                this.attachmentsNoResults.$el.addClass('hidden no-media');
                this.attachmentsNoResults.$el.html(l10n.noMedia);
                this.views.add(this.attachmentsNoResults);
            }
        },
    });

    // ---- search box: debounce the query by how much has been typed ----
    var searchEvents = { input: 'onChange' };

    _.extend(media.view.Search.prototype, {
        searchTerm: '',
        prevTerm: '',
        searchDelay: 1000,
        timer: 0,
        events: searchEvents,
        onChange: function (event) {
            clearTimeout(this.timer);
            this.searchTerm = event.target.value.trim();
            this.searchDelay = 2000 / this.searchTerm.length;

            var library = this.controller.state().get('library');

            if (this.searchTerm) {
                if (this.searchTerm.length > 1) {
                    var refinesEmptyResult =
                        !library.length &&
                        this.prevTerm.length &&
                        this.searchTerm.startsWith(this.prevTerm);

                    if (!refinesEmptyResult) {
                        this.timer = setTimeout(_.bind(this.runSearch, this), this.searchDelay);
                    }
                }
            } else {
                this.prevTerm = this.searchTerm;
                this.model.unset('search');
            }
        },
        runSearch: function () {
            this.prevTerm = this.searchTerm;
            this.model.set('search', this.searchTerm);
        },
    });

    $(document).ready(function () {
        $(document).on(
            'mousedown',
            '.media-frame .attachments-browser .attachments li',
            function (event) {
                if (event.ctrlKey || event.shiftKey) {
                    event.preventDefault();
                }
            },
        );
    });

    $('body').addClass('eml-media-css');
})(jQuery, _);
