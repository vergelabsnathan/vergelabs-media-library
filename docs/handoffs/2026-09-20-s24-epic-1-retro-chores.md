# S24 — Epic 1 retro chores: A-1, A-3, A-4, A-5, A-7, A-8, A-11, A-2

**Date:** 2026-09-20. **Model:** Opus 5. **From:**
`docs/handoffs/2026-09-20-s23-pro-1-0-2-works-on-4-0-0.md`. **Spec:**
`_bmad-output/implementation-artifacts/spec-epic-1-retro-chores.md` (status
`done`; sprint status: the eight action items `done`, A-6/A-9/A-10 and the
four Nathan-owned ones still `open`). Plugin `5abbbf1` → `1ec93c1` (+ this
handoff's commit); pro `ef1709b` → `cea2e1e`; service `4dfff34` → `9da6ddd`
(one new file on the shelf, **not pushed, not deployed**). Nothing spent;
nothing on ms2 or the real shop; no key used (Nathan skipped the keyed
proofs); the `upg` fixture is as found (4.0.0 active, Pro 1.0.2 inactive).

## The score lines

Not re-taken: no engine, screen or filing code changed. S21's stand: shop
347 of 581 (60 %) · 72 %; tech 153 of 200 (77 %) · 76 %.

## What landed, one commit each

- **A-1 `6863a83` — one ship list.** `deploy.mjs` derives its payload from
  `git check-attr export-ignore` (asking every ancestor: git marks the
  directory entry only); `.harness` export-ignored; the zip writer moved to
  `tools/lib/zip.mjs`; `tests/release/archive-hygiene.mjs` registered
  (`archive-hygiene`, env local): no `.`/`_` segment in `git archive HEAD` or
  the Playground zip, both hold the same files, the zip is the tree's own.
  Payload **170 → 136**. The suite was red (5/7) against the pre-commit
  HEAD — the leak itself — and **7/7** after.
- **A-3 `08a5221`** — `box-upgrade-site.sh plugin <zip> [--inactive]` prints
  the zip's sha256 and the installed slug's version and status (proven on
  `upg` with a same-bytes Pro 1.0.2 reinstall: `2a6a7946426f…`, `1.0.2
  inactive`); the snapshot records `plugin_digest` (equal to an independent
  `sha256sum` pipeline on the box: `51c264bcc326…`, 137 files).
- **A-4 `e7037cf`** — `VGMLPRO_COMPAT_ARCHIVES` dropped from `compat-free-upg`.
- **A-5 `f39c58f`** — `upgrade-3161.php` judges the debug.log lines since the
  file started, after the two front-door requests: `upg` **31/31**, `[1225
  vs 1222 (this run)]`, 3 new / 3 known (was 1,163 new / 154 known).
- **A-7 `dcb369b`** — `"'\\''"`; proven end to end later: a value `a'b"c$d`
  reached the box intact through the runner's own spawn shape.
- **A-8 pro `be98f98` → re-derived `cea2e1e`** — archives leg on PHP 8.5; the
  review showed `error_get_last()` can never see a Pro boot error (one slot,
  and the free archive's `ai.php:1995` deprecation overwrites it after Pro
  loads), so the check now reads a collector installed **before WordPress
  boots** — the Playground prelude, and wp-cli `--exec` on the box (a new
  suite `exec` field in the free runner). Proven on `upg`: the collector
  caught `ai.php:1995`. The same commit export-ignores `tools`, `.harness`
  and `.gitignore` in pro (Pro's archive shipped all three).
- **A-11 `3d00864`** — `upg-pro-shots.mjs`: everything after the activation
  inside the `try`; the stored key set aside while Pro deactivates, on every
  path. `verify.mjs`: `after` deactivates Pro only when the stored key is
  empty or the test key; keyless `compat-free-upg` beside others is SKIPPED
  and the others run (`tree-view 65/65, SKIPPED compat-free-upg, exit 2`);
  the not-registered line only on a bare run or when asked; `reachable()`
  probes the suite's own site (`→ http://upg.46.225.66.194.nip.io`).
- **A-2 `641f480` + service `9da6ddd`** — the rollback target cut and
  proven: `tools/recut-release.mjs` over the served `539e4937` build
  (service `dd07dd0^`) minus `tickets/` and `pnpm-lock.yaml` →
  **`vergelabs-media-library-3.16.1-b787a3bb6a20.zip`**, 142 entries,
  `diff -r` against the served build lists only those two paths, `Version:
  3.16.1`, `release-files` 7/7. The smoke reads both 3.16.1s from the shelf
  and walks from each: **GREEN from `b787a3bb6a20` (27/27, 0 differences,
  113 s)** and **GREEN from `7f2a4fe9bee9` (27/27, 0 differences, 86 s)**;
  the A-3 digest shows two different old builds landing on one 4.0.0.
  `rollback.md` names the target and the 22-file code difference;
  `catalogue-rollback-3.16.1.json` points at it.
- **Review pass `1ec93c1`** — three lenses, 34 findings: 15 patched (the
  furniture check by name — the served leak was `tickets/`, which no
  dot-rule catches; five more `.gitattributes` lines; the `after` step
  recognises the test key; `--drop tickets` no longer takes
  `tickets-notes.md`; the smoke's `--old` edges; one `endRecord()` in
  `zip.mjs`), 4 deferred, 15 refuted with the refutation in the spec's
  triage log. After the patches: `archive-hygiene` **8/8**, `upgrade-3161`
  **31/31**, smoke `--old b787` **GREEN**, the re-cut still `b787a3bb6a20`.

## Found, not done (on the record in `deferred-work.md`)

1. The box walk from the rollback target: `upg` was built from
   `7f2a4fe9bee9` and stays up until the next schema bump; at that `reset`,
   `box-upgrade-site.sh plugin <b787 zip>` is the old side.
2. The three keyed proofs skipped today: pro `verify.mjs compat-free`
   (expect **24/24** on 8.5, seat back `0/1`), free `verify.mjs
   compat-free-upg` (the `working trees` header, 23/23, the `after` step
   printing `Pro deactivated`), `tools/upg-pro-shots.mjs` (three screens,
   the new `finally` order).
3. `plugin_digest` recorded, not asserted against the zips (BH5).
4. A Pro-side archive check (the lines landed; the Pro runner has no local
   kind).
5. A tree leg for `compat-free` on 8.5 (with the deferred `--free-tree`).
6. The A-11 seat invariant is pinned by no test (VG3).
7. `AGENTS.md` says 48 suites; it is 50 now (`archive-hygiene` joined).
   `bmad-project-context` refreshes it (P-3, Nathan's).

## Open, and Nathan's

- **The service commit `9da6ddd` is local.** Pushing it puts
  `…-3.16.1-b787a3bb6a20.zip` on the shelf at vergelabsmedia.com (a new
  file; the catalogue still serves 4.0.0 and is untouched). Nothing else
  waits on it until a rollback does.
- `7f2a4fe9bee9` stays on the shelf; retiring it is its own commit, yours.
- Still yours from S22/S23: the 4.0.1 cut (FR10, FR12, the PHP 8.4+ fix,
  `.harness` export-ignore — done in A-1 — and now `research`/`dist`/
  `.claude` lines too), Pro 1.0.3 and the minimum-free gate (its archive is
  clean since `cea2e1e`), the wordpress.org form, S-1, P-2, P-3, P-4.
- Two transport traps met today, worth the memory: a heredoc halves `\\`
  and the shell runs backticks inside `git commit -m "…"` — write files
  with the Write tool, commit with `-F` and a quoted heredoc.

## Next — S25

Epic 2, story 2.1, the buyer walk. Opener, cwd `plugin`:

```
Read docs/handoffs/2026-09-20-s24-epic-1-retro-chores.md, then
_bmad-output/planning-artifacts/epics.md (Epic 2, story 2.1) and
_bmad-output/implementation-artifacts/sprint-status.yaml. AGENTS.md loads
via CLAUDE.md. State which model you are and follow that profile in
~/.claude/harness/model-profiles.md. This session is bmad-build on story
2.1 (bmad-spec first: the buyer walk on 4.0.0 needs its spec). Stop points:
the real payment starts only on my go with the amount said; nothing on ms2
or the real shop. End with a handoff in docs/handoffs/.
```
