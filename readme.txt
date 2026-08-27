=== VergeLabs Media Library ===
Contributors: vergelabsnathan
Tags: media library, media folders, media tags, media categories, mime types
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 3.3.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Folders for the media library, plus categories, tags and custom taxonomies. A maintained fork of Enhanced Media Library, repaired for WordPress 7.

## Description ##

**Handy for those who need to manage a lot of media files.**

VergeLabs Media Library is a maintained fork of [Enhanced Media Library](https://wordpress.org/plugins/enhanced-media-library/) by wpUXsolutions, which has had no release since July 2024. It repairs the media toolbar on WordPress 7.0 and clears the PHP 8 warnings. Everything else is the plugin you already know.

Based on Enhanced Media Library by wpUXsolutions, and licensed GPLv2 or later as the original is.

[Source and issue tracker](https://github.com/vergelabsnathan/vergelabs-media-library)


### Folders ###

A folder tree sits beside the media library. Drag files onto a folder to file them, drag folders into each other to rearrange, rename in place, colour them, and arrange them in whatever order you want. It is in the list view, in the grid, and in the media panel that opens when you insert an image into a post — which is where a media library is most used, and where a folder plugin most often is not.

**Your folders are ordinary media categories.** Nothing is kept in a private table of ours. That means your structure is readable by WordPress itself, by your theme, by WP-CLI and by any other plugin — and it is still there, intact and usable, if you ever remove this plugin. A folder plugin that stores your organisation somewhere only it can read is a folder plugin you cannot leave.

**A file can be in more than one folder.** Dragging a file moves it, exactly as it does on your desktop. Hold Ctrl while dropping and it is added to that folder as well -- the same photo in Products and in Autumn Campaign without a second copy of it existing, which is the thing single-folder plugins cannot do. If you want the one-folder promise kept absolutely, there is a setting that makes every drop a move.

**A folder can be a gallery.** Add the Folder gallery block in the block editor -- or the Folder gallery element in Elementor, Divi or WPBakery, or drop `[vergeml_gallery folder="12"]` anywhere shortcodes work -- pick a folder, and it shows every image in it. Grid or carousel, with a built-in lightbox if you want one. It stays a folder rather than becoming a list of files: put a new image in the folder and every page using that gallery has it, with nothing re-edited. WordPress's own gallery block freezes a list of images at the moment you insert it.

**Smart folders answer questions.** Above your folders sit five that nothing was ever filed into: Unused media, Missing alt text, Large files, Unattached, and This month. Each is a live view of the library. Unused and Large need one scan -- a click, a progress count, done -- which reads every post once to learn which files are genuinely referenced: embedded images, pasted URLs, featured images, galleries. "1,400 images have no alt text" and "2 GB of this library is unused" are the two numbers most worth knowing about a media library, and no folder plugin can tell you either.

**Uploads land where you are.** Upload while a folder is open and the files arrive filed into it -- no second step. With All files open, uploads arrive unfiled, exactly as before.

**A folder can leave as a ZIP.** Pick a folder, choose Download as ZIP, and its files -- sub-folders included, as directories -- arrive as one archive named after the folder.

**Already using another folder plugin?** Settings → Import Folders reads FileBird, Premio Folders, WP Media Folder, HappyFiles, Wicked Folders, Real Media Library and Enhanced Media Library. It shows you what it will do before it does it, says which folders will merge with ones you already have, and the whole import can be undone from the same screen. Nothing is taken from the other plugin — it keeps everything exactly as it was, so you can go back at any time.


### What this fork fixes ###

* **The WordPress 7.0 toolbar layout.** WP 7.0 turned the media toolbar into a fixed two-column CSS grid and gave placement to its own two filters only. The extra filters this plugin adds had nowhere to go, so they stacked into a 300px-tall block with every label sitting above the wrong control. The toolbar is one tidy row again, whatever number of filters you enable.
* **The author filter drew on top of the type filter.** It rendered with the same HTML id as the type filter, which was invisible under the old layout but made the two overlap once WordPress started placing elements by id. It now uses the id its own label was already pointing at, which fixes the overlap and the mislabelled control together.
* **PHP 8 warnings.** The four settings handlers read their nonce field before checking whether it was there. Also four `get_terms()` calls still using the argument order deprecated back in WordPress 4.5.

### Moving over from Enhanced Media Library ###

Activating this plugin copies your existing Enhanced Media Library settings across: taxonomies, MIME types, library and filter options. The originals are left untouched, so nothing is lost if you switch back. Deactivate Enhanced Media Library before activating this one, since running both at once means two copies of the same taxonomies.


### Categorize by Anything! ###

* Unlimited **categories & tags** for media items
* Unlimited **custom taxonomies:** create in a few clicks
* Unlimited **third-party taxonomies:** assign to the media library


### Configurable Filters ###

* **Show / hide** data, author, taxonomy filters
* **Per taxonomy** filters
* **Configurable outcome** of the filtering: include / exclude child categories


### Enhanced Media Library ###

* **Show captions:** title, filename, or caption field for each media item
* **Bulk selection:** no special mode anymore, faster editing
* **Drag'n'Drop re-order** right in the media library
* **Infinite scroll** and manageable loads per page options


### Dynamic Galleries / Playlists ###

Additional parameters for the [gallery] and [playlist] shortcodes:

* `media_category` or any other taxonomy
* `monthnum`
* `year`
* `limit` of media items to show


### MIME Types Management ###

Add or remove file types, allow or disallow uploading. The plugin incorporates a file type into media filters if you wish.


### Feels Native to WordPress ###

We spent hours to make Enhanced Media Library operates as though it were native WordPress functionality. All plugin features are incorporated into WordPress UI seamlessly.


### Developer-Friendly ###

* **Core hooks just work** for media taxonomies and media items
* **All taxonomies supported:** custom and code-registered
* **REST API supported** out of the box
* **No custom tables** in the database
* **Deactivation makes no harm to data:** all media items and taxonomies remain after deactivation


### Export / Import / Restore Plugin Settings ###

If you need to move your media library to another website you should export and import WordPress content with WordPress built-in export/import. But to make the Enhanced Media Library work on the new site with the same settings you are provided with the export/import feature.


### Multisite compatible ###

Network activate the plugin and choose which options will be available to your admins. In the PRO version, the license key should be activated once for the whole network.

[More about the basic version on wpUXsolutions.com](https://www.wpuxsolutions.com/plugins/enhanced-media-library)


### Enhanced Media Library PRO ###

Additional comfort and even more convenient way to organize WordPress media library:

* **Unlimited & Super-Fast** Bulk Edit
* **User-friendly** dynamic galleries / playlists: all options set with dropdowns and checkboxes, no "coding"
* **Advanced search:** filter media items by just typing the first letters of its name in the search field
* **Auto-Categorize** for post media items

[More about the premium version on wpUXsolutions.com](https://www.wpuxsolutions.com/plugins/enhanced-media-library-pro)


### Support ###

Support is free for both versions of the plugin. "PRO"-users do not have priority. We do our best to respond in 24 hours if not sooner.


### Compatible with the Plugins: ###

* [Advanced Custom Fields](https://wordpress.org/plugins/advanced-custom-fields/)
* [WooCommerce](https://wordpress.org/plugins/woocommerce/)
* [FooGallery](https://wordpress.org/plugins/foogallery/) - [How to use?](https://wpuxsolutions.com/documents/enhanced-media-library/how-to-create-a-dynamic-foogallery)
* [Anything Order by Terms](https://wordpress.org/plugins/anything-order-by-terms/)
* [Search & Filter](https://wordpress.org/plugins/search-filter/)
* [Document Gallery](https://wordpress.org/plugins/document-gallery/)
* [Jetpack Carousel](https://wordpress.org/plugins/jetpack/)
* [Jetpack Tiled Galleries](https://wordpress.org/plugins/jetpack/)
* [Simple Lightbox](https://wordpress.org/plugins/simple-lightbox/)
* [Justified Gallery](https://wordpress.org/plugins/justified-gallery/)
* [Meow Gallery](https://wordpress.org/plugins/meow-gallery/)
* [Meow Lightbox](https://wordpress.org/plugins/meow-lightbox/)
* [MetaSlider](https://wordpress.org/plugins/ml-slider/)
* [Responsive Lightbox & Gallery](https://wordpress.org/plugins/responsive-lightbox/)
* [Compress JPEG & PNG Images](https://wordpress.org/plugins/tiny-compress-images/) (TinyPNG)


Please let us know if you find any issue with the plugins from the list above or others.


### Incompatibility ###

Please notice that you use Enhanced Media Library with other plugins that add media categories, media folders, or manage MIME Types at your own risk. We cannot guarantee their compatibility because of the different approaches to the same functionality. We do not recommend using other media library (folder) plugin at the same time with the Enhanced Media Library. Please choose the one you prefer.


### Useful Links ###

* [Basic version: more details](https://wpuxsolutions.com/plugins/enhanced-media-library)
* [PRO version: more details](https://wpuxsolutions.com/plugins/enhanced-media-library-pro)
* [Documentation](https://www.wpuxsolutions.com/documents/enhanced-media-library)
* [FAQs](https://www.wpuxsolutions.com/documents/enhanced-media-library/faqs)


## Installation ##

1. Install the zip through **Plugins > Add New > Upload Plugin**, or upload the plugin folder to `/wp-content/plugins/`.

2. Activate the plugin through the **Plugins** menu in WordPress.

3. Adjust the settings under **Settings > Media**.

If you are moving over from Enhanced Media Library, deactivate it before activating this one.



## Frequently Asked Questions ##

= Will my Enhanced Media Library settings carry over? =

Yes. Activating this plugin copies your taxonomies, MIME types, and library and filter settings across. The originals are left where they are, so nothing is lost if you decide to switch back.

= Can I run this alongside Enhanced Media Library? =

It will not break your site if you do: every function, class, option, script handle and AJAX action carries its own prefix. But both plugins register taxonomies against your media library, so you would see each of them twice. Deactivate Enhanced Media Library first.

= How different is this from the original? =

Not very. It is Enhanced Media Library 2.9.4 with the WordPress 7 toolbar layout repaired, the PHP 8 warnings cleared, a missing capability check added to the multisite settings handler, and the Bulk select button restored. Roughly 97% of the code is unchanged.

= Does it send anything to an external service? =

No. The original polled its author's server twice a day for admin notices and printed whatever came back into your dashboard. That has been removed. This plugin makes no outbound requests of any kind.

= What happens if the plugin crashes my site? =

It tries to get out of your way. After two fatal errors in its own code within an hour it puts itself into safe mode: its features stop loading, the site comes back, and a notice in the dashboard tells you what happened and offers to switch them back on. That is there so a white screen does not mean an FTP client and a renamed folder.

It only counts errors in its own files, so it will never deactivate itself because a different plugin crashed.

= Where do I report a problem? =

On the [issue tracker](https://github.com/vergelabsnathan/vergelabs-media-library/issues).



## Screenshots ##

1. The media library grid, with filters for type, date, author and any taxonomy you have assigned, plus Reset All Filters and Bulk select.

2. List view, with your media taxonomies as sortable columns and filters above the table.

3. Media Library settings: ordering, filters, search fields, grid captions and infinite scrolling.

4. Media Taxonomies settings: assign existing taxonomies to media or create your own.

5. MIME Types settings: add, remove, rename and allow or disallow file types.

6. The media modal inside the editor, with the same filters available when you insert an image.



## Changelog ##

### 3.3.0 ###
*The Librarian: see the folders your library would get, and put them back*

= Added =
* **The Librarian.** It shows you the folder structure it would build from your own files, branch by branch, before anything is filed. Each folder shows how many files it would hold, a few of them as thumbnails, and a line saying why those files were grouped together. You can rename a folder before it is made, or say "not this one" and leave those files where they are.
* **Two ways to file, and you choose.** By subject, grouped from what the pictures show; or by date, from when each file was uploaded — which needs no AI, no credits and no licence.
* **Undo, as a first-class thing rather than an apology.** Every assignment the Librarian makes is written down, so undoing it removes exactly what it did and nothing else. A file you have moved yourself since is left where you put it and reported. A folder the Librarian created is deleted only if it is empty afterwards; if you have since put your own files in it, it is kept and you are told.
* **It only touches files that have no folder.** A library you have already organised by hand is unchanged by pressing Apply, and the files it skipped are counted so you can see it did nothing rather than wondering.
* **Existing folders are reused, never duplicated.** If a proposed folder already exists, your files go into the one you already have. No "Photos (2)".
* **It says what it will do before it does it.** Counted, never estimated: how many files, how many folders would be created, how many existing folders would be added to, and how many files it would leave alone. If it cannot count something honestly it says so instead of guessing.
* **A way in from where you notice the problem.** The folder tree on the media library screen now shows how many files have no folder, with a link to sort them. It appears only when something is unfiled.
* **Applying is interruptible.** It works in small batches, so nothing times out on shared hosting. You can pause it, close the tab, and pick it up where it stopped.
* **Demo mode is now a setting you can click**, on the AI screen, instead of something only reachable through the REST API. It invents descriptions locally from file names — nothing is sent anywhere and no credits are spent — so you can see what the Librarian would do to your library before paying for anything. The captions are not real, and the screen says so.

### 3.1.0 ###
*Folders*

= Added =
* **A folder tree beside the media library.** Drag files onto a folder to file them, drag folders into each other to rearrange, rename in place, and colour them. It is there in the list view, the grid, and in the media panel that opens when you insert an image into a post — which is where a media library is most often used and where a folder plugin most often is not.
* **Folders are ordinary media categories.** Nothing is stored in a private table, so your structure is readable by WordPress itself, by your theme, by WP-CLI, and by any other plugin — and it is still there if you ever remove this one.
* **A file can be in more than one folder.** Turn on "one folder per file" in the settings if you would rather it behaved like the folder plugins that have no choice about it.
* **Import your folders from another plugin.** FileBird, Premio Folders, WP Media Folder, HappyFiles, Wicked Folders, Real Media Library and Enhanced Media Library. It shows you what it will do before it does it, tells you which folders will merge with ones you already have, and the whole import can be undone from the same screen. Nothing is taken from the other plugin: it keeps everything exactly as it is.
* **Arrange folders by hand.** Drop a folder on the top or bottom edge of another to place it beside that one rather than inside it, or use Alt+Up and Alt+Down. Folders stay alphabetical until you arrange them.
* **Eight folder colours**, each one named, so the palette is usable without seeing colour.

= Fixed =
* **Filtering by a category could show an empty library.** Whether it worked depended on whether the URL still had the Filter button's name in it, so the dropdown worked but a bookmarked or shared filter link came back with nothing in it. The same fault made the "all" and "not in" options show nothing. Both forms of the URL now mean the same thing.
* **Deleting the folder you were looking at left the library empty from then on.** The selection outlived the folder, so every later visit filtered on a category that no longer existed — with no explanation and no obvious way back.

### 3.0.0 ###
*Recovering from a white screen without FTP*

= Added =
* **The plugin now watches itself for fatal errors.** If its own code causes a fatal error twice within an hour, it stops loading its features so the site comes back instead of showing a white screen. It stays active, and a notice in the dashboard shows the error, the file and the line, with a button to switch the features back on once the cause is dealt with.
* It only ever counts crashes in its own files. A fatal error caused by another plugin is left alone — this will never deactivate anything on somebody else's behalf.
* Switch it off with `define( 'VERGEML_NO_WATCHDOG', true );` in wp-config.php, or force safe mode on with `define( 'VERGEML_SAFE_MODE', true );`.

### 2.10.0 ###
*The three paid features, rebuilt and free*

= Added =
* **Search by filename, uploader and category.** WordPress searches titles, captions and descriptions. This adds the rest, and lets you switch each column off — searching a category name finds everything filed under it, whether or not the name appears in the file. Every word has to match something, though not necessarily the same field.
* **New uploads inherit the post's categories.** Turn it on per taxonomy, and a file uploaded while editing a post is filed under that post's terms automatically. Terms are added, never replaced, so nothing you set by hand is overwritten.
* **Bulk categorise from the media list.** Two entries in the Bulk actions menu — add to, or remove from, the category picked beside it. Built on WordPress's own bulk-action handling, so it keeps working when the list table changes. Files you cannot edit are skipped and counted, rather than silently ignored.

All three were previously sold as part of a paid add-on. Nothing here phones home, and there is nothing to buy.

= Changed =
* The plugin is now VergeLabs Media Library. Your existing settings are copied across on activation and the originals are left untouched, so nothing is lost if you go back.
* A pass over the settings screens to sit with how the WordPress admin looks now, using core's own greys, borders and button shapes. The row actions used to be absolutely positioned at fixed offsets, so a long or translated taxonomy name slid underneath them; they sit in the flow now, and the edit panel opens underneath the label it belongs to.

= Compatibility =
* Tested alongside Elementor, WooCommerce, Advanced Custom Fields, Beaver Builder, Divi Builder, Jetpack, Polylang, WPML, Yoast SEO, Rank Math, WP Rocket, LiteSpeed Cache, FooGallery, MetaSlider, NextGEN Gallery, Smush, ShortPixel and Classic Editor — each on its own, and fourteen of them at once.
* Upgrading from Enhanced Media Library is covered by its own tests: settings, taxonomies, custom file types and every category assignment are carried over unchanged, and the site survives both plugins being active at the same time.

### 2.9.8 ###
*Security, privacy and review readiness*

= Security =
* Request data across the media filters, the list table filters, the network settings form and the drag-and-drop reorder is now unslashed and sanitised on the way in.
* The settings import no longer passes the uploaded file through as-is; each field is rebuilt and sanitised before anything touches it.
* The list view was handing the entire raw query string to JavaScript. It is sanitised now.
* Saving an attachment's fields verifies its nonce before reading what was submitted, rather than after.

= Removed =
* Three settings blocks that were permanently greyed out and labelled "/ Premium Feature" — Search, Bulk Edit, and auto-assign with Synchronize Now. None of them did anything: their options were stored but never read, because the behaviour lived in a paid add-on that is not part of this plugin. The Search box stays, and now simply works: search on enter, minimum letters and auto search were never paid features to begin with.
* Links that sent you to the original author's documentation and support desk, and a note promising a fix in "the upcoming major update v3.0", which was their roadmap.

= Fixed =
* Reordering media now clears the affected items from the object cache, so the old order is not served back afterwards.
* Media filters in the modal no longer shrink to a few characters wide when several are enabled.

= Under the hood =
* Every output is escaped and every translated string carries this plugin's text domain. Some labels that previously borrowed WordPress's own translations will read in English until translated.
* WordPress's automated Plugin Check reports zero errors and zero warnings.

### 2.9.7 ###
*Feature restored, and the media view code made maintainable*

= Restored =
* **Bulk select** and **Delete selected** are back on the media library grid. Stock WordPress has them; this plugin has been shipping without them because its media grid runs as a custom frame that WordPress does not recognise as the grid, so core never built them. Selecting items and deleting or trashing them in bulk works again, using WordPress's own handling.
* Pressing Escape now leaves bulk select. The frame's reference to the page body had been commented out while the key handler still used it, so entering bulk select threw a JavaScript error — invisible until now only because bulk select could not be entered.

= Fixed =
* The media toolbar now renders WordPress's `filters-heading`, the hidden heading that tells screen reader users what the filter row is. It was lost because the plugin replaced WordPress's toolbar code wholesale rather than extending it, so anything core added afterwards never appeared.
* Filters are ordered so the Bulk select button sits after the filter row rather than between two dropdowns.

= Under the hood =
* `eml-media-views.min.js` and `eml-taxonomies-options.min.js` shipped minified with no readable source, and the constant that was supposed to load a readable copy pointed at a directory that was never in the distribution. Both are now plain, readable source. No behaviour changed in the recovery.
* The plugin's toolbar code now extends WordPress's instead of replacing it. This is the change that stops the WordPress 7.0 breakage from recurring: fixes and additions in WordPress arrive on their own instead of needing to be copied in.

### 2.9.6 ###
*Security and isolation pass*

= Security =
* The AJAX handler that applies settings across a multisite network verified a nonce but no capability. It now requires `manage_network_options`. Not cleanly exploitable before, since the nonce is only printed on a super-admin screen, but the nonce was the only thing standing between a lower-privileged user and network-wide option writes.

= Bugfixes =
* Script and style handles, CSS classes and DOM ids still carried the upstream `wpuxss-eml-` prefix, which the 2.9.5 rename missed because it only moved the underscore form. The style handle in particular would have collided with Enhanced Media Library and left one plugin's assets unloaded. All moved to `vergeml-`.
* The three custom AJAX actions were still unprefixed, so both plugins would have answered the same request. Now `vergeml-*`.
* Admin menu and page titles still read "Enhanced Media Library Utilities" and "EML Utilities".

### 2.9.5 ###
*First release of the VergeLabs Media Library fork*

= Bugfixes =
* Media toolbar layout repaired on WordPress 7.0. Core made `.media-toolbar-secondary` a fixed 2x2 CSS grid and placed only its own two filters explicitly; every control this plugin adds fell into implicit auto-placement and stacked below the toolbar with labels attached to the wrong controls. Measured on WP 7.0.4, the toolbar goes from 300px and six grid rows back to 66px and two.
* The author filter rendered with the same HTML id as the type filter. Under the new id-based placement both were assigned the same grid cell and drew on top of each other, hiding the type filter. The author filter now uses the id its label already referenced, which also reconnects that label to the right control.
* Undefined array key warnings on PHP 8 from the settings export, import, restore and cleanup handlers, which read their nonce field before checking it existed. With WP_DEBUG on, each also produced a run of "Cannot modify header information" warnings.
* Four `get_terms()` calls updated from the taxonomy-first signature deprecated in WordPress 4.5.

= Fork notes =
* Renamed from Enhanced Media Library, with all functions, classes, options and hooks moved off the `wpuxss_eml_` prefix so both plugins can be installed without a fatal redeclaration.
* Activation copies existing Enhanced Media Library settings across, leaving the originals in place.

### 2.9.4 ###
*Release Date - July 15, 2024*

= Improvement = 
* Validation and transliteration on the Media Taxonomies admin page improved, minor bugs fixed

= Bugfixes =
* A fatal error bug while JetPack VideoPress syncing is probably fixed, requires confirmation

= 3.0 Early Beta is available for testing! =
* [Take a look &raquo;](https://wpuxsolutions.com/blog/enhanced-media-library-3-0-is-coming)

= SECURITY UPDATE =
* Security issue related to MIME types upload has been fixed since v2.8.10. Please update to the latest version on all your websites.

= Notes =
* EML is compatible with PHP 5.6, 7, and 8. Don't hesitate to update. If you previously had issues because of the PHP version, it's not the case anymore.

= Thank you! =
For being EML users for so many years.
* *This update has been issued in Ukraine under everyday missile attacks.*
* *Please do not buy into ruzzian lies and propaganda. This aggression is unprovoked, illegal, and unfair. The people of Ukraine have all the right to live peacefully without ungrounded ruzzian claims and crimes committed.* 
* *Support Ukraine. It would be self-deception to believe that a neighboring country with the Nazi and anti-Western ideology, they are raising their young in, is heavily militarizing its economy and population so as never to pose a threat and never to attack the West.*


### 2.9.3 ###
*Release Date - June 19, 2024*

= Improvement = 
* `xlsm` file type upload ensured if allowed


### 2.9.2 ###
*Release Date - June 14, 2024*

= Bugfixes =
* Elementor compatibility bug of v2.9.1 (not showing filters in Elementor's media popup) fixed


### 2.9.1 ###
*Release Date - May 27, 2024*

= New =
* WP native search performance improved for both free and PRO versions in Media Library Grid Mode
* PRO only: new options added: `Search on enter`, `Auto search`, and `Minimun number of letters`

= Bugfixes =
* PRO only: plugin update module PHP-warnings issue fixed


### 2.9 ###
*Release Date - May 16, 2024*

= New =
* `Uploaded to this post by default` option added to the `Media` > `Media Library` > `Filters` section
  *Enable the option to get media files initially filtered by "Uploaded to this post" in a Media Popup while adding or editing them for a post, page, or custom post type.*
* Some changes made to the plugin's code and the PRO version updating mechanism in preparation for an upcoming major update EML v3.0

= Bugfixes =
* Tiny bugs fixed


### 2.8.15 ###
*Release Date - May 10, 2024*

= Improvements =
* Gallery / playlist shortcodes improved for better compatibility with other plugins
* `AND` logic within a single taxonomy implemented for `[gallery]` and `[playlist]` shortcodes

***Examples:***

`[gallery media_category="california+flowers" genre="landscape"]`
*— Displays images having **both** categories "california" **AND** "flowers" AND also from the genre "landscape"*

`[gallery media_category="flowers,mosses" genre="garden"]`
*— Displays imager **either** from "flowers" **OR** "mosses" category AND also from the genre "garden"*

*Note: For performance it's better to use IDs instead of slugs in the gallery shortcodes.*

= Bugfixes =
* Layout issues fixed for the **media popup** with the `Infinite scrolling` option enabled
* `Fatal Error – Too Few Arguments to function` fixed for two plugins: "cred-frontend-editor" and AJAX Thumbnail Rebuild
* Minor CSS fixes


### 2.8.14 ###
*Release Date - April 30, 2024*

= Improvements =
* Divi Builder compatibility ensured on uploading font files
*Note: Font files are allowed for upload with Divi Builder even if you haven't added them with the EML settings because Divi adds its own allowed file types.*


### 2.8.13 ###
*Release Date - April 26, 2024*

= Bugfixes =
* A bug since v2.8.10 with the right sidebar covering media library files when `Infinite scrolling` option is enabled fixed


### 2.8.12 ###
*Release Date - April 23, 2024*

= Bugfixes =
* A critical error bug of v2.8.11 with filtering by media taxonomies in the media library List View fixed


### 2.8.11 ###
*Release Date - April 19, 2024*

= Improvements =
* Database queries for taxonomies improved
* A mechanism for vetting allowed mime types added

= Bugfixes =
* Deprecated notices for author filter fixed
* Issue with uploading font mime types fixed (Report other mime types you experience issues uploading, please)
* PRO only: Search issue fixed and the mechanism improved to ensure compatibility with other plugins


### 2.8.10 ###
*Release Date - April 11, 2024*

= Improvements =
* Plugin admin menu items order and compatibility improved
* `Right sidebar width` and `Ideal column width` options added
* Caption (grid mode) is no longer cropped when it is a filename
* Caption lenght before crop depends on the thumbnail side - added
* PRO only: Enhanced search mechanism in media library improved + `filenames` option added
* Minor improvements to desltop/mobile layout made
* PHP 8 compatibility ensured (no deprecated notices anymore)
* Latest jQuery standards compatibility ensured (no deprecated notices anymore)

= Bugfixes =
* Duplicate listings when editing a single image edit in the list mode fixed
* WP excess utility taxonomies hidden in the settings


### 2.8.9 ###
*Release Date - January 09, 2022*

= Improvements =
* Infinite scroll and manageable loads per page options added
* SVG full support ensured
* Taxonomy archive pages improved - are now fully disabled and not indexed if chosen 
* Compatibility with SimpLy Gallery Blocks plugin added


### 2.8.8 ###
*Release Date - August 26, 2021*

= Improvements =
* Media Library Grid Mode: "More Details" / "Less Details" button improved to remember the latest choice after page reload
* Better third-party admin menu compatibility
* Compatibility for Impreza theme categories added


### 2.8.7 ###
*Release Date - August 8, 2021*

= Compatibility =
* Enfold theme masonry gallery (latest version) compatibility ensured

= Bugfixes =
* Edit image wrong link fixed for the Grid mode


### 2.8.6 ###
*Release Date - August 5, 2021*

= Compatibility =
* WordPress 5.8 compatibility ensured

= Bugfixes =
* A minor ACF-related bug fixed


### 2.8.5 ###
*Release Date - April 10, 2021*

= Bugfixes =
* A critical bug of v2.8.4 fixed (FooGallery related)
* A few minor bugs fixed


### 2.8.4 ###
*Release Date - April 9, 2021*

= Bugfixes =
* A bug with a category checkbox not showing unchecked after deselecting a category fixed
* A bug with taxonomy archive pages are being actually empty instead of 404 fixed

= Compatibility =
* FooGallery compatibility with EML dynamic galleries added, see [how to use Dynamic FooGalleries](https://wpuxsolutions.com/documents/enhanced-media-library/how-to-create-a-dynamic-foogallery)


### 2.8.3 ###
*Release Date - March 9, 2021*

= Improvements =
* Taxonomy archive pages mechanism improved. When the pages are disabled (404) they are no more accessible by friendly URLs

= Compatibility =
* WordPress 5.7 compatibility ensured


### 2.8.2 ###
*Release Date - December 9, 2020*

= Compatibility =
* WordPress 5.0 - 5.6 minor compatibility issues resolved


### 2.8.1 ###
*Release Date - October 29, 2020*

= Bugfixes =
* Enfold theme compatibility error fixed
* The bug with ACF's panel expand fixed 
* Minor bugs fixed

= Improvements =
* Allowed MIME Types upload improved
* Scroll to a selected media item improved (Media Library Grid Mode)
* PRO only: Faster Select All


### 2.8 ###
*Release Date - October 11, 2020*

= Bugfixes =
* Critical WP core compatibility issues fixed
* Gallery and Playlist editing bug fixed
* Image uploading issue fixed

= Improvements =
* ACF attachment custom fields - better compatibility
* Enfold Theme: added `[av_masonry_gallery]` shortcode compatibility with media category parameters like `media_category='10'`, `tag='21'`
* PRO only: The bulk Save Changes button is disabled by default since v2.8 for new plugin installations. All changes are being made on the fly. If you prefer the button, you can enable it at Settings > Media Taxonomies > Bulk Edit > Save Changes button. 


### Previous releases... ###
