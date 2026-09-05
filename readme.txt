=== VergeLabs Media Library – Media folders, categories and AI alt text ===
Contributors: vergelabsnathan
Tags: media library, media folders, alt text, accessibility, media categories
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 3.16.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Media library folders, categories and tags, plus AI alt text and captions. The maintained successor to Enhanced Media Library, fixed for WordPress 7.

## Description ##

**Folders for your media library, categories that stay in WordPress, and alt text written for you.**

VergeLabs Media Library is a maintained fork of [Enhanced Media Library](https://wordpress.org/plugins/enhanced-media-library/) by wpUXsolutions, which has had no release since July 2024. It repairs the media toolbar on WordPress 7.0 and clears the PHP 8 warnings. Everything else is the plugin you already know.

Based on Enhanced Media Library by wpUXsolutions, and licensed GPLv2 or later as the original is.

[Source and issue tracker](https://github.com/vergelabsnathan/vergelabs-media-library)


### Folders ###

A folder tree sits beside the media library. Drag files onto a folder to file them, drag folders into each other to rearrange, rename in place, colour them, and arrange them in whatever order you want. It is in the list view, in the grid, and in the media panel that opens when you insert an image into a post — which is where a media library is most used, and where a folder plugin most often is not.

**Your folders are ordinary media categories.** Nothing is kept in a private table of ours. That means your structure is readable by WordPress itself, by your theme, by WP-CLI and by any other plugin — and it is still there, intact and usable, if you ever remove this plugin. A folder plugin that stores your organisation somewhere only it can read is a folder plugin you cannot leave.

**A file can be in more than one folder.** Dragging a file moves it, exactly as it does on your desktop. Hold Ctrl while dropping and it is added to that folder as well -- the same photo in Products and in Autumn Campaign without a second copy of it existing, which is the thing single-folder plugins cannot do. If you want the one-folder promise kept absolutely, there is a setting that makes every drop a move.

**A folder can be a gallery.** Add the Folder gallery block in the block editor -- or the Folder gallery element in Elementor, Divi or WPBakery, or drop `[vergeml_gallery folder="12"]` anywhere shortcodes work -- pick a folder, and it shows every image in it. Grid or carousel, with a built-in lightbox if you want one. It stays a folder rather than becoming a list of files: put a new image in the folder and every page using that gallery has it, with nothing re-edited. WordPress's own gallery block freezes a list of images at the moment you insert it.

**One set of folders in every language.** With Polylang or WPML, a translated copy of an image goes into the same folders as the original, and the tree shows the same folders whichever language you are working in.

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

**Library counts go only if you switch them on.** Under Library settings, "Share library counts" (off by default) posts to the same service once a day: how many files and folders, how deep the folders nest, files added in the last thirty days, and the plugin, WordPress and PHP versions with the site language, alongside the licence key and the site address. Never a file name, a title, a folder name or a picture.

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

With a licence key entered and a describe run started, each image is sent -- downsized, never the original -- to `https://ai.vergelabs.nl/v1`, along with its file name, its MIME type, your site's address and the key. With "Page context" on (it is on by default, and one switch turns it off), the title of the page the image is used on goes too, and if Yoast, Rank Math, SEOPress or All in One SEO gave that page a focus keyphrase and description, those as well -- as wording for the model, never as instructions. See "External services" above for what comes back and what is kept.

The original plugin polled its author's server twice a day for admin notices and printed whatever came back into your dashboard. That has been removed and nothing replaced it.

= What happens if the plugin crashes my site? =

It tries to get out of your way. After two fatal errors in its own code within an hour it puts itself into safe mode: its features stop loading, the site comes back, and a notice in the dashboard tells you what happened and offers to switch them back on. That is there so a white screen does not mean an FTP client and a renamed folder.

It only counts errors in its own files, so it will never deactivate itself because a different plugin crashed.

= What happens to my folders if I uninstall it? =

They stay. The folders are terms in WordPress's own tables, not the plugin's, so deleting the plugin leaves them exactly where they are -- a reinstall picks them up as you left them, and so does any other folder plugin that reads the same `media_category` taxonomy. AI descriptions already written into your images stay written. Only caches and scheduled tasks are removed.

If you genuinely want everything gone, that exists too, in two forms on the Utilities page: a Complete Cleanup button that wipes immediately, and a switch that makes deleting the plugin from the Plugins screen take all its data with it. Neither is ever the default, and neither touches a media file.

= Where do I report a problem? =

On the [issue tracker](https://github.com/vergelabsnathan/vergelabs-media-library/issues).



### Which versions of WordPress and other plugins has it been checked against? ###

Every night an automated watch looks for new releases of WordPress, PHP and the plugins and themes this plugin integrates with, greps each new release for every hook and field we rely on, upgrades a staging site and runs the checks there. What passed is recorded here, newest first:

<!-- watch:verified -->
* Dokan 5.0.19 — contract intact, stage suites passed (2026-09-04)
* Divi 5.11.0 — Folder gallery module renders from a built page (2026-09-01)
* Polylang Pro 3.8.6 — a translated image keeps its folders (2026-09-01)
* Yoast SEO 28.4, Rank Math 1.0.277.2, SEOPress 10.1, All in One SEO 5.0.1.1 — page context reaches the describe request (2026-09-01)
* WooCommerce, Advanced Custom Fields — product context and duplicate repoint (2026-09-01)

## Screenshots ##

1. The media library grid, with filters for type, date, author and any taxonomy you have assigned, plus Reset All Filters and Bulk select.

2. List view, with your media taxonomies as sortable columns and filters above the table.

3. Media Library settings: ordering, filters, search fields, grid captions and infinite scrolling.

4. Media Taxonomies settings: assign existing taxonomies to media or create your own.

5. MIME Types settings: add, remove, rename and allow or disallow file types.

6. The media modal inside the editor, with the same filters available when you insert an image.



## Changelog ##

### 3.16.0 ###
*One button instead of a copied key*

= Added =
* **Connect a site without copying anything.** The AI screen has a Connect button: it sends you to vergelabsmedia.com, you pick which licence this site should use, and you are sent straight back with the key already in place. The key is fetched between the two servers and never travels through the browser, the request carries a nonce that must come back unchanged, and the code it returns is single use, expires in ten minutes, and only works for the site it was minted for. Pasting a key by hand still works, for a site that cannot reach out and for a network licence.

= Fixed =
* **The credit balance was only ever overheard.** The service reports what is left with every description, and the plugin remembered that — so a site that had bought credits and not described anything since kept showing the number from its last run, which reads exactly like a payment that never arrived. It now asks outright, at most once every few minutes, and immediately after connecting. A site that cannot reach the service shows the last number it knew rather than an error.

### 3.15.0 ###
*In every language, the same drawer*

= Multilingual =
* **Polylang and WPML.** A translated copy of a filed image goes into the same folders as the original, whether it is made with the media screen's "+", Polylang Pro's duplicate-at-upload, or WPML Media Translation. Folders are where a file is kept, not what it says, so they are shared across languages: the plugin tells Polylang and WPML (`wpml-config.xml`) that its folder taxonomies are not to be translated, and the tree shows one set of folders whichever language is selected. A site that wants folders per language can say so with the `vergeml_multilingual_shared_folders` filter.
* The tree no longer offers Polylang's own `language` taxonomy as a set of folders once media translation is on.

= Verified against the real plugins =
* The SEO context (3.13) now has a live check against Yoast 28.4, Rank Math 1.0.277, SEOPress 10.1 and AIOSEO 5.0.1 on the test box: keyphrase, description and related keyphrases reach the describe request, lead the filename only when the model saw them, and drive the page-gap scope. WooCommerce product categories reach the context and the product gallery follows a surviving duplicate; an ACF URL field follows it too.
* Divi 5.11 renders the Folder gallery module from a built page. Polylang Pro 3.8.6 makes a Dutch copy that lands in the original's folder. `tests/integrations/` holds the checks.

### 3.14.0 ###
*A network, from A to Z*

= Multisite =
* Network activation provisions every site, and a site created later is provisioned the moment it exists — no more subsite without folders until somebody opens its admin.
* Deleting a site drops the plugin's tables for it, whether or not the plugin is loaded in that request.
* The network settings screen no longer destroys its own option on save (a wrong validator was writing the main site's taxonomy settings over the two network flags), and a missing option no longer locks site administrators out of Settings > Media.
* One subsite's fatal errors can no longer deactivate the plugin for the whole network: that site goes into safe mode, and the network administrator gets a notice naming it.
* Complete Cleanup is a network administrator's action on a network, and cleans every site fully — all four tables, transients and scheduled tasks per site.
* Uninstall walks every site; the network administrator's "remove all data" switch decides for the network, and a site's own switch for itself.
* A network-wide AI licence: enter it once, every site inherits it; lock it and sites cannot enter their own.
* A network overview: per site, version, tables, safe mode, where its AI key comes from, credits last seen.
* Request caches are forgotten on switch_to_blog, and a person’s remembered tree state is kept per site. The usage scan keeps its progress on the server between steps.
* `pnpm test:multisite` runs the whole lifecycle on a real network on the test box.

= Added =
* **Filing without a mouse.** A Move selected button beside the folder search, the M key anywhere in the library with files selected, and a folder list you can type into and arrow through. Shift+F10 opens a folder's menu from the keyboard.
* Right-to-left stylesheets for every screen, generated from the sources (`pnpm rtl`), and registered so WordPress swaps them in on RTL locales.
* Folder talk: applying a proposal needs the plan id the proposal came with — bound to what was shown and to whom, fifteen minutes, once.
* A notice when another folder plugin is active alongside, and exclusions so optimisers leave our scripts and the transport fallback alone.
* Describing is refused on a staging or development copy unless `VERGEML_AI_ALLOW_NONPROD` says otherwise, and the environment travels with each request.

= Fixed =
* The translation template now covers every string (316 → 1,060), and the plugin loads translations from its own languages folder.
* Duplicate delete repoints URL-bearing post meta, builder layouts and WooCommerce galleries as well as post content and featured images.

### 3.13.2 ###
*Audited before anyone else's site*

= Security =
* Renaming titles through the REST API now checks, per file, that the caller may edit that file; the whole-library run and its undo are administrator actions. Before this an Author could rename every title in the library in one request.
* Applying stored alt text across the library is an administrator action. Before this an Author could push alt text onto files they could not otherwise edit.

= Fixed =
* The on-disk file renamer is switched off until it rewrites everything it moves. It was reachable through the REST API with no screen calling it, and it did not yet update builder layouts, field data or scaled originals.
* Upgrades run their migration from the admin, cron or the command line only, one process at a time, instead of on whichever visitor's request came first after an update.
* The usage scan keeps its progress on the server between steps instead of sending it through the browser; large libraries no longer fail at the upload-size limit.
* Deleting duplicates refuses until the usage scan has run, so the copy that is actually in use is the one kept.
* Multisite: network-wide cleanup, settings and uninstall reach every site, not only the first hundred.
* The shipped plugin no longer carries screenshots, debug images or repository files.

### 3.13.1 ###
*Checked against the SEO plugins' own source*

= Added =
* Cornerstone (Yoast) and pillar (Rank Math) pages come first in "Fix alt text on your SEO pages" — the pages the site cares most about get their images described before the rest.
* Related keyphrases: Yoast Premium's related keyphrases and the extra keywords Rank Math and SEOPress keep after the focus one travel as wording too, up to three, under the same never-add-what-you-do-not-see rule.

= Verified =
* Every meta key this plugin reads — Yoast, Rank Math Pro, SEOPress Pro — was checked against the current source of those plugins rather than remembered.

### 3.13.0 ###
*The page's keyphrase, where the picture earned it*

= Added =
* **Fix alt text on your SEO pages.** A new button on the AI screen finds images without alt text on the pages your SEO plugin gives a focus keyphrase — the images Yoast, Rank Math, SEOPress or All in One SEO are already marking those pages down for — and describes them first. It carries its count and stays hidden when there is nothing to fix.
* **File names led by the keyphrase — only when earned.** When the file renamer runs and the page has a focus keyphrase, the new name starts with it if, and only if, every word of the keyphrase appears in what the model wrote about the picture. The photo of the table on the oak-tables page becomes `handmade-oak-table-workshop-bench.jpg`; the photo of the workshop door stays `workshop-door.jpg`. Same switch as the rest of the page context.

### 3.12.0 ###
*Descriptions that know what the page is for*

= Added =
* **Page context.** When an image was uploaded to a page, or the "Used in" scan found it on one, the description is written knowing that page's title — and, with Yoast, Rank Math, SEOPress or All in One SEO, its focus keyphrase and meta description. Advisory by design: the model names what it sees the way the page names it, and is told never to add the keyphrase to a picture that does not show it. One switch on the AI screen; off, nothing about your pages leaves the site.

= Fixed =
* Product categories were gathered for shop images and then never sent. They travel now.

### 3.11.0 ###
*The tree, wherever you build*

= Added =
* **Twenty page builders.** The folder tree now appears in the media modal of Elementor, WPBakery, Divi 4 and 5, Beaver Builder, Brizy, Bricks, Oxygen, Breakdance, Avada / Fusion Builder, Thrive Architect, Cornerstone, Zion, YooTheme, BeTheme, Themify, Tailor, Oshine, LearnPress's front-end editor and Dokan's vendor dashboard. Elementor, WPBakery, Beaver, Brizy and Zion were verified in a browser against their current releases; the rest use each builder's own documented hook and are best effort until we can run them.

= Fixed =
* On front-end builders the tree's fallback transport was never registered, so the tree script silently failed to print. It registers itself now wherever the tree loads.

### 3.10.1 ###

= Fixed =
* The REST media listing no longer re-sanitizes every configured MIME type on every call — 38,000 sanitizations per hundred images, a third of the response time, now done once per request. A hundred-image listing dropped from roughly 630ms to 390ms.

### 3.10.0 ###
*Leaving is safe*

= Added =
* **Uninstalling keeps your folders.** Deleting the plugin now removes only its caches and scheduled tasks; the folders and AI descriptions stay in WordPress's own tables, ready for a reinstall or for any other plugin that reads the same taxonomy. In writing, in the FAQ.
* A switch on the Utilities page for the opposite: have deleting the plugin take every folder, setting and the AI index with it. Off unless you turn it on.

= Fixed =
* Complete Cleanup now also removes the AI index table, the newer settings, every cached count and the scheduled background task. It said complete; now it is.

### 3.9.1 ###

= Fixed =
* Search by meaning reads a compact projection instead of unpacking full embeddings: the same search over a 20,000-image library went from over five seconds to under half a second, and it converts old rows in the background within a time budget a small shared server can afford.

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
