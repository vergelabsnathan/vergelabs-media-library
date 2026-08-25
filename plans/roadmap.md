# Roadmap: parity with FileBird, then past it

Decided 2026-08-24. Everything FileBird has, in this order, with Divi deferred.

The ordering is by what the absence of it costs, not by how hard it is. Most of
this list is **cheaper on taxonomies than on their custom tables**, which is worth
saying plainly because it is the opposite of what the feature list looks like from
outside.

## Done

**T0 — the endpoints.** `vergeml/v1/tree` and `/assign`, 4 queries flat from 200
to 2,000 folders. Media taxonomies made REST-visible, with a migration.

**T1 — the tree.** Grid and list, drag on jQuery UI, folder CRUD with the
self-descendant guard, undo, four skins, keyboard and ARIA, windowed rendering,
first paint with no request, and "one folder per file" for switchers.

**T2 — the media modal.** The tree in every wp.media frame: insert into post,
featured image, gallery, replace, the block editor's own flows, the upload tab
and the customiser. Filtering sets the frame's own library props rather than
faking a search, and the panel is removed on close -- wp.media detaches frames
instead of destroying them, so mounting without removing leaked a fresh tree on
every open.

**T3 — the importers.** Seven sources, two readers. Plan, run, undo, all chunked
and resumable in both directions. Proven against FileBird's real tables: 200
folders and 16,000 files in ~29s, undone in ~17s, their tables untouched.

**T4 — arranging by hand.** `order` on the folder endpoint takes a whole sibling
list and re-parents while it is at it, so dragging a folder between two others in
another branch stays one gesture. Drop zones on the top and bottom thirds of a
row, an insertion line, and Alt+Up/Down for anyone not using a mouse. Folders
created or moved into an arranged branch land at the end of it rather than the
top.

Three bugs came out of building it, all of them older than it:

- **paint() decided nothing had changed by counting rows.** Anything that changed
  a row without changing how many there were -- a rename, a colour, a file count,
  an arrangement -- was fetched, stored and never drawn until the next reload.
- **A remembered selection outlived its folder.** Delete the folder you were
  looking at and every later visit filtered the library to a term that was gone,
  so the media library came up empty and stayed empty.
- **Folder colours had an endpoint, a palette and a rendered icon, and no control
  anywhere.** Now in the toolbar, eight colours, each one named.

**T5 — folders on posts, pages and custom post types.** One argument to
`register_taxonomy`, chosen per taxonomy in Media Taxonomies. The tree appears
beside any list screen it is turned on for, filters it, and takes a dragged row.

The work was not the storage, it was the counting: a folder now answers two
different questions and only one number is stored on it. The stored count stays
the *attachment* count, because that is what the media library, the filter
dropdown and the Media Categories screen all read; post counts are one grouped
query when a post screen asks. That costs the tree one extra query there and
nothing at all on the media library.

Two bugs came out of it, both older:

- **A media taxonomy on posts stopped counting files.** Inherited code swapped in
  a counter that counts everything *except* attachments whenever a taxonomy was
  on both, so a folder holding one image and three posts reported three files --
  on every screen that reads the stored count, all wrong together.
- **A settings write that omitted the labels unregistered the taxonomy.** No
  tree, no filters, no Media Categories screen, every term still in the database.
  Every folder gone from the site with nothing to say why. Registration now names
  an unlabelled taxonomy from its slug instead of skipping it, and the sanitiser
  carries labels it was not given.

**T6 — page builders.** Elementor and Divi both open wp.media, so neither needed
an interface of its own -- only an entry point. Elementor's editor has its own
enqueue hook; Divi's visual builder runs on the front end, where
admin_enqueue_scripts never fires at all. Verified in both, in a real browser.

**T7 — the folder gallery block.** The query has existed since Enhanced Media
Library; what it never had was a way to reach it. Pick a folder from a list,
choose columns, size, order and what the images link to, and see the real
gallery in the editor -- it renders on the server, so there is one renderer
rather than two that drift.

The point of it is that it stays a folder: add an image to Press and every page
showing that gallery has it, with nothing re-edited. Core's gallery block freezes
a list of ids at the moment you insert it. Proven end to end -- 6 images, then 7,
on a published page nobody touched.

Core's own `wp-block-gallery has-nested-images` markup, so themes style it
without knowing we exist.

## Next, in order

### 1. CSV import and export

Terms and assignments out, terms and assignments in. Cheap, and it is the answer
to "how do I set up two hundred folders without clicking two hundred times".

### 2. WPML and Polylang

Also cheaper here: both translate taxonomies natively. FileBird had to write
`Support/WPML.php` to keep custom tables in sync. Expect mostly testing plus a
shim where the tree passes term ids around.

### 3. Per-user folders

Term meta for an owner and a filter on the tree. The work is not the storage, it
is deciding what a file inside a folder only one person can see is supposed to do
when somebody else opens the library.

### 4. Galleries: DONE

One renderer, five doors -- the Gutenberg block, the Elementor widget, the Divi
module, the WPBakery element, and a first-party `[vergeml_gallery]` shortcode
that needs no builder and hides behind no setting. Carousel and lightbox live in
the renderer, so every door has them. All five proven on the front of the site
in each host's own save format.

Still open, small: a "copy the shortcode" action on a folder in the tree.

A folder becomes a gallery. Split deliberately, because the three integrations are
not one job:

- **Gutenberg block** — first. A dynamic block rendering server-side needs no
  build step, which keeps the no-build rule intact.
- **Shortcode** — same renderer, covers every page builder that accepts one, and
  costs almost nothing once the block exists.
- **Elementor widget** — a PHP widget class. Moderate, and only once somebody asks.
- **Divi** — **deferred.** Divi 5 is a rewrite rather than a version bump, and
  building against it now means rebuilding against it shortly. Revisit when it has
  settled, or when a paying customer needs it.

## What this does not change

The free plugin stays free and complete. Pro sells AI descriptions -- alt text,
captions and tags -- which neither competitor has at all. None of the above moves
behind the paywall: FileBird charges for post-type folders and per-user folders,
and shipping those free is a straight comparison line.
