=== VergeLabs Media Library ===
Contributors: vergelabsdev
Tags: media library, media folders, media tags, media categories, mime types
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 3.9.0
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


### Sorting a library nobody ever filed ###

**The Librarian shows you the folders before it makes any.** Point it at a library that was never organised and it draws the structure it would build from your own files: every folder with the number of files it would hold, a few of them as thumbnails, and a line saying why those files were grouped together. Rename a folder before it exists, or say "not this one" and those files stay where they are.

**Two ways to sort, and one of them needs nothing.** By date and file type, built from what WordPress already knows -- no account, no licence, nothing sent anywhere. Or by subject, grouped from what the pictures show, which is the part that uses the AI service described below.

**Undo is the feature, not the apology.** Every assignment the Librarian makes is written down, so undoing it removes exactly what it did and nothing else. A file you moved yourself in the meantime is left where you put it and reported. A folder it created is deleted only if it is still empty; if you have put your own files in it, it is kept and you are told. It only ever touches files that had no folder, so a library you organised by hand is unchanged.

**And once it has looked, the descriptions become folders.** The folder panel grows a group of its own — Photos, Screenshots, Documents, Logos, pictures with people in them, pictures with text in them — built from what was found rather than from anything you filed. Empty ones are not shown, and the group says how much of your library it is drawn from.

**Nothing that cannot be interrupted.** Applying works in small batches, so it does not time out on shared hosting. Pause it, close the tab, pick it up where it stopped.


### External services ###

This plugin can send images to an AI service run by VergeLabs, at `https://ai.vergelabs.nl/v1`. It does so only when you ask it to.

**Nothing is sent until you enter a licence key and start a run.** With no key the AI screens do nothing: no request on page load, none on upload, none in the background. Demo mode invents captions locally from the file names and sends nothing anywhere, and the Librarian's date-and-type scheme needs no service at all.

**What is sent, when you do start a run:** a downsized copy of the image being described (never the original file), its file name, its MIME type, your site's address and your licence key -- one request per image, only while a run is in progress. What comes back is a caption, alt text, tags and a suggested title. The service processes the image and discards it; what it keeps is a usage count.

**What the service does with it, in writing.** [Sub-processors](https://vergelabsmedia.com/legal/sub-processors) names every company that touches the data and where each one is. [What is kept, and for how long](https://vergelabsmedia.com/legal/retention) answers that per category — images are kept for no time at all, and it says which file in the code proves it. [Data Processing Agreement](https://vergelabsmedia.com/legal/dpa), if you need one.

Service terms: [https://vergelabs.nl/voorwaarden](https://vergelabs.nl/voorwaarden) -- Privacy policy: [https://vergelabs.nl/privacy](https://vergelabs.nl/privacy)


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

If you need to move your media library to another website you should export and import WordPress content with WordPress built-in export/import. But to make this plugin work on the new site with the same settings you are provided with the export/import feature.


### Multisite compatible ###

Network activate the plugin and choose which options will be available to your admins.


### Support ###

Questions and problems go to the [issue tracker](https://github.com/vergelabsnathan/vergelabs-media-library/issues), which is read by the person who maintains the plugin.


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

* [Source and issue tracker](https://github.com/vergelabsnathan/vergelabs-media-library)
* [Enhanced Media Library, the plugin this one is forked from](https://wordpress.org/plugins/enhanced-media-library/)
* [Upstream documentation](https://www.wpuxsolutions.com/documents/enhanced-media-library), still accurate for the parts this fork did not change


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

Only if you switch the AI features on and give them a licence key, and then only the images you asked to have described.

The folder tree, the smart folders, the health report, the importer, the galleries, the MIME settings and the Librarian's date-and-type scheme make no outbound requests at all. Neither does demo mode, which makes its captions up locally so you can see the shape of the thing before paying for anything.

With a licence key entered and a describe run started, each image is sent -- downsized, never the original -- to `https://ai.vergelabs.nl/v1`, along with its file name, its MIME type, your site's address and the key. See "External services" above for what comes back and what is kept.

The original plugin polled its author's server twice a day for admin notices and printed whatever came back into your dashboard. That has been removed and nothing replaced it.

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

### 3.9.0 ###
*Work that carries on without you, and folders you can take with you*

= Added =
* **Describe in the background.** The run carries on after you close the tab, and picks up exactly where it left off. A library of twenty thousand no longer needs somebody keeping a browser open for it.
* It stops by itself when the licence runs out of credits or is refused, and says which it was — rather than working through the rest of your library writing failures over it.
* Slower than the on-screen run, and the screen says so: it works whenever the site is visited. A site with a real system cron runs it every minute regardless.
* **Folders as a spreadsheet.** Write your whole structure out as a CSV, edit it wherever you like editing things, and read it back. Two hundred folders without two hundred clicks — and a way to take your structure with you.
* **The plugin's screens are one place now.** A nav down the left, a single content pane, and sections separated by a rule instead of every block sitting in its own box. Eight screens that behaved like eight settings pages now behave like one tool.
* Reading a file in is an import like any other: you see what it will do before it does it, folders you already have are merged rather than duplicated, and the whole thing can be undone afterwards.

* **Folders only you see.** On a site where several people upload, a folder can belong to the person who made it and stay out of everyone else's sidebar. Ten people's filing in one panel is nobody's filing.
* It hides the **folder**, never the files. Anything inside stays in the library for everyone, exactly as before, and the screen says so — because a folder that looked like a locked drawer would eventually be trusted with something that needed one.
* Administrators can see whose a folder is, so nothing is stranded when somebody leaves. Nobody, administrators included, can quietly share out a folder that is not theirs.

= Fixed =
* **“Copy gallery shortcode” said it had copied when it had not.** On sites served over plain http, and any time the browser refused, the message appeared anyway while the clipboard still held whatever was there before. It now says so only when it worked, and shows you the shortcode when it did not.
* The same action disappeared entirely on hosts without the ZIP extension, because it had been tucked inside the download-as-ZIP branch. Copying a line of text needs no extension.

### 3.8.0 ###
*Duplicates, alt text, and finding the one like this*

= Added =
* **Duplicates can be tidied without deleting anything.** It keeps the copy something actually points at — or the oldest, when nothing points at any of them — and sets the rest aside, where they wait thirty days and can be taken back with one click.
* **Alt text, filled in from descriptions you already paid for.** Only where alt is empty, and never over anything you have written yourself. Everything it writes is marked, so the whole lot comes back out again if you do not like it.
* **"More like this one."** Free, instant, and no AI call at the moment you ask: it compares against descriptions your library already holds.

= Fixed =
* **Filling in alt text marked its own writing as yours.** The protection that stops a model overwriting your words was tripped by the one writer it was meant to allow, so a filled field could never be undone and was skipped on the next run.


### 3.7.0 ###
*Set aside, which is not deleted*

= Added =
* **A place to put files you think you are finished with.** Set one aside and it leaves the media library — but it stays exactly where it is: same file on disk, same URL, still working in every page that uses it. If something you could not see was using it, nothing breaks and you find out.
* **Nothing may be considered for removal for thirty days.** Not a setting you can turn down; a floor. The evidence for "unused" is that nobody found a reference, and absence is disproved by somebody noticing, which takes weeks.
* **A list you can keep.** Download everything set aside — ids, file names, paths, sizes, and the reason each one was — and check it against a backup before you decide anything.
* **It never deletes.** There is no delete button, no delete endpoint, and no code in this feature that removes a file. When the wait is over it tells you so and hands you the list; what happens next is yours.
* Taking something back is instant and always available. A delay that protects you must not also trap you.


Earlier releases — including the Enhanced Media Library history this fork
continues — are in the repository linked at the top of this page.
