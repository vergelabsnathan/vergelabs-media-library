# Phase 3 — the Librarian: review, apply, undo

## Problem

Phase 2 built a machine that proposes trees and nothing that can show one to a
person or act on one. Concretely, in this repo today:

- `core/organize.php` stores runs with a full tree — branches carrying `label`,
  `path`, `depth`, `size`, `members` with a per-file `why` line — and the only
  readers are the test suite and three fixture files. `GET /vergeml/v1/organize-run`
  has no UI caller.
- `GET /vergeml/v1/organize-quote` returns the counted pre-flight (files,
  duplicates, skips, to-describe, memory arithmetic) and nothing renders it.
- Nothing anywhere turns a stored tree into real folders. There is no move-log,
  so there is nothing to undo. The roadmap's one-line thesis — "undo is the
  feature, not the apology" — has no code behind it yet.
- The product's conversion event ("the review screen on the user's own files")
  does not exist. Everything under it does.

## User story

As a site owner with an unfiled media library, I want to see the folder
structure the plugin proposes for my own files, adjust it branch by branch,
apply it in one supervised action, and put everything back with one click if I
regret it — so that letting an AI touch my library never feels like a gamble.

## Decisions taken

Every line below was answered explicitly in the prime interview (27-08-2026).

- **Two schemes before assignment.** The AI subject tree (latest done organize
  run) and a deterministic date/type scheme built from `post_date` plus the
  index's `kind` — no model call, no credits. The section/where-used scheme is
  deferred until where-used signals exist.
- **Apply files only the unfiled.** A file with any existing folder assignment
  in the target taxonomy is skipped and counted. Existing folders and existing
  assignments are never modified.
- **One folder per file.** Apply gives each file exactly one assignment (the
  branch it sits in). Manual multi-tagging elsewhere is unaffected.
- **Screen is its own page**: submenu "Librarian" under VergeLabs Library,
  following the Library-health page pattern in `core/admin-menu.php`.
- **Credits gate hook, open.** Apply passes through a gate
  (`vergeml_librarian_gate` filter, default allow). Pro will hang the credit
  check there later; the free plugin ships with it open. A gate refusal pauses
  the batch with a reason — it never fails it.
- **Undo removes only our own work.** It removes the assignments the batch
  made (skipping and counting files the user moved or deleted since), deletes a
  folder the batch created only when it is empty afterwards, and reports
  folders kept because manual content arrived. Never touches assignments or
  folders it did not create.
- **Undo window: the last 10 apply batches, indefinitely.** Same pruning rule
  as organize runs. The log lives in custom tables that survive deactivation;
  uninstall removal follows the repo's existing opt-in convention.
- **Partial failure pauses, never rolls back automatically.** Each chunk is
  atomically logged; a failed or interrupted batch is `paused` with exact
  progress, and the panel offers resume and undo-what-is-there.
- **Review defaults: everything checked except flagged branches.** "Needs a
  look" and depth-capped branches start unchecked and are styled as uncertain.
- **Review editing: rename + refuse.** Branch label editable inline (it
  becomes the folder name); per-branch "not this one" leaves those members
  unfiled. Split/merge refine stays backend-only this phase.
- **Pre-flight always shows the full counted line**, credits shown honestly as
  `0 (mock)` until the service is live. Counted, never estimated; refuses with
  the quote's own reason when the duplicate scan has not run.
- **Six sample thumbs per branch, nearest to the centroid.**
- **Empty states are a guided ladder on the page itself**: duplicate scan →
  index → propose, each started in place with its existing step loop — never a
  dead-end message pointing elsewhere.
- **Validation: the full seven gates plus a new browser suite.**
- **Execution: a fresh session runs `/execute` on this file.**
- **Name collision on apply: reuse the existing folder.** Decided 27-08-2026
  ("geen vervuiling"). When the tree proposes a name that already exists at
  the same level, Apply assigns into that folder instead of creating one —
  additions only, the user's own files in it stay untouched, and the moves
  log records `term_created = 0` so undo removes only our assignments and
  never that folder. No suffixing, ever.

## Out of scope

- File-as-you-upload, natural-language commands, AI smart-folder views —
  Phase 4, all of it.
- Refine UI (split/merge/regenerate buttons). The backend verbs exist; no
  buttons this phase.
- Document text extraction (Phase-1 debt) — the tree works on what the index
  holds.
- Any deletion, quarantine or merge of media. Phase 5.
- The paid service, real credits, real embeddings. The gate is a hook and the
  costs line says `0 (mock)`.
- Applying to post/page folders. Attachments only, in the primary media
  taxonomy returned by `vergeml_tree_taxonomies()`.

## Context

**Files to read first**

- `core/organize.php` — `vergeml_organize_row_out()` (the exact run shape the
  screen consumes), the tree/branch JSON keys (`key`, `label`, `path`, `depth`,
  `size`, `members[].{id,distance,why}`, the outliers/"Needs a look" branch,
  the depth-cap flag), and the four routes `/organize-step|cancel|run|quote`.
- `tests/organize/fixtures/flat.json`, `deep.json`, `diff.json` — the recorded
  runs the review screen is built against before any real run exists.
- `core/rest-tree.php` — `vergeml_tree_taxonomies()` (the taxonomy Apply
  targets), `vergeml_rest_assign()` / `vergeml_rest_assign_batch()` (the
  assignment semantics to reuse as internal helpers, NOT over REST), and
  `vergeml_count_unassigned()` (the unfiled count the pre-flight shows).
- `core/import.php` — `vergeml_import_run()` / `vergeml_import_undo_step()`:
  the chunked, resumable, both-directions idiom this phase's apply/undo copies.
- `core/health.php` — `vergeml_health_scan_step()` (the step-loop shape), and
  the Library-health admin page as the page-registration pattern.
- `core/ai-index.php` — table registration on `$wpdb`, `vergeml_activate`
  schema hook, `kind` column (feeds the date/type scheme), migrate-step idiom.
- `core/admin-menu.php` — how submenu pages and the home cards register.
- `vergelabs-media-library.php` around the safe-mode guard — where
  `core/librarian.php` loads (inside the guard, after `core/organize.php`).
- the constraints notes — PHP 7.4 floor, no build step, attach-never-replace, version
  triple, options migrations.
- the internal validation gates — the seven gates and the Plugin Check
  invocation.
- `tools/verify.mjs` — suite registration and the lock; `tests/perf/bench.mjs`
  — where the new endpoints' budgets are asserted.

**Files that change**

- `vergelabs-media-library.php` — one `include_once` for `core/librarian.php`
  inside the safe-mode guard, after `core/organize.php`.
- `core/admin-menu.php` — the "Librarian" submenu + a home card.
- `tools/verify.mjs` — register the `librarian` suite.
- `tests/perf/bench.mjs` — budgets for the three new hot endpoints.

**Files created**

- `core/librarian.php` — schema, scheme builder, pre-flight, apply/undo step
  endpoints, gate hook, pruning.
- `js/vergeml-librarian.js`, `css/vergeml-librarian.css` — the page (ES5, no
  build step).
- `tests/librarian/test-librarian.php` — the PHP suite.
- `tests/librarian/librarian.mjs` — the browser suite.

**Prior art in this repo**

- Chunked resumable walks and undo: `vergeml_import_run/undo_step`,
  `vergeml_health_scan_step`, `vergeml_index_migrate_step`.
- Custom tables done right: `core/ai-index.php`, `core/organize.php` —
  `dbDelta`, `vergeml_activate`, `$wpdb` registration. Copy exactly.
- Batch REST + browser loop: `/vergeml/v1/ai-index` + `js/vergeml-ai.js`.
- Admin page pattern: Library health.

**External docs** — none.

## Tasks

1. **`core/librarian.php`: the tables.** `{prefix}vergeml_librarian_batches`
   (`batch_id`, `run_id`, `scheme` varchar, `status`
   `running|paused|done|undoing|undone|failed`, `cursor`, `done_n`, `skip_n`,
   `params` longtext JSON — the checked branches, renames, refusals —
   `reason` varchar for pause cause, `created_at`, `updated_at`) and
   `{prefix}vergeml_librarian_moves` (`move_id`, `batch_id`, `attachment_id`,
   `term_id`, `term_created` tinyint, `undone` tinyint). Registered on
   `$wpdb`, hooked to `vergeml_activate`, `dbDelta`, no DEFAULT on text
   columns — the `ai-index` pattern verbatim.
2. **`core/librarian.php`: the date/type scheme.**
   `vergeml_librarian_scheme_datetype()` — one grouped query over attachments
   (`YEAR(post_date)`, `MONTH(post_date)`) joined with the index's `kind`
   (fallback: top-level mime), emitted in the organize tree JSON shape so the
   review screen renders both schemes with one code path. `why` line per
   member: "Uploaded March 2026 · photo". Months under `MIN_BRANCH` fold into
   their year. Deterministic by construction; the suite asserts two calls are
   identical.
3. **`core/librarian.php`: `GET /vergeml/v1/librarian-schemes`.** Returns both
   candidates as summaries (scheme id, top branches with label+size, total,
   source run_id for the subject scheme or `null` when no done run exists).
   Permission `manage_categories` like organize. Budget: ≤ 5 queries.
4. **`core/librarian.php`: `GET /vergeml/v1/librarian-preflight`.** Wraps
   `/organize-quote`'s counting and adds the apply side: unfiled files in the
   checked branches (via `vergeml_count_unassigned()` logic scoped to member
   ids), folders to create, folders reused (see OPEN), skip counts, measured
   time estimate (first-chunk extrapolation, the organize idiom), and a
   `credits` block from the gate hook — `array( 'cost' => 0, 'mode' => 'mock' )`
   by default. Refuses with the quote's own reason when the duplicate scan is
   missing. Budget: ≤ 6 queries.
5. **`core/librarian.php`: the gate.**
   `vergeml_librarian_gate( $context )` → `apply_filters( 'vergeml_librarian_gate', array( 'allow' => true, 'reason' => '' ), $context )`.
   Called at batch creation and at the top of every apply step. A deny pauses
   the batch with the reason; it never errors.
6. **`core/librarian.php`: `POST /vergeml/v1/librarian-apply-step`.** Body
   `{batch_id?}` or `{run_id, scheme, branches:[{key, label, enabled}]}` on
   first call. Creates the batch row; each step processes one chunk
   (`VERGEML_LIBRARIAN_CHUNK`, default 25, adjusted down if the measured step
   nears 5s): create the chunk's still-missing terms under the primary
   taxonomy (respecting inline renames; on a name collision at the same
   level, reuse the existing term with `term_created = 0` — the decided
   behaviour), assign each unfiled member (one folder per file; filed members
   skipped + counted), write one moves row per assignment with `term_created`
   set when this batch made the folder. Returns
   `{batch_id, status, done, skipped, remaining, estimate}`. Cancel-flag and
   gate checked first. Budget: ≤ 4 + 2·chunk, flat — the suite asserts it does
   not grow with the branch count or with steps already taken.
7. **`core/librarian.php`: `POST /vergeml/v1/librarian-pause`.** Sets the
   flag on the batch row; a separate endpoint for the same reason
   organize-cancel is one.
8. **`core/librarian.php`: `POST /vergeml/v1/librarian-undo-step`.** Body
   `{batch_id}`. Walks moves newest-first in chunks: if the file still has
   exactly the logged assignment, remove it; if the user moved or deleted the
   file since, skip and count; after the chunk, delete any `term_created`
   folder that is now empty, count the kept ones. Marks moves `undone`.
   Returns `{status, undone, skipped_touched, folders_removed, folders_kept,
   remaining}`. The import-undo idiom. Budget: ≤ 4 + 2·chunk, flat.
9. **`core/librarian.php`: `GET /vergeml/v1/librarian-batches`.** The last 10
   batches with their counts and status — the history/undo list. Budget: ≤ 3.
10. **`core/librarian.php`: pruning.** Keep the last 10 batches; delete older
    batch+moves rows. **Destructive — its own tables only**; the suite proves
    it cannot touch `posts`, `postmeta`, `terms`, or organize's tables.
11. **`core/admin-menu.php`: the page + home card.** Submenu "Librarian",
    Library-health pattern; a home card with the current state (no scan / no
    index / no run / run ready / batch history).
12. **`js/vergeml-librarian.js` + `css/vergeml-librarian.css`: the ladder.**
    One page, four states, detected from existing endpoints: duplicate scan
    missing → run it here (the health step loop); index behind → run it here
    (the ai-index loop); no done run → start one here (the organize-step
    loop with progress, partial-tree peek, cancel); run ready → the chooser.
13. **`js/vergeml-librarian.js`: the axis chooser.** Two scheme cards from
    `/librarian-schemes` (top branches, totals); picking one loads the review.
14. **`js/vergeml-librarian.js`: the review.** Branch cards from the run tree:
    checkbox (defaults per decision), inline rename, "not this one", six
    thumbs nearest the centroid (one `include=`-batched media request per
    page, never per branch), the branch's `why` summary + shared tags, a
    distance mini-distribution, distinct styling + default-unchecked for
    "Needs a look" and depth-capped branches. Footer: the pre-flight panel
    (task 4's payload rendered honestly, including the refusal state) and
    Apply.
15. **`js/vergeml-librarian.js`: apply/undo progress.** The step loop with
    progress, improving estimate, pause, resume for `paused` batches, the
    batch history with per-batch Undo, and the undo report (skipped-touched,
    folders kept) shown rather than summarised away.
16. **`vergelabs-media-library.php`:** load `core/librarian.php` inside the
    safe-mode guard after `core/organize.php`.
17. **`tests/librarian/test-librarian.php`.** Seeds attachments + index rows +
    a recorded run (fixtures), then asserts at minimum: datetype scheme
    deterministic; apply creates terms + assignments for unfiled members only;
    filed members skipped + counted; renames respected; refused branch leaves
    members unfiled; flagged branches excluded unless opted in; one folder per
    file; moves logged with `term_created` correct; gate deny → `paused` with
    reason, resume after allow; pause/resume mid-batch consistent; undo
    restores, skips + reports manually-moved files, removes only empty
    created folders, keeps + reports the rest; prune keeps 10 and touches only
    its own tables; `vergeml_set_options()` leaves the new option(s) alone
    (gate-7 regression guard); query budgets per endpoint over REST.
18. **`tests/librarian/librarian.mjs` + `tools/verify.mjs` +
    `tests/perf/bench.mjs`.** Browser: ladder renders per state → chooser →
    review renders the flat fixture's branches (counts, thumbs, flags) →
    apply on Playground → folders appear in the tree with the right counts →
    undo → gone, report shown. Register the suite (respect the lock; never
    two browser suites at once). Bench: `librarian-preflight`,
    `librarian-apply-step`, `librarian-undo-step` alongside `health-report`.

Tasks 1–9 and 11–18 are reversible. **Task 10 deletes rows** — own tables
only, guarded by tests.

## Validation strategy

- **All seven gates.** Gate 6 (new PHP file, two new tables — `$wpdb`
  registration required for the SQL sniffs) and gate 7 (new tables + any new
  option: prove `vergeml_set_options()` leaves them alone; schema hook must
  survive an upgrade that never visits an admin screen).
- **Query budgets, asserted over REST in `bench.mjs`, never via `wp eval`:**
  `librarian-schemes` ≤ 5 · `librarian-preflight` ≤ 6 ·
  `librarian-apply-step` ≤ 4 + 2·chunk flat · `librarian-undo-step` ≤ 4 +
  2·chunk flat · `librarian-batches` ≤ 3.
- **Wall-clock: one step ≤ ~5s regardless of library size** (shared hosts
  time out at 30; the browser drives the loop; chunk size is the valve,
  adjusted from the measured rate).
- **The estimate is tested for honesty** the same way organize's is: first
  chunk extrapolated, whole run compared, ±30%.
- **New suites:** `tests/librarian/test-librarian.php` (PHP) and
  `tests/librarian/librarian.mjs` (browser), both through
  `node tools/verify.mjs librarian`.

## Risks

- **Safe-mode load order.** `core/librarian.php` must load inside the guard
  and after `core/organize.php`, or a crash in apply cannot be switched off
  and the organize helpers will not exist.
- **The one-folder-per-file promise vs multi-taxonomy sites.** Apply targets
  only the primary taxonomy from `vergeml_tree_taxonomies()`; a site with
  several media taxonomies must not see assignments leak into the others.
  Test with two registered taxonomies.
- **Existing installs.** Two new tables and the activation hook; gate 7; a
  site upgrading without visiting wp-admin must still get the schema on first
  REST call (lazy `dbDelta` check, the ai-index approach).
- **Term-name collisions and sanitisation.** Collisions reuse the existing
  term (decided); the suite pins that `wp_insert_term`'s duplicate error path
  resolves to the existing term id and that undo never removes a reused
  folder. Labels from clustering can be odd — sanitise to valid term names
  without emptying them.
- **PHP 7.4.** No arrow functions, no `str_contains`, in exactly the loops
  where they would read nicely.
- **Undo racing the user.** A file deleted between apply and undo must not
  fatal the walk; a folder the user renamed keeps its new name when kept.
- **The thumbs request.** Six per branch across forty branches is 240 ids —
  batch into one or two `include=` requests, or the review screen becomes the
  N+1 the query budgets exist to prevent.
- **JS stays ES5 and attach-never-replace** — the page is its own screen, but
  any grid/tree touchpoints hook, never override.

---

**Review this, then start a new session to run `/execute`.** Continuing in
this session would execute from a context already full of exploration — two
peers' reports, the roadmap, the phase-2 plan, the interview — which is
exactly what the plan/execute split exists to prevent. There are no `OPEN:`
lines left — every decision in this plan has been taken.
