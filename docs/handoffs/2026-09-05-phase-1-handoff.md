# Session handover — 2026-09-05, Phase 1 done (Fable 5.1)

For the next session. Read in this order, then open Phase 2 of the plan:

1. `plans/folders-one-tree.md` — Phases 0 and 1 are done; Phase 2 (the
   shared tree component and the folders version stamp, plugin side) is
   next and wants Fable 5.1. Phase 2 must land before Phase 3.
2. `docs/superpowers/specs/2026-09-05-folders-screen-design.md` §2 (one tree
   component, version stamp), §11 "The tree at scale", and the mock
   `docs/superpowers/mocks/2026-09-05-folders-screen.html` for the tree's
   two states.
3. `docs/ai-service.md`, the last section — the stream contract Phase 3
   consumes; `docs/handoffs/2026-09-05-phase-0-handoff.md` for the morning.
4. `~/.claude/harness/model-profiles.md` — state the model, follow the
   profile.

Both repos on `main`. Plugin: `e6f80f7` + this handoff's commit. Service:
`ef6f592` on top of `1953de6`. The nightly watch commits to `main` around
05:17 UTC: `git pull --rebase` before pushing.

## Still Nathan's

Migration 018 (`library_counts`) on the production database, from the
Phase 0 handoff. Nothing in Phases 1–3 depends on it.

## What landed (service `ef6f592`)

**Task 6 — `POST /v1/guide/session`.** Body `{ license_key, site, summary }`
from the plugin server-side. Answers `{ token, expires_at, summary_hash }`:
HS256 JWT, one hour, claims `licence_id`, `site`, `summary_hash`. Auth and
metering exactly as `/v1/guide` (licence known, entitled, site activated;
`meterCall('guide', 10)`; the daily site limits). Signed with
`GUIDE_TOKEN_SECRET` when set, else a key derived by HMAC from
`LICENCE_KEY_SECRET` (`lib/guide-token.ts`) — so nothing new had to reach
Vercel or the box; setting the dedicated secret later rotates every token.

**Task 7 — `POST /v1/guide/stream`.** `Authorization: Bearer <token>`, body
`{ conversation, tree, input: { text | choice | edit | open: true }, summary,
goal?, current? }`; the summary must hash to the claim. `text/event-stream`:
`say` `{ text }` deltas, exactly one `tree` `{ tree }`, then `done`
`{ usage, choices }` (≤3 chips); or `error` `{ code, retry_after? }` with
`provider_busy`, `turn_cap` (25 assistant turns, enforced here too),
`bad_tree`, `failed`. Before the stream: `401 bad_token` (`why`:
`missing | malformed | bad_signature | expired | site | summary | licence`),
`403 not_entitled`, `400 empty_input`, `429` on limits. CORS allows exactly
the token's site; the preflight (no token) is answered for any origin that
is an activated site (`store.siteActivated`). Prompt: the guide rules, then
"talk first, then a fenced ```tree block holding the whole tree and the
chips"; the split happens on the text as it streams (`lib/guide-stream.ts`,
fence and its preceding whitespace held back until known); a bad block is
asked for once more, non-streamed, with the words kept.

**Task 8 — evals against the stream.** `evals/guide-stream.eval.ts`
(`pnpm eval:guide-stream[:local]`): the three fixed conversations through
`guideStreamTurn`, events assembled, the five scores as before plus "a tree
arrives" and "no fence in the words". All seven at 100%.

## Evidence

- `pnpm test` → `Test Files 19 passed | 5 skipped (24)`,
  `Tests 342 passed | 12 skipped (354)`; `pnpm typecheck` clean. New:
  `lib/guide-token.test.ts` (7), `lib/guide-stream.test.ts` (10: every
  chunk boundary of a fenced answer, re-ask, bad_tree, busy before and
  mid-stream), `app/api/ai/guide/{session,stream}/route.test.ts` (4 + 13:
  expired / wrong site / wrong licence / wrong summary → 401; other origin →
  no allow header; preflight for an activated site only; turn_cap as an
  event; one meter per turn).
- `pnpm eval:guide-stream:local`: 3/3, seven scores at 100% (about ten
  cents of Sonnet 5).
- Production, by deployment age: Vercel build 48 s old after the push. Real
  turn from the box with its licence against `https://ai.vergelabs.nl/v1`
  (`tools/box-stream-check.sh`): session `HTTP 200` in 1.9 s; stream
  `HTTP 200`, 3,512 bytes, first byte at 3.0 s, last at 7.3 s — it streamed;
  83 events (81 say, 1 tree, 1 done, 0 error); no fence in the words; usage
  4,721 in / 360 out; chips "Sort unfiled into existing subject folders",
  "Split first by kind…", "Something else".
- Box service: `vps.yml` run 33965607087 success; `127.0.0.1:3100` carries
  the two routes.

## Decisions taken here, for Nathan to overrule

- Chips travel inside the fenced block (`choices`, ≤3) and out on `done`,
  not as their own event — the spec lists say/tree/done/error and does not
  say where chips go.
- `input.open: true` opens the conversation (the assistant's first turn with
  nothing said); `error` also has `failed` for an unexpected exception.
- Turn cap 25 is enforced by the service as well as the plugin (the old
  route's backstop was 60).
- The token secret is derived from `LICENCE_KEY_SECRET` until a
  `GUIDE_TOKEN_SECRET` exists — algorithm and lifetime as specified.
- Origin mismatch is `401 bad_token · site` (the validation strategy said
  401 for "wrong site"); a caller without an Origin header is not a browser
  and gets no CORS headers and no refusal.

## For Phase 3 (found, not done)

- On `open`, the plugin must send today's folders as `tree`: the model
  answers with the WHOLE tree, so an empty draft came back empty on the box
  (0 folders) — correct for the input it got, wrong for the screen.
- The opener on the box was 819 characters (a list of facts, then the
  question) — longer than the spec's "two sentences". Tune in Phase 3 with
  the eval, not by hand.
- The `say` text uses "- " lines for facts; the renderer turns those into
  the brand-mark list.
- `service/tsconfig.tsbuildinfo` is still tracked; `git checkout` it before
  committing.
- The service's working copy is CRLF after a stash/pop; patch scripts must
  normalise line endings (memory `file-edits-in-this-harness`).

## Phase 2 opener, to paste

```
Read docs/handoffs/2026-09-05-phase-1-handoff.md, then plans/folders-one-tree.md.
State which model you are and follow that profile in ~/.claude/harness/model-profiles.md.
This session is Phase 2, tasks 9 and 10 (plugin: js/vergeml-tree-view.js as the one tree component for the media library panel and Folders, draft overlay by term_id, changes-first and all-folders states, find box; the folders version stamp, its REST route and the 5 s poll).
Stop points: any change to how the media library panel behaves that the spec does not describe; anything that moves files.
Gates: tests/tree/folders-version.php with its mutation check, tests/ui/library.spec.mjs on the box, Gate 5 (the version poll is one option read), the shell screenshots.
End with a handoff in docs/handoffs/.
```
