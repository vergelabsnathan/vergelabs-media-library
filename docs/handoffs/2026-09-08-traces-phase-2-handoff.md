# Session handover — 2026-09-08, traces Phase 2: the model stops counting (Opus 5)

Phase 2 of `plans/traces.md` is built. Every number beside a draft folder now
comes from `vergeml_filing_pick()`, the model is told it may give none of its
own, and `core/filing.php` is untouched — which the box was made to prove
rather than argue.

Read in this order:

1. `docs/superpowers/specs/2026-09-08-traces.md` — the contract.
2. `docs/superpowers/specs/2026-09-08-traces-diagnosis.md` — the argument.
3. `docs/handoffs/2026-09-08-traces-phase-1-handoff.md` — the trail underneath.
4. `plans/traces.md` — the five phases. Phase 3 is next.
5. This handoff.

## The assertion this phase had to keep, and how it was kept

Nothing here changes where a picture goes. Not argued from the diff — measured
on the box, twice, in the same minute:

    tools/box-filing-baseline.php with the change   ── byte-identical ──┐
    tools/box-filing-baseline.php with it reverted  ────────────────────┘

Both over all 641 pictures. The change was stashed, deployed, run, restored,
deployed, run. Two consecutive runs with the change are also byte-identical to
each other, so the run is repeatable.

**`core/filing.php` is unmodified.** The floor (0.55), the margin (0.08), the
gates and the order of the matcher are untouched.

## What changed

| file | what |
|---|---|
| `service/lib/guide.ts` | `guideRules` gains the spec's rule, verbatim |
| `service/lib/guide-stream.ts` | the streamed prompt stops asking for "folders **and counts**" |
| `service/lib/guide.test.ts`, `guide-stream.test.ts` | the rule asserted by quotation, and the stream asserted not to ask for counts |
| `service/evals/guide/filed.json` | the case that started this: *"did you put them in folder yes or no?"* |
| `service/evals/guide.eval.ts`, `guide-stream.eval.ts` | two scorers: speaks about the draft; gives no count of its own |
| `core/guide.php` | `vergeml_guide_draft_fit()`, `vergeml_guide_draft_profile()`; the turn route runs them; `fit` on the session |
| `core/search-meaning.php` | a phrase vector held for the request, and kept a week rather than an hour |
| `js/vergeml-folders.js` | the model's `count` dropped; the run's numbers taken; the Move button counts what the run counted |
| `tests/ui/folders.spec.mjs` | every number on the draft equals the run's; the walk greps the model's message |
| `tools/box-fit-cost.php` and five more | the probes that measured all of the above |

### Where the number is computed, and why there

The browser streams the words straight from the service (`/guide/token`, then
the service's own `/guide/stream`), so the plugin never sees them. But the tree
the browser builds out of them comes back through `/guide/turn` and no further.
That is where the draft settles and where the number belongs, and it is where
`vergeml_guide_draft_fit()` runs — over **every described picture**, because a
conversation's Move re-files every one of them (`vergeml_talk_refile_run()`,
no `assign`). A narrower scope would print a number the Move does not
reproduce, which is the defect, not the fix.

Counts are "after Move", read exactly as `vergeml_guide_rule_draft()` reads
them: a kept folder is `live + landed − left`, a new folder is what lands.

### Profiles for folders that do not exist

A draft's folders mostly have no term, and `vergeml_filing_pick()` scores
against profiles. `vergeml_guide_draft_profile()` builds one in memory from the
draft's own fields — the same seed `vergeml_talk_apply()` hands
`vergeml_filing_profile_build()` when it makes the folder for real. It cannot
call that function: it needs a `WP_Term` to walk and it writes term meta, and a
draft must leave no trace. **The two must be kept in step** — the text is what
the vector is made from, and a difference there is a number on screen the Move
will not reproduce. A folder that exists and that the draft neither renames nor
moves keeps its stored profile, because that is what the Move will match it on.

Profiles are keyed by a number of the run's own making, not by term id:
`vergeml_filing_pick()` casts its keys to int, a new folder has none to give,
and a synthetic id colliding with a real one would misfile silently.

## Two changes beyond the three in the brief, and why

**`core/search-meaning.php`.** Not on the list, and the dry run is unusable
without it. `vergeml_meaning_vector()` costs 0.161 ms per call fully warm — two
`get_option()`s and four `apply_filters()`, with only a per-request object
cache — and `vergeml_filing_class_match()` asks twice per class pair. One run
asked **204,014 times** and spent **32.9 of its 44 seconds** there; the
arithmetic underneath was 7. A request-scoped memo holds the ~450 distinct
phrases. Then the transient's hourly expiry was found to cost far more (below),
so phrases are kept a week. Neither changes a vector, and the baseline says so.

**The Move button.** It read *"Move 4 pictures"* beside *"241 pictures move
into 19 folders"*, both about the same draft. The tree can only total what
folders **gain**, and a draft that consolidates makes them shrink — the Move
puts each placed picture in one folder and takes it out of every other. Both
numbers now come from the same run. This was a contradiction the phase's own
change made visible, so it is fixed here rather than left for Phase 3.

## Proof

**Screenshot into the conversation:** `tests/ui/shots/folders-dry-run.png`. The
planted probe folder carries `count: 12` — the exact shape of the number this
replaces — and the screen shows the matcher's **0**. Under the conversation, in
the brand-mark list:

    241 pictures move into 19 folders
    263 score below the floor
    18 too close to call
    15 the wrong kind

Those four lines are `vergeml_guide_rule_fit()`'s own strings, word for word.
No new user-facing copy was written anywhere in this phase.

**`tests/ui/folders.spec.mjs`** asserts, per folder, that the draft's `count`
is the run's `counts[key]`; that every folder has one; that the abstentions
account for the pictures looked at; and that the Move button's digits equal
`fit.move`. It costs no turn — the route takes a draft with no words beside it.

**The model's own message, on the box, through the deployed service:**

    "There are two "Apparel" folders right now: a"    (stopped mid-stream)

No count, no claim about the library. And asked the question from the
diagnosis, through `lib/guide.ts` against the live prompt:

    "The draft isn't filed yet — nothing moves until you confirm it. …"

## The eval, and the half of the rule that does not hold

Braintrust, one layer, models through OpenRouter. Four cases, both paths:

| scorer | turn | stream |
|---|---|---|
| speaks about the draft, not the library | **100%** | **100%** |
| gives no count of its own | **50%** | **25%** |

**The first is the failure this phase set out to end, and it is ended.** No
"Yes", no "done", no "routed", no "nothing is left unfiled".

**The second does not hold, and it is Nathan's to settle.** The full answer
above continues: *"the evidence supports Landscape and nature at roughly 180
and a smaller Architecture around 44"*. Those are object-class totals from the
summary offered as folder sizes. No matcher produced them; the screen beside
the conversation now says 91 and 26. The rule says *"Give no counts of your
own"* while rules 2 and 3 say *"say the number"* — the model resolves the
conflict by quoting the evidence, which is defensible and still puts a wrong
folder size in front of an owner.

It is scored rather than hidden, so a wording change can be measured. The
wording is a stop point in the spec: **a change to it is Nathan's.** One
amendment that would fit the existing rules: *"Shares of the library you were
given may be quoted as shares. Never turn one into a number of pictures in a
folder."*

## The cold cache, which is the real limit

Measured on the box, 641 pictures against 31 folders:

| | seconds | queries |
|---|---|---|
| cache empty | **210.6** | 2,180 |
| warm | **7.5 – 9.9** | 3 |

The gap is ~700 phrase vectors fetched one HTTP call at a time. With the week
kept and the memo held, a site pays this once per phrase rather than hourly,
and the steady state — any site whose library has ever been filed or previewed
— is ten seconds.

A cold site does not get numbers. The run stops at twenty seconds
(`vergeml_guide_fit_budget`) and answers **nothing**, not half: a count over
the first two hundred pictures is not a smaller truth, it is a wrong number.
Each attempt warms ~37 phrases, so a cold library needs roughly nineteen turns
before a number appears. **That is not good enough and it is the first thing
Phase 3 should look at.** The right shape is the one the Move already uses:
slices, a deadline and cron, with the screen polling — `vergeml_talk_refile_run()`
is the model to copy. It was not built here because the screen needs a state
for "working it out", and that is a surface, which is Phase 3's.

## Found, not done

- **The filing baseline has drifted, and not from this phase.**
  `tests/tree/filing-baseline.txt` no longer matches the box: 108 of 641 rows
  differ, 107 of them by ~0.0001 in the runner-up's score alone — same folder,
  same word, same winning score — and **one picture (2817) moved from `ok` to
  `margin`**, its score down exactly 0.030000, which is the deepest-folder
  tie-break flipping. Ruled out, each by measurement: this phase's changes (the
  box answers identically with and without them), the service (three fresh
  embeds of one phrase, one checksum), the folder profiles (`tools/box-profiles-when.php`
  — none rebuilt since 7 September), and the descriptions (`tools/box-index-when.php`
  — 2817 last described 3 September). **The plan's last gate depends on this
  file being reproducible, so this needs an answer before Phase 5.** The file
  was deliberately not re-taken: re-baselining would destroy the evidence.
- **The walk's in-progress assertion is red, and the Move it watches works.**
  `folders.spec.mjs:352` waits for `[data-state="moving"]`; at the moment it
  gave up the page already read *"Undo until tomorrow 04:56 PM"* — the Move had
  finished first. 109 pictures had moved into four folders. **The walk does not
  undo when it fails**, so the box was put back by hand
  (`vergeml_talk_undo()` → `{"restored":109,"unmade":4}`, 31 folders again).
  The walk is not in the phase's gate list and is skipped without
  `GUIDE_WALK=1`; it was not green before this phase either. Its own new
  assertions — no count, no claim about the library — passed.
- **A hand edit shows no numbers until the next turn settles.** `guide/session`
  clears the fit rather than showing one about a tree that has changed. Most
  hand edits do reach `/guide/turn`; one made at the turn cap does not.
- **`modes.spec` is still 337px over `ROW_ALARM` (300)** in both grid and list,
  over `fb_folder, fb_filesize, qode-optimizer, seopress_alt_text,
  aioseo-details`. Other plugins' columns, same message as Phase 1, not chased.
- **Scale.** Ten seconds is 641 pictures. The work is linear in pictures ×
  folders, so the plan's "library of fifty thousand" is minutes even warm. The
  background pass above is the answer to that too.

## Gates

| gate | result |
|---|---|
| `npx vitest run` (service, all) | 360 passed, 12 skipped |
| `npx vitest run lib/guide.test.ts lib/guide-stream.test.ts` | 22 passed |
| `npx tsc --noEmit` (service) | clean |
| `node tools/verify.mjs filing-trail` | 47/47 passed |
| `node tools/verify.mjs copy journey guide` | 62/62 passed |
| `node tools/verify.mjs tree` (Playground) | 21/21 passed |
| `npx playwright test … modes.spec shell.spec shots.spec folders.spec` | 32 passed, 3 skipped, **3 failed** — see below |

**Two of the three failures are the `ROW_ALARM` canary and are not this
phase's**: `modes.spec:506`, once in grid and once in list, 337px against 300
over `title, author, date, fb_folder, fb_filesize, qode-optimizer,
seopress_alt_text, aioseo-details` — three core columns and five belonging to
FileBird, Qode, SEOPress and AIOSEO, none of ours. Identical message and
identical column list to Phase 1's.

**The third was a flake.** `shots.spec › screenshot: ai-how` failed with
`net::ERR_ABORTED; maybe frame was detached?` on `page.goto` — a navigation
abort, not a fatal and not a layout. Re-run alone afterwards: **11 passed**,
`ai-how` in 11.9 s.

The mutation check on the new service assertion ran: one word changed inside
the rule and `carries the draft-is-not-the-library rule verbatim` went red;
reverted, green. The assertion quotes the rule rather than matching a keyword,
so softening the wording cannot pass it.

`t0-endpoints.js` cannot be run directly — `node tools/deploy.mjs --zip`, then
`node tools/play.mjs --port 8899`, then `node tools/verify.mjs tree`. Playground
mounts the source directory, so the zip matters only for the shared link.

A throwaway administrator was made with `tools/box-ui-user.sh` and deleted at
the end.

## The box, as it was left

31 folders, which is what it had at the start. The walk's Move was undone by
hand (above). The session the specs found was put back: 14 turns and the
34-folder draft from 4 September — **and that draft still carries the model's
own counts**, Illustrations 23, Screenshots 6, Diagrams 5, the three the
diagnosis quotes. It is Nathan's session and was restored rather than
corrected; the first turn taken on it will replace every one of those numbers
with the matcher's. Opening the screen and reading them side by side is the
shortest demonstration of what this phase does.

## The plugin repo is not pushed

Five commits here (`bb4e6db` … `c2c12bf`), and local `main` is **25 ahead of
`origin/main` and 2 behind** — the two are the nightly watch's own
`chore(watch)` commits. Phase 1's commits are among the 25, so this is where
the last session left it, not something this one caused. The plugin ships
through `tools/deploy.mjs`, not through GitHub, so nothing is blocked by it;
but two phases of work exist only on this machine. A `git pull --rebase` would
land cleanly — the watch commits touch only watch reports — and it was left
alone because pushing this repo was not asked for and is Nathan's call.

## The service is deployed

`04f9490` and `0fd052e` are on `main`. Deployment `dpl_33HDLBckpqs7XDCMG2QCmkogAPLJ`
is Ready and **carries the `ai.vergelabs.nl` alias** — which is what the plugin
calls — with the edge cache reset (`age 0`, `x-vercel-cache MISS`). Checked by
identity, not by a status code.

## Cost

The walk spent two planner calls, about 20 credits. The two evals spent eight
Sonnet 5 turns through OpenRouter, roughly 25 cents. The dry run itself spends
no credits: `/api/ai/embed` charges none by design, and it is rate limited on
the describe counters instead.

## What Phase 3 is

The two surfaces, and the mock was lifted by Nathan on 2026-09-08 — built from
the Folders screen's existing components, no new visual grammar. Beside the
draft: each folder with the count the matcher gives it, which this phase now
supplies. On a picture: "why is it here", read from the two tables Phase 1
filled. Screenshots into the conversation before it is called done.

**And before either: the cold cache.** A surface that shows nothing for
nineteen turns is not a surface.
