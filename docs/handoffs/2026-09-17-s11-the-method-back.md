# Handover — 2026-09-17, S11: the method back, the owner's round, the questions' grain, the sweep gate, the describer eval (Opus 5)

Card: `.harness/active.json` (S11). Plan: `plans/every-picture-a-home.md`
Phase D. Spec: `docs/superpowers/specs/2026-09-16-folders-at-catalogue-scale.md`
(S10.5 carries its Built paragraph now). Evidence before: S10's handoff.
Lean held: test first, one mutation per story (each seen red, the code put
back), both sites measured, ms2 untouched except two read-only reads.

**Spend:** the describer eval, ≈ $1.05 on the OpenRouter ledger (three
models × 100 pictures, said before it ran, nothing written to any library);
the owner's round, 0 credits and 0 planner calls (three fills on the tech
site, the group names metered); **one credit on the box licence** to
re-describe picture 105862 after a mutation run wrote a mock row over it
(below). Nothing else.

Commits, plugin (`main`, deployed to the box after each): `93de358` the
review's four fixes, `5729a2d` the round + the progress-row fix + guide G1,
`c9bcee2` S10.5, `717b7d2` the grain tool, `480d0d5` the sweep gate row,
then the eval set tool, the spec and this handoff. Service: `evals/
describer-models.eval.ts`, `evals/describer-table.mjs`, `evals/data/
describer-set-2026-09-17.json`, `evals/probe-transport.ts`.

## The method, as found

- The two "zips" in `_reset-2026-09-14/` are GNU tar archives named `.zip`
  (`tar tf` reads them; unzip and .NET refuse). **BMAD is in neither and
  never was on this machine** ("Zero BMAD anywhere" in the audit); it is
  per-project (`npx bmad-method install …`) and the audit chose to pilot it
  elsewhere first. `bmad-*` in the global CLAUDE.md names skills that were
  never installed here. **The harness profiles are in the home archive**:
  `harness/model-profiles.md` (115 lines), `harness/hooks-design.md`, the
  three `harness-*` skills and `session-handoff`; extracted to my scratchpad,
  nothing copied back — Nathan's call, two choices put to him at the start:
  BMAD in `plugin/` (a pilot session, not this one — my recommendation) and
  the profiles file back under `~/.claude/harness/` (a copy, no hooks).
- Run by hand this session, as S10: the card, a story per finding, test
  first, one mutation, the handoff — plus what S10 lacked: `/code-review` on
  its commits.

## The code review of S10 (`3727f6a..64c9e7a`, 18 commits)

Four findings, each a story (test red → fix → mutation red → restored):

- **A tick that met the pass lock spun.** `vergeml_talk_refile_event` booked
  the event at `time()` and chained a loopback that met the lock again at
  once — a request a second for as long as the lock stood (10 s behind a
  kick, 120 s behind a killed pass). Now booked a stall out, not posted.
  `sticky.php` E5.
- **The run's end was unbounded inside a poll.** The finish deleted folders
  then named every residue group (a 20 s call each), and a kick's finish ran
  inside the browser's request. Now names first, once per run (`asked`,
  written before each call), in the pass's own time — out of time, it
  returns false with the run still active and the next pass carries on from
  the names kept; the terms go only after the questions are built.
  `sticky.php` F1–F3.
- **keep-parent pulled a hand move back.** Since 09-17 keep-parent and split
  moved every picture of the question and marked them `answer`; a picture
  the person had dragged into a child since (placed_by `user`) was pulled to
  the parent and downgraded. An `answer` move now skips a picture that is
  the person's. `sticky.php` G1.
- **"Counting" for ever on a host whose cron never runs.** The fit's revive
  only ever re-booked. On the third poll with no job started it works the
  fit in the poll's own request when the shape fits one, and settles it as
  unknown ("The counts are not worked out yet") when it does not.
  `guide.php` F7–F8.

Suites: `sticky` 35/35, `guide` 40/40, `filing` 31/31 + 34/34, registers
regenerated (line numbers only), tech baseline 4/4.

## The owner's round (`tests/ui/round.spec.mjs`, 3.2 min, green)

Confirm → Fill → an answer → Leave the rest → Fill → Unconfirm → × on a word
→ confirm → Fill, every button real, on the tech site, a shot at every
state (`tests/ui/shots/round-01…13.png`). `tests/ui/round-fixture.php`
snapshots the whole library's filing by literal SQL before (1,000
relationships, 21 folders' meta, 62 marks, the fill's options, the trail's
high water) and puts it back row for row after — `restored: 1000 (1000),
21 (21)` printed and asserted. It also clears the fill's state so the round
starts as an owner does (the box's 30 open questions wait in the snapshot).

Held: the fill after a fill runs (494 placed, 15 s; `guide.php` G1 drives
the route on a confirmed session with no draft, its run stopped in the same
breath); the second fill asks nothing about the 6 pictures "Keep them in
Hardware" placed; the word taken off Data centres is gone from its profile
after the confirm and the third fill runs (494, 15 s). Two confirms, 0
planner calls (`profiled 0`).

**Found by the round, fixed the same hour:** the progress row stood at
"Filling 1,000 of 1,000 · nothing moved for 34 s" under a finished fill —
`renderFill` returned before the one call that hides it whenever the run
ended into questions (or a done tree left open). The row is the run's now:
gone the moment the run is. `folders.spec` "the progress row", red before.

**Seen, not fixed (design, mock-first):** at 1600×1000 the fill opens every
parent (≤ 40 folders) and the button and its row sit below the fold; the
running shot shows the tree, not the progress.

## S10.5 — the questions' grain

- Either/or pairs of one picture fold into one card (`e:one`): each picture
  keeps its own two folders (`pairs`, best first, said on its thumbnail as
  "A or B"), the answers are split / leave / look; one such pair alone keeps
  its own card with its put-ins. Two folders that share a leaf are named by
  their paths ("Bags & Luggage › Backpacks or Camping › Backpacks?"), in
  every siblings and either/or sentence and on the folded card's thumbnails.
  `residue.php` 24–26, both mutations red. The engine untouched: tech 4/4.
- The shop's number for the fold waits for its next fill: the 41 pairs of
  09-16 were answered, the state holds 5 questions and no pair now
  (`tools/box-question-grain.php`, read-only). Tech: 2 pairs, one single —
  unchanged, as the rule says.
- Rule 3 (the head noun) untouched: it moves the tech baseline.

## The sweep gate — the premise, corrected

"A model change sweeps every customer's library by itself (it does today)"
is not what the code does. `vergeml_ai_pending('stale')` judges staleness
**on the prompt hash alone, never on the model** (core/ai.php, deliberate:
one picture escalated to a stronger model once made a whole library stale),
and the service's hash is the system prompt only. So a describer switched in
the service leaves every row where it is, and the only way to re-describe
them is the dashboard's "Re-describe N images · Costs N credits" button.
**What does sweep by itself is a prompt or profile change** (the 09-03
design: the run's end starts a `stale` run, `prompt_changed`). The gate row
proves the model half: `background.php` G1 (rows on another model, the same
prompt → stale 0, no run), mutation (judge on model) red.

**A rule broken, repaired, and fenced:** under that mutation the sweep
started a run over 999 pictures in demo mode, and one cron tick got in
before the suite's stop and wrote a mock caption over picture 105862 ("Japan
Arrival Leaflet Notice"). Re-described for real the same hour (one credit;
1,000 rows on one hash again, `stale 0`). The suite now answers the cron
nudge itself, so a mutation there can never reach a tick.

**Decision for Nathan (not taken):** should the *prompt* sweep also become
the button? Today a prompt change costs every customer's credits without a
press; a model switch costs nothing until they press. If the eval leads to
a switch, the service would fold the model into the prompt hash — and that
would sweep automatically under today's rule. One line in `core/ai.php`
(the two auto-starts) turns the sweep into the button only.

## The describer eval (`service/evals/describer-models.eval.ts`)

Set: 100 real pictures — 43 tech and 36 shop pictures whose folder Nathan
marked right on the 09-16 sheets, 21 from the shop's To sort — exported
read-only (`tools/box-eval-set.php`, `evals/data/describer-set-2026-09-17.
json`). Each described once on the service's own prompts and schema; scored
on whether an object phrase names the marked folder (leaf name or profile
word, equal or head noun — the plugin's rule) or, for the parked, any leaf.
The number is comparable across models, not an accuracy: the real matcher
also has the vector and the second phrase, which is how many of the marked
rights landed.

Transport, verified first: **through the service's path (Anthropic SDK →
OpenRouter) Gemini's JSON came back cut mid-string and GPT-5 mini answered
nothing in 30 s**; the eval's own OpenAI-compatible path (same prompts,
`response_format json_schema`, effort low) carries all three. Haiku ran on
the production path.

OpenRouter today: `google/gemini-3.8-flash` $0.75 / $3.75 per M (Haiku 4.5
$1 / $5; `openai/gpt-5-mini` $0.25 / $2). "Much cheaper" in the pasted
comparison is a quarter per token; the ledger per picture is the number.

| model (transport) | folder named, 79 marked | tech 43 | shop 36 | leaf named, 21 parked | JSON failures | latency p50 / p95 | ledger per picture / per 100 | tokens in / out |
|---|---|---|---|---|---|---|---|---|
| **Haiku 4.5** (service, today) | **54 % (43)** | **65 % (28)** | 42 % (15) | **62 % (13)** | 3 % (3: structured output unparseable) | 6.5 s / 15.3 s | ≈ $0.0050 / $0.50 (from tokens; no ledger id on this path) | 3,844 / 248 |
| Gemini 3.8 Flash (openai) | 52 % (41) | 49 % (21) | **56 % (20)** | **62 % (13)** | 2 % (2: cut mid-JSON) | 2.5 s / **35.0 s** | $0.0033 / $0.33 | 3,120 / 265 |
| GPT-5 mini (openai) | 46 % (36) | 44 % (19) | 47 % (17) | 57 % (12) | **0 %** | **1.5 s / 3.1 s** | **$0.0016 / $0.16** | 2,700 / 616 |

Local table: `node evals/describer-table.mjs` over `evals/out/*.jsonl` (gitignored);
the Braintrust experiments were run `--no-send-logs` (no BRAINTRUST_API_KEY on
this machine; it lives in Vercel prod) and print the same scores.

Verdict: **no switch.** The rule was 5+ points at equal or lower cost. Gemini 3.8 Flash is −2 on the folder overall (−16 on the tech library, +14 on the shop's product shots), equal on the parked, a third cheaper, faster at the median and worse at the tail (35 s p95); GPT-5 mini is −8, the cheapest by two thirds, the fastest and the only one with no JSON failure. With 43 and 36 pictures a subset moves ±7 points on one picture in six, so the tech/shop split is a lead (Gemini reads products better, Haiku reads hardware better), not a finding. The 3 % of Haiku's answers the service path could not parse are the escalation rate in production (they go to Opus there; here they count as failures). Worth a second look later: Gemini on a shop-only set of 100 at the same cost, and the transport (the Anthropic path cut Gemini's JSON; the OpenAI path did not) — but nothing in this table pays for a switch, a sweep gate and two re-taken baselines.

## Not done, and why

- `folders.spec` on ms2 (Nathan was on it); the shop band after S10.5 (the
  engine did not move — tech proves it — but the gate says both, and a
  read-only baseline on ms2 is a boot of his site).
- S10.4, S10.6 (mock-first), S10.5 rule 3, S10.5b, the tree's "× on the
  last word" — S12.
- `vgmls11`, a session admin on the tech site (password in this session's
  scratchpad only) — **deleted at the end of this session**; `vgmls9` on ms2
  still to delete when C.5 closes.
- The profiles file and BMAD: Nathan's two answers.

## Next — S12

Card, to `plugin/.harness/active.json`:

```json
{
  "phase": "Every picture a home — S12: the sweep decision, the tree at scale (S10.4, S10.6 mock-first), the last word, folders.spec on ms2",
  "model": "opus",
  "plan": "plans/every-picture-a-home.md (Phase D: S12)",
  "spec": "docs/superpowers/specs/2026-09-16-folders-at-catalogue-scale.md",
  "scope": [
    "core/ai.php, core/ai-background.php (the sweep as a button only, if Nathan says so: the two auto-starts, tests/ai/background.php row with its mutation)",
    "js/vergeml-tree-view.js, css/vergeml-tree.css (S10.4: the fold control and the hover card — one concept line, tools/shoot-mock.mjs, Nathan's yes, then tree-view.mjs + folders.spec at 1600 and 1280)",
    "core/media-list.php, js/vergeml-tree.js, css (S10.6: the list opens on the pictures, the sidebar full height, the tree first — mock-first; modes.spec)",
    "js/vergeml-tree-view.js (× on a folder's last word means no words: an explicit empty, tree-view.mjs row)",
    "js/vergeml-folders.js (the fill's progress row visible at 1600×1000 with every parent open — design, mock-first)",
    "tests/ui/folders.spec.mjs on ms2 when Nathan is off it; the shop band after S10.5; the fold's shop number (tools/box-question-grain.php after the next fill)",
    "service/lib/anthropic.ts (only if the eval verdict says switch: the transport that carries the model, the sweep gate first)",
    "docs/handoffs/**",
    "plans/**"
  ],
  "readFirst": [
    "docs/handoffs/2026-09-17-s11-the-method-back.md (this: the review's four fixes, the round, the grain, the sweep premise corrected, the eval table and verdict)",
    "docs/superpowers/specs/2026-09-16-folders-at-catalogue-scale.md (S10.4, S10.6, S10.5 rule 3, S10.5b)",
    "memory: hetzner-box-fixtures (ms2, vgmls9), tests-never-touch-live-state, model-spend-discipline, braintrust-eval-stack, openrouter-always"
  ],
  "handoffDir": "docs/handoffs",
  "stopPoints": [
    "First: Nathan's three answers — the profiles file back (a copy), BMAD in plugin/ (a pilot session, not now), and the prompt sweep as a button only",
    "Never run a suite or a walk on ms2 while Nathan is on it; ask first",
    "S10.4 and S10.6 are mock-first: one concept line each, tools/shoot-mock.mjs, Nathan's yes",
    "Every bug from a walk is a story: test first, one mutation, then the fix; a mutation of anything that can start a run holds the cron wire (tests/ai/background.php shows how)",
    "No model switch without the sweep gate and both baselines re-taken with the reason",
    "Every change measured on both sites: tech 4/4 unchanged, the shop within 3 % or re-taken with the reason"
  ],
  "gates": [
    "tree-view.mjs and folders.spec green at 1600 and 1280 for S10.4; modes.spec for S10.6 (the first row ≤ 240 px at 1280×800, the sidebar ≥ the viewport)",
    "× on the last word: the draft says 'no words' and the confirm writes a name-only profile (tree-view.mjs row, mutation)",
    "node tools/verify.mjs filing sticky guide surface roles escaping copy tree-view seed-shop ai-background → green (escaping 9/10 known); both baselines 4/4; folders.spec and round.spec on ms2 when Nathan is off it"
  ]
}
```

Opener, cwd `plugin`:

```
Read docs/handoffs/2026-09-17-s11-the-method-back.md, then
docs/superpowers/specs/2026-09-16-folders-at-catalogue-scale.md. State
which model you are. This session is S12 of every-picture-a-home. Write the
card from the handoff to .harness/active.json before anything else. First
Nathan's three answers (the profiles file, BMAD, the prompt sweep as a
button). Then the last word on the tree, S10.4 and S10.6 mock-first, and
folders.spec on ms2 when Nathan is off it. Test first, one mutation per
story, both sites measured, say every cost before it is spent, never touch
ms2 while Nathan is on it. Talk plainly. End with a handoff carrying the S13
card.
```
