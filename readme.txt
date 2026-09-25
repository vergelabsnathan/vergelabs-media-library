=== VergeLabs Media Library ===
Contributors: vergelabsnathan
Tags: media library, media folders, alt text, accessibility, media categories
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 4.0.7
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Folders, categories and tags for the WordPress media library, with AI alt text and captions.

## Description ##

**Folders for your media library, categories that stay in WordPress, and alt text written for you.**

Folders that are real taxonomy terms, so nothing breaks when you deactivate. Categories and tags on every attachment, filterable in the grid and the list. Alt text and captions written by a model that has looked at the picture, one run at a time, only when you start it. Works on WordPress 6.5 through 7.1 and PHP 7.4 through 8.3.

Grown from [Enhanced Media Library](https://wordpress.org/plugins/enhanced-media-library/) by wpUXsolutions, GPLv2 or later as the original is; its settings carry over on activation.

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


### Developer-Friendly ###

* **Core hooks just work** for media taxonomies and media items
* **All taxonomies supported:** custom and code-registered
* **REST API supported** out of the box
* **No custom tables** in the database
* **Deactivation makes no harm to data:** all media items and taxonomies remain after deactivation


## External services ##

This plugin talks to three places: an AI service run by VergeLabs at `https://ai.vergelabs.nl/v1`, the VergeLabs site at `https://vergelabsmedia.com` when you connect a licence, and GitHub, to read a list of known problems when you ask for it. Without a licence key, nothing is contacted by installing, activating or opening a screen; every request below follows something you do. Everything below says what goes where, when, and what you have to do for it to happen.

**The AI features need a licence key, and do nothing without one** (a free trial key counts). With no key, no folder is filed, no picture is described, no search is answered by meaning, and no conversation can start -- the requests are refused before they are made. Demo mode invents captions locally from the file names and sends nothing anywhere. The folder tree itself, the smart folders, the health report, the importer, the galleries, the MIME settings and the Librarian's date-and-type scheme need no service at all.

**Describing a picture** sends a downsized copy of it (never the original, unless the original is already small), its file name, its MIME type, the picture's own title and caption, your site's address, your licence key and whether the site is production or staging. If the picture is used on a page, that page's title goes too; on a WooCommerce site, up to six of the product's category names. With "Page context" on -- it is on by default, and one switch turns it off -- the page's focus keyphrase, meta description and related keyphrases from Yoast, Rank Math, SEOPress or All in One SEO go as well, as wording for the model, never as instructions. What comes back is a caption, alt text, tags and a suggested title. No picture is sent on upload, and none on a page load: only while a run you started is in progress.

**Sorting pictures into folders** sends more than the pictures. When you ask for a plan, confirm one, fill your folders, or talk to the Folders screen, these go to the same service: **your folder names, their parents and how many files are in each**, the captions the AI wrote for your pictures, the words it recorded about what they show, and anything you typed into the screen along with the conversation so far. On a WooCommerce site your product category paths go too. Searching by meaning sends the phrase you typed. None of this happens on its own: each of them is a button you press.

**The Folders screen talks to the service from your browser**, not from your server, for the part that streams a reply as it is written. That request carries what you typed, your draft folder tree and the same library summary -- and, because it comes from your browser rather than your server, your own IP address and browser headers reach the service, as they would visiting any website.

**Trying it free** (the AI screen, on a site without a licence) sends the email address you type and your site's address to `https://vergelabsmedia.com/api/trial`, which answers with a free key for that site, holding 25 credits. It happens only when you press Start. The email address receives one message, once the 25 credits are used, with the key and a way to buy more.

**Connecting a licence** sends you to `https://vergelabsmedia.com`, carrying your site's address and the address of your admin screen, and sends back a one-time code your site exchanges for a key. Saving, checking or removing a key sends the key and your site's address to the AI service. Once a key is set, the plugin checks your remaining credits when you open a VergeLabs screen, at most once every five minutes.

**Asking for help** works without a licence key, as connecting one and the known-problems list below do. Pressing Send on the Get help screen posts what you typed, the email address you gave, the last four characters of your licence key if you have one (never the key itself), and a full system report: your site's address, your WordPress, PHP and MySQL versions, your server's limits, your theme, how many files you have, your folder and taxonomy counts, this plugin's settings, and **the name and version of every plugin you have active**. The screen shows you the report before you send it, and it will not send without the tick box.

**Library counts go only if you switch them on.** Under Library settings, "Share library counts" (off by default) posts to the same service once a day: how many files and folders, how deep the folders nest, how many files are of each broad type, files added in the last thirty days, and the plugin, WordPress and PHP versions with the site language, alongside the licence key and the site address. Never a file name, a title, a folder name or a picture.

**Known problems, fetched from GitHub.** Pressing "Check for known problems" on the Get help screen reads `known-issues.json` from this plugin's own public repository at `raw.githubusercontent.com` and compares it with what your site runs. It happens only when you press the button; the list is then kept for twelve hours, and if GitHub cannot be reached the screen says so rather than showing an empty list. It is a plain file download and carries no key and no counts -- though, like every request WordPress makes, it identifies itself with your site's address.

**Pointing it somewhere else.** `VERGEML_AI_SERVICE`, `VERGEML_AI_STREAM`, `VERGEML_SITE_URL` and `VERGEML_KNOWN_ISSUES_URL` in `wp-config.php` send these requests to a host of your choosing. There is no filter for any of them on purpose: a destination for your files should not be changeable by another plugin.

**What the service does with it, in writing.** [Sub-processors](https://vergelabsmedia.com/legal/sub-processors) names every company that touches the data and where each one is. [What is kept, and for how long](https://vergelabsmedia.com/legal/retention) answers that per category — images are kept for no time at all, and it says which file in the code proves it. [Data Processing Agreement](https://vergelabsmedia.com/legal/dpa), if you need one.

Service terms: [https://vergelabsmedia.com/legal/terms](https://vergelabsmedia.com/legal/terms) -- Privacy policy: [https://vergelabsmedia.com/legal/privacy](https://vergelabsmedia.com/legal/privacy)

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

= How is this different from Enhanced Media Library? =

It started as a fork of Enhanced Media Library 2.9.4 and keeps what that plugin did: media categories and taxonomies, filters, MIME types and dynamic galleries, with your settings carried over. On top of that it adds a folder tree with drag and drop, smart folders, an importer for other folder plugins, a Librarian that sorts an unfiled library, duplicate detection, and optional AI features that write alt text and captions and let you search by what a picture shows. It is not made by, or affiliated with, wpUXsolutions. The [upstream documentation](https://www.wpuxsolutions.com/documents/enhanced-media-library) is still accurate for the parts this fork did not change.

= What does this fork fix in the original features? =

* **The WordPress 7.0 toolbar layout.** WP 7.0 turned the media toolbar into a fixed two-column CSS grid and gave placement to its own two filters only. The extra filters this plugin adds had nowhere to go, so they stacked into a 300px-tall block with every label sitting above the wrong control. The toolbar is one tidy row again, whatever number of filters you enable.
* **The author filter drew on top of the type filter.** It rendered with the same HTML id as the type filter, which was invisible under the old layout but made the two overlap once WordPress started placing elements by id. It now uses the id its own label was already pointing at, which fixes the overlap and the mislabelled control together.
* **PHP 8 warnings.** The four settings handlers read their nonce field before checking whether it was there. Also four `get_terms()` calls still using the argument order deprecated back in WordPress 4.5.

= Can I run it next to another folder plugin? =

Running a second folder plugin at the same time is not recommended: both would register folders against the same media. Bring its folders across instead, under Settings → Import Folders, and deactivate it. Which releases of WordPress and of the plugins this one integrates with have been checked is listed near the end of this page.

= Can I take my settings to another site? =

If you need to move your media library to another website you should export and import WordPress content with WordPress built-in export/import. But to make this plugin work on the new site with the same settings you are provided with the export/import feature.

= Does it work on multisite? =

Network activate the plugin and choose which options will be available to your admins.

= Does it send anything to an external service? =

Yes, if you use the AI features -- and not only the pictures. Describing sends the picture; sorting into folders sends your folder names and what the AI wrote about your pictures; searching by meaning sends the phrase you typed; asking for help sends a system report with your plugin list in it. "External services" above is the full account, written out in one place.

All of it needs a licence key except the Get help screen and the known-problems list. Without a key the AI features refuse before they make a request, and demo mode makes its captions up locally so you can see the shape of the thing before paying for anything.

The folder tree itself, the smart folders, the health report, the importer, the galleries, the MIME settings and the Librarian's date-and-type scheme make no outbound requests at all. What does talk to the service is the part of the Folders screen that plans, fills and answers -- that is the AI, wearing the folder screen's clothes.

The original plugin polled its author's server twice a day for admin notices and printed whatever came back into your dashboard. That has been removed and nothing replaced it.

= What happens when the AI service is down? =

Your plugin keeps working without the AI features: filing, search and everything already described keep working, and no credits are taken for a picture that was not described. A picture the service could not answer for — a temporary error, or no answer at all — is set aside and tried again: a run in the background comes back to it ten minutes later, a run you are watching leaves it for your next press. A picture is marked as failed only when the service answers that the file itself cannot be described. Searching by meaning falls back to the ordinary word search, and your credit balance shows the last number it read until the service answers again.

= What happens if the plugin crashes my site? =

It tries to get out of your way. After two fatal errors in its own code within an hour it puts itself into safe mode: its features stop loading, the site comes back, and a notice in the dashboard tells you what happened and offers to switch them back on. That is there so a white screen does not mean an FTP client and a renamed folder.

It only counts errors in its own files, so it will never deactivate itself because a different plugin crashed.

= What happens to my folders if I uninstall it? =

They stay. The folders are terms in WordPress's own tables, not the plugin's, so deleting the plugin leaves them exactly where they are -- a reinstall picks them up as you left them, and so does any other folder plugin that reads the same `media_category` taxonomy. AI descriptions already written into your images stay written. Only caches and scheduled tasks are removed.

If you genuinely want everything gone, that exists too, in two forms on the Utilities page: a Complete Cleanup button that wipes immediately, and a switch that makes deleting the plugin from the Plugins screen take all its data with it. Neither is ever the default, and neither touches a media file.

= Where do I report a problem? =

Questions and problems go to the [issue tracker](https://github.com/vergelabsnathan/vergelabs-media-library/issues), which is read by the person who maintains the plugin. Licence holders write to support@vergelabsmedia.com; you can expect an answer within one business day, Monday to Friday.

= Where do I report security bugs found in this plugin? =

Please report security bugs found in the source code of the VergeLabs Media Library plugin through the [Patchstack Vulnerability Disclosure Program](https://patchstack.com/database/vdp/dc92736f-d80d-4eca-8412-aa5e58c3599a). The Patchstack team will assist you with verification, CVE assignment, and notify the developers of this plugin. You can also email security@in.vergelabs.nl.



### Which versions of WordPress and other plugins has it been checked against? ###

Every night an automated watch looks for new releases of WordPress, PHP and the plugins and themes this plugin integrates with, greps each new release for every hook and field we rely on, upgrades a staging site and runs the checks there. What passed is recorded here, newest first:

<!-- watch:verified -->
* Elementor 4.3.2 — contract intact, stage suites passed (2026-09-25)
* PHP 8.5.11 — contract intact (2026-09-25)
* Elementor 4.3.1 — contract intact, stage suites passed (2026-09-24)
* WooCommerce 11.1.2 — contract intact, stage suites passed (2026-09-23)
* Rank Math SEO 1.0.279 — contract intact, stage suites passed (2026-09-22)
* Beaver Builder 2.11.0.5 — contract intact, stage suites passed (2026-09-12)
* Beaver Builder 2.11.0.4 — contract intact, stage suites passed (2026-09-11)
* Dokan 5.1.1 — contract intact, stage suites passed (2026-09-10)
* Polylang (Pro follows the same numbering) 3.8.9 — contract intact, stage suites passed (2026-09-09)
* Brizy 2.8.23 — contract intact, stage suites passed (2026-09-09)
* Rank Math SEO 1.0.278 — contract intact, stage suites passed (2026-09-08)
* Dokan 5.1.0 — contract intact, stage suites passed (2026-09-08)
* Polylang (Pro follows the same numbering) 3.8.8 — contract intact, stage suites passed (2026-09-07)
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



## Upgrade Notice ##

### 4.0.0 ###
The Folders screen is rebuilt: build a tree, confirm it, fill it. Filing now reads what pictures show, so a fill after updating places things differently than before. The Rules tab is gone and the media list toolbar is one row. Read the changelog before you fill.

## Changelog ##

### 4.0.7 ###
*The plugin contacts nothing until you ask it to.*

= Changed =
* **No price is fetched on the AI screen.** A site without a licence sees how many credits its library needs; the price is on the cart the button opens.
* **The known-problems list is fetched only when you press "Check for known problems"** on the Get help screen. Opening the dashboard or the Help screen no longer downloads it, and when GitHub cannot be reached the screen says so instead of reporting no problems.
* **Versions before 4.0 moved from the readme to `changelog.txt`.**
* **Reference sections moved from the description into the FAQ**, so the plugin page shows the whole External services section instead of cutting it off.

### 4.0.6 ###
*Demo mode stays usable on a site without a licence, and code the original plugin kept for its paid edition is gone.*

= Changed =
* **Demo mode is never replaced by the free-try offer.** It runs on your site and needs no licence, so the AI screen keeps its Describe buttons while it is on.
* **Nothing can hold back applying a folder tree.** The hook the original plugin kept for a paid add-on is gone, and the Librarian applies every folder whatever any other code says.
* **Code the original plugin kept for its paid edition is removed**, including a Select all button only that edition could show.
* **The readme says which requests the free try and the price quote make**, under External services.

### 4.0.5 ###
*A site without a licence can try the AI free on 25 pictures, and sees what its own library would cost, instead of an error.*

= New =
* **Try it free on 25 pictures.** On the AI screen, a site without a licence enters an email address and gets 25 free credits for that site, connected by itself. Alt text is then written for 25 pictures without one. One free try per site and per email; not on local or test sites.
* **The AI screen says what is missing and what it costs.** Where pressing Alt text used to answer "Configure an AI endpoint and key first", a site without a licence sees how many of its pictures have no alt text, and a button that buys exactly those credits at the current price.

### 4.0.4 ###
*While the AI service is away, pictures wait instead of being marked as failed; a re-describe that fails keeps the description the picture already had.*

= Fixed =
* **While the AI service is away, pictures wait.** A picture the service could not answer for — a temporary error, or no answer at all — is set aside and tried again, for as long as the service is away; nothing is marked as failed for a failure that is not the file's. Before, an unreachable service marked every picture a run reached, and three temporary errors marked one for good.
* **A re-describe that fails keeps the description the picture already had.** It stays in search, filing and the counts; the failure is in the run's report only.
* **A run in the background whose pictures are all set aside waits ten minutes** instead of asking again at once.

### 4.0.3 ###
*Screen Options opens over the folder panel on the media list, not under it; the AI-outage answer in the FAQ says what a describe run does.*

= Fixed =
* **Screen Options opens over the folder panel on the media list, not under it.** 4.0.2 brought the tab back; the panel it opens was still under the folder tree, so the column checkboxes on the left could not be ticked.
* **The FAQ answer "What happens when the AI service is down?" says what the describe run does:** a temporary error sets a picture aside and tries again, an unreachable service marks it, and the Alt text button on the AI screen picks marked pictures up once the service is back. The previous answer promised more than the run did.

### 4.0.2 ###
*Dragging into a folder works again beside FileBird, and two tabs the folder panel had covered are back.*

= Fixed =
* **Dragging one file into a folder works again beside FileBird and other plugins that add a column to the media list;** the checkbox column no longer takes the table's spare width.
* **Screen Options and Help open again on the media list beside the folder panel;** a folder no longer lights up for another plugin's drag.

### 4.0.1 ###
*A support ticket that keeps your key to itself, a CSV export a spreadsheet can't turn into a command, and a PHP 8.4 warning that is gone.*

= Fixed =
* **The support ticket no longer carries your licence key.** It now sends the last four characters of the key and your site's address; the service matches those to a licence itself, and a free install still sends nothing.
* **A CSV cell that starts with `=`, `+`, `-`, `@`, tab or a carriage return no longer opens as a formula.** The export quotes it safely; importing it back strips the safety mark, so the cell round-trips unchanged.
* **A PHP 8.4+ deprecation notice on every AI request is gone.** `vergeml_ai_rest_status()` took an implicit-nullable parameter; it is explicit now.

### 4.0.0 ###
*A screen that builds your folders and fills them, filing that reads what a picture shows, and a shop that sorts itself*

= Folders: build a tree, then fill it =
* **The Folders screen is a workflow, not a conversation.** Tree, Fill, Describe, Alt text, Rename -- each step reachable by clicking, none of them a gate. The page opens without asking the AI anything; *Propose folders* is a button with its credit cost printed on it.
* **Paste your folder tree.** One folder per line, `Clothing > Hoodies` for depth. A folder that already exists at that place is reused. Refusals name the line: no name, deeper than five levels, more than five hundred folders.
* **Or build it by clicking** -- `+` on any row adds a child, `×` removes one, and a name may itself be a path.
* **Confirm locks the tree** and stores its words on the folders. Unconfirm offers to restore the classes a confirm replaced.
* **The words a folder files by are yours to edit.** They show as pills after the folder's name; `×` removes one and the count re-runs without asking the AI; `#word` adds one.
* **Built for a catalogue.** Parents start closed and carry their counts, a find box appears from ten folders, and hovering a closed parent previews what is inside it without opening it.

= Filing that reads what a picture shows =
* **One way of filing, with three honest answers:** it fits, it is between two folders, or nothing fits. A picture between two children of one folder goes in the parent rather than nowhere. A picture you placed by hand is never moved, and a locked folder is never filed into.
* **The number before you press is the number that happens.** The estimate on the Tree step and the fill itself now count with the same code.
* **It learns from your own folders.** A folder holding three or more described pictures is read over them, and a second round re-reads what the first left: *round 1: 553 placed · round 2: 87 more · 113 to sort*.
* **A "view" of your tree files nothing.** A branch that repeats names from elsewhere -- a sale, a season -- is recognised as a view. One such branch had been swallowing 201 of 626 pictures.
* **Why a picture is where it is** now shows on the picture's own edit screen and in the grid: the folder, the word that matched, the score, the folder it could not beat, and the batch someone approved.

= Shops =
* **One press turns your product categories into folders.** On a site that sells, the Tree step offers your own categories -- nine folders where it used to take four typed instructions.
* **Pictures are filed by the product they belong to**, before anything is guessed at: featured image, gallery, or uploaded to the product. Your product's category named the folder, so it is a fact rather than a judgement -- nothing is asked about where the picture might go. On a real shop that placed 32 of 33 pictures.
* **The steps come in a different order on a shop** -- Tree, Fill, then Describe -- because the products place their own pictures first and describing is only for what nothing placed.

= Alt text and describing =
* **Alt text never overwrites what you wrote.**
* **Changing the brief no longer re-describes your library by itself.** The count waits on a button that shows the credits before you press it.
* The AI screen's summary line updates when a run ends instead of waiting for a reload.

= The media library =
* **The folders panel is on the list view too**, not only the grid, and the table keeps its own width beside it.
* **The list toolbar is one row** -- the folder you are in, search, filters, the view switch and the pages. On a test site the first row went from 429 to 201 pixels tall.
* **A new filter, "Placed by hand"**, lists the pictures you moved yourself.
* **The media library opens at a quarter of a million pictures.** The counts behind the smart folders cost ten seconds of database time on every admin page and now cost 8.6 milliseconds. Above fifty thousand files the expensive ones say "not looked" rather than guessing.

= Fixed =
* **Six buttons did nothing at all, silently** -- Complete Cleanup, Restore default MIME types, Apply settings to the network and three taxonomy confirmations. Sixteen call sites named four functions that did not exist.
* **The reset-filters button crashed the media list on a fresh site**, taking the rest of the screen's JavaScript with it.
* **An update did not reach a browser that already had the old scripts.** The cache-busting string was built from timestamps that arrive as 1980 from a zip, so it never changed.
* **A folder with an `&` in its name** was matched against the stored `&amp;` and quietly failed to match itself.
* **Running beside Enhanced Media Library**, its older copy of the shared media scripts answered first and the grid ignored your folders.
* Pressing Move no longer waits half a minute before answering, and a fill can no longer stall behind a stuck cron.

= Changed, and worth knowing before you update =
* **Filing decisions have moved.** A fill after updating will place pictures differently than the same library did before.
* **The Rules tab is gone.** Paste, product categories, clicking or the conversation are the ways in.
* **The media list's toolbar is rebuilt as one row** -- the controls are all there, but not where they were.
* **Above fifty thousand files the smart-folder counts are not computed**, on purpose.
* **A confirmed tree refuses edits** until you unconfirm it.
* **With Enhanced Media Library active, its media scripts and grid template are now set aside** so the two do not fight.
* **The readme's "External services" section has been rewritten** to say everything that leaves your site, which is more than it used to say. Nothing new was added to what the plugin sends; the account of it was incomplete.

Versions before 4.0 are in `changelog.txt`, shipped with the plugin.
