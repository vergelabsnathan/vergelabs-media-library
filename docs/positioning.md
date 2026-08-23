# Where the combination wins

Three inputs, not two: FileBird's interaction quality, Premio's substrate choice, and the
taxonomy system this plugin has had for a decade. Every claim below traces to code or to a
measurement, because a comparison page that overstates is worse than none.

## The one-line version

**FileBird gives you a good tree over data you cannot take with you. Premio gives you portable
data under a tree that fights core. We give you a tree over the data you already have.**

## What each brings

| From | What |
|---|---|
| **FileBird** | The interaction bar: real tree, drag, per-user state, counts that roll up through descendants. This is why people love it and it is worth matching exactly. |
| **Premio Folders** | Proof that native terms are a viable substrate — portable, REST-visible, survives uninstall. They validated the bet. |
| **Enhanced Media Library (ours)** | The filing system itself: any number of taxonomies on attachments, hierarchical *and* flat, plus search across them, bulk filing, auto-assign on upload, MIME control. |

Neither competitor has the third column. That is the whole argument.

## Capabilities that only exist in the combination

### 1. Folders over data that is already there

FileBird and Folders both require you to *build* a filing system from nothing. Every existing
media file starts unfiled.

This plugin has been storing media categories since Enhanced Media Library. On upgrade, those
terms become the folder tree — **no import, no migration, nothing to rebuild**. A site with ten
years of categorised media gets a folder tree over it the moment 3.1 installs.

Neither competitor can offer this to anyone, because neither has anyone's data.

### 2. More than one filing system over the same library

FileBird's schema (`includes/Install.php`) has a single `parent` column: one tree, permanently.
Folders registers one `media_folder` taxonomy.

This plugin lets the site owner define any number of media taxonomies. So a photographer can run
a **Clients** tree and a **Projects** tree and a **Usage rights** tree over the same files, each
independent, switched from the tree header (T1, decision 4).

For FileBird this needs a schema change. For Folders it needs a product rewrite. For us it is a
dropdown, because the substrate was always plural.

### 3. A file in more than one folder

FileBird cannot: `folder_id` is one column on one join row. Folders could — terms permit it —
but the product is single-folder, so it never surfaces.

Ours is the default (T1, decision 1), with Ctrl-drag to move and a "one folder per file" toggle
for people who want the old behaviour. This is the single feature neither can match without
rebuilding, and it is why decision 17 (dual counts) matters: a folder showing `3` when the
library holds `2` files is correct and must read as correct.

### 4. Folders and tags together

Media taxonomies here can be non-hierarchical. So: **folders for structure, tags for the things
that cut across it** — "needs alt text", "client approved", "hero image" — on the same file, in
the same UI, filterable together.

Neither competitor has tags for media at all. The tree replaces the dropdown only for
hierarchical taxonomies (T1, decision 3) precisely so flat ones keep working alongside it.

### 5. Search that reaches into the structure

`core/search.php` already widens WordPress's title/caption/description search to filenames, the
uploader, and **any term the file is filed under**. Combined with the tree, that is find-inside-
this-folder over a library the size of the whole site.

### 6. The folders are in the REST API

Enabled in T0. FileBird's folders live in custom tables and are invisible to `wp/v2/media`
entirely — the block editor, a headless front end, or any other plugin cannot see them. Ours are
terms with `show_in_rest`, so everything that speaks WordPress can read them.

### 7. It does not take the site down

`core/watchdog.php` catches a fatal, escalates to safe mode, then deactivates — no FTP required.
Neither competitor has anything of the kind. For a plugin that hooks the media library on every
admin page, that is not a nice-to-have.

### 8. Numbers instead of adjectives

Measured on real MariaDB at 20,000 attachments: **4 queries, flat from 200 to 2,000 folders,
46ms**, against core's own 6 queries for a single page of 40 items. `tests/perf/bench.mjs`
reports query count, which is identical in Playground and on real hardware because it is a
property of the algorithm.

Nobody in this category publishes numbers. We can, and they are good.

### 9. Free where they charge

Their entire paid "folder tree themes" feature is a three-value hex constant with a nag in free
saying the switcher is preview-only. Ours is four real skins including one that derives from the
user's own admin colour scheme — free.

## Where we are behind, honestly

Worth writing down, because a comparison page that only flatters is not believed and because
these are the things that will actually cost us.

- **No galleries, no page-builder integrations.** FileBird Pro turns a folder into an Elementor
  widget, a Divi module and a Gutenberg block. We have decided not to follow: three companies'
  release schedules is a permanent maintenance surface for one person.
- **Attachments only.** Both competitors file posts, pages and custom post types. T1 leaves the
  seam open; it is not built.
- **No users, no reviews, no track record.** They have years of both.
- **Multi-folder is a support burden as well as a feature.** Counts exceeding the file total is
  correct and confusing. The copy has to carry it from day one.
- **Nothing here is shipped yet.** T1 is a plan.

## What this makes the comparison pages say

- **vs FileBird** — "Everything you like about the tree, over folders that are ordinary
  WordPress categories. Uninstall us and keep your structure."
- **vs Folders** — "The same native terms, with a tree that does not replace core's media
  browser, plus tags, plus a file that can live in two folders."
- **vs both** — "You may already have folders. If you have used Enhanced Media Library, your
  categories become the tree on upgrade — nothing to import."
