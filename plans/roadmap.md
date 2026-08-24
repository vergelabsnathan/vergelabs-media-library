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

## Next, in order

### 1. T2 — the media modal

The largest hole and the reason it goes first: the tree does not exist when you
insert an image into a post, which is half of what a media library is for. Seven
contexts (insert into post, featured image, gallery, replace, block editor's own
media flows, the upload tab, and the customiser) and each can break separately.

Needs its own `/prime` -- seven contexts is exactly where things break quietly.

### 2. T3 — importers

Highest value per hour on the list, because it is the acquisition path. FileBird's
own `Controller/Import` showed the shape: **one generic term importer** covers
every taxonomy-based rival, plus a custom reader per custom-table one.

Slugs, taken from their table: `media_folder` (Premio Folders), `happyfiles_category`,
`wpmf-category` (WP Media Folder), `wf_attachment_folders` (Wicked Folders),
`feml-folder`. Custom tables: FileBird's `fbv` / `fbv_attachment_folder`, and Real
Media Library.

### 3. Manual folder ordering

`vergeml_order` term meta is already registered and unused, and the tree already
sorts in PHP. This is a drag handler and a save. FileBird has an `ord` column and
we have been sorting alphabetically, which reads as missing rather than as a
choice.

### 4. Folders for posts, pages and custom post types

Cheaper here than for them: taxonomies already attach to any post type, so this is
`register_taxonomy_for_object_type` plus a panel on the list screens and a setting
for which post types. They needed a `type` column and an entire addon.

### 5. CSV import and export

Terms and assignments out, terms and assignments in. Cheap, and it is the answer
to "how do I set up two hundred folders without clicking two hundred times".

### 6. WPML and Polylang

Also cheaper here: both translate taxonomies natively. FileBird had to write
`Support/WPML.php` to keep custom tables in sync. Expect mostly testing plus a
shim where the tree passes term ids around.

### 7. Per-user folders

Term meta for an owner and a filter on the tree. The work is not the storage, it
is deciding what a file inside a folder only one person can see is supposed to do
when somebody else opens the library.

### 8. Galleries

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
