# Phase 4a — AI smart folders

## Problem

The index has held answers since 3.2.0 that nothing ever asks for.

`{prefix}vergeml_ai_index` stores, per described attachment, a `kind` enum
(photo, illustration, screenshot, document, diagram, logo), `has_people`,
`has_text`, a `document_type` (invoice, receipt, contract, form, report) and an
`orientation` — three of them with a `KEY` on the table. `core/ai.php` writes
them on every describe run and the mock writes them too, so a demo-mode library
is fully populated. Nothing reads them. Grep for `kind` outside `ai-index.php`
and `ai.php` and the only caller is `core/organize.php`, which uses it to build
the Librarian's date/type scheme — one internal consumer, no user-facing one.

Meanwhile `core/smart-folders.php` has exactly the machinery this needs and
five folders in it, none of which know the index exists. `vergeml_smart_folders()`
is the registry, `vergeml_smart_query_args()` is the single translation from a
key to a query, and three surfaces already share that translation: the grid's
`ajax_query_attachments_args` filter, the list screen's `pre_get_posts`, and
`vergeml_smart_counts()`. The extension point is built. Nothing extends it.

So a site that has paid to have eight thousand files described cannot ask
"show me the screenshots", and the plugin cannot show it the one thing the
description was for.

## User story

As somebody whose library has been described, I want the questions the
descriptions can answer to appear as folders beside my real ones — screenshots,
documents, pictures with people in them — so that I can find and file by what a
file *is* without having named anything myself.

## Decisions taken

Every line below was answered explicitly in the prime interview (27-08-2026).

- **Enum folders only.** Similarity ("show me what looks like this") is out.
  It cannot be expressed in SQL, `organize.php` does distance in PHP over
  projected vectors in a stepped loop, and doing that at view time is the N+1
  the query budgets exist to prevent. Its own cycle, later.
- **A fixed set, not a builder.** We choose which questions are worth asking,
  the way the existing five were chosen. No screen, no stored definitions, no
  option migration for user-made folders. **But the registry becomes
  filterable**, so a builder can later sit on top without a rewrite.
- **Thirteen rows**, in a fixed order we control:
  six for `kind` (Photos, Illustrations, Screenshots, Documents, Diagrams,
  Logos), one for `has_people`, one for `has_text`, five for `document_type`
  (Invoices, Receipts, Contracts, Forms, Reports).
- **Orientation is not one of them.** It is the one column the plugin computes
  itself from the image metadata rather than something the model saw, so it
  does not belong under a heading that promises AI. It stays where it is.
- **Their own group in the tree**, under the existing smart folders, with its
  own heading. The five existing ones are about tidying up; these are about
  content. Different questions, and they behave differently when nothing has
  been described.
- **A folder with no matches is not shown.** Same rule as the Unfiled row: it
  appears when there is something in it. A photo library does not see five
  empty document types.
- **Nothing described yet is a ladder, not an empty list.** The group shows one
  row that says nothing has been looked at and links to the AI screen
  (`admin.php?page=media-ai`) where a run can be started — the Librarian's
  ladder pattern, and the same rule the scan-backed folders already follow:
  "we have not looked" and "there are none" are different answers.
- **Counts ride in the existing UNION.** `vergeml_smart_counts()` is one
  statement today and the tree endpoint's budget of six queries depends on it
  staying one. The AI counts are extra branches on the same statement.
- **A half-described library counts what is described and says so.** The number
  is the count over described rows; the group also carries how many files have
  not been looked at yet, and the panel shows it. A count of 40 on a library
  where 200 of 8000 files are described must not read as "40 screenshots".
- **Selection replaces, it does not combine.** Picking an AI folder clears the
  real-folder selection and vice versa, exactly as the existing smart folders
  behave. No intersection; that is a filter idea, not a folder idea.
- **Dragging out of an AI folder works.** The whole daily-use win is "show me
  every screenshot, drag them into Screenshots". The AI folder is the source,
  a real folder the destination, and the existing assignment code does the
  work. There is no "file all of these" button — that is 4b, and 4b has an
  autonomy gate this does not.
- **Unknown enum values are dropped silently.** The service contract allows a
  `kind` we do not know; `ai-index.php` stores it verbatim and it matches no
  row. No "Other" row. It follows that the rows do not sum to the described
  total, which is why the group states the described total separately.
- **One query parameter, existing keys.** `?vgml_smart=ai-kind-screenshot`,
  through the same registry and the same `array_key_exists()` gate that guards
  the five today. No second parameter, no second code path.
- **Visible everywhere the tree is** — list, grid, and the media panel that
  opens when inserting into a post. That last one is where a media library is
  most used and it is the argument the readme already makes for real folders.
- **Switchable off** with one checkbox beside the existing filter settings, as
  a new `ai` member of `vergeml_lib_options['filters_to_show']`. Default on;
  the group hides itself anyway when there is no index.
- **Validation: all seven gates, plus a PHP suite, a browser suite and a bench
  assertion.**
- **Execution: a fresh session runs `/execute` on this file.**

## Out of scope

- **Similarity / "looks like this"** — deferred, see above.
- **Auto-filing** — Phase 4b, with the three-sample agreement gate, the
  centroid-distance check and per-folder earned autonomy. Here the user drags.
- **Natural-language commands** — Phase 4c.
- **A builder for user-defined AI folders.** The registry becomes filterable
  and that is all.
- **Orientation folders.**
- **Any change to how descriptions are produced.** `core/ai.php` is read-only
  in this phase; nothing here calls the service.
- **Post and page folders.** `vergeml_smart_for_tree()` already returns an
  empty array for a post type and that stays true.
- **Changing the five existing smart folders' behaviour, labels or counts.**

## Context

**Files to read first**

- `core/smart-folders.php` — the whole file, and in this order:
  `vergeml_smart_folders()` (the registry shape: `label` + `scan`),
  `vergeml_smart_query_args()` (the single translation, returning `WP_Query`
  arguments), `vergeml_smart_counts()` (the one-statement UNION and the
  null-means-not-looked rule), `vergeml_smart_grid_query()` and
  `vergeml_smart_list_query()` (the two surfaces that consume the translation,
  both gated on `array_key_exists( $key, vergeml_smart_folders() )`).
- `core/ai-index.php` — `vergeml_index_install()` for the schema and which
  columns carry a `KEY`; `vergeml_index_table()` and
  `vergeml_index_register_table()` for the `$wpdb` registration the SQL sniffs
  need; `vergeml_index_table_exists()`; and **`vergeml_index_maybe_migrate()`,
  which is where the trap is** — see task 1.
- `core/rest-tree.php` — `vergeml_smart_for_tree()` (the exact row shape the
  panel consumes: `key`, `label`, `count`, `scan`) and the response array
  around line 295 that carries `smart`, `unassigned` and `state`.
- `js/vergeml-tree.js` — `state.smart` (line ~136), the smart block in the
  render (line ~265), `smartRow()` (line ~788) and `smartSelect()` (line ~835),
  which sets `vergeml_smart` on the grid query and `vgml_smart` on the list URL.
- `core/librarian.php` — `vergeml_librarian_maybe_install()` as the corrected
  pattern for a version-aware lazy schema check (fixed 27-08-2026, commit
  `8ae7360`), and the ladder on the Librarian screen as the empty-state
  pattern to copy.
- `vergelabs-media-library.php` — the safe-mode guard from line 1187: the
  include order is `smart-folders.php` (1208), `ai-index.php` (1214),
  `organize.php` (1219), `librarian.php` (1225). Also `vergeml_set_options()`
  and its `version_compare` migration guards (lines 980, 1028, 1073), and the
  `filters_to_show` defaults at lines 550, 756 and 999.
- `core/options-pages.php` around line 1828 — the four existing
  `filters_to_show` checkboxes, which is where the fifth goes.
- `docs/ai-roadmap.md` — Phase 4, and the standing rules. Read the vocabulary
  law before naming anything.
- `CLAUDE.md` — PHP 7.4 floor, no build step, attach-never-replace, the version
  triple, and the options-migration rule.
- `.claude/skills/validate/SKILL.md` — the seven gates.

**Files that change**

- `core/ai-index.php` — schema version, one new `KEY`, the lazy check.
- `core/smart-folders.php` — the registry gains a filter and a `group`; the
  translation gains an index-filter shape; the counts gain four branches.
- `core/rest-tree.php` — `vergeml_smart_for_tree()` grows the group, the
  hide-at-zero rule and the described/total pair.
- `js/vergeml-tree.js` — a second group, its heading, the ladder row.
- `css/` — whichever file styles `.vgml-smart`; the AI group reuses it.
- `vergelabs-media-library.php` — the include, the `filters_to_show` defaults,
  the migration, and the version triple.
- `core/options-pages.php` — the checkbox.
- `readme.txt` — version, and a changelog entry.
- `tools/verify.mjs` — two new suites.
- `tests/perf/bench.mjs` — the tree budget assertion, with AI folders present.

**Files created**

- `core/ai-folders.php` — the registry entries, their labels, their fixed
  order, the `posts_clauses` join and the count branches. Loaded **inside** the
  safe-mode guard, **after** `core/ai-index.php`.
- `tests/tree/ai-folders.php` — the PHP suite.
- `tests/tree/ai-folders.mjs` — the browser suite.

**Prior art in this repo**

- The five existing smart folders are the whole pattern: one registry, one
  translation, three surfaces, one UNION.
- `tests/tree/smart.mjs` is the browser suite to model the new one on, including
  its precondition (`wp option delete vergeml_smart_scan`) registered in
  `tools/verify.mjs`.
- `core/librarian.php`'s lazy install is the corrected schema pattern.
- **There is no `posts_clauses` use anywhere in `core/` yet.** This phase
  introduces the first one, which is why task 5 is its own task with its own
  test.

## Tasks

Nothing here deletes a row or a term. Tasks 1 and 10 change what existing
installs have saved, and are the two to be careful with; everything else is
additive and reversible.

1. **`core/ai-index.php`: make the lazy check version-aware, then bump.**
   `vergeml_index_maybe_migrate()` currently reads
   `if ( empty( $state['schema'] ) || ! vergeml_index_table_exists() )`. It
   never compares `$state['schema']` against `VERGEML_INDEX_VERSION`, so
   bumping the constant on its own installs nothing on a site that already has
   the table — the migration would silently not happen and the new index would
   exist only on fresh installs. Fix that first, mirroring
   `vergeml_librarian_maybe_install()`: install when the stored schema is empty,
   when it differs from the constant, **or** when the table is gone. Only then
   add `KEY has_text (has_text)` to the `CREATE TABLE` in
   `vergeml_index_install()` and set `VERGEML_INDEX_VERSION` to `2`.
   *Verify:* on a site with schema 1 and the table present, loading an admin
   screen creates the index and writes schema 2. Assert the `KEY` exists with
   `SHOW INDEX`.

2. **`core/smart-folders.php`: open the registry.**
   `vergeml_smart_folders()` returns `apply_filters( 'vergeml_smart_folders', $folders )`,
   and every spec gains `'group' => 'clean'`. Nothing else changes; the five
   existing entries keep their `label` and `scan` keys and their order. This is
   the seam everything after it hangs on, and it is also the seam a builder
   would use later.
   *Verify:* the tree renders exactly as before; the PHP suite asserts the five
   keys, their order and their group.

3. **`core/ai-folders.php`: the thirteen entries.**
   A new file, included inside the safe-mode guard after `core/ai-index.php`.
   It hooks `vergeml_smart_folders` and appends, in this fixed order:
   `ai-kind-photo`, `ai-kind-illustration`, `ai-kind-screenshot`,
   `ai-kind-document`, `ai-kind-diagram`, `ai-kind-logo`, `ai-people`,
   `ai-text`, `ai-doc-invoice`, `ai-doc-receipt`, `ai-doc-contract`,
   `ai-doc-form`, `ai-doc-report`. Each spec carries
   `'group' => 'ai'`, `'scan' => false`, a translated `label` with the literal
   text domain, and an `'index'` key describing the filter:
   `array( 'column' => 'kind', 'value' => 'screenshot' )`, or
   `array( 'column' => 'has_people', 'value' => 1 )`. Column names are
   validated against a hard allowlist inside this file and never interpolated
   from anything a caller supplies.
   It appends nothing when `vergeml_lib_options['filters_to_show']` does not
   contain `ai`.
   *Verify:* PHP suite asserts eighteen keys in the expected order, and five
   when the setting is off.

4. **`core/smart-folders.php`: the translation learns the index shape.**
   `vergeml_smart_query_args()` returns, for any key whose spec has an `index`
   entry, a single custom argument — `array( 'vergeml_ai_filter' => $key )` —
   rather than a `meta_query`. WP_Query cannot express a join, so the argument
   is a marker and task 5 turns it into SQL. Both consumers keep working
   untouched: the grid merges the argument, the list `set()`s it.
   *Verify:* PHP suite asserts the shape for one AI key and that the five
   existing keys still return exactly the args they return today.

5. **`core/ai-folders.php`: the join.**
   A `posts_clauses` filter, added at a late priority, that fires only when the
   query carries `vergeml_ai_filter`, only for `post_type = attachment`, and
   only when the value is a key that exists in the registry. It adds one
   `INNER JOIN` on `$wpdb->vergeml_ai_index` (registered on `$wpdb`, so the
   sniffs can follow it) and one `WHERE` on the column named by the spec, with
   the value bound through `$wpdb->prepare`. It must not touch the clauses of
   any other query, and it must remove itself from consideration if the query
   is not the one that asked.
   *Verify:* PHP suite builds a `WP_Query` with the marker and asserts the ids
   returned; a second assertion runs an unrelated attachment query in the same
   request and asserts its clauses are unchanged.

6. **`core/smart-folders.php` + `core/ai-folders.php`: the counts.**
   Four branches added to the existing UNION, not thirteen — grouped, so the
   statement stays short:
   `SELECT CONCAT('ai-kind-', x.kind), COUNT(*) ... GROUP BY x.kind`,
   the same for `document_type` as `ai-doc-`, and two scalar branches for
   `has_people = 1` and `has_text = 1`. Two more branches give the honesty
   pair: how many attachments have an index row, and how many exist in total.
   Still one statement, so the tree endpoint stays at six queries.
   **The failure path matters.** If the index table is missing the whole
   statement fails and the five existing counts would go with it. So: the AI
   branches are appended only when `vergeml_index_state()['schema']` is set,
   and if `$wpdb->last_error` is non-empty after the statement, the counts are
   fetched again with the core five branches only and every AI count reports
   null. One query on the normal path, two on the broken one, and a broken
   index never costs the tree its own numbers.
   *Verify:* PHP suite asserts the counts against seeded rows, asserts null for
   every AI key with the table dropped, and asserts the five core numbers
   survive that.

7. **`core/rest-tree.php`: the payload.**
   `vergeml_smart_for_tree()` gains `group` on every row; omits any `ai` row
   whose count is `0` (never an `ai` row whose count is `null`, which is the
   ladder case); and returns alongside `smart` a small `ai` object carrying
   `described`, `total` and whether the group should render its ladder. The
   five existing rows keep their exact shape — `key`, `label`, `count`, `scan` —
   so nothing in the panel that reads them has to change.
   *Verify:* endpoint suite asserts the shape in three states: no index, index
   with nothing described, index half described.

8. **`js/vergeml-tree.js`: the second group.**
   A heading, the AI rows beneath it, and the ladder row when nothing has been
   described — one line saying nothing has been looked at, linking to
   `admin.php?page=media-ai`. Reuse `smartRow()` and `smartSelect()`; an AI row
   is a smart row with a different group, and selection must clear
   `state.selected` exactly as it does now. Add the described/total line under
   the heading when the two numbers differ. ES5, no build step, and the panel
   is extended rather than replaced.
   *Verify:* browser suite.

9. **Dragging out of an AI view.**
   Confirm — do not assume — that dragging a file from the grid onto a real
   folder still works while an AI folder is selected. The assignment path takes
   attachment ids and should not care what filtered the view, but
   `state.smartSelected` is new to that code path's neighbourhood. If it is
   broken, fix it here; if it works, the task is the browser assertion that
   pins it.

10. **The setting, and the migration.**
    Add `ai` to the `filters_to_show` defaults in `vergelabs-media-library.php`
    (lines 550, 756 and 999) and a fifth checkbox in `core/options-pages.php`
    around line 1828. **A default does nothing for an existing install**, so
    `vergeml_set_options()` needs a migration guarded by
    `version_compare( get_option( 'vergeml_version', '' ), '3.4.0', '<' )`
    that appends `ai` to the saved array if it is not already there and touches
    nothing else in the option.
    *Verify:* gate 7 — set `vergeml_version` to `3.3.0`, run
    `vergeml_set_options()`, assert `ai` appears and that a site which had
    `dates` switched off still has it switched off.

11. **The version triple** to `3.4.0`: the header, `VERGEML_VERSION`, and
    `Stable tag` in `readme.txt`. Plus a changelog entry and a line in the
    readme's description — the AI section already exists, this adds the folders
    to it.

12. **The suites.**
    `tests/tree/ai-folders.php` for the registry, the translation, the join,
    the counts and the degraded states; `tests/tree/ai-folders.mjs` for the
    group, the ladder, selection and the drag. Register both in
    `tools/verify.mjs` — the PHP one on the box, the browser one on Playground,
    and respect the lock. The browser suite's precondition is a described
    library, which demo mode can produce: switch demo mode on and run a
    describe pass over the seeded fixtures rather than expecting a service.

13. **`tests/perf/bench.mjs`.** Assert `vergeml/v1/tree` is still **6** with the
    AI group present and populated, and that the number does not move between a
    library with 200 described files and one with 2000.

## Validation strategy

All seven gates in `.claude/skills/validate/SKILL.md`.

- **Gate 1 and 2** as always — one new PHP file, PHP 7.4 floor, no `match`, no
  nullsafe, no arrow-only syntax.
- **Gate 3** — three places, `3.4.0`.
- **Gate 4** — the Playground suites, including the new browser one.
- **Gate 5 is the one that can fail quietly.** `vergeml/v1/tree` must stay at
  **6 queries** with thirteen extra folders in the payload. If it moves to
  seven, the count branches did not land in the existing UNION. If it moves
  with the size of the library, the join is not using `KEY kind` /
  `KEY has_people` / `KEY has_text` / `KEY document_type` — that is a hard
  fail, not a slow query. Measure over REST, never from `wp eval`.
  No new endpoint is added, so there is no new endpoint budget.
- **Gate 6** — Plugin Check on a clean archive, all five categories. The
  `posts_clauses` join is raw SQL in a new file: register the table on `$wpdb`
  (already done by `vergeml_index_register_table()`) and prepare the value, or
  the sniffs will read it as unprepared. Run it with
  `node tools/plugin-check.mjs` against a Playground boot of the extracted
  archive; the box route is in the doc if there is a box.
- **Gate 7 applies twice**, and both halves must be proved:
  the `filters_to_show` migration from a saved 3.3.0 option, and the index
  schema bump from a site holding schema 1 with the table already present —
  which is exactly the case task 1 exists to make work.
  `tests/librarian/gate7-schema.php` is the model for the second, and the same
  four-scenario shape applies: baseline, dropped table, stale schema version,
  and the same loss reached through the real surface.

**New tests and where they go**

- `tests/tree/ai-folders.php` — PHP, runs on the box via `wp eval-file`.
- `tests/tree/ai-folders.mjs` — browser, runs on Playground.
- Both registered in `tools/verify.mjs`; neither may run beside another
  browser suite.

## Risks

- **The index lazy check does not compare versions.** This is the single most
  likely way this phase ships broken: bump `VERGEML_INDEX_VERSION`, add the
  `KEY`, and every existing install silently keeps schema 1 and no index —
  working perfectly on the developer's fresh Playground and degrading to a
  table scan on the one library big enough to notice. Task 1 exists only for
  this and it is first for a reason.
- **The UNION is now load-bearing for two features.** A malformed AI branch
  takes the five existing counts down with it and the tree loses every number
  it shows. The fallback path in task 6 is not optional.
- **`posts_clauses` has no prior art here and it is global.** A filter that
  forgets to check which query it is looking at will join the index table onto
  every attachment query on the site, including core's own media grid with no
  folder selected. Test the negative case explicitly, not just the positive.
- **Safe-mode load order.** `core/ai-folders.php` must be included inside the
  guard and after `core/ai-index.php`, or a crash in it cannot be switched off
  and `vergeml_index_table()` will not exist when it is called.
- **`document_type` is only populated when `kind` is `document`.** Five of the
  thirteen rows are structurally empty on a photo library — which the
  hide-at-zero rule handles, but only if the count query returns 0 and not
  null. Make sure the two are distinguishable end to end.
- **Rows do not sum to the described total**, by decision: unknown enum values
  are stored and matched by nothing. If the panel ever shows a total beside
  the rows, it must be the described count and not a sum, or it will read as
  a bug.
- **Hide-at-zero makes the panel move.** A row appears mid-run as a describe
  pass fills the index. Acceptable, but the browser suite should not assume a
  stable row count between assertions.
- **The media modal is narrow.** Thirteen rows plus a heading in the insert
  panel is a long list in a small box. It is behind the same group heading as
  everywhere else and the empty ones are hidden, but check it at the width the
  modal actually uses rather than at desktop width.
- **PHP 7.4.** No arrow functions in the count-branch builder and no
  `str_contains` in the key parsing, which are exactly the two places they
  would read well.
- **Vocabulary.** These labels name what a file *is*, from a model's answer.
  Never phrase a row as a judgement — "Screenshots", not "Junk"; and nothing
  anywhere may say a description is certain.
- **`vergeml_lib_options` is an old option touched by three defaults blocks.**
  Adding a member to `filters_to_show` means reading all three (lines 550, 756,
  999) and the sanitiser in `core/taxonomies.php:238`, not just the one that
  looks like the right one.

---

**Review this, then start a new session to run `/execute`.** Continuing in this
session would execute from a context already full of exploration — the
submission work, the gate-7 fix, four rounds of interview — which is exactly
what the plan/execute split exists to prevent. There are no `OPEN:` lines: every
decision in this plan has been taken.
