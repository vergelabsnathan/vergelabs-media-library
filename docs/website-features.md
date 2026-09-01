# VergeLabs Media Library — what it does

Feature overview for vergelabsmedia.com, written from the shipping code at 3.15.0.
Every claim below traces to a file in the plugin or a route in the service.

---

## The premise, in one paragraph

WordPress has no folders. Every plugin that adds them invents a private table, and
your filing then lives somewhere only that plugin can read. **VergeLabs Media Library
puts a real folder tree over the taxonomy WordPress already has.** Your folders are
`media_category` terms — visible to your theme, to WP-CLI, to the REST API, to any
other plugin, and still there, intact, the day you remove ours. A folder plugin that
holds your organisation hostage is a folder plugin you cannot leave.

Everything except the AI works with no account, no key and no outbound request.

---

# Part one — the media library

### The tree, wherever media appears

Beside the media library in list view and grid, in the editor's insert-media panel,
and in the media modal of **twenty page builders** — Elementor, WPBakery, Divi 4 and
5, Beaver Builder, Brizy, Bricks, Oxygen, Breakdance, Avada/Fusion, Thrive Architect,
Cornerstone, Zion, YooTheme, BeTheme, Themify, Tailor, Oshine, LearnPress's front-end
editor, Dokan's vendor dashboard. Drag files onto folders, drag folders into each
other, rename in place, colour them, order them by hand. Counts roll up through
descendants.

### A file can be in more than one folder

Dragging moves, as it does on your desktop; Ctrl-drop adds. The same photo in
*Products* and in *Autumn Campaign* without a second copy existing — the one thing
single-folder plugins cannot do without a schema change. A setting makes every drop a
move for people who want the strict promise kept.

### More than one filing system over the same files

Folders are terms, and terms are plural: run a **Clients** tree, a **Projects** tree
and a **Usage rights** tree over the same library, switched from the tree header. Add
flat taxonomies alongside them — *needs approval*, *hero image*, *client supplied* —
so folders carry structure and tags carry what cuts across it. Unlimited categories,
tags and custom taxonomies, created in a few clicks, plus any third-party taxonomy
assigned to media.

### Filing without effort

* **Uploads land where you are.** Upload with a folder open and files arrive filed.
* **Auto-assign on upload.** Files uploaded while editing a post inherit that post's terms.
* **Keyboard filing.** A Move button, the `M` key with files selected, a typeable and
  arrowable folder list, `Shift+F10` for a folder's menu. Full RTL stylesheets.
* **Private folders.** On a site where several people upload, a folder can belong to
  its maker and stay out of everyone else's sidebar. It hides the folder, never the
  files — and the screen says so, because a folder that looked like a locked drawer
  would eventually be trusted with something that needed one.

### Smart folders — folders whose contents are a question

Five sit above your own and nothing was ever filed into them: **Unused media**,
**Missing alt text**, **Large files**, **Unattached**, **This month**. Each is a live
query drawn as a row in the tree. Unused and Large need one chunked scan that reads
every post once for genuine references — embedded images, pasted URLs, featured
images, galleries. *"1,400 images have no alt text"* and *"2 GB of this library is
unused"* are the two numbers most worth knowing about a media library, and no
parent-child folder table can answer either.

### A folder can be a gallery

The **Folder gallery** block, plus native elements for Elementor, Divi and WPBakery,
plus `[vergeml_gallery folder="12"]` anywhere shortcodes work. Grid or carousel, with
an optional lightbox. It stays a folder rather than freezing into a list: drop a new
image in and every page using that gallery has it, nothing re-edited. The core gallery
block cannot do this. The old `[gallery]` and `[playlist]` shortcodes also gain
`media_category` (or any taxonomy), `monthnum`, `year` and `limit`.

### Library health — duplicates, found two ways

An md5 finds the byte-identical re-upload; a **dHash** (the picture reduced to 9×8 grey
pixels) finds the same photograph exported again at another size or quality. Tidying
keeps the copy something actually points at — or the oldest, when nothing points at any
of them — and sets the rest aside. Deleting a duplicate repoints post content, featured
images, URL-bearing post meta, builder layouts, ACF fields and WooCommerce product
galleries onto the survivor.

### Set aside, which is not deleting

A file you think you are finished with leaves the media library and stays exactly where
it is: same file on disk, same URL, still working in every page that uses it. If
something you could not see was using it, nothing breaks and you find out. **Nothing may
be considered for removal for thirty days** — a floor, not a setting. There is no delete
button, no delete endpoint, and no code in the feature that removes a file. You can
download the whole set-aside list — ids, names, paths, sizes, reasons — and check it
against a backup. Taking something back is one click, always.

### Moving in, and moving on

* **Import from seven plugins.** FileBird, Premio Folders, WP Media Folder, HappyFiles,
  Wicked Folders, Real Media Library, Enhanced Media Library. You see what it will do
  first, it says which folders merge with ones you already have, and the whole import
  undoes from the same screen. Nothing is taken from the other plugin.
* **Folders as a spreadsheet.** Write the whole structure out as CSV, edit it anywhere,
  read it back — two hundred folders without two hundred clicks, and a way to take your
  structure with you.
* **A folder leaves as a ZIP**, sub-folders included as directories.
* **Uninstalling keeps your folders.** Deleting the plugin removes caches and scheduled
  tasks only. If you genuinely want everything gone, that exists too — on the Utilities
  page, never as a default, and it never touches a media file.

### The library screen itself

Filters for type, date, author and every taxonomy you assign, configurable per taxonomy
and per filter, with include/exclude child categories. Taxonomy columns in list view,
sortable. Search widened past core's title/caption/description to filenames, uploader and
every term a file is filed under. Bulk select with no special mode, drag-and-drop
reordering, grid captions (title, filename or caption), infinite scroll and per-page
loads. Full MIME type management: add, remove, rename, allow or disallow uploading, and
fold a type into the filters.

### It behaves on real sites

* **Multilingual.** With Polylang or WPML a translated copy lands in the original's
  folders, and one set of folders shows in every language. A filter switches to
  per-language folders if you want them.
* **Multisite.** Network activation provisions every site, including ones created later;
  a network-wide licence sites inherit; a per-site overview; per-site safe mode so one
  subsite's fatal cannot deactivate the network.
* **Safe mode.** Two fatals in its own code within an hour and the plugin steps aside:
  features stop loading, the site comes back, a dashboard notice offers to switch them on
  again. It only counts errors in its own files. No FTP client, no renamed folder.
* **Neighbours.** A notice when a second folder plugin is active, and exclusions so
  optimisers leave the scripts alone.
* **Measured, not adjectived.** 20,000 attachments on real MariaDB: 4 queries, 46 ms, flat
  from 200 to 2,000 folders — against core's 6 queries for a single page of 40.
* **A nightly watch** greps each new release of WordPress, PHP and every integrated plugin
  for the hooks we rely on, upgrades a staging site and runs the checks. What passed is
  published in the readme, dated.

### Built the WordPress way

No custom tables for your data. Folders in the REST API out of the box. Core hooks work.
Deactivation harms nothing. Export/import/restore of plugin settings. GPLv2.

---

# Part two — the AI

**One pass, then everything reads from it.** Each image is shown once to a vision model,
which returns a caption, alt text, tags, a human title, a handful of attributes (what kind
of thing it is, whether there are people in it, whether there is text in the picture) and
an embedding. All of it is stored per file in an indexed table. Every AI feature below
reads that description — nothing asks the model twice.

### Descriptions and alt text

* **Alt text written for screen readers, not search engines.** Never empty, one sentence,
  no "image of" preamble, nothing invented that is not visible. In your site's language.
* **Backfill from descriptions you already paid for.** Only where alt is empty, never over
  your own words. Everything it writes is marked, so the whole lot comes back out again.
* **Page context.** An image inherits the intent of the page it sits on: that page's title,
  and — with Yoast, Rank Math, SEOPress or All in One SEO — its focus keyphrase, meta
  description and up to three related keyphrases. Strictly advisory: the model is told to
  use it for wording and never to repeat a keyphrase back at a picture that does not show
  it, because keyphrase-stuffed alt text is exactly what those same SEO plugins mark down.
  One switch, on by default; off, nothing about your pages leaves the site.
* **Fix alt text on your SEO pages.** Finds images with no alt text on the pages your SEO
  plugin has given a focus keyphrase — the ones you are already being marked down for — and
  describes those first, cornerstone and pillar pages ahead of the rest.
* **In the background.** The run carries on after you close the tab and resumes exactly
  where it stopped; twenty thousand images no longer need a browser held open. It stops by
  itself when credits run out or the licence is refused, and says which.

### Finding things

* **Search by meaning.** "Seaside" finds a beach; "dog" finds a golden retriever. Your typed
  phrase is embedded and compared against every described file. A compact projection keeps a
  20,000-image search under half a second. It sits beside the ordinary search, not over it.
* **More like this one.** Free and instant, with no AI call at the moment you ask — it
  compares descriptions your library already holds.

### Sorting a library nobody ever filed

* **The Librarian shows you the folders before it makes any.** Point it at an unorganised
  library and it draws the structure it would build: every folder with its file count, a few
  thumbnails, and a line saying why those files were grouped. Rename a folder before it
  exists, or say "not this one" and those files stay put.
* **Two schemes, and one needs nothing.** By date and file type, from what WordPress already
  knows — no account, no licence, nothing sent anywhere. Or by subject, grouped from what the
  pictures show.
* **Folder talk.** Change the proposal by saying so: *"drop nature, I want buildings, split
  into modern and classic."* Propose and apply are separate steps — you read the difference
  before anything changes, and applying is bound to the plan you were shown, for fifteen
  minutes, once.
* **Undo is the feature, not the apology.** Every assignment is written down, so undo removes
  exactly what was done and nothing else. A file you moved yourself since is left where you
  put it and reported. A created folder is deleted only if still empty. It only ever touches
  files that had no folder, so a hand-organised library is untouched.
* **Nothing that cannot be interrupted.** Small batches, no shared-host timeouts. Pause it,
  close the tab, pick it up.

### Filing that keeps going

* **Auto-file on upload.** A described file has an embedding; a folder full of described
  files has a middle — so "which folder does this belong in" is arithmetic, not a model call.
  Suggesting is the default; **filing itself is earned per folder**, and a folder that has
  never had a suggestion accepted never files anything by itself.
* **Say what you want, in words.** *"Move the screenshots into Products."* An allowlist of
  four verbs — move, tag, rename, create. **Delete is not on it, and there is no delete in the
  file to reach.**
* **Folders built from what was found.** Photos, Screenshots, Documents, Logos, pictures with
  people in them, pictures with text in them — a group in the panel drawn from the index
  rather than from anything you filed. Empty ones are hidden, and the group says how much of
  your library it covers.

### Where you are, and what to do next

A single screen that knows the order: what has been scanned, what has been described, what has
been proposed, what is ready — and turns each state into one sentence you can act on. The
plugin's eight screens are one tool with a nav, not eight settings pages.

### Privacy, in writing

* **Nothing is sent until you enter a licence key and start a run.** No request on page load,
  none on upload, none in the background. **Demo mode** invents captions locally from filenames
  and sends nothing anywhere, so you can see the shape of it before paying.
* **What is sent, per image, only during a run:** a downsized copy (never the original), its
  filename, its MIME type, your site address, your key — and, with page context on, the page
  title and SEO wording.
* **What comes back:** caption, alt text, tags, a suggested title, and your remaining credits.
* **What is kept:** a usage count. The image is processed and discarded, and not used to train
  anything.
* The plugin talks only to `https://ai.vergelabs.nl/v1`, hard-coded — no filter, because a
  filter would let any co-installed plugin redirect requests carrying your key.
* Describing is refused on a staging or development copy unless you say otherwise.
* Published: [Sub-processors](https://vergelabsmedia.com/legal/sub-processors) (who touches the
  data and where), [Retention](https://vergelabsmedia.com/legal/retention) (per category, naming
  the file in the code that proves it), and a [DPA](https://vergelabsmedia.com/legal/dpa).

### Credits

One credit is one image described. Prepaid, and they do not expire while the licence is active.
At zero the work pauses and nothing is billed afterwards. Credits are metered **before** the
image is sent, and **every failed image is refunded** — you pay for descriptions, not attempts.
Large backlogs are split into jobs of up to 1,000 so a failure costs a chunk, not the run.

---

# Reference — plans and prices

Quoted in EUR, USD or GBP by the visitor's country (from Vercel's edge header — no IP lookup
service, nothing about the visitor sent anywhere).

| Licence | Sites | Credits included per year | EUR | USD | GBP |
|---|---|---|---|---|---|
| Single | 1 | 2,000 | €39 | $45 | £35 |
| Five | 5 | 5,000 | €79 | $89 | £69 |
| Agency | unlimited | 20,000 | €249 | $279 | £219 |
| Lifetime | 1 | 2,000 every year, one payment | €149 | $169 | £129 |

| Credit pack | EUR | USD | GBP |
|---|---|---|---|
| 2,000 | €29 | $34 | £25 |
| 5,000 | €59 | $69 | £49 |
| 20,000 | €179 | $199 | £155 |

Licences are metered on credits, not gated on sites — most plugins gate on sites because sites
are countable; here the thing that actually costs money is the images.

---

# Notes before this goes on the site

1. **Do not advertise the on-disk file renamer.** It is switched off behind
   `VERGEML_FILE_RENAME` since 3.13.2 and does not yet rewrite builder layouts, field data or
   scaled originals. The keyphrase-led-filenames story (3.13.0) is part of it — leave both out.
2. **One product or two.** The AI ships *inside* the main plugin, gated by a licence key, while
   `pro/` is a separate add-on at 1.0.0 offering a subset (describe, licence, updates). The site
   should tell one story. Recommended: **one plugin, free, with paid AI credits** — it matches
   the code, and it is the honest version of "free plugin, hosted service".
3. **Speed tiers are gone.** Overnight/standard/priority all cost 1 credit now; the names only
   survive because older plugin versions send them. Don't put tiers on a pricing page.
4. **Page-builder honesty.** Elementor, WPBakery, Beaver, Brizy, Zion and Divi 5 were verified
   in a browser; the other fourteen use each builder's documented hook and are best effort. The
   readme says so, and the site should too.
