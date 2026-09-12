# Handover — Phase 2, session S3: task 2.3 (Opus 5)

Run on 2026-09-12, 15:00–15:35, on the box (`46.225.66.194`, `/var/www/wp`,
plugin 3.16.1). **The promise holds on a real library:** 100 pictures
described through the AI screen on the real model, alt text on every one, a
meaning search finds a picture by its caption alone, the Duplicates screen
finds byte-identical sets. No file in any repo changed. One folder of
screenshots: `docs/handoffs/2026-09-12-phase-2-s3-real-library/`.

## What was done, in order

1. Read-only probe: mock on, all 1,000 index rows `mock` (the 09-11 state),
   0 undescribed, 0 byte-identical groups on the box.
2. Session admin `vgml-s3` (`tools/box-ui-user.sh`), deleted at the end.
3. `wp eval-file`: hold transients cleared, exactly 100 images
   (`posts_per_page => 130`, the first 100 with a file on disk) had their
   index row and `_wp_attachment_image_alt` removed; ids kept in
   `/tmp/vgml-s3-ids.txt` for the proof → `undescribed 100; pending
   unindexed now 100`.
4. Playwright, signed in as `vgml-s3`: mock off through
   `/vergeml/v1/ai-settings` (`mock: 0`, verified by re-reading), then
   `#vgml-ai-run` ("Describe new images", watch-it-here).
5. Search on the grid, Duplicates scan after planting three copies
   (`wp_insert_attachment` of a byte-for-byte copy of 105810, 105823, 105836
   → 358369–358371, `md5 same: yes`).
6. Copies deleted with their files (`wp post delete --force`; `0` files
   left, `get_post` → 0), admin removed, `/root/box-ui-user.sh` and the
   `/tmp` files removed, **mock put back on** (Nathan: "what's best?" —
   recommended on, since the default was on unless he said otherwise).

## Gates, with the lines

- **Screenshot 1** `01-ai-screen-100-described.png`: the finished line
  `100 pictures described · 0 failed · 03:14 PM` (132 s from click), the
  log of real captions (#106797 Casio PV-1000 controller, #106780 Traylor
  Motor Garage …). Reloaded (`01b-ai-screen-reloaded.png`): `1,000 pictures
  · 1,000 described · 100 with alt text · last run 12 September 14:14`,
  credits 25,872 → 25,774, "100 of 1,000 on the brief in use".
- **Screenshot 2** `02-grid-search-damaged.png`: search `damaged` → one
  tile, `Broken_glass_screen_Huawei_P7.jpg` (105879). From the
  `query-attachments` response: 1 hit, expected id present, the word in no
  hit's filename. The caption: it is one of the 100.
- **Screenshot 3** `03-duplicates-three-sets.png`: band `3 exact copies ·
  11 look-alike sets · 11.1 MB`, the three planted sets listed pair by pair
  (`Data_Center_of_CNPC.jpg` / `s3-copy-…`, 588.2 KB each, "Used nowhere").
- **wp eval** (`proof.php`, read-only, against the 100 ids):
  ```
  the 100: 100 rows with model <> 'mock' and described_at today (2026-09-12 UTC)
  the 100: 0 rows with error <> ''
  the 100: 100 with non-empty alt text
  whole table: 100 rows model <> 'mock' and described_at today; 900 rows still mock
  models today: claude-haiku-4-5 100 · mock 900
  ```
- `vergeml_ai_describe()` was never called from `wp eval`; every describe
  went through the screen's `/ai-index` loop.

## Cost

100 pictures on `claude-haiku-4-5` ≈ **€0.48** at €0.00483/image (the
screen's credit counter moved 98). Said before the run. Nothing else reached
a model: the Duplicates scan and the search are local. Stripe untouched.

## Found, not done

- **The automatic stale sweep did not fire, and would from the other path.**
  I told Nathan before the run that the sweep would re-describe the 900 mock
  rows (≈ €4.35) and he approved it. It did not happen: the browser-loop
  path at `core/ai.php:1636` requires the stamp *before* the step to carry a
  prompt hash, and with every row `mock` it was empty. The background path
  (`core/ai-background.php:433`, `vergeml_ai_run_sweep_stale`) checks only
  the stamp *after*, so the first background run that ends on the box will
  start a `stale` run over the 900 mock rows — free while mock is on, ≈
  €4.35 the day it is off. The two paths disagree; one rule is wanted. Not
  fixed here (no file changes in 2.3).
- 98 credits for 100 pictures. Two pictures cost nothing on the service's
  side — a twin fill or a service-side dedupe; not investigated.
- The AI screen's header line and the button label do not refresh when a
  watched run finishes: the finished screenshot still reads "900 described ·
  0 with alt text" and "Describing 100 of 100" until reload. Phase 3.7 copy /
  state, if wanted.
- The Duplicates screen, mid-scan, shows `978 to go` beside `Last scanned 8
  hours ago` while the report below is already drawn from the stored hashes.
  Correct, but two clocks on one screen.
- The plan's pointer "`tools/box-fixture.php` plants copies" is wrong: it
  plants folders and fixture rows, no duplicates. The box has no standing
  byte-identical pair; this session planted and removed its own.
- The box's site profile is a skateboard shop; the library is Wikimedia
  photographs of data centres and phones. The captions came out clean
  regardless.

## State left behind

- Box: 100 real rows (`claude-haiku-4-5`, 2026-09-12 14:12–14:14 UTC) among
  900 mock rows; alt text on those 100; **mock on** again; `vgml-s3` gone;
  the three copies gone; hash meta on every attachment (the scan re-stamped
  some). `admin` is the only administrator.
- Repo: nothing changed except this handoff and its folder (untracked, as
  the S2c handoff still is). `tsconfig.tsbuildinfo` / `docs/mocks/` as
  before.
- Scratchpad tools used (not in the repo): `box.mjs` (ssh/scp wrapper),
  `undescribe.php`, `proof.php`, `plant.php`, `describe.mjs`, `walk2.mjs`.

## Next

- **S4 is 2.4**, test mode with a test clock; Nathan supplies a test-mode
  key for the session (`scripts/mode-check.mjs`). The lifecycle walk's
  "customer is told" strings include the download control's label on a
  lapsed licence (S2c).
- Decide whether the two stale-sweep paths should agree (a small task with
  a test in `tests/ai/background.php`) before any real-mode background run
  on the box.
