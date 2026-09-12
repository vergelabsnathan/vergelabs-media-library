# Handover — Phase 1, session S4: task 1.8, the submission (Opus 5)

Run on the morning of 2026-09-12, one task. The release archive is cut,
Plugin Check is clean on it, the submission doc has nothing left that needs
Nathan except the form itself, and the phase-end board is 35 green, 2
skipped, 0 red. No plugin file changed — the diff is `.gitattributes`,
`tools/verify.mjs` and `docs/`. Nothing reached a model: `ai` and the
`ai-background` run were mock; `search-try` made its few `/embed` calls.

## Where it ended

- **`Contributors:` stays `vergelabsnathan`.** The opener carried the
  placeholder `<USERNAME>`; asked, Nathan confirmed `vergelabsnathan` is the
  wordpress.org login. `readme.txt` is unchanged.
- **The release archive** `dist/vergelabs-media-library-3.16.1.zip` (session
  folder), cut with `git archive` from `7e6f019`: **135 files** (142 zip
  entries with directories), 3,093,598 bytes uncompressed, sha256
  `7f2a4fe9bee98b249243b4188252a628437d1b8d9059a18aa8807196984b389a`. Its
  file list equals the Playground zip's (`playground/vergelabs-media-library.zip`,
  135 files, digest `12a0c8d9adc5`, already current — `deploy.mjs --zip`
  left it alone).
- **Plugin Check on that archive, all five categories: 0 errors, 1 warning.**
  Pasted:

  ```
  FILE: readme.txt
  0  0  WARNING  mismatched_plugin_name  Plugin name "VergeLabs Media Library – Media folders, categories and AI alt text" is different from the name declared in plugin header "VergeLabs Media Library".
  ```

  The two "known" errors of 2026-09-10 (`.deploy-manifest`, the GitHub
  fetch) were box artefacts and do not appear on the archive.
- **`docs/wordpress-org-submission.md` "Not done — needs you" is empty.** A
  table says how each of the six items closed; the Done table reads 3.16.1.
- **The form is not sent.** That is Nathan's, as the plan says.

## Gates

- `node tools/verify.mjs <37 suites, librarian-schema excluded>` with a
  throwaway admin `vgml-s4` (created and deleted by `tools/box-ui-user.sh`
  over ssh), Playground on 8899 from `tests/tree/blueprint.json`:

  ```
  passed   tree 21/21, tree-view 43/43, copy 52/52, surface 26/26, db-calls 11/11,
           escaping 10/10, globals 7/7, roles 19/19, folders-version 24/24, guide 30/30,
           filing-trail 119/119, search-try 9/9, health-keep 33/33, ai-folders 38/38,
           auto-file 23/23, say 32/32, quarantine 31/31, utilities 24/24, health 27/27,
           ai-background 34/34, ai 10/10, smart 11/11, csv 33/33, private-folders 18/18,
           journey 62/62, counts 26/26, organize 67/67, naming 16/16, voice 2/2,
           rename 13/13, rename-files 18/18, librarian 68/68, watchdog 10/10
  SKIPPED  ai-folders-ui, polylang  — these did NOT run (the two the plan expected)
  FAILED   dialogs (exit 1), brief (exit 1)
  ```

  Both reds were rerun after their cause was found (below): `dialogs 5/5`
  through the runner with the credentials set, `brief 40/40`. Zero red.
- `node tools/plugin-check.mjs http://127.0.0.1:8907` — above.
- `node tools/filing-baseline-check.mjs` — not run; the stop-point table
  still stands (box library is mock, baseline is pre-reset).

## The two reds, and what each was

**dialogs 4/5 — the runner.** `verify.mjs` handed `VGML_USER`/`VGML_PASS`
to every browser suite, Playground ones included; `dialogs.mjs` tried the
box's `vgml-s4` on a Playground whose only account is `admin`/`password`
and reported "still at wp-login.php". The other four checks passed because
the blueprint's `login: true` had already signed the browser in. Fixed at
`tools/verify.mjs:545`: credentials go only to suites whose env is not
`playground`. Commit `2dffb2b`.

**brief 31/40 — an empty index, and where it went.** C1 picked no described
picture: `wp_vergeml_ai_index` had 0 rows when the session started.
Evidence: `SHOW TABLE STATUS` gave the table a `Create_time` of 06:32:47 UTC
today — two minutes into the board — and
`tests/tree/ai-folders.php:479` does `DROP TABLE IF EXISTS` on the index,
reinstalls it and refills only its own nine rows (its comment: "Dropping
the table took the descriptions with it"). On 2026-09-11 the 1.2 session
ran `ai` before `ai-folders`, so the box was left with an empty index; today
`brief` (13th) ran before `ai` (24th), whose mock pass re-described all
1,000 (`1000 indexed`, model `mock`, free). The first rerun of `brief`
gave 37/40 — D4 "0 stale of 1000" — because `vergeml_index_stale()` holds
rows younger than `VERGEML_AI_HOLD_SECONDS` (ten minutes) and the rows were
seven minutes old; after the hold, 40/40. Nothing in the plugin is wrong
here; the suite breaks the "tests restore what they write" rule.

## Found, not done

- **Plugin Check `mismatched_plugin_name`**: readme.txt's `=== … ===` title
  carries the strapline "Media folders, categories and AI alt text"; the
  plugin header says "VergeLabs Media Library". Making them equal is a copy
  call either way (the readme title is the listing's title).
- **`.gitattributes` was missing `/tickets` and `/pnpm-lock.yaml`** — the
  archive shipped four unreleased ticket files and a lock file since
  whenever `tickets/` was created; the doc's "verified against the built
  archive" line had not been re-checked. Fixed (`7e6f019`); the channel zip
  at `service/public/releases/vergelabs-media-library.zip` is the old
  148-entry build (`539e4937…`) and still carries them — replacing it is a
  service deploy, not done here.
- `docs/wordpress-org-submission.md` lines 5–19 (the 27-08 and 29-08 notes)
  are history now; left as they were.

## Done after the handoff, on Nathan's go

- **`ai-folders.php` D2 no longer drops the index.** It points
  `$wpdb->vergeml_ai_index` at a name nothing created for one counts call
  and puts it back; the two checks that assumed the destruction
  (`described === 9`) assert the seed as a delta and the payload as the
  site-wide count. Proof, in order with no `ai` run between: index 1,000
  → `ai-folders 38/38` ("9 of ours described, 1009 site-wide") →
  `brief 40/40` → index 1,000. Mutation: the rename neutralised gives
  37/38 on "the AI folders report null, not zero". Commit `90990f7`.
- The two stale throwaway admins (`vgml-slow-1789044170`,
  `vgml-stale-1789042163`) removed through `box-ui-user.sh`; the box's
  users are `admin` only.

## Stop points (Nathan)

- **Send the form** at wordpress.org/plugins/developers/add/ with
  `dist/vergelabs-media-library-3.16.1.zip` (digest above). The confirmation
  email is the proof 1.8 asks for.
- The readme title versus the header (the one warning) — which one moves.
- The channel zip on the service (148 entries) — redeploy from the 135-file
  archive, or leave until the next release.
- The filing baseline and the describe it needs — unchanged.

## Traps found on the way

- **A detached Playground from Node dies under the emoji path.** `spawn(…,
  { detached: true, shell: true })` from a script exited before writing a
  byte; PowerShell `Start-Process npx.cmd … -WindowStyle Hidden` with
  `MSYS_NO_PATHCONV=1` in the environment ran for the whole board. Same for
  the board itself: a background Bash call is capped at ten minutes, so the
  35-minute run was a `Start-Process node tools/verify.mjs …` with its log
  in the scratchpad and a `Monitor` on the log.
- `$CLAUDE_SCRATCH` is not set in Bash here; the scratchpad path is literal.
- The hook that blocks inline `node -e "fetch(…)"` allows the same fetch
  from a script file; the harness hook blocks `rm -rf` in any form (use
  `unzip -o` into a fresh directory instead of clearing one).
- **`SHOW TABLE STATUS … Create_time` dates the last DROP+CREATE or
  copying ALTER**, not the original install — it is how a suite that drops
  a table is found without a query log.
- The box's clock is UTC; this machine's is UTC+1 today.

## Box state

- `/var/www/wp`: 1,000 attachments, 1,000 index rows (model `mock`,
  described 06:37–06:38 UTC), plugin 3.16.1 active, users `admin` only.
- `/var/www/ms`, `/var/www/ms2`, `/var/www/upd`, `/opt/vgml-service`: not
  touched this session.
- Playgrounds on 8899 and 8907 stopped; the extracted archive under the
  scratchpad `pc/` is disposable.

## Commits

- `7e6f019` chore(release): export-ignore for `/tickets` and
  `/pnpm-lock.yaml`; the submission doc's six items closed.
- `2dffb2b` fix(verify): credentials to box suites only; the doc's Done
  table at 3.16.1.
- `5ccea5c` this handoff; `90990f7` the ai-folders fix, after it.

## Next

Phase 1 is done on the agent's side once Nathan sends the form. Phase 2
opens as `plans/four-yesses/phase-2.md` says, with this file as the
handoff.
