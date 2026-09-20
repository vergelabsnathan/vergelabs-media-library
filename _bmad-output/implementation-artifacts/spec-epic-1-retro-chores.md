---
title: 'Epic 1 retro chores: A-1, A-3, A-4, A-5, A-7, A-8, A-11, then A-2'
type: 'chore'
created: '2026-09-20'
status: 'done'
route: 'dispatch'
baseline_commit: 'plugin 5abbbf1495d8f2840f2e6f2af86be6703b1d3735 · pro ef1709b8e82cfcde0e1d0a08a731b419978ded70 · service 4dfff348f69d681dd7bc01660721aa94ed28099a'
review_loop_iteration: 1
context: ['_bmad-output/implementation-artifacts/epic-1-retro-2026-09-20.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** The Epic 1 retrospective left eleven "fix now" items; eight are for this session. Two are leaks (the release archive and the Playground/box payload carry `_bmad*`, `.harness`, `AGENTS.md`), five are suite and tool hygiene, and A-2 is the one that matters: the upgrade proof walks from a 3.16.1 no customer downloaded.

**Approach:** One chore, one commit per item, in the order A-1, A-3, A-4, A-5, A-7, A-8, A-11, A-2. A-2 cuts the decided rollback target (the served `539e4937` build minus its six leaked entries), proves it by `diff -r`, shelves it as a new file, and makes the smoke walk from both 3.16.1 builds.

## Boundaries & Constraints

**Always:** Nothing on ms2 or the real shop. The shelf (`service/public/releases/`) only gains files, never overwrites (AD-1). Say what goes to the service before it goes; a commit there, no push, no deploy. Keys stay in the environment, never in a file. Every suite change is proven by its own output line. Commit subjects name the item (`retro A-n`).

**Never:** A-6, A-9, A-10, S-1, P-* (not this session). No `reset` of the `upg` fixture. No retirement of the `7f2a4fe9bee9` zip (its own commit, Nathan's). No shared ssh helper (D-1).

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| A-1 payload | tracked file under an `export-ignore` root | absent from `--zip` and `--box` payload | git unavailable → the walk with the old SKIP lists, said aloud |
| A-1 check | `git archive HEAD` and `playground/*.zip` | no entry segment starts with `.` or `_`; both hold the same entry set | a stray entry names itself, exit 1 |
| A-11 keyless | `verify.mjs compat-free-upg tree` without the key | one line, `compat-free-upg` SKIPPED, `tree` runs | alone without the key → exit 2 as today |
| A-11 after | fixture holds a connected key after the suite | `after` leaves Pro active and says so; a real seat is never released | — |
| A-2 re-cut | `539e4937…` from service `dd07dd0^` | 142 entries, `diff -r` vs served = only the six leaked paths; name carries its own sha256 prefix | pin mismatch → smoke exit 2 |

</frozen-after-approval>

## Code Map

- `tools/deploy.mjs:75-124` -- `SKIP`/`SKIP_FILE` and `payload()`; move the zip writer/reader (`crc32`, `writeZip`, `readZipIndex`) to `tools/lib/zip.mjs` so the new check and the re-cut reuse them. `git check-attr` sets the attribute on the directory entry only, so test each path's ancestors.
- `.gitattributes` -- add `/.harness export-ignore`.
- `tools/verify.mjs:236-240` (the `compat-free-upg` entry), `:498` `baseFor`, `:606` the vars quoting, `:800-806` the keyless branch, `:822-834` the loop. Suites with `wp: '/var/www/<site>'` answer at `http://<site>.46.225.66.194.nip.io`.
- `tools/box-upgrade-site.sh:75-80` -- the `plugin` verb.
- `tests/compat/upgrade-3161-snapshot.php:74-80` -- `debug_log_lines` and `moving`; add `plugin_digest` beside them, recorded not compared.
- `tests/compat/upgrade-3161.php:105-122` -- the debug.log window; `:127-147` the front-door requests it should also cover.
- `tests/compat/upgrade-3161.spec.mjs:32-57` -- one `OLD` from `../dist`; becomes a list of shelf files pinned by hash, the blueprint run once per old side.
- `tools/upg-pro-shots.mjs:66-75, 152-175` -- activation before the `try`; the `finally` order.
- `../pro/tools/verify.mjs:233` -- `php: '8.3'`; `../pro/tests/compat-free.php:142-173` -- the gates and the error handler.
- `docs/runbooks/rollback.md:46-53`, `_bmad-output/implementation-artifacts/catalogue-rollback-3.16.1.json:8` -- name the target.
- Mirror for a local check suite: `tests/tree/tree-view.mjs` (env `local`, exits by its own count).

## Tasks & Acceptance

**Execution:**
- [x] A-1 `tools/lib/zip.mjs`, `tools/deploy.mjs`, `.gitattributes`, `tests/release/archive-hygiene.mjs`, `tools/verify.mjs` (register `archive-hygiene`, env local), `playground/vergelabs-media-library.zip` re-cut -- one ship list.
- [x] A-3 `tools/box-upgrade-site.sh` -- `plugin <zip> [--inactive]`, prints `sha256sum` and the installed slug's version; `upgrade-3161-snapshot.php` records `plugin_digest` (sha256 over sorted `path sha256` lines of the plugin directory).
- [x] A-4 `tools/verify.mjs` -- drop `VGMLPRO_COMPAT_ARCHIVES` from `compat-free-upg`.
- [x] A-5 `tests/compat/upgrade-3161.php` -- count debug.log lines at the top; on the box judge from there, in the smoke from the snapshot's count; the judgement after the front-door requests.
- [x] A-7 `tools/verify.mjs:606` -- `"'\\''"`.
- [x] A-8 `../pro/tools/verify.mjs` `php: '8.5'` and a collecting `set_error_handler` in the Playground prelude before `wp-load.php`; `plugin/tools/verify.mjs` a suite `exec` field passed as wp-cli `--exec` (runs before WordPress loads) and the same collector on the `compat-free-upg` entry; `../pro/tests/compat-free.php` -- asserts `$GLOBALS['cf_boot_noise']` empty when the collector is present (FAIL names file:line and message), falls back to `error_get_last()` labelled "last error only" when it is not. (Amended after pass 1; see Spec Change Log.)
- [x] A-11 `tools/upg-pro-shots.mjs` (safe-mode exit deactivates what it activated; `finally`: seat → key emptied and Pro deactivated → key/state put back → column), `tools/verify.mjs` (`after` deactivates only when no key is connected; keyless `compat-free-upg` among others is SKIPPED; `reachable()` per suite site; the keyless line on a bare run or when asked).
- [x] A-2 `tools/recut-release.mjs` (drop paths from a zip, deterministic, named `<slug>-<version>-<sha12>.zip`); the file into `../service/public/releases/` (say so first); `upgrade-3161.spec.mjs` walks from both shelf 3.16.1s; `rollback.md`, `catalogue-rollback-3.16.1.json` name the target; `deferred-work.md` notes the box walk from it waits for the fixture's next `reset`.

**Acceptance Criteria:**
- Given the tree, when `node tools/deploy.mjs --check`, then the payload count drops by 32 and `node tools/verify.mjs archive-hygiene` prints its own `N/N passed`.
- Given `upg`, when `node tools/verify.mjs upgrade-3161`, then `31/31 passed` with the debug.log line naming this run's count, not 1,100.
- Given `../dist`, when `node tools/verify.mjs compat-free` in `../pro` with the key, then `23/23 passed` on PHP 8.5 with one more check (`24/24`).
- Given both shelf zips, when `node tests/compat/upgrade-3161.spec.mjs`, then `SMOKE GREEN` twice, each side named with its hash.
- Given `../service`, when `pnpm test`, then `release-files` passes with the new file on the shelf.

## Implementation Notes

- Implemented inline (remote-ops build: the box, the shelf, the key); one commit per item. Plugin `6863a83` A-1, `08a5221` A-3, `e7037cf` A-4, `f39c58f` A-5, `dcb369b` A-7, `3d00864` A-11, `641f480` A-2; pro `be98f98` A-8; service `9da6ddd` A-2 (the shelf file, no push).
- A-1: `git check-attr` marks the directory entry only, so `deploy.mjs` asks for every ancestor in one call. `--check --zip` no longer touches the box (the suite needs that). `archive-hygiene` was red (5/7) against HEAD before the commit -- HEAD's `.gitattributes` lacked `.harness` -- and 7/7 after: the check bites on the very leak it was written for. Payload 170 -> 136.
- A-3 proven with a same-bytes `--force` reinstall of Pro 1.0.2, `--inactive`; the PHP digest equals an independent sha256sum pipeline on the box (`51c264bcc326`, 137 files).
- A-5: the judgement moved after the two front-door requests; the window is the run's own on the box (`1225 vs 1222`, 3 new, 3 known) and the snapshot's in the smoke (`660 vs 660`).
- A-11: the safe-mode exit and every step after the activation are inside the `try`; the `finally` empties the test key and deactivates Pro before the fixture's key goes back; the `after` eval was run on the fixture as written (`Pro deactivated`, status `inactive`).
- A-2: the served build (`539e4937`, service `dd07dd0^`) re-cut with `tools/recut-release.mjs` -> `b787a3bb6a20` (142 entries); `diff -r` lists only `tickets/` and `pnpm-lock.yaml`; 22 files differ in content between the served build and git's 3.16.1 (the retro said 24, counting the leaked entries). The smoke reads both old sides from the shelf: GREEN from `b787a3bb6a20` (27/27, 0 differences, 113 s) and from `7f2a4fe9bee9` (27/27, 0 differences, 86 s).
- Skipped by Nathan's choice: the three keyed proofs (pro `compat-free` 24/24 on 8.5; `compat-free-upg` working trees / after step; `upg-pro-shots.mjs`). Recorded in `deferred-work.md`.
- Surprises: the heredoc transport halved `\\n` escapes in the smoke (restored by Edit); backticks inside a `git commit -m "…"` string are executed by the shell (message amended before any push; use `-F` with a quoted heredoc).

## Spec Change Log

- 2026-09-20, pass 1, BH6/EC13/VG1 (bad_spec). Trigger: A-8's "`error_get_last()` after the gates" is one slot, and the free 4.0.0 archive's own compile-time deprecation lands in it after Pro loads, so the check cannot see a Pro boot error in the archives leg or on the box. Amended task A-8: a collecting error handler installed before WordPress boots -- the Playground prelude in `pro/tools/verify.mjs`, and `--exec` on the box through a suite `exec` field in `plugin/tools/verify.mjs` -- recording every error from a Pro file into `$GLOBALS['cf_boot_noise']`; the suite asserts it empty when the collector is present and says "last error only" when it is not. Known-bad state avoided: a green line over the exact deprecation the check exists for. KEEP: `php: '8.5'` on the archives leg; the check counts (24 on the archives, 23 on the trees); the FAIL names file:line and message.

## Review Triage Log

Pass 1, 2026-09-20 (blind BH, edge-case EC, verification-gap VG). Verdict · route · evidence.

| # | Finding | Verdict | Route | Evidence |
|---|---|---|---|---|
| BH1 + EC16 | `archive-hygiene` cannot catch a `tickets/` or `pnpm-lock.yaml` leak (no `.`/`_`), and both archives now read one list, so dropping an export-ignore line stays green; `research`, `dist`, `.claude`, `.verify.lock`, `.deploy-manifest` are not in `.gitattributes` | medium | patch | The served 3.16.1 leak was `tickets/` -- the very case A-2 is about -- and the rule as written would not have named it. Fix: a furniture deny-list in the suite, the five lines in `.gitattributes`. |
| BH2 + VG2 | Pro's release cut has no ship list: `pro/.gitattributes` ignores only `tests/`; `.harness/active.json`, `tools/verify.mjs`, `.gitignore` are tracked and ship; the shelf's Pro 1.0.2 already carries `.gitignore` | medium | patch (`.gitattributes`) + defer (a Pro `archive-hygiene`) | VG cut `git archive HEAD` in `pro/`: the three files are in it. Pro 1.0.3 is on Nathan's list. The lines are trivial; a Pro-side suite needs a `local` kind the Pro runner has no notion of. |
| BH3 + EC3 | `git archive HEAD` reads HEAD's attributes, the Playground zip the working tree's: an uncommitted `.gitattributes` edit or a staged add turns "same files" red with nothing leaked | low | patch | Seen in this build (5/7 before the A-1 commit). Fix: the detail line says the tree is dirty, so a red names its likely cause. |
| BH4 + EC1 + VG-o2 | `$log_at_start` is counted after `wp-load` has booted the plugin in the CLI process, so the box window holds the two front-door boots, not "the CLI boot above" | low | patch | True: `wp eval-file` runs the file after `plugins_loaded`. Coverage is two full PHP-FPM boots, which is what a customer runs; the comment overstates. Fix the comment. |
| BH6 + EC13 + VG1 | `error_get_last()` is one slot; the free 4.0.0 archive raises its compile-time deprecation (`core/ai.php:1995`) after Pro's files load (Pro sorts first in `active_plugins`), so in the archives leg and on the box the slot never holds Pro's boot error | high | bad_spec | VG traced it: Pro requires its includes at load; free's archive compiles `ai.php` unconditionally; no handler consumes the slot first. The check passes green on a Pro deprecation it was written to catch. The spec prescribed the mechanism (from the retro's wording). Amended: a collector installed before WordPress boots, in both legs. |
| BH7 + EC8 | `recut-release --drop tickets` also drops `tickets-notes.md` (bare prefix) | low | patch | `rel.startsWith( d )`; fix: exact match or `d + '/'`. |
| EC6 + EC7 | `--out` omitted → `outDir` is the zip; `--drop` last with no value → `[undefined]` | low | patch | `indexOf + 1` on -1 is 0. One guard on the argument list. |
| BH8 | `unzip` may not be on the box | false | reject | `deploy.mjs` runs `unzip` on the box on every deploy; the A-3 run used it. |
| BH9 | `rollback.md` names the tool without the re-derivation command; `plugin/tools/…` beside a bare `tests/compat/…` | low | patch | Direct correction of two lines. |
| BH10 | `readZipEntries` re-finds the end record with a rule different from `readZipIndex` (no 66000 cap) | low | patch | Two rules for one thing in one file; a shared `endRecord()`. |
| BH11 | `SKIP`/`SKIP_FILE` extended instead of retired | false | reject | The lists serve the no-git walk only and the comment says `.gitattributes` is the one list; a folder with no repository is the case the walk exists for (header, 2026-09-07). |
| BH12 | `--old` as the last argument prints `undefined`; the `error` path leaves the scratch dir unnamed | low | patch | Both true; two lines. |
| EC2 | unreadable subdirectory / `hash_file` false in the digest | false | reject | The plugin directory was just loaded by the same process; on the fixture it is `www-data`-owned and read by root. Guarding it is handling a state the program cannot reach. |
| EC4 | `git archive` throwing leaves a stack and a temp dir | low | reject | git is present wherever this runs; a throw exits non-zero and the runner counts it failed. |
| EC5, EC9 | `__MACOSX` / empty archive / no top directory | false | reject | Only this repo's archives go through these tools; a wrong shape fails loudly. |
| EC10 + VG-o1 | `upg-pro-shots`: Pro found inactive, fixture holds a real key, failure before `activate` → deactivate with the key present → real seat released | medium | patch | The `delete_option` sits inside `if ( activate )`. Fix: on that path stash and clear whatever key is stored, deactivate, put it back. |
| EC11 | a `wpEval` throwing inside `finally` aborts the rest | low | reject | Pre-existing shape; wrapping each step is guard-adding for an ssh drop mid-teardown. |
| EC12 | ssh dies after the remote activate ran but before its output returns → `activate` stays `''`, `finally` skips release | low | patch | Narrow but real; one flag set before the call. |
| EC14 | the suite dying before its restore leaves the test key stored; `after` then leaves Pro active and the seat taken (the old unconditional deactivate released it) | medium | patch | `cf_restore` is a shutdown function so a fatal still restores; a killed process does not. Fix: `boxStep` carries the suite's vars; `after` also deactivates when the stored key is the seats key. |
| EC15 | `--check --box` now checks the box only; undocumented | low | patch | One header line. |
| EC17 | spec says "sorted `path sha256` lines", code sorts `sha256  path` | false | reject | The fix is to edit this build's spec; the commit and the code comment say `sha256  path`. |
| BH5 | the digest is recorded, never asserted against the zips | low | defer | As specified by A-3 ("records"); asserting `plugin before` = digest of `old.zip`'s entries would make it a proof. Cheap later: `readZipEntries` exists. |
| VG1-b | the default `compat-free` never compiles Pro's tree; a tree deprecation only boots under `--tree` | low | defer | By design (the archives leg is what customers have); a tree leg belongs with the deferred `--free-tree` pairing. |
| VG3 | the A-11 seat-safety ordering is pinned by no test | low | defer | Hand-run box tools against the live licence server; the honest check needs a fixture holding a real key on purpose. One runbook line. |

## Verification

**Commands:**
- `node tools/deploy.mjs --check` -- expected: file count without `_bmad*`, `.harness`, `AGENTS.md`; zip up to date after the re-cut.
- `node tools/verify.mjs archive-hygiene tree-view` -- expected: both pass.
- `node tools/verify.mjs upgrade-3161` -- expected: `31/31 passed`.
- `node -e` on the quoting helper -- expected: `a'b` → `a'\''b`.
- `cd ../pro && node tools/verify.mjs compat-free` (key in env) -- expected: `24/24 passed`, seat back `0/1`.
- `node tests/compat/upgrade-3161.spec.mjs` -- expected: `SMOKE GREEN` for `7f2a4fe9bee9` and for the new re-cut.
- `cd ../service && pnpm test` -- expected: green.
