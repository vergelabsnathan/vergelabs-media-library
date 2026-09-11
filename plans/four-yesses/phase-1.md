# Phase 1 — the free plugin holds up across 2,000 installs

Tasks for `plans/four-yesses.md` Phase 1, in the shape the Opus profile needs.
Written 2026-09-11 from a survey of the repo; every path named exists.

**State on 2026-09-11.** 30 of 33 suites green (task 1.2 handoff). Red:
`librarian-ui`. Skipped: 2. Plugin Check 2 known errors. `readme.txt`
`Contributors: vergelabsnathan` — the username is still Nathan's to confirm.

**Model.** Opus 5 for every task except 1.4, which is Fable-sized (a runner
across three axes with a document as its output) and is written both ways.

**Sessions.** S1: 1.3 + 1.6. S2: 1.4 (its own session; two if Fable is not
available). S3: 1.5 + 1.7. S4: 1.8, when Nathan has done his part.

**Stop points (Nathan).**
- 1.3 — retire `librarian-ui` with the sentence below, or keep it red until
  Phase 3 rebuilds it. Default in this file: retire.
- 1.4 — the language axis: no `nl_NL` or `ar` translation exists
  (`languages/` holds the `.pot` only). Either add a task to machine-translate
  the `.pot` with a review pass, or the axis means WordPress locale set, RTL
  sheets exercised, plugin strings English. Default in this file: the latter,
  and Phase 3.11 carries the translation question.
- 1.4 — WPML is paid and not in `Pluginexamples/`; Premio Folders is not there
  either but is on wordpress.org. Default: Premio from wordpress.org, WPML
  dropped from the row unless Nathan supplies the zip.
- 1.5 — Kamatera was retired 2026-08-29 (`tools/verify.mjs` says so). The box
  is real MariaDB 11.8 but carries 28 plugins. Default: run on the box and
  say so in the numbers; a clean host is Nathan's call and his cost.
- 1.8 — the WordPress.org username.

**Gates, as commands.**
- `node tools/verify.mjs` — 33 of 33 (32 after 1.3 retires one), zero red.
- `node tools/plugin-check.mjs` — ≤ 2 known.
- `node tools/filing-baseline-check.mjs` — the baseline unchanged.
- `docs/compatibility.md` carries the 1.4 table; `docs/runbooks/rollback.md`
  exists and was followed once.

**Spend.** Nothing in this phase reaches a model. Every `ai` run is mock.
Every browser suite needs `ACTION=create UI_USER=… UI_PASS=… bash
tools/box-ui-user.sh` over ssh, `VGML_USER`/`VGML_PASS` on `verify.mjs`, and
`ACTION=delete` at the end.

---

## 1.3 · librarian-ui, retired with a sentence — Opus

- **Files:** `tools/verify.mjs` (the `librarian-ui` entry at line 296),
  `tests/librarian/librarian.mjs` (delete), `docs/testing.md` if it lists the
  suite.
- **Behaviour:** the suite that asserted `#vgml-lib-stage .vgml-lib-rung` — the
  ladder, rungs and cards of the screen replaced on 2026-09-04 — is gone from
  the board. Its twenty checks name a screen no PHP renders; the Folders
  screen that replaced it is covered by `tests/ui/folders.spec.mjs` (eight
  tests: paint budget, hand edit by id, rules without a model, refused
  `guide/rules` says so, dry-run numbers, old address lands here, the walk).
- **Proof:** `node tools/verify.mjs` lists 32 suites and no `librarian-ui`;
  `grep -rn "vgml-lib-stage" core js css tests` returns only
  `js/vergeml-autofile.js` (a Phase 3 item, noted in the 1.2 handoff);
  `node tools/verify.mjs folders-ui` (or whatever `folders.spec.mjs` is named
  in verify.mjs — check) green on the box with a throwaway admin.
- **Mirror:** the sentence for a deletion the plan requires: "A deletion needs
  a sentence that survives Nathan reading it." The sentence: *"`librarian.mjs`
  asserted the ladder-and-rungs screen removed on 4 September; nothing has
  rendered `#vgml-lib-stage` since, and the Folders screen that replaced it is
  covered by `tests/ui/folders.spec.mjs`."* Put it in the commit and in
  `docs/testing.md`.
- **Copy:** none on screen.
- **Do not:** rewrite the suite against the Folders screen (that is Phase 3's
  build to test); touch `js/vergeml-autofile.js`; retire anything else.

## 1.4 · The compatibility matrix — Fable, or Opus over two sessions

- **Files:** create `tools/matrix.mjs` (the runner), create
  `tests/compat/five-minutes.mjs` (the script it runs on each cell), modify
  `docs/compatibility.md` (the table at the top, above the 2026-08-20
  section), modify `tests/compat/smoke.js` only if its `BASE` constant is
  reused (it is hard-coded to `http://localhost:8888`; take it as an
  argument).
- **Behaviour:** one command boots each cell, installs the release zip, runs
  the same five-minute script, and writes one row. The script, in order: make
  a folder; upload one image; drag it into the folder (or move it with the
  modal's folder control — the drag suite `tests/tree/drag.mjs` shows the
  handle); filter the grid by that folder; select two files and bulk-move
  them; deactivate and delete the plugin. Any JS error, any fatal in the debug
  log, any step that does not reach its assertion is a ✗ with the step named.
  Cells:
  - WordPress 6.5, 7.0, 7.1 × PHP 7.4, 8.2, 8.5 — Playground:
    `npx @wp-playground/cli server --php=<v> --wp=<v> --login` with the flags
    `tests/compat/upgrade.js` already passes (`WP_DEBUG`, `WP_DEBUG_DISPLAY`
    false). Nine cells.
  - Install shape: single (Playground), multisite subdirectory and subdomain —
    the box's second WordPress at `/var/www/ms` through `tools/multisite.mjs`
    (subdirectory today; subdomain needs a `define( 'SUBDOMAIN_INSTALL' )`
    flip and a wildcard host entry — one cell, or a stated ✗ "not provisioned").
  - Language: `en_US`; `nl_NL` and `ar` as WordPress locale
    (`wp language core install ar && wp site switch-language ar` on the box or
    a Playground `setSiteLanguage` step). Arabic also runs
    `node tools/rtl.mjs --check`.
  - Alongside: nothing; FileBird (`Pluginexamples/filebird.zip`); Premio
    Folders (wordpress.org slug `folders`); Enhanced Media Library (the 2.9.4
    `tests/compat/upgrade.js` installs); Polylang
    (`Pluginexamples/*polylang-pro*.zip`, and `tests/compat/polylang.php`
    already exists); WPML — see stop point.
- **Proof:** `docs/compatibility.md` has a table with one row per cell, ✓ or ✗
  with the step named, dated, and the command that produced it. The runner
  exits non-zero if any cell is ✗, so it is a gate. `node tools/matrix.mjs
  --cell wp=6.5,php=7.4` reruns one cell. A mutation check on the runner:
  make the script assert the wrong folder count and every cell goes ✗.
- **Mirror:** `tools/plugin-check.mjs` drives Playground through a browser and
  is the boot-and-drive shape; `tests/compat/upgrade.js` lines 1–20 carry the
  Playground CLI flags; `tests/compat/smoke.js` names the surfaces a conflict
  shows on; `tests/compat/matrix.sh` is the old wp-env loop and its comment on
  `localhost` versus `127.0.0.1` cookies still applies; `tools/multisite.mjs`
  reaches `/var/www/ms`.
- **Copy:** the table's column heads "WordPress · PHP · Shape · Language ·
  Alongside · Result · Step"; a ✗ cell reads the step's own name from the
  script, e.g. "bulk move: 1 of 2 moved".
- **Do not:** use wp-env or Docker (Docker Desktop crashed five times in one
  run — memory); skip Arabic (three RTL sheets are hand-kept and this is where
  they show); run the matrix against the box's main site (`/var/www/wp` is the
  fixture; use Playground and `/var/www/ms`); deactivate a plugin on the box's
  main site (it fatals in core's FTP class — not ours); treat a JS error as
  noise.
- **Written for Fable:** decisions taken — Playground per cell, the box for
  multisite, the six steps above, ✗ names a step. Out of scope — fixing
  anything the matrix finds (each finding is a new ticket line under "Found,
  not done"). Stop points — WPML, the language axis, subdomain provisioning.
  Acceptance — the table, the exit code, the one-cell rerun.

## 1.5 · Two library sizes, on real MySQL — Opus

- **Files:** `tools/scale.php` (`up n=… folders=…`, `time`, `down`),
  `tools/box-scale-fixture.sh` / `.php` (`VGML_SCALE_N`, default 250000),
  modify `docs/benchmarks.md` (a dated section per size).
- **Behaviour:** at 10,000 and at 250,000 attachments on the box, time: the
  media library grid's first paint (`tools/box-grid-speed.sh`), the tree
  endpoint (`/vergeml/v1/tree` — the six-statement budget in
  `tests/tree/t0-endpoints.js`), a word search and a meaning search
  (`tests/tree/search-try.php` prints seconds), the smart counts cold
  (`vergeml_smart_counts( true )`; `tools/box-counts-cost.sh`), and the
  duplicate scan (the Duplicates screen's scan endpoint — `core/health.php`).
  Each number recorded three times; the middle one is written. Then
  `tools/scale.php down` and the fixture back to 1,000 (`tools/box-reset-library.sh`).
- **Proof:** `docs/benchmarks.md` has the two tables, each row a number with
  its command, dated, and the line "on the box with 28 plugins active; a clean
  host is faster and these are upper bounds". The tree endpoint stays inside
  its statement budget at 250,000 (`t0-endpoints.js` green after the fixture
  is up). The smart counts are the already-measured 8.6 ms cold at 251,000 —
  reproduce, do not re-derive.
- **Mirror:** `plans/counts-at-scale.md` and `docs/benchmarks.md` for the
  shape of a measured claim; memory `capacity-plan` for what the last
  measuring session learned.
- **Copy:** none.
- **Do not:** leave the 250,000 fixture on the box (every later suite pays for
  it); measure on Playground; describe anything (a fixture at size with no
  index rows is the point — the numbers are for the paths that do not need
  the model); re-take `tests/tree/filing-baseline.txt`.

## 1.6 · Uninstall is safe, proven — Opus

- **Files:** create `tools/uninstall-walk.mjs`; `uninstall.php` (read, not
  changed unless the walk finds a defect — then stop and say so); modify
  `docs/testing.md` (one line naming the walk).
- **Behaviour:** on a fresh Playground site: install the release zip, make
  three folders (one nested), upload four images, file three of them, describe
  none, switch the "also remove everything" option off; deactivate and delete
  the plugin through the Plugins screen; assert: the three terms still exist in
  `media_category`, every attachment still exists, every term assignment
  survives, the option sweep did not run (a `vergeml_` option remains only if
  the switch is off — check which; `uninstall.php:118` is the sweep). Then the
  second run with the switch on: every `vergeml_%` option gone, the tables
  named at `uninstall.php:109` gone, attachments and terms still present, and
  the debug log clean of anything from this plugin.
- **Proof:** `node tools/uninstall-walk.mjs` prints `N/N passed` for both
  runs. Mutation: comment out the `if ( get_option( 'vergeml_uninstall_wipe' ) )`
  guard in a copy of `uninstall.php` shipped into the Playground and the
  first run goes red on the options check.
- **Mirror:** `tools/plugin-check.mjs` for driving Playground's admin through a
  browser; `tests/compat/seed.php` for content; `tests/multisite/network.php`
  for the network uninstall option names (`vergeml_uninstall_wipe_network`).
- **Copy:** none on screen. `docs/testing.md`: "Uninstall, both ways:
  `node tools/uninstall-walk.mjs`".
- **Do not:** uninstall on the box (deactivating any plugin there fatals);
  test only the wipe path (the default path is the one that carries the
  FileBird argument — folders survive as categories); delete the walk's
  Playground before reading its debug log.

## 1.7 · A release can be pulled back — Opus

- **Files:** service `lib/updates.ts` (reads `PLUGIN_RELEASES`),
  `app/api/plugin/update/route.ts`, `app/api/cron/release-check/route.ts`;
  the stage site on the box at `/var/www/upd` (memory `the-watch`); create
  `docs/runbooks/rollback.md`; modify `docs/recovery.md` (a pointer).
- **Behaviour:** build a deliberately broken 3.16.2 (a zip whose main file
  fatals on load, version bumped); publish it to the channel by changing
  `PLUGIN_RELEASES` on Vercel (`vercel env`); watch the stage site offer it
  (`wp plugin update --dry-run` or the Updates screen); roll
  `PLUGIN_RELEASES` back to 3.16.1; confirm the stage site stops offering it
  and `release-check` reports the channel consistent. Time each step. Write
  the runbook while doing it: what to change, where, how to confirm a site
  stopped seeing it, how long propagation took, and what to tell a site that
  already took the bad one (deactivate through FTP — the exact file to rename).
- **Proof:** the runbook has a "Rehearsed on <date>" line with the times; the
  stage site's before/after `wp plugin update --dry-run` output pasted in the
  handoff; `curl -s https://vergelabsmedia.com/api/health` 200 at the end with `releases`
  passing; the channel back at 3.16.1 (`vercel env pull` shows it).
- **Mirror:** `docs/recovery.md` for the tone of a runbook; the
  `release-check` route's own comment block for what "stale" meant on
  2026-09-10; memory `the-watch` for `contract.json` and `/var/www/upd`.
- **Copy:** runbook title "Pulling a release back"; sections "When", "What to
  change", "How you know it worked", "A site that already took it",
  "Rehearsed".
- **Do not:** point the broken 3.16.2 at any site but the stage; leave
  `PLUGIN_RELEASES` on 3.16.2 for a minute longer than the rehearsal; bump the
  real plugin's version in the repo (the broken zip is built from a scratch
  copy).

## 1.8 · The submission — Opus, blocked on Nathan

- **Files:** `readme.txt` (`Contributors:`), `dist/` (the zip),
  `docs/wordpress-org-submission.md` ("Not done — needs you").
- **Behaviour:** `Contributors:` set to the username Nathan gives; the zip
  rebuilt (`node tools/deploy.mjs --zip`, and the release zip by whatever
  `docs/wordpress-org-submission.md` names); `node tools/plugin-check.mjs`
  re-run on that zip; the submission form filled and sent by Nathan.
- **Proof:** Plugin Check output ≤ 2 known, pasted; the zip's file count and
  digest in the handoff; the "Not done — needs you" section of the submission
  doc empty; the submission confirmation email is Nathan's proof.
- **Mirror:** `docs/wordpress-org-submission.md` "Running Plugin Check
  without Docker".
- **Copy:** none.
- **Do not:** submit on Nathan's behalf; guess the username; rebuild the zip
  with uncommitted changes (`deploy.mjs` prints "(uncommitted changes)" — stop
  if it does).
