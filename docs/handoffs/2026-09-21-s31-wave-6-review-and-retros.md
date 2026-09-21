# S31 — Wave 6: story 2.1 reviewed, epics 3 and 4 retro'd

**Date:** 2026-09-21, late afternoon. **Model:** Opus 5. **From:**
`docs/handoffs/2026-09-21-s30-the-decision-sitting.md`. **Plan:**
`plans/finish-the-suite.md` — "Wave 6 — done". Plugin `a3987c2` → `59f67ca`
(+ this handoff's commit); service checkout `eee31ea` → `33063ea` (unpushed,
on your branch with the pricing commits; nothing in it serves). **Spent:
nothing.** Nothing on ms2 or the real shop; `upg` was read and put back
(`0 differences`); nothing pushed.

## Story 2.1's code review

Four lenses over the walk tools and the service's `f581f56` + two scripts.
19 rows in `spec-2-1-…md` "Review Triage Log": 17 patched, 1 decision, 1
deferred, 21 rejected. What the patches changed:

- `tools/buyer-walk-site.mjs`: a **`snapshot`** stage (the T1 deliverable
  nothing produced — by hand on both walks); `restore` refuses to delete
  until the snapshot file is there, runs the diff outside the `&&` chain so
  its lines print either way, presses **Disconnect on Pro's Licence screen**
  (the buyer's path, never walked before) and judges the seat by `verify`;
  `describe` refuses a picture Pro already wrote and exits 1 unless the alt
  changed and exactly one credit went; `finally` puts the column option and
  the browser back. Proof on `upg`: snapshot **5 rows, `19cba051d577`** (the
  digest wave 4 had by hand), restore **`0 differences`**.
- `tools/buyer-walk.mjs`: registration through the order page's own
  "Go to your account" link (the finding D fixed can regress unseen
  otherwise); `final` picks the walk's licence tab and deletes the browser
  profile at the end; the default address carries the day. Parsed, not run —
  the next walk proves these.
- service: `e2e/auth.spec.ts` (**2 passed** against production, the two
  POSTs answered by `page.route`); `walk-inspect.mjs` lists invoices instead
  of searching (search is index-lagged); `make-walk-code.ts`'s header says
  it writes a production row and that `max_uses` does not hold on a yearly
  plan.

**The decision (row 7), yours:** the "Check your inbox" view after *Forgot
password* says "We sent a link to <address> to choose a new password." while
`/api/auth/password` deliberately answers "If that address has an account, a
reset link is on its way." Proposal: for `forgot`, show the route's own
sentence (already shipped copy, no new words). Say go, or give the sentence.

Sprint status: **2.1 → done**, and **4.2 → done** (its S27 review was on
file).

## The retros (headless, one `bmad-review` pass over both epics' diff)

**Epic 3 — accepted-with-open-items** (`epic-3-retro-2026-09-21.md`). The
finding that matters: the outage FAQ we shipped in S30 (readme +
`credits.md`, live) says "Pictures waiting to be described are described
when it is back". `core/ai.php:1568-1570` treats only `vergeml_ai_service_*`
codes as transient — a transport failure (`http_request_failed`,
`vergeml_ai_transport`) is stubbed permanently on the **first** miss, and a
5xx outage stubs a picture on its third strike; stubs are skipped by the
backlog. So a service that is *unreachable* marks every picture it touches
as failed until someone re-describes. My S30 reading of the row was from
line references, not a suite — that is the process item. Nine action
items; three are yours (E3-2 the qualified sentence, E3-4 the privacy page
words, E3-5 which plans the one-business-day promise covers — readme says
licence holders, the site says Pro and Agency, and `SUPPORT_REPLY_TO` still
defaults to `in.vergelabs.nl`). Epic 3 → `done`.

**Epic 4 — rejected** by the machine rule: 4.3 is decided, not built (FR14's
chosen branch is a screen). Override is yours; my recommendation is to leave
it until the 4.3 mock is approved. And one shipped defect, found by a probe
on `upg` (free 4.0.2): **Screen Options open in list mode sits under the
tree panel** — 3 of 4 column checkboxes unclickable (Author, Media
Categories, Used; only Date is right of the panel). `#screen-meta` is z 2
from 4.5's tab lift, the fixed panel z 3. Shot
`docs/superpowers/mocks/shots/2026-09-21-screen-options-under-tree-upg.png`.
One CSS line plus a modes.spec assertion — E4-1, 4.0.3 material, not
patched here (a retro proposes). Six action items.

Both retro keys `done`, 15 action items in `sprint-status.yaml`, one
proposed transition on epic 1's items (14 → in-progress: commits name their
story now; the `AGENTS.md` line is missing) — not written, headless.

## Found, not done

- The service commit `33063ea` sits on your checkout branch behind
  `dc0a692`/`eee31ea` (pricing); it goes out when you push, or I cherry-pick
  it through a worktree at `origin/main` on your word.
- `deferred-work.md`: the Playground-pin row marked resolved (S30's work);
  one new row (the walk's PDF is saved, never read for its number).
- `git_evidence.py` attributes `3f590ea` to story 3.1 on the string
  `3.1.54` — noted in the retro, harmless.

## Next — S32

Yours first: the row-7 sentence (say go), E3-2's FAQ sentence, E3-5's
audience word, the epic 4 override or not. Then, in order:

1. **4.0.3 material in one small story**: E4-1 (Screen Options over the
   panel, with the probe as the assertion) + E3-2 (the FAQ sentence) + E4-3
   (the `test.skip`). Spec → build → review; the train after.
2. **E3-1** as its own story: transport errors transient, stubs from a
   transient status offered again.
3. **4.3's mock** (E4-5), then the story; epic 4's verdict re-rendered after.
4. Epic 2's retro waits on 2.3 (the accountant).

Opener:

```
Read docs/handoffs/2026-09-21-s31-wave-6-review-and-retros.md, then
_bmad-output/implementation-artifacts/epic-4-retro-2026-09-21.md (E4-1) and
epic-3-retro-2026-09-21.md (E3-2). State which model you are and follow that
profile in ~/.claude/harness/model-profiles.md. This session: bmad-spec for
the 4.0.3 story (E4-1 + E3-2 + E4-3), then bmad-build, the probe's 4/4 on
upg and modes.spec on the box as the proof, bmad-code-review. Stop points:
nothing on ms2 or the real shop; every user-facing string is mine; say what
goes out before any push; no cut. End with a handoff in docs/handoffs/.
```
