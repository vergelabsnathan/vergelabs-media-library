# Phase 0 — Foundations: duplicates report, honest "Used in" wording, opt-in stats

## Problem

Three gaps, all free-tier, all prerequisites for the AI Librarian phases:

1. **Duplicates are invisible and expensive.** Libraries carry exact copies
   (re-uploads) and near-copies (same photo, different crop or export). Nothing
   in the plugin sees them. Every future describe-run will pay credits per
   duplicate unless duplicates are known first, and a free duplicates report is
   the strongest zero-risk teaser the free tier can carry.
2. **The "Used in" field overpromises.** `vergeml_used_in_field()` in
   `core/smart-folders.php` (~line 607) renders *"Nothing found. The last scan
   saw no page, post or setting using this file."* — which reads as "safe to
   delete". The scan only covers what it covers (post content, builder meta,
   widgets, site settings); references from theme code, page-builder CSS files,
   or external embeds are invisible to it. The market's one-star graveyards
   (Media Cleaner: "deleted half the images on the website") are what this
   wording invites.
3. **Every parameter decision ahead (chunk sizes, folder-scheme defaults,
   pricing) is guesswork** without knowing what real libraries look like:
   size, file-type mix, folder-tree shape, upload rhythm. There is no
   instrumentation and no consent mechanism to gather any of it.

## User story

As a site owner with a messy media library, I want to see which files are
duplicates of each other and how much space they waste, without the plugin
touching anything, so that I can trust it before I let any feature change my
library.

## Decisions taken

- Report lives on its own page: **VergeLabs Library → Library health**
  (submenu slug `media-health`). Not on the AI page — health is free and
  AI-free.
- **Read-only in this phase.** No delete, no merge, no quarantine buttons.
  The report shows groups, thumbnails, per-group wasted bytes and a total.
  Mutation waits for the Phase-5 quarantine machinery.
- **Two lists, always**: "Duplicates" (exact md5 matches, and dHash Hamming
  distance ≤ 5) and "Possibly related" (Hamming 6–10). Never merged into one.
- "Used in" change is **wording only** in this phase: the empty state becomes
  *"No references found in the locations the scan covers (post content,
  builder layouts, widgets and site settings). Other uses — theme files,
  external links — are not scanned."* Typed reference tiers (ID-verified vs
  URL-match) wait for Phase 5, which is their first real consumer.
- **Stats are opt-in, numbers only, local-first.** Collected: attachment
  count, counts per mime family, folder count, max folder depth, uploads in
  the last 30 days, WP version, PHP version, site locale. Never filenames,
  URLs, or content. Off by default; enabled by a `manage_options` user via a
  card on the overview page; snapshot stored in an option and transmitted
  only after a licence is activated (the service does not exist yet).
- Skip rules for hashing: SVGs, images smaller than 64px on a side, animated
  GIFs, and files whose original is missing from disk.
- The five-user interview script is a deliverable of this phase
  (`docs/cohort-interview.md`), written for the owner to send as-is.

## Out of scope

- Deleting, merging, trashing or quarantining anything.
- Typed "Used in" tiers or any change to scan storage (`_vergeml_used_in`
  stays a bare CSV of post ids).
- Describing duplicates once and propagating (needs the Phase-1 index).
- Sending telemetry anywhere (no endpoint exists; local snapshot only).
- Any AI or credits interaction.

## Context

- Files to read first:
  - `core/smart-folders.php` — the chunked, resumable scan is the pattern the
    hash scan copies exactly (resume token, step endpoint, option-stored
    progress); also holds the "Used in" field to reword (~line 589–640).
  - `core/import-ui.php` — the submenu page + progress-bar + step-loop JS
    pattern the health page reuses.
  - `core/ai.php` — REST registration style, `vergeml_ai_pending()` for the
    "query the backlog, process a capped batch" shape.
  - `vergelabs-media-library.php` line ~1176 — the safe-mode guard all
    feature files load inside.
  - the internal validation gates — the gates this must pass.
- Files that change:
  - `vergelabs-media-library.php` — one include line for `core/health.php`,
    one for `core/instrument.php`.
  - `core/admin-menu.php` — a "Library health" card on the overview; the
    overview page also hosts the stats opt-in card.
  - `core/smart-folders.php` — the empty-state wording of
    `vergeml_used_in_field()`.
- Files created:
  - `core/health.php` — hashing (md5 + dHash), the step/report REST
    endpoints, the Library health page.
  - `js/vergeml-health.js` — scan loop + report rendering.
  - `core/instrument.php` — snapshot builder, opt-in handling, the overview
    card.
  - `docs/cohort-interview.md` — the five-user interview script.
  - `tests/health/test-health.php` — PHP suite (eval-file).
  - `tests/tree/health.mjs` — browser suite.
- Prior art in this repo:
  - Chunked resumable loops: `vergeml_smart_scan_step()` and the importer.
  - Batch REST + browser loop: `/vergeml/v1/ai-index` + `js/vergeml-ai.js`.
  - Admin page with progress: `core/import-ui.php`.
- External docs: none needed. dHash is implemented by hand on GD (resize to
  9×8 grayscale, threshold adjacent pixels, 64-bit hash) — no Composer
  dependency.

## Tasks

1. `core/health.php`: constants and storage. Per-attachment meta
   `_vergeml_hash` holding `md5:<hex>|dhash:<16-hex>` (dhash empty for
   non-images/skips). One option `vergeml_health` for scan progress
   (`{cursor, finished, time}`).
2. `core/health.php`: `vergeml_health_hash_file( $attachment_id )` — md5 of
   the original file; dHash from the thumbnail intermediate (fallback:
   medium, then original if < 1.5MB). Apply the skip rules from Decisions.
   GD only; if GD is absent, store md5 and an empty dhash.
3. `core/health.php`: REST `POST /vergeml/v1/health-scan` — resumable step
   (cursor = last attachment ID, batch ≤ 25 hashes per call), permission
   `manage_categories`, returns `{done, remaining, cursor}`. New files with
   no `_vergeml_hash` are the backlog; rescan = delete option + metas absent
   files only.
4. `core/health.php`: REST `GET /vergeml/v1/health-report` — builds the two
   lists. Exact groups: SQL GROUP BY on the md5 segment. Near groups: load
   all (id, dhash) pairs once, bucket by four 16-bit bands, compare only
   pairs sharing a band, split matches into ≤5 ("duplicates") and 6–10
   ("possibly related"). Response: groups with attachment ids, thumb URLs,
   file sizes, wasted-bytes per group, totals. Cap response at 200 groups
   per list with a "and N more" count.
5. `core/health.php` + `js/vergeml-health.js`: the Library health page —
   submenu under `VERGEML_MENU`, scan button + progress bar (import-ui
   pattern), then the two lists with thumbnails and sizes. Read-only; the
   only buttons are "Scan" / "Rescan".
6. `core/admin-menu.php`: add the "Library health" card to the overview
   cards array.
7. `core/smart-folders.php`: replace the empty-state string in
   `vergeml_used_in_field()` with the honest wording from Decisions.
   Update the string check in `tests/tree/` suites if any asserts the old
   text (grep first).
8. `core/instrument.php`: snapshot builder (the eight numbers from
   Decisions), option `vergeml_stats` `{opted, snapshot, time}`, REST
   `POST /vergeml/v1/stats-opt` (`manage_options`) to toggle, snapshot
   refreshed weekly via the existing scheduled hook if one exists, else on
   admin page load throttled to daily. The overview card states exactly
   what is collected, links nowhere, defaults off.
9. `docs/cohort-interview.md`: ~10 questions covering: what they bought and
   why (folders? galleries? something else), library size and file-type mix,
   how their folder tree is organized (ask for a screenshot), how many
   people upload and how often, what they type into media search, and what
   "organize my library automatically" would be worth to them. Plain
   language, sendable as-is.
10. `tests/health/test-health.php`: seed two identical files + one resized
    copy + one unrelated; assert md5 group of 2, near group catches the
    resize, unrelated stays out, skip rules skip, rescan is idempotent,
    wasted-bytes math is right. Also assert the new "Used in" wording and
    the stats snapshot shape + opt-in gating.
11. `tests/tree/health.mjs`: page renders, scan loop completes on the box,
    both lists render with thumbs, totals shown, no delete-shaped controls
    exist (assert absence), no JS errors.
12. Full validate run (all seven gates) + deploy to the box + suites.

No task in this plan is irreversible.

## Validation strategy

- Gates 1–5 always (new REST endpoints: state a budget — `health-report`
  must answer in ≤ 3 queries + the one meta sweep; `health-scan` step is
  I/O-bound by design, budget = 1 query per batch item + constant overhead,
  and the *step size* is the safety valve, not the query count).
- Gate 6 (Plugin Check on clean archive) — new PHP files, must stay at 0.
- Gate 7 — `vergeml_health` and `vergeml_stats` are new options with
  defaults; prove `vergeml_set_options()` leaves them alone (regression
  guard on the fix from 8825909).
- New suites from tasks 10–11 join the standing battery.

## Risks

- Safe-mode load order: both new files must load inside the guard, after
  `smart-folders.php` (the wording change assumes it is loaded).
- md5 over a large library is real disk I/O on shared hosting — the batch
  cap (25) and resumability are the mitigation; never hash in a request that
  renders a page.
- The report page must not look like a cleanup tool: no destructive
  affordances, and the wasted-bytes number is labelled "potential", not
  "reclaimable".
- PHP 7.4 floor: no arrow-function shorthand in new PHP, no `str_contains`.
- Existing installs: new options must not be touched by
  `vergeml_set_options()` (verified by gate 7).
