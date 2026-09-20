---
title: 'The compatibility matrix on 4.0.1'
type: 'verification'
created: '2026-09-20'
status: 'review'
route: 'dispatch'
review_loop_iteration: 0
baseline_commit: 'e3ac8dc'
context:
  - '{project-root}/plans/finish-the-suite.md'
  - '{project-root}/docs/handoffs/2026-09-20-s26-the-release-train.md'
  - '{project-root}/docs/compatibility.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** `docs/compatibility.md` says the plugin works on 18 cells — WordPress 6.5/7.0/7.1 × PHP 7.4/8.2/8.5, Dutch and Arabic, beside FileBird, Premio Folders, Enhanced Media Library and Polylang Pro, on the box's multisite networks — but that table was produced on 2026-09-11 from a pre-4.0.0 zip (`fe9f647216ea`). 4.0.1 is what is served now (GitHub `releases/latest`, the catalogue, the box). The table describes bytes nobody runs any more. FR13 in `plans/suite-readiness.md`; story 4.2 in `epics.md` (written for 4.0.0; the train moved the target to 4.0.1 before this story ran).

**Approach:** Run the same runner, `tools/matrix.mjs`, over the same 18 cells against the 4.0.1 bytes, and let the rows fall where they fall. The Playground version and language cells run three at a time (the machine has 15 GB and 2.5 GB free); the companion cells and the box cells run one after another as before. Every row lands in `tests/compat/matrix-results.json` and is rendered into `docs/compatibility.md` between the markers, dated today. A ✗ is a finding: the row names the step and the cause, the handoff carries it, a fix is a story of its own. Nothing is changed in the plugin.

## Boundaries & Constraints

**Always:** the bytes under test are `playground/vergelabs-media-library.zip` as committed at `b6a89f1` (sha256 `66814aa2d056…`, header `Version: 4.0.1`) on Playground, and `/var/www/wp/wp-content/plugins/vergelabs-media-library` on the box (`deploy.mjs --check`: up to date, 136 files, tree `e3ac8dc`; the shipped files are unchanged since `b6a89f1`, `git diff --stat` empty outside tests/docs/plans); `/var/www/ms` reaches the same directory through a symlink. Mock mode in every cell: `VERGEML_AI_MOCK` defined on every Playground cell, the `mock` flag switched on in `/var/www/ms`'s `vergeml_ai` option for the box cells and the option put back exactly as it was (deleted if it was absent, which it is today). The five-minute script and its steps stay what they were on 2026-09-11, so a row is comparable to the row above it.

**Never:** ms2 or the real shop — the `shape=multisite-subdomain` cell runs on `two.ms2…`, which holds the shop library since 2026-09-16, so it is not run and its row says so; a describe against a real library (no cell has a licence key; mock is on); a fix to the plugin, a stylesheet, or a companion inside this story; a file outside `tools/matrix.mjs`, `tests/compat/matrix-results.json`, `docs/compatibility.md` (between the markers and the one sentence under the table that names ms2), this spec, `sprint-status.yaml`, `deferred-work.md` and the handoff; a push or deploy without saying what goes out first.

## I/O & Edge-Case Matrix

| Cell | Where | Mock | Runs as | Expected |
|---|---|---|---|---|
| `wp=6.5\|7.0\|7.1,php=7.4\|8.2\|8.5` (9) | Playground | `--define-bool VERGEML_AI_MOCK true` | pool of 3 | ✓ all 9 steps; the row records the version the probe saw (7.1 asks for `latest`) |
| `lang=nl_NL`, `lang=ar` (2) | Playground | as above | same pool | ✓ all 10 / 11 steps; `ar` also runs `tools/rtl.mjs --check` as its 11th step |
| `with=filebird` | Playground | — | skipped, as on 09-11 | — : FileBird's `FIND_IN_SET` query does not run on SQLite; the MariaDB row answers |
| `with=folders`, `with=enhanced-media-library`, `with=polylang-pro` (3) | Playground | as above | one at a time, after the pool | ✓ all 9 steps |
| `shape=multisite-subdirectory` (`/var/www/ms`) | box | `vergeml_ai.mock=1` for the run, restored after | one at a time, after the companions | ✓ all 9 steps (uninstall replaced by clean-up, as on 09-11) |
| `with=filebird,shape=multisite-subdirectory` | box, FileBird 6.5.8 linked in for the run | as above | after the subdirectory cell | ✓ all 9 steps |
| `shape=multisite-subdomain` (`/var/www/ms2`) | box | — | **not run** | — : the row names the stop point and the last result (✓ 2026-09-11) |
| a cell whose Playground never boots | — | — | — | ✗ "the site answers…" with the log tail; rerun once with `--cell <key>` before it is called a finding |
| any ✗ | — | — | — | the row carries the step and the detail; `matrix.mjs` exits 1 naming the cell; handoff + `deferred-work.md`; no fix here |
| `VGML_MATRIX_MUTATE=1 --cell wp=7.1,php=8.2` | Playground | — | the pool with one cell | ✗ at "make a folder" — the runner and the pool report a red cell as red |

</frozen-after-approval>

## Code Map

- `tools/matrix.mjs:78-104` — the 18 cells; `:106-119` argv (`--cell`, `--list`); `:195-307` `runPlayground()` (one Playground per cell, port counter from 8930, the probe, the `ar` RTL check); `:314-343` `runBox()` (throwaway network admin, FileBird link/unlink, no uninstall); `:345-391` the sequential loop that writes `matrix-results.json` and the doc after every cell; `:398-444` `writeDoc()` — the block between the markers, including the paragraph that names ms2.
- `tests/compat/five-minutes.mjs` — the nine to eleven steps; unchanged.
- `tests/compat/matrix-probe.php` — the mu-plugin the Playground cells read the debug log and the state through; unchanged.
- `core/ai.php:362,577,827` — where `VERGEML_AI_MOCK` or the `mock` setting turns describing into `vergeml_ai_mock_describe()`; `:46` the option is `vergeml_ai`.
- `tools/deploy.mjs:370-402` `verifyBox()` — the `--check` that says the box holds this tree.
- Mirror for the pool: none in the repo runs Playgrounds concurrently; `runPlayground()` already isolates a cell (own temp dir, own port, own log), so the pool is a small worker loop over the existing function.

## Tasks & Acceptance

**Execution:**
- [x] T1 `tools/matrix.mjs`: `--parallel <n>` (default 1 — the sequential run of today); the Playground version and language cells go through a pool of `n`, the companion cells and the box cells stay sequential; each echoed line carries its cell key when `n > 1` so interleaved output reads; results written after every cell as today; `command` in the results file records the flag used. `VERGEML_AI_MOCK` defined on every Playground cell. The box cells snapshot `vergeml_ai` with WP-CLI, set `mock` on, and put the snapshot back (or delete) after. The `shape=multisite-subdomain` cell carries a `skip` reason; the doc paragraph's ms2 sentence says the same. The header comment matches.
- [x] T2 The mutation: `VGML_MATRIX_MUTATE=1 node tools/matrix.mjs --parallel 3 --cell wp=7.1,php=8.2` → ✗ at "make a folder", exit 1, nothing written.
- [x] T3 The run: `node tools/matrix.mjs --parallel 3` — every cell; ✗ cells rerun once with `--cell <key>` when the detail says the Playground did not boot; `node tools/rtl.mjs --check` exits 0 on its own as well.
- [x] T4 Read the table back; each ✗ into the handoff and `deferred-work.md` with its cause; `sprint-status.yaml` 4.2 → `review`; commit; handoff.

**Acceptance Criteria:**
- Given the 4.0.1 zip, when `node tools/matrix.mjs --parallel 3` runs, then `tests/compat/matrix-results.json` holds a row dated 2026-09-20 for every one of the 18 cells and `docs/compatibility.md` shows the table headed with that date and the zip's digest.
- Given the `ar` cell, when it finishes, then its 11th step "the RTL sheets are current" is ✓ and `node tools/rtl.mjs --check` exits 0.
- Given the `shape=multisite-subdomain` cell, when the run reaches it, then nothing is sent to `/var/www/ms2` and the row says why it was not run.
- Given the box cells, when they finish, then `/var/www/ms` has no `vergeml_ai` option again, no `Matrix run` folder and no `matrix-*.png` attachment, and the `vgmlmatrix` user is gone.
- Given a ✗ cell, when the run ends, then `matrix.mjs` exits 1 naming it, the row names the step, and the handoff names the cause — and the plugin is unchanged.

## Decisions taken

One question per step, the plan's default taken and recorded (Nathan: "offer Advanced Elicitation once, then take the plan's defaults and record them").

- **Which bytes are "4.0.1"?** The Playground zip at `b6a89f1` (`66814aa2d056`) and the box's plugin directory. The GitHub/shelf zip is `c5510da6b16a` — a `git archive` of the same commit, so the same 136 shipped files in a different container; the run uses the Playground zip because that is what the runner installs and what the doc's header names. The box is the same source by `deploy.mjs --check` plus the empty diff since `b6a89f1`.
- **How parallel?** A pool of three. Eleven Playgrounds at once do not fit in 2.5 GB free; three is the number the machine can hold without paging the browser out, and it is a flag, so the next machine picks its own. Companions stay sequential (Nathan's brief; two of them install from the network and one is a licensed zip) and the box cells share one network and one throwaway user, so they cannot overlap.
- **The ms2 cell.** Not run. The stop point is explicit and `/var/www/ms2` is the shop library now. The row says so and keeps the date of the last result; the doc's sentence under the table stops claiming the sub-site was tested. The subdomain shape is untested on 4.0.1 — a gap, on record, not a ✗.
- **Mock on the box.** Switched on through the option for the run and restored, not through `wp-config.php`. The site has no option today, so the restore is a delete; a snapshot is taken with WP-CLI before the write, never through the plugin.
- **Mock on Playground changes what 09-11 tested.** ~~Uploads now go through `vergeml_ai_mock_describe()`~~ — corrected at review: nothing describes on `add_attachment`; `core/ai.php` is reached through the index step, the cron pass and the brief, none of which the five-minute script triggers. Mock is the safety net for anything that might fire (a cron pass on the box), not a path under test; the rows are comparable with 09-11 because the path did not change. A ✗ that appears only with mock on would still be a finding.
- **A ✗ is a finding.** The row and the handoff carry the step, the detail and the cause as far as the log tells it; no fix in this story, no rerun to make it green. One rerun is allowed only when the detail is a boot failure (`did not finish`, `nothing usable`, `did not sign`) — that is the sandbox, not the plugin.
- **Advanced Elicitation** — offered; in a one-go session with the defaults pre-approved, a pre-mortem was run on this spec by the session instead: (1) the pool could exhaust memory and fail cells that are fine → the rerun rule above; (2) parallel writes to the results file → one process, one `stored` object, writes serialised on the event loop; (3) interleaved console lines → the key prefix; (4) the restore of `vergeml_ai` could be skipped by a crash → the restore is in a `finally`, and the acceptance reads the option back; (5) the doc paragraph would keep saying ms2 was tested → T1 edits the sentence. Nathan can still ask for a proper elicitation pass on the spec.

## Open questions

- **The subdomain shape on 4.0.1** has no answer until either ms2 is free again or a third network is provisioned (`tools/box-ms2-provision.sh` is the recipe). Nathan's call; recorded in `deferred-work.md`.
- **The doc's prose outside the markers** ("Tested 2026-08-20…", "Not covered: Multisite") predates the matrix and contradicts it in places; not this story's file, left as is.

## Implementation Notes

- **The bytes.** `playground/vergelabs-media-library.zip` (`66814aa2d056`) and `dist/vergelabs-media-library-4.0.1.zip` (`c5510da6b16a`, the GitHub and shelf zip) unpack to the same 136 files, sha256-identical file by file — one container differs, the content does not. `deploy.mjs --check --box`: `box up to date (136 files verified)`, tree `e3ac8dc`; `git diff --stat b6a89f1 HEAD` outside tests/docs/plans touches only `tools/watch/*.json`.
- **T2.** `VGML_MATRIX_MUTATE=1 node tools/matrix.mjs --parallel 3 --cell wp=7.1,php=8.2` → `✗ wp=7.1,php=8.2 · 8/9 · 178s`, `1 of 1 cell(s) ✗` at "make a folder: create answered 200; 1 folders, expected 2", exit 1, results file and doc untouched. With mock on: 32 log lines, none ours.
- **T3, the full run** (`node tools/matrix.mjs --parallel 3`, 66814aa2d056): `4 of 18 cell(s) ✗`, exit 1. The pooled eleven took 15 min (cells 150–290 s; alone they take ~130–200 s). Of the four: `wp=6.5,php=7.4` and `wp=6.5,php=8.2` never booted (Playground's `eval.php`: "The 'wp-config.php' file is not a valid PHP file" on the `--define` step; 502 for ten minutes; the third 6.5 cell and all three 7.0 cells started together booted) — rerun alone per the boot rule: **✓ 9/9 · 132 s** and **✓ 9/9 · 130 s** (WordPress 6.5.11). Both box cells failed in 10 s on the runner's own mock write: `echo … | cd dir && sudo wp option update` runs `wp` in `/root` without stdin — the `cd` was inside the pipeline. Fixed (the `cd` before the pipeline), rerun: `shape=multisite-subdirectory` **✓ 9/9 · 41 s**; `with=filebird,shape=multisite-subdirectory` **✗ 6/9 · 69 s** — a real finding (below). The box's `vergeml_ai` read `{"site_profile":""}` before and after each box cell; `vgmlmatrix` and the FileBird link gone after.
- **The finding.** FileBird 6.5.8 beside 4.0.1 on MariaDB: "drag one into the folder: the folder lit up; file 251050 is in []", then "select two files and move them in one drag: 2 of 2 moved; the folder counts 2". By hand with mock **off** (a scratch script, no row written): the same 6/9 — mock is not in it. The shape matches the drop handler (`js/vergeml-tree.js:2310`): `dragging` is set only in our draggable's `helper()`, and the fallback is the checked rows; for the single unchecked row nothing was found, so nothing was assigned. `armOne()` (`:2073-2082`) arms the title cell so our instance wins the press over FileBird's on the `<tr>`; on 4.0.1 with WordPress 7.1.1 it no longer does. The 09-11 bytes (`c85cde6`) passed this cell; the two 09-17 list commits (`aedb0ef`, `3a13b74`) are the candidates. In `deferred-work.md`; a story of its own.
- **Gates.** `matrix.mjs` named its red cells (exit 1) — after the reruns one stands. `node tools/rtl.mjs --check` exit 0, and the `ar` cell's 11th step ✓ ("up to date"). The doc block is dated 2026-09-20 with `--parallel 3` and the digest; 18 rows: 15 ✓, 2 not run with the reason, 1 ✗ with the step.
- **The spec's frozen text says /var/www/ms has no `vergeml_ai` option.** It has one — `{"site_profile":""}`; the earlier probe filtered it out. The runner's snapshot/restore path was the live one, and it restored. Left as written in the frozen block; corrected here.

## Spec Change Log

- 2026-09-20 written from wave 3 of `plans/finish-the-suite.md` and the S27 brief; the ms2 stop point and the mock rule reconciled with the runner's cells.
- 2026-09-20 (build) the frozen "Always" clause's "deleted if it was absent, which it is today" is wrong for `/var/www/ms` — the option exists; see Implementation Notes.

## Review Triage Log

2026-09-20, `bmad-code-review` over `e3ac8dc..14b4813`, four lenses (blind hunter 12, edge-case hunter 8, verification gap 2 + 3, acceptance auditor 8). Verdicts and routes:

| # | Finding | Verdict | Route |
|---|---|---|---|
| 1 | the restore of `vergeml_ai` is never read back; a failed restore leaves mock on under a green row (VG, blind, edge, acceptance) | medium | patch: read back in the `finally`, compared with the snapshot, pushed as the row's last step; ✗ when it differs |
| 2 | a snapshot that fails for any reason but absence reads as absent → the restore deletes the real option; non-JSON snapshot → `saved = {}` (edge ×2, acceptance) | medium | patch: "absent" is WP-CLI's "Does it exist?" only; anything else, or a snapshot that does not parse, stops the cell before the write |
| 3 | a throw in one pooled worker rejects `Promise.all`, the process exits, the other workers' Playgrounds are orphaned on their ports (edge, VG) | medium | patch: `guarded()` records the cell as ✗ and lets the pool drain |
| 4 | same-day `--cell` reruns are invisible: the table says one command produced every row (blind, acceptance) | medium | patch: rows written by `--cell` carry `rerun: true` and render "(rerun)"; the paragraph says what it means |
| 5 | skipped cells count as ✓ in the closing line ("18 of 18 ✓" with two not run) (blind, acceptance) | low | patch: "N ✓, M not run"; the per-cell line prints — for a skip |
| 6 | `--parallel abc` / `0` / `1.5` silently runs sequentially (edge) | low | patch: exit 2 with the reason |
| 7 | output tagged per chunk, not per line — a fragment lands under another cell's key (blind, edge) | low | patch: whole lines only, the partial flushed on close |
| 8 | "the upload path is exercised" — nothing describes on upload (VG) | medium (a false claim in the runner and the doc) | patch: comment, header, doc sentence, the spec's decision bullet |
| 9 | the skip sentence says "on this release" and will print the same on 4.0.2 (blind) | low | patch: "not run: …"; the flag to run ms2 when free → defer |
| 10 | `docs/testing.md` documents the runner without `--parallel`, mock or the ms2 skip (blind) | low | patch |
| 11 | `docs/wordpress-org-submission.md:39` "18 of 18 matrix cells on 2026-09-11" is now false; the release-notes proposal cites it (blind) | medium (Nathan's evidence table) | patch the submission line; the proposal is marked shipped, left |
| 12 | the FileBird entry names two commits and ignores that the box's core moved 7.1 → 7.1.1; the FileBird version is a label the runner never reads (blind) | medium (the fix story's bisect) | patch the entry (core moved; FileBird verified 6.5.8 on the box); reading the companion's version → defer |
| 13 | spec Code Map lines stale; `armOne()` is at 2085 (blind) | low | reject (spec edit); the deferred entry corrected |
| 14 | Ctrl-C during a box cell: the `finally` never runs — mock on, user and link left (edge) | medium, unverified (not demonstrated) | defer: a SIGINT handler that runs the cleanup; Nathan's call whether the runner should own it |
| 15 | a warm-up boot per WordPress version would settle the pool's boot race (edge) | low | defer (recorded with the hazard) |
| 16 | nothing observes `VERGEML_AI_MOCK` landed on the Playground worker that served the request (VG) | low (the sentence now claims only what is true) | defer: a `ready` field in the probe |
| 17 | AC4 / Verification say "no option" — the option exists (blind, VG, acceptance) | — | reject (spec edit); the Verification line corrected as build hygiene, the frozen AC logged |
| 18 | `.harness/active.json` not in the frozen Never list (acceptance) | — | reject (spec edit; the plan requires the file per session) |
| 19 | the box cells' rerun after the runner's own bug is outside the frozen rerun rule (acceptance) | — | reject (spec edit); noted in Implementation Notes |
| 20 | the ms2 row's 09-11 steps were deleted from the JSON (blind, acceptance) | low | reject: git holds them; keeping two results per row is more shape than the reader needs |
| 21 | the handoff is missing (blind, acceptance) | — | reject: the handoff is the session's last step, after this review |
| 22 | a mock-off run was made on the box by hand (acceptance) | — | reject: no key on the network, nothing spent; on record in Implementation Notes |

After the patches: `shape=multisite-subdirectory` **✓ 10/10 · 41 s** ("restored: {"site_profile":""}"), `with=filebird,shape=multisite-subdirectory` **✗ 7/10 · 70 s** (the same drag finding, the restore step ✓), `--parallel abc` → exit 2; the two 6.5 cells and the mutation rerun through the patched pool — lines in the handoff.

## Verification

**Commands:**
- `node tools/deploy.mjs --check --box` → `box up to date (136 files verified)` — run before the box cells.
- `VGML_MATRIX_MUTATE=1 node tools/matrix.mjs --parallel 3 --cell wp=7.1,php=8.2` → `1 of 1 cell(s) ✗`, exit 1.
- `node tools/matrix.mjs --parallel 3` → the per-cell lines, then `N of 18 cell(s) ✓` (exit 0) or the named ✗ cells (exit 1).
- `node tools/rtl.mjs --check` → exit 0.
- On the box after the run: the row's last step "the site's vergeml_ai option is as it was" ✓ (`restored: {"site_profile":""}` on `/var/www/ms` — the option exists there; "not found" as first written was wrong); `wp user get vgmlmatrix` → not found.
- Cost: nothing. No credits (mock everywhere, no key on any cell), no Stripe, no describe on a real library. Time: about 35 minutes of runs.
