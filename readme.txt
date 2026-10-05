=== VergeLabs Media Library ===
Contributors: vergelabsdev
Tags: media library, media folders, alt text, accessibility, media categories
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 4.1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Folders, categories and tags for the WordPress media library, with AI alt text and captions.

## Description ##

**Folders for your media library, categories that stay in WordPress, and alt text written for you.**

Folders that are real taxonomy terms, so nothing breaks when you deactivate. Categories and tags on every attachment, filterable in the grid and the list. Alt text and captions written by a model that has looked at the picture, one run at a time, only when you start it. Works on WordPress 6.5 through 7.1 and PHP 7.4 through 8.3.

Grown from [Enhanced Media Library](https://wordpress.org/plugins/enhanced-media-library/) by wpUXsolutions, GPLv2 or later as the original is; its settings carry over on activation.

[Source and issue tracker](https://github.com/vergelabsnathan/vergelabs-media-library)


### Folders ###

A folder tree sits beside the media library. Drag files onto a folder to file them, drag folders into each other to rearrange, rename in place, colour them, and arrange them in whatever order you want. It is in the list view, in the grid, and in the media panel that opens when you insert an image into a post — which is where a media library is most used.

**Your folders are ordinary media categories.** The folder structure is not kept in a table of ours. That means your structure is readable by WordPress itself, by your theme, by WP-CLI and by any other plugin — and it is still there, intact and usable, if you ever remove this plugin.

**A file can be in more than one folder.** Dragging a file moves it, exactly as it does on your desktop. Hold Ctrl while dropping and it is added to that folder as well -- the same photo in Products and in Autumn Campaign without a second copy of it existing. If you want the one-folder promise kept absolutely, there is a setting that makes every drop a move.

**A folder can be a gallery.** Add the Folder gallery block in the block editor -- or the Folder gallery element in Elementor, Divi or WPBakery, or drop `[vergeml_gallery folder="12"]` anywhere shortcodes work -- pick a folder, and it shows every image in it. Grid or carousel, with a built-in lightbox if you want one. It stays a folder rather than becoming a list of files: put a new image in the folder and every page using that gallery has it, with nothing re-edited.

**One set of folders in every language.** With Polylang or WPML, a translated copy of an image goes into the same folders as the original, and the tree shows the same folders whichever language you are working in.

**Smart folders answer questions.** Above your folders sit five that nothing was ever filed into: Unused media, Missing alt text, Large files, Unattached, and This month. Each is a live view of the library. Unused and Large need one scan -- a click, a progress count, done -- which reads every post once to learn which files are genuinely referenced: embedded images, pasted URLs, featured images, galleries. "1,400 images have no alt text" and "2 GB of this library is unused" are the two numbers most worth knowing about a media library.

**Uploads land where you are.** Upload while a folder is open and the files arrive filed into it -- no second step. With All files open, uploads arrive unfiled, exactly as before.

**A folder can leave as a ZIP.** Pick a folder, choose Download as ZIP, and its files -- sub-folders included, as directories -- arrive as one archive named after the folder.

**Already using another folder plugin?** VergeLabs Library → Import Folders reads FileBird, Premio Folders, WP Media Folder, HappyFiles, Wicked Folders, Real Media Library and Enhanced Media Library. It shows you what it will do before it does it, says which folders will merge with ones you already have, and the whole import can be undone from the same screen. Nothing is taken from the other plugin — it keeps everything exactly as it was, so you can go back at any time.


### Sorting a library nobody ever filed ###

**The Librarian shows you the folders before it makes any.** Point it at a library that was never organised and it draws the structure it would build from your own files: every folder with the number of files it would hold, a few of them as thumbnails, and a line saying why those files were grouped together. Rename a folder before it exists, or say "not this one" and those files stay where they are.

**Two ways to sort, and one of them needs nothing.** By date and file type, built from what WordPress already knows -- no account, no licence, nothing sent anywhere. Or by subject, grouped from what the pictures show, which is the part that uses the AI service described below.

**Undo is the feature, not the apology.** Every assignment the Librarian makes is written down, so undoing it removes exactly what it did and nothing else. A file you moved yourself in the meantime is left where you put it and reported. A folder it created is deleted only if it is still empty; if you have put your own files in it, it is kept and you are told. It only ever touches files that had no folder, so a library you organised by hand is unchanged.

**And once it has looked, the descriptions become folders.** The folder panel grows a group of its own — Photos, Screenshots, Documents, Logos, pictures with people in them, pictures with text in them — built from what was found rather than from anything you filed. Empty ones are not shown, and the group says how much of your library it is drawn from.

**Nothing that cannot be interrupted.** Applying works in small batches, so it does not time out on shared hosting. Pause it, close the tab, pick it up where it stopped.


### Categorize by Anything! ###

* Unlimited **categories & tags** for media items
* Unlimited **custom taxonomies:** create in a few clicks
* Unlimited **third-party taxonomies:** assign to the media library


### Configurable Filters ###

* **Show / hide** data, author, taxonomy filters
* **Per taxonomy** filters
* **Configurable outcome** of the filtering: include / exclude child categories


### The media grid ###

* **Show captions:** title, filename, or caption field for each media item
* **Bulk selection:** no special mode anymore, faster editing
* **Infinite scroll** and manageable loads per page options


### Dynamic Galleries / Playlists ###

Additional parameters for the [gallery] and [playlist] shortcodes:

* `media_category` or any other taxonomy
* `monthnum`
* `year`
* `limit` of media items to show


### MIME Types Management ###

Choose which of WordPress's own file types may be uploaded, and add any of them to the media filters. Types that can run code -- PHP, HTML, JavaScript, SVG, executables -- cannot be allowed.


### Developer-Friendly ###

* **Core hooks just work** for media taxonomies and media items
* **All taxonomies supported:** custom and code-registered
* **REST API supported** out of the box
* **Folders are ordinary terms** in WordPress's own tables; four tables of the plugin's own hold the AI descriptions, the Librarian's batches and moves, and the sorting runs
* **Deactivation makes no harm to data:** all media items and taxonomies remain after deactivation


## External services ##

This plugin talks to two places: an AI service run by VergeLabs at `https://ai.vergelabs.nl/v1`, which also serves a list of known problems when you ask for it, and the VergeLabs site at `https://vergelabsmedia.com` when you connect a licence. Without a licence key, nothing is contacted by installing, activating or opening a screen; every request below follows something you do. Everything below says what goes where, when, and what you have to do for it to happen.

**The AI features need a licence key, and do nothing without one** (a free trial key counts). With no key, no folder is filed, no picture is described, no search is answered by meaning, and no conversation can start -- the requests are refused before they are made. Demo mode invents captions locally from the file names and sends nothing anywhere. The folder tree itself, the smart folders, the health report, the importer, the galleries, the MIME settings and the Librarian's date-and-type scheme need no service at all.

**Describing a picture** sends a downsized copy of it (never the original, unless the original is already small), its file name, its MIME type, the picture's own title and caption, your site's address, your licence key and whether the site is production or staging. If the picture is used on a page, that page's title goes too; on a WooCommerce site, up to six of the product's category names. With "Page context" on -- it is on by default, and one switch turns it off -- the page's focus keyphrase, meta description and related keyphrases from Yoast, Rank Math, SEOPress or All in One SEO go as well, as wording for the model, never as instructions. What comes back is a caption, alt text, tags and a suggested title. No picture is sent on upload, and none on a page load: only while a run you started is in progress.

**Sorting pictures into folders** sends more than the pictures. When you ask for a plan, confirm one, fill your folders, or talk to the Folders screen, these go to the same service: **your folder names, their parents and how many files are in each**, the captions the AI wrote for your pictures, the words it recorded about what they show, and anything you typed into the screen along with the conversation so far. On a WooCommerce site your product category paths go too. Editing the draft tree sends its folder names, so the screen can show which pictures each one would hold. Searching by meaning sends the phrase you typed. None of this happens on its own: each of them is a button you press.

**Planning your folders** (the Plan my folders button on the Folders screen) sends your site's address, your licence key, a random reference number the plugin makes for the plan, and every distinct label the AI recorded about what your pictures show -- the object, its broader class and the kind of picture, such as "platform sneaker; footwear" -- with how many pictures carry each and, where the description says so, how many of those are for men, women or kids. It also says whether an audience split is confirmed: whether you answered "Yes" to the one question the Tree step asks when your pictures do not show it, or your product category names already do. No picture, caption, file name or folder name is sent. It happens only when you press the button. When a plan is not shown to you -- it would not improve the folders you already have, its answer did not arrive, or you confirmed your tree while it ran -- the plugin asks the service for the plan's credits back, sending your site's address, your licence key and the plan's reference number, nothing else.

**The Folders screen talks to the service from your browser**, not from your server, for the part that streams a reply as it is written. That request carries what you typed, your draft folder tree and the same library summary -- and, because it comes from your browser rather than your server, your own IP address and browser headers reach the service, as they would visiting any website.

**Trying it free** (the AI screen, on a site without a licence) sends the email address you type and your site's address to `https://vergelabsmedia.com/api/trial`, which answers with a free key for that site, holding 25 credits. It happens only when you press Start. The email address receives one message, once the 25 credits are used, with the key and a way to buy more.

**Connecting a licence** sends you to `https://vergelabsmedia.com`, carrying your site's address and the address of your admin screen, and sends back a one-time code your site exchanges for a key. Saving, checking or removing a key sends the key and your site's address to the AI service. Once a key is set, the plugin checks your remaining credits when you open a VergeLabs screen -- at most once every five minutes, except the Licence screen, which checks each time you open it.

**Asking for help** works without a licence key, as connecting one and the known-problems list do. Pressing Send on the Get help screen posts what you typed, the email address you gave, the last four characters of your licence key if you have one (never the key itself), a random token this site keeps so replies reach the same conversation, the known problems that matched your site if you checked for them, and a full system report: your site's address, your WordPress, PHP and MySQL versions, your server's limits, your theme, how many files you have, your folder and taxonomy counts, this plugin's settings, and **the name and version of every plugin you have active**. The screen shows you the report before you send it, and it will not send without the tick box. Pressing "Check for known problems" on the same screen reads the list of known problems from the AI service at `https://ai.vergelabs.nl/v1/known-issues` and compares it with what your site runs. It happens only when you press the button; the list is then kept for twelve hours, and if the service cannot be reached the screen says so rather than showing an empty list. That request carries no key and no counts -- though, like every request WordPress makes, it identifies itself with your site's address.

**Library counts go only if you switch them on.** Under Library settings, "Share library counts" (off by default) posts to the same service once a day: how many files and folders, how deep the folders nest, how many files are of each broad type, files added in the last thirty days, and the plugin, WordPress and PHP versions with the site language, alongside the licence key and the site address. Never a file name, a title, a folder name or a picture.

**Pointing it somewhere else.** `VERGEML_AI_SERVICE`, `VERGEML_AI_STREAM`, `VERGEML_SITE_URL` and `VERGEML_KNOWN_ISSUES_URL` in `wp-config.php` send these requests to a host of your choosing. There is no filter for any of them on purpose: a destination for your files should not be changeable by another plugin.

**What the service does with it, in writing.** [Sub-processors](https://vergelabsmedia.com/legal/sub-processors) names every company that touches the data and where each one is. [What is kept, and for how long](https://vergelabsmedia.com/legal/retention) answers that per category — images are kept for no time at all, and it says which file in the code proves it. [Data Processing Agreement](https://vergelabsmedia.com/legal/dpa), if you need one.

Service terms: [https://vergelabsmedia.com/legal/terms](https://vergelabsmedia.com/legal/terms) -- Privacy policy: [https://vergelabsmedia.com/legal/privacy](https://vergelabsmedia.com/legal/privacy)

## Installation ##

1. Install the zip through **Plugins > Add New > Upload Plugin**, or upload the plugin folder to `/wp-content/plugins/`.

2. Activate the plugin through the **Plugins** menu in WordPress.

3. Find it under **VergeLabs Library** in the admin menu. The folders work straight away. The AI features need a licence key: connect one under **VergeLabs Library > Licence**, or start the free trial on the AI screen.

If you are moving over from Enhanced Media Library, deactivate it before activating this one.



## Frequently Asked Questions ##

= Will my Enhanced Media Library settings carry over? =

Yes. Activating this plugin copies your taxonomies, MIME types, and library and filter settings across. The originals are left where they are, so nothing is lost if you decide to switch back.

= Can I run this alongside Enhanced Media Library? =

It will not break your site if you do: every function, class, option, script handle and AJAX action carries its own prefix. But both plugins register taxonomies against your media library, so you would see each of them twice. Deactivate Enhanced Media Library first.

= What is it built on? =

It started as a fork of Enhanced Media Library 2.9.4 and keeps what that plugin did: media categories and taxonomies, filters, MIME types and dynamic galleries, with your settings carried over. On top of that it adds a folder tree with drag and drop, smart folders, an importer for other folder plugins, a Librarian that sorts an unfiled library, duplicate detection, and optional AI features that write alt text and captions and let you search by what a picture shows. It is not made by, or affiliated with, wpUXsolutions. The [upstream documentation](https://www.wpuxsolutions.com/documents/enhanced-media-library) is still accurate for the parts this fork did not change.

= Can I run it next to another folder plugin? =

Running a second folder plugin at the same time is not recommended: both would register folders against the same media. Bring its folders across instead, under VergeLabs Library → Import Folders, and deactivate it. Which releases of WordPress and of the plugins this one integrates with have been checked is listed near the end of this page.

= Can I take my settings to another site? =

If you need to move your media library to another website you should export and import WordPress content with WordPress built-in export/import. But to make this plugin work on the new site with the same settings you are provided with the export/import feature.

= Does it work on multisite? =

Network activate the plugin and choose which options will be available to your admins.

= Does it send anything to an external service? =

Yes, if you use the AI features -- and not only the pictures. Describing sends the picture; sorting into folders sends your folder names and what the AI wrote about your pictures; searching by meaning sends the phrase you typed; asking for help sends a system report with your plugin list in it. "External services" above is the full account, written out in one place.

All of it needs a licence key except the Get help screen and the known-problems list. Without a key the AI features refuse before they make a request, and demo mode makes its captions up locally so you can see the shape of the thing before paying for anything.

The folder tree itself, the smart folders, the health report, the importer, the galleries, the MIME settings and the Librarian's date-and-type scheme make no outbound requests at all. What does talk to the service is the part of the Folders screen that plans, fills and answers -- that is the AI, wearing the folder screen's clothes.

= What happens when the AI service is down? =

Your plugin keeps working without the AI features: filing, search and everything already described keep working, and no credits are taken for a picture that was not described. A picture the service could not answer for — a temporary error, or no answer at all — is set aside and tried again: a run in the background comes back to it ten minutes later, a run you are watching leaves it for your next press. A picture is marked as failed only when the service answers that the file itself cannot be described. Searching by meaning falls back to the ordinary word search, and your credit balance shows the last number it read until the service answers again.

= What happens if the plugin crashes my site? =

It tries to get out of your way. After two fatal errors in its own code within an hour it puts itself into safe mode: its features stop loading, the site comes back, and a notice in the dashboard tells you what happened and offers to switch them back on, or to deactivate the plugin. That is there so a white screen does not mean an FTP client and a renamed folder.

It only counts errors in its own files, and it never deactivates itself: that stays your decision.

= What happens to my folders if I uninstall it? =

They stay. The folders are terms in WordPress's own tables, not the plugin's, so deleting the plugin leaves them exactly where they are -- a reinstall picks them up as you left them, and so does any other folder plugin that reads the same `media_category` taxonomy. AI descriptions already written into your images stay written. Only caches and scheduled tasks are removed.

If you genuinely want everything gone, that exists too, in two forms on the Utilities page: a Complete Cleanup button that wipes immediately, and a switch that makes deleting the plugin from the Plugins screen take all its data with it. Neither is ever the default, and neither touches a media file.

= Where do I report a problem? =

Questions and problems go to the [issue tracker](https://github.com/vergelabsnathan/vergelabs-media-library/issues), which is read by the person who maintains the plugin. Licence holders write to support@vergelabsmedia.com; you can expect an answer within one business day, Monday to Friday.

= Where do I report security bugs found in this plugin? =

Please report security bugs found in the source code of the VergeLabs Media Library plugin through the [Patchstack Vulnerability Disclosure Program](https://patchstack.com/database/vdp/dc92736f-d80d-4eca-8412-aa5e58c3599a). The Patchstack team will assist you with verification, CVE assignment, and notify the developers of this plugin. You can also email security@in.vergelabs.nl.

= What does it include from other projects? =

* Enhanced Media Library 2.9.4 by wpUXsolutions, GPLv2 or later: the code this plugin is a fork of, and its webbistro icon font.
* Inter by The Inter Project Authors, SIL Open Font License 1.1 (fonts/INTER-LICENSE.txt).
* natural-compare-lite by Lauri Rooden, MIT License: the natural sort in js/eml-media-models.js.
* The admin screen layout follows WP Rocket's admin stylesheet (GPLv2 or later).
* The spinner images are WordPress's own (GPLv2 or later).



### Which versions of WordPress and other plugins has it been checked against? ###

Every night an automated watch looks for new releases of WordPress, PHP and the plugins and themes this plugin integrates with, greps each new release for every hook and field we rely on, upgrades a staging site and runs the checks there. What passed is recorded here, newest first:

* Yoast SEO (Premium follows the same numbering) 28.6 — contract intact, stage suites passed (2026-09-29)
* Polylang (Pro follows the same numbering) 3.8.10 — contract intact, stage suites passed (2026-09-28)
* Beaver Builder 2.11.0.6 — contract intact, stage suites passed (2026-09-26)
* Elementor 4.3.2 — contract intact, stage suites passed (2026-09-25)
* PHP 8.5.11 — contract intact (2026-09-25)
* WooCommerce 11.1.2 — contract intact, stage suites passed (2026-09-23)
* Rank Math SEO 1.0.279 — contract intact, stage suites passed (2026-09-22)
* Dokan 5.1.1 — contract intact, stage suites passed (2026-09-10)
* Brizy 2.8.23 — contract intact, stage suites passed (2026-09-09)
* Divi 5.11.0 — Folder gallery module renders from a built page (2026-09-01)
* Polylang Pro 3.8.6 — a translated image keeps its folders (2026-09-01)
* Yoast SEO 28.4, Rank Math 1.0.277.2, SEOPress 10.1, All in One SEO 5.0.1.1 — page context reaches the describe request (2026-09-01)
* WooCommerce, Advanced Custom Fields — product context and duplicate repoint (2026-09-01)

## Screenshots ##

1. The media library grid, with filters for type, date, author and any taxonomy you have assigned, plus Reset All Filters and Bulk select.

2. List view, with your media taxonomies as sortable columns and filters above the table.

3. Media Library settings: ordering, filters, search fields, grid captions and infinite scrolling.

4. Media Taxonomies settings: assign existing taxonomies to media or create your own.

5. MIME Types settings: label WordPress's file types and allow or disallow uploading them.

6. The media modal inside the editor, with the same filters available when you insert an image.



## Upgrade Notice ##

### 4.1.0 ###
New: Plan my folders on the Folders screen builds a folder tree from what your pictures show. It costs credits (30, plus 15 per 100 distinct labels; the button shows the price) and asks for them back if the plan would not beat your current folders.

## Changelog ##

### 4.1.0 ###
*A folder plan built from what your pictures show.*

= Added =
* **Plan my folders.** On the Folders screen, one button builds a folder tree from every label the AI has recorded about your pictures. It costs 30 credits plus 15 per 100 distinct labels; the price is on the button, and it needs at least five described pictures.
* **Your tree grows for free.** Once you confirm a tree, new pictures with new labels get new folders proposed at no charge.
* **A plan that does not beat your folders is not shown, and its credits are asked back.** The same happens if the plan does not arrive or you confirm your tree while it runs.
* **The audience is asked, not guessed.** When your pictures do not show whether they are for men, women or kids, the Tree step asks once before splitting folders that way.

= Fixed =
* **A licence with no free seat now says so.** Saving or connecting one used to report success, and every AI request after it failed.

= Changed =
* **The known-problems list comes from the AI service** at `ai.vergelabs.nl`, not from GitHub. It is still fetched only when you press "Check for known problems".
* **The plugin's admin screens have new addresses**, all starting `vergeml-` (for example `admin.php?page=vergeml-ai`). Bookmarks to the old ones are sent on to the new.
* **Library-wide work is an administrator's.** The dashboard, Folders, AI, Health and Import screens, and everything that runs across the library, spends credits or calls the AI service, now need the Administrator role. Editors keep the folder tree in the media library: filing, and changing one folder or one file at a time. Making a folder private, the smart-folder scan and search by meaning are an administrator's.
* **File types: WordPress's own only.** The File types screen switches WordPress's known types on and off; it no longer adds new ones, and types that can run code -- PHP, HTML, JavaScript, SVG, executables -- are never allowed. A type saved earlier that WordPress does not know is left out, and the screen names it.
* **The plugin never deactivates itself.** After repeated crashes it still switches its own features off, and the notice offers a Deactivate button. Delete All Data no longer deactivates the plugin either; deleting it afterwards from the Plugins screen takes the rest.
* **Settings > Media is WordPress's own screen again.** The plugin's copy of it is gone; its old address opens WordPress's.
* **With Enhanced Media Library active, nothing of it is switched off any more.** A notice on the media screens asks you to deactivate one of the two.
* **Describing goes one picture at a time, through WordPress's own HTTP functions**, so proxy and firewall settings apply to it. A run takes longer than before.

Earlier versions are in `changelog.txt`, shipped with the plugin.
