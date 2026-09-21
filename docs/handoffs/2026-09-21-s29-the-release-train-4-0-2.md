# S29 — The release train: 4.0.2 is served

**Date:** 2026-09-21, midday. **Model:** Opus 5. **From:**
`docs/handoffs/2026-09-21-s28b-our-folders-take-only-our-own-drag.md`.
**Plan:** `plans/finish-the-suite.md` — "The release train, 4.0.2 — done"
holds every evidence line. Plugin `8e71f2e` → `9b121f0` (+ this handoff's
commit); service `origin/main` `468748c` → `fc940b9` (serving). **Spent:
nothing.** Nothing on ms2 or the real shop; no describe; no Stripe.

## What Nathan said

"do whats best" on the proposed 4.0.2 block — taken as go on that copy and
say-and-do at every step. Every outward step was said before it ran; no
gate went red.

## What is live

| | before | after | proof |
|---|---|---|---|
| Free plugin, catalogue | 4.0.1 | **4.0.2** `d57c3020567e` | health `free 4.0.2, pro 1.0.3`; `4.0.1 → update:true`, `4.0.2 → update:false` |
| Free plugin, GitHub latest | v4.0.1 | **v4.0.2** | `releases/latest` → `v4.0.2`; asset `200 · d57c3020567e · 1,102,302 · Version: 4.0.2 · 143 entries` |
| Shelf | — | `public/releases/vergelabs-media-library-4.0.2-d57c3020567e.zip` | HTTPS read-back the same line |
| release-check | | `200 ok:true` | both slugs "the catalogue and the package agree" |
| Test box | `e47cc1e` bytes | 4.0.2 (`box.yml` on the push, 11:21 UTC) | `deploy.mjs --check`: zip and box up to date, 136 files verified |

Gates: Plugin Check on `git archive` of `0e03cd4` **0 errors** (the 2 known
warnings); `archive-hygiene` **8/8**; service `release-files` **10 passed**,
`pnpm test` **544 passed | 14 skipped**. Env swap 11:34:12 → :20 UTC;
redeploy `6d65ofa17` Ready in 43 s; `promote.mjs` says both hostnames serve
it. Pro 1.0.3 untouched.

## The copy that shipped

The S28b handoff's two lines verbatim as the readme's `= Fixed =` and the
GitHub release body; **one strapline is mine, accepted with "do whats best":**
*Dragging into a folder works again beside FileBird, and two tabs the folder
panel had covered are back.* — the 4.0.1 entry has one and the catalogue's
changelog line reuses it (`4.0.2: dragging into a folder …`). Rewording it
is a readme edit and a catalogue row, no new version.

## What else changed

- `docs/runbooks/rollback.md`: the shelf list, "previous" = 4.0.1 / v4.0.1,
  the 4.0.2 timings, two things learned (below). `docs/wordpress-org-submission.md`:
  the archive to upload is 4.0.2, Plugin Check's date row, the FileBird ✗
  row closed. `sprint-status.yaml`: 4.4 and 4.5 → `done`. Plan: the done
  section. `.harness/active.json`: S29.
- Service: your checkout was rebased onto `origin/main` (`--autostash`);
  your pricing commits are now `decef17`, `d3c34ba`, still unpushed, the
  `tsconfig.tsbuildinfo` change kept. The worktree and `shelf/4.0.2` branch
  are gone.

## Found, not done

1. **`npx @wp-playground/cli` resolves to 3.1.55, which cannot install**
   (`@php-wasm/node-8-1@3.1.55` unpublished). Plugin Check ran on the
   cached 3.1.54. `tools/matrix.mjs`, `play.mjs`, `uninstall-walk.mjs` and
   `verify.mjs` call the unpinned name — the next Playground suite fails at
   install until the package is fixed or the calls pin `@3.1.54`.
2. **`promote.mjs` and a checkout with unpushed work.** It waits for the
   checkout's HEAD on `/api/build`; from the service checkout it would have
   waited forever on `8bd695c`. Ran from the worktree. Same for
   `vercel env pull`, which needs the `.vercel/` link the worktree lacks.
3. `box.yml` again deployed before the read-back (order row from S26 stands).
4. Plugin `main` was pushed as a merge, not a rebase: the nightly watch had
   committed `d92a798` and the tag already sat on `0e03cd4`. Fine, but the
   train's push should `git fetch` first next time.
5. `dist/` held no 4.0.1 zip on this machine (only 2.9.x and now 4.0.2); the
   shelf and the release asset are the copies of record.
6. Nothing retired from the shelf (five free, three Pro files). Nathan's.

## Open, and Nathan's

- The wordpress.org form with `dist/vergelabs-media-library-4.0.2.zip`.
- The pricing commits (`decef17`, `d3c34ba`) and migration 020 — push when
  the migration is applied.
- Wave 4, the walk, paused at the Pay button (S28b addendum; `upg` holds
  free 4.0.1 — install 4.0.2 there first or walk on 4.0.1 as prepared).

## Next — S30

Per `plans/finish-the-suite.md`: wave 4 (the walk, your go, €0.50 + 1
credit) or wave 5 (the decision sitting). Opener:

```
Read docs/handoffs/2026-09-21-s29-the-release-train-4-0-2.md, then
plans/finish-the-suite.md ("Waves", wave 5). State which model you are and
follow that profile in ~/.claude/harness/model-profiles.md. This session is
wave 5: docs/decisions-2026-09.md, one row per question with the evidence
and a recommended answer. Stop points: nothing on ms2 or the real shop; no
answer is written into a doc or the code until I give it. End with a
handoff in docs/handoffs/.
```
