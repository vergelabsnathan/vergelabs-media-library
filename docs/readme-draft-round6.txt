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

Folders that are real taxonomy terms, so nothing breaks when you deactivate. Categories and tags on every attachment, filterable in the grid and the list. Alt text and captions written by a model that has looked at the picture, one run at a time, only when you start it. Works on WordPress 6.5 through 7.1 and PHP 7.4 through 8.5.

Grown from [Enhanced Media Library](https://wordpress.org/plugins/enhanced-media-library/) by wpUXsolutions, GPLv2 or later as the original is; its settings carry over on activation. It is not made by, or affiliated with, wpUXsolutions.

[Source and issue tracker](https://github.com/vergelabsnathan/vergelabs-media-library)

### Folders ###

A folder tree sits beside the media library. Drag files onto a folder to file them, drag folders into each other to rearrange, rename in place, colour them, and arrange them in whatever order you want. It is in the list view, in the grid, and in the media panel that opens when you insert an image into a post.

**Your folders are ordinary media categories.** The folder structure is not kept in a table of ours. That means your structure is readable by WordPress itself, by your theme, by WP-CLI and by any other plugin — and it is still there, intact and usable, if you ever remove this plugin.

**A file can be in more than one folder.** Dragging a file moves it, exactly as it does on your desktop. Hold Ctrl while dropping and it is added to that folder as well.

**A folder can be a gallery.** Add the Folder gallery block in the block editor -- or the Folder gallery element in Elementor, Divi or WPBakery, or drop `[vergeml_gallery folder="12"]` anywhere shortcodes work -- pick a folder, and it shows every image in it.

**Smart folders answer questions.** Above your folders sit five that nothing was ever filed into: Unused media, Missing alt text, Large files, Unattached, and This month. Each is a live view of the library. Unused and Large need one scan, which reads every post once to learn which files are genuinely referenced.

**Already using another folder plugin?** VergeLabs Library → Import Folders reads FileBird, Premio Folders, WP Media Folder, HappyFiles, Wicked Folders, Real Media Library and Enhanced Media Library. It shows you what it will do before it does it, and the whole import can be undone from the same screen.

### Sorting a library nobody ever filed ###

**The Librarian shows you the folders before it makes any.** Point it at a library that was never organised and it draws the structure it would build from your own files. Rename a folder before it exists, or say "not this one" and those files stay where they are.

**Two ways to sort, and one of them needs nothing.** By date and file type, built from what WordPress already knows -- no account, no licence, nothing sent anywhere. Or by subject, grouped from what the pictures show, which is the part that uses the AI service described below.

**Undo is the feature, not the apology.** Every assignment the Librarian makes is written down, so undoing it removes exactly what it did and nothing else.

### More ###

* Unlimited **custom and third-party taxonomies** for media items
* **MIME types:** choose which of WordPress's own file types may be uploaded. Types that can run code or that a browser interprets -- PHP, HTML, CSS, JavaScript, SVG, XML, executables -- cannot be allowed.
* **Folders are ordinary terms** in WordPress's own tables; four tables of the plugin's own hold the AI descriptions, the Librarian's batches and moves, and the sorting runs

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

= Does it work on multisite? =

Network activate the plugin and choose which options will be available to your admins.

= What happens when the AI service is down? =

Your plugin keeps working without the AI features: filing, search and everything already described keep working, and no credits are taken for a picture that was not described.

= What happens if the plugin crashes my site? =

A fatal error in the plugin is handled by WordPress's own recovery mode. The plugin does not switch itself off and never deactivates itself.

If you want its features off, add `define( 'VERGEML_SAFE_MODE', true );` to `wp-config.php`. Folders, the media views and the AI features are then off; the System report and Get help stay, and nothing is deleted. A notice in the dashboard says so. Remove the line and they switch back on.

= What happens to my folders if I uninstall it? =

They stay. The folders are terms in WordPress's own tables, not the plugin's, so deleting the plugin leaves them exactly where they are -- a reinstall picks them up as you left them, and so does any other folder plugin that reads the same `media_category` taxonomy. AI descriptions already written into your images stay written. Only caches and scheduled tasks are removed.

If you want the rest gone, that exists too, in two forms on the Utilities page: a Complete Cleanup button that wipes immediately, and a switch that makes deleting the plugin from the Plugins screen take its data with it. Both keep your folders -- the `media_category` terms and which files are in them -- because folders are ordinary terms shared with other folder plugins; they remove the plugin's own settings, tables and other data. Neither is ever the default, and neither touches a media file.

= Where do I report a problem? =

Questions and problems go to the [issue tracker](https://github.com/vergelabsnathan/vergelabs-media-library/issues). Licence holders write to support@vergelabsmedia.com; you can expect an answer within one business day, Monday to Friday.

= Where do I report security bugs found in this plugin? =

Please report security bugs found in the source code of the VergeLabs Media Library plugin through the [Patchstack Vulnerability Disclosure Program](https://patchstack.com/database/vdp/dc92736f-d80d-4eca-8412-aa5e58c3599a). You can also email security@in.vergelabs.nl.

= What does it include from other projects? =

* Enhanced Media Library 2.9.4 by wpUXsolutions, GPLv2 or later: the code this plugin is a fork of, and its webbistro icon font.
* Inter by The Inter Project Authors, SIL Open Font License 1.1 (fonts/INTER-LICENSE.txt).
* natural-compare-lite by Lauri Rooden, MIT License (js/eml-media-models.js).
* WP Rocket's admin stylesheet, GPLv2 or later: the admin screen layout follows it.
* WordPress's own spinner images, GPLv2 or later.

### Which versions of WordPress and other plugins has it been checked against? ###

Every night an automated watch looks for new releases of WordPress, PHP and the plugins and themes this plugin integrates with, greps each new release for every hook and field we rely on, upgrades a staging site and runs the checks there. What passed is recorded here, newest first:

* Yoast SEO (Premium follows the same numbering) 28.6 — contract intact, stage suites passed (2026-09-29)
* Polylang (Pro follows the same numbering) 3.8.10 — contract intact, stage suites passed (2026-09-28)
* Beaver Builder 2.11.0.6 — contract intact, stage suites passed (2026-09-26)
* Elementor 4.3.2 — contract intact, stage suites passed (2026-09-25)
* PHP 8.5.11 — contract intact (2026-09-25)
* WooCommerce 11.1.2 — contract intact, stage suites passed (2026-09-23)
* Rank Math SEO 1.0.279 — contract intact, stage suites passed (2026-09-22)

## Screenshots ##

1. The media library grid, with filters for type, date, author and any taxonomy you have assigned.
2. List view, with your media taxonomies as sortable columns and filters above the table.
3. Media Library settings: ordering, filters, search fields, grid captions and infinite scrolling.
4. Media Taxonomies settings: assign existing taxonomies to media or create your own.
5. MIME Types settings: label WordPress's file types and allow or disallow uploading them.
6. The media modal inside the editor, with the same filters available when you insert an image.

## Upgrade Notice ##

### 4.1.0 ###
New: Plan my folders on the Folders screen builds a folder tree from what your pictures show. It costs credits; the button shows the price.

## Changelog ##

### 4.1.0 ###
*A folder plan built from what your pictures show.*

= Added =
* **Plan my folders.** On the Folders screen, one button builds a folder tree from every label the AI has recorded about your pictures. It costs 30 credits plus 15 per 100 distinct labels; the price is on the button.
* **Your tree grows for free.** Once you confirm a tree, new pictures with new labels get new folders proposed at no charge.
* **A plan that does not beat your folders is not shown, and its credits are asked back.**
* **The audience is asked, not guessed.** When your pictures do not show whether they are for men, women or kids, the Tree step asks once.

= Fixed =
* **A licence with no free seat now says so.** Saving or connecting one used to report success, and every AI request after it failed.

= Changed =
* **The known-problems list comes from the AI service** at `ai.vergelabs.nl`, not from GitHub. It is still fetched only when you press "Check for known problems".
* **The plugin's admin screens have new addresses**, all starting `vergeml-`. Bookmarks to the old ones are sent on to the new.
* **Library-wide work is an administrator's.** The dashboard, Folders, AI, Health and Import screens, and everything that runs across the library, spends credits or calls the AI service, now need the Administrator role.
* **File types: WordPress's own only.** The File types screen no longer adds new types, and types that can run code or that a browser interprets are never allowed.
* **The plugin never deactivates itself.** Delete All Data no longer deactivates the plugin either; deleting it afterwards from the Plugins screen takes the rest.
* **Safe mode is switched only in wp-config.php.** The plugin no longer enters it after crashes (see the FAQ).
* **The wipes keep your folders.** Complete Cleanup and the delete-on-uninstall switch leave the `media_category` terms and which files are in them.
* **Activation creates the plugin's tables itself.**
* **PHP mbstring and OpenSSL are checked**, with a notice if either is missing.
* **Scripts and styles load only on the plugin's screens and media frames.**
* **Describing goes one picture at a time, through WordPress's own HTTP functions**, so proxy and firewall settings apply to it.

Earlier versions are in `changelog.txt`, shipped with the plugin.
