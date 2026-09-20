# S25b — Wave 1 (A, B, D, E, F) and consolidation 1c, same day

**Date:** 2026-09-20, afternoon. **Model:** Opus 5 (this session; the five
agents A, B, D, E on Opus 5, F on Sonnet 5). **From:**
`docs/handoffs/2026-09-20-s25-buyer-walk-on-4-0-0.md` (the walk, story C,
the plan). **Plan:** `plans/finish-the-suite.md` — its "Consolidation 1c —
done" section holds the merge list, the batteries, the 29-row triage and
the copy block. Plugin `43003b4` → `a850512` (+ this handoff's commit);
service `7c12bf1` → `b75aed9` (pushed, serving); pro `cea2e1e` → `da04526`
(not pushed — Pro has no channel until the 1.0.3 cut). **Spent: nothing.**
Nothing on the box, ms2, the real shop or Stripe. Worktrees removed,
branches kept.

## What Nathan asked

"Wave through hard now, go on for a few hours" — and: are BMAD and the
harness overboard? No: every story ran spec → build → test-first →
mutation → proof line → handoff, the consolidation ran `bmad-code-review`
(four lenses) with a triage table, sprint status tracks 3.2 and 4.1 at
`review`. What was skipped on his word: the per-story elicitation question
(plan defaults taken, recorded in each spec's "Decisions taken").

## What landed

- **A — story 3.2, the key leaves the ticket.** Plugin sends the key's last
  four characters as `licence` beside the site, never `key`; the service
  resolves by prefix + an activation on that site, honours a full key from
  3.16.x, and says in the support note when nothing matched (and how many
  licences end that way). `get-help` **22/22** (new Playground-booted PHP
  runner in `verify.mjs`), service **55** then **541 passed** overall;
  mutation 18/22. `readme.txt:183` carries the minimal factual sentence
  (the plan's Files line said same commit; the words stay Nathan's).
- **B — story 4.1, the CSV export cannot execute.** Cells starting `= + -
  @ TAB CR` get a quote prefix inside the quoted cell; the import strips
  it; `csv-local` (fixture-only, wasm) **69/69**, mutation 48/69.
- **D — Billing and the order page.** `shownInvoices()` (paid; open only on
  active / past_due / unpaid / trialing; hand-issued open shown; 50 asked,
  12 shown; the expand path from the exported API pin); `/api/order` answers
  `has_account`; a first-time buyer's "Go to your account" opens
  registration with the cart's address pre-filled from localStorage.
  `billing.test.ts` 11, `order/route.test.ts` 4.
- **E — Pro's minimum-free gate.** `VGMLPRO_MIN_FREE = '4.0.0'`; on an
  older free plugin the features stay unloaded and a notice says so; new
  suite `min-free` on the 3.16.1 archive **15/15** and on 4.0.0 **15/15**;
  no version bump. The runner now finds `../plugin` and `../dist` from a
  worktree too.
- **F — the 4.0.1 copy proposals** in `docs/release-notes-4.0.1-proposal.md`,
  reconciled at consolidation with what A landed.
- **Review patches** (`b75aed9`, `a850512`, `da04526`): rows 1–8, 10–15,
  22–23, 27 of the triage table.

Live after the push: the order page's new chunk serves (`has_account`,
`mode=register` in `2aqi9zdwce_9l.js`); `/api/order` and
`/v1/support/ticket` answer their own 400s. A 3.16.x plugin's ticket keeps
resolving by full key (tested); the 4.0.1 plugin is not cut yet, so no
customer sends a prefix until the train.

## Found, not done (all in `deferred-work.md`)

The moved-origin ticket case (a privacy call: may a unique prefix resolve
without a seat?); the box `csv` fixture never exports a trigger folder; a
4.0.0 export of a folder literally named `'=x`; Pro's in-page "too old"
sentence and readme floor; `compat-free --tree` with a real key; an `/order`
e2e row; `runPhpPlayground`'s duplicated judging; subscription purchases
never write a redemption (from story C).

## Open, and Nathan's

- **The copy block** in the plan's 1c section — five items; nothing ships
  in a readme until he has read it.
- **The train (wave 2):** 4.0.1 cut (readme lines, version, Plugin Check on
  `git archive`, `archive-hygiene`, zip, tag, release, `/releases/…`,
  `PLUGIN_RELEASES`, health, release-check), Pro 1.0.3 cut (pro HEAD carries
  the gate and the sealed key; `compat-free --tree` with `VGMLPRO_SEATS_KEY`
  first), then `csv` and `secrets` on the box after the plugin deploys.
- PRIVATE0 (harmless on yearly plans now; still 99 % on credits) and the
  `…AB26` licence — his clicks. The Stripe feedback text from S25 — unsent,
  awaiting yes/no.
- Two transport notes: a Bash heredoc followed by another command hangs
  the tool (one job per call); `rm -rf` cannot delete a pnpm `node_modules`
  tree on this path (filename too long) — `cmd //c rmdir /s /q` can.

## Next — S26

Wave 2 on Opus 5, sequential, cwd `plugin`, after Nathan's copy:

```
Read docs/handoffs/2026-09-20-s25b-wave-1-and-consolidation.md, then
plans/finish-the-suite.md ("The release train" and the 1c copy block) and
docs/runbooks/rollback.md. State which model you are and follow that
profile in ~/.claude/harness/model-profiles.md. This session is the
release train: 4.0.1 then Pro 1.0.3. Stop points: every readme line is
mine — use the copy block as I mark it; say what goes out before
PLUGIN_RELEASES changes and before any push; nothing on ms2 or the real
shop; the box deploy of 4.0.1 only after the served zip's header is read
back. Gates: Plugin Check 0 errors on git archive, archive-hygiene,
release-files, health free 4.0.1 / pro 1.0.3, release-check by hand. End
with a handoff in docs/handoffs/.
```
