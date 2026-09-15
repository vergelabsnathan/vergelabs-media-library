# Handover — 2026-09-14, evening: the box used as a customer, and what it found (Opus 5)

One session across `plugin` and `service`, after the morning's stop-building
handoff. Nathan used the box as a user and found two things wrong at once;
chasing them found five more. Everything below is deployed and pushed. Cost:
898 describes (≈ $4.07 in model tokens, 898 credits, refunded ones excluded),
one Folders-screen open (20 credits), four direct retries (4 credits).

## What Nathan saw, and what it was

- **"The folders are full of wrong pictures."** Not the plugin's judgement.
  `tools/box-fixture.php:155` files picture *n* into folder *n mod 12* by
  position, and 900 of 1,000 descriptions were mock mode. The plugin's own
  proposal, on real descriptions, is the same four parents and ten children
  (Data centres, Energy, Hardware, People) with 816 placed and 184 abstained —
  it matches what the library is. The proposal sits on the box's Folders page,
  Move not pressed.
- **"The list view is clipped and the columns are a mess."** Our 09-10 rule
  `display: block` on the `<table>` (so it could scroll under the panel)
  removed core's `table-layout: fixed`: every nowrap line became a width
  floor (File 759px at any window), long alt-text cells ballooned, three
  columns went off the right edge, rows 211px. Fixed in `e68bbcd`: the table
  sits in a scrolling `div` (`.vgml-list-scroll`, wrapped by
  `js/vergeml-tree.js`), and `core/media-list.php` writes File's share only
  when columns beyond core's are on — 40% among ours (rows 81/98px at 1600),
  30% when other plugins' columns need room (rows 231 at 1440, theirs). No
  page scroll at 1280/1440/1920. `modes.spec` updated: the panel *is* on the
  list screen (Nathan, 09-10), the page never scrolls sideways, `OURS` gains
  `taxonomy-audience`. Its two other failures (folder filter, grid modal)
  were red before this session and are not layout.

## The three pictures that would not describe

Braintrust: 914 attempts, 898 ok, 16 failed — 14 of them **A4, alt is more
than one sentence**, on keynote photos. The service refunded each, but the
refund carried `source_id = null` and `recentSpend` never looked for one, so
every retry inside ten minutes was refused as **409 "described a moment
ago"** — and a retry in the same bucket collided with the refunded spend's
ledger row anyway. The plugin read 409 as "sent twice", struck it, moved on.

Service fixes, `a3a9f6d` and `5e5efb5`, tests first, each mutation-checked:

- `normalise()` cuts a two-sentence alt to its first sentence
  (`firstSentence`); `sentenceEnd()` is the one rule it and A4 share, so an
  abbreviation ("Candy Co. from 1907"), an initial or a number is never a
  sentence end for either. The old A4 refused what the cut had kept.
- The refund is keyed to its spend; `recentSpend` ignores a spend with a
  refund against it; a retry after *n* refunds takes an `:r<n>` source id
  (`refundsFor`). Two identical pictures in one batch still collide, as
  they should.
- `pnpm test` 463 passed; typecheck clean.

**The box does not talk to Vercel.** `wp-config.php` defines
`VERGEML_AI_SERVICE = http://127.0.0.1:3100/v1`: a standalone service build
under pm2 in `/opt/vgml-service`, shipped by the `vps` GitHub workflow
(`workflow_dispatch`), and it was **nine days old** (2026-09-05). Every
describe on the box goes through it — same prod database, same Braintrust,
real credits — on whatever code was last dispatched. Dispatched twice this
session; BUILD_ID 18:39:49 UTC is `5e5efb5`. A Vercel deploy changes nothing
on the box until `gh workflow run vps.yml --ref main` is run.

All 1,000 pictures described at the end (the last two by the run path after
the honest ten-minute hold from their direct retries).

## Money, from Braintrust

- 3,353 input tokens a picture, 236 out; $4.07 for 898 on Haiku 4.5 list
  price ≈ €0.0042 a picture. The pricing memory's €0.002 cost basis is
  wrong; the 50,000 tier (€0.0080) is a 1.9× margin, not 4×.
- 39% of every input is the same 1,312-token system prompt, uncached:
  Haiku caches a prefix of 2,048+ only (`lib/anthropic.ts:243`). Padding the
  prefix past that ≈ −35% on input cost. Not done.

## The design pass

Nathan: too much text, not scannable, "editorial" is the wrong register for
a media plugin, Folders page worst. Saved as memory `ui-less-text-pills`.
Mock built, screenshotted, committed (`76814eb`):
`docs/superpowers/mocks/2026-09-14-folders-glance.html` — the tree is the
screen, every fact a pill, the conversation a one-line input with three
chips, one button that names its consequence. 82 words on screen; the
shipped page has 439. Real numbers from the box's own proposal.
**Awaiting Nathan's yes.** The AI page (420 words) and the Dashboard (505)
get the same treatment after.

## Also done today

- Free disclosure channels: GitHub private advisories on, Dependabot on,
  `security.txt` on both hosts, Patchstack VDP program (manual review; name
  typo "VerlabsMedia" to fix in its settings), FAQ and SECURITY.md lines,
  zip rebuilt. `docs/security-brief.md` gains the 62-finding class history
  from WPScan. Repo description/homepage/topics set; readme leads with the
  product, EML one line.
- Memory: `ui-less-text-pills`. The S2d handoff's "Next" card was stale
  (Phase 5 S1 was done 09-13); `~/.claude/harness/model-profiles.md` does
  not exist.

## Found, not done

- **Describe speed is cron cadence.** 10/min under a 45-second poll, 50/min
  under the box nudger's 10 seconds, 82 the ceiling. A customer's site in
  background mode waits for page views; a quiet site describes slowly and is
  not told why.
- The proposal includes `2026 / September` with 0 pictures — an empty date
  folder from the planner.
- The Folders page spends 20 credits the moment it opens on an empty
  session.
- The box runs five page builders and three SEO plugins *active* (memory
  says inactive): 765px of their notices on every screen, three of their
  columns on the list. Judged through their noise until deactivated.
- Prompt-cache padding (above). `credit_entries` refunds before today have
  null sources; harmless, they age out of every window.
- Tokens in transcripts to rotate if wanted: Better Stack, WPScan.
- `escaping.mjs` 9/10 (Phase 3 S3's row), unchanged.

## Later the same evening

- Nathan pressed Move on the box: 20 folders, **487 placed, 513 in no
  folder** (247 under the floor, 232 inside the margin between siblings, 34
  gated); the preview had said 816. His verdict as a user: unusable, and an
  architecture problem, not a threshold. He is right: the matcher was built
  to be right and the button promised to be done.
- The Move button had also come back enabled mid-request (nginx 499): the
  apply made a planner call and filed for five seconds before answering,
  and the version watcher redrew the button. Fixed in `49b3e88`: apply
  answers at once (3.9 s on the box), the screen holds an applying state,
  a second apply returns the running one's progress. Verified through the
  screen with a throwaway Move, undone.
- Nathan's order for the feature, recorded in the ticket: describe → tree
  (proposed or his, confirmed 100% before anything files) → fill (residue
  is *asked about*, grouped, never forced on a low score) → alt text as its
  own step → rename last. Written up as spec + ticket + plan (`caa9dec`):
  `docs/superpowers/specs/2026-09-14-every-picture-a-home.md`,
  `tickets/2026-09-14-every-picture-a-home.md`,
  `plans/every-picture-a-home.md` — Phase A the engine (A.1–A.5), Phase B
  the screens (B.1–B.6), seven sessions, mini-specs with a mutation check
  each. "Alt follows the page" parked in the spec for its own card after.
- The Folders glance mock is approved (Nathan, 2026-09-14): it is B's
  grammar.
- The box is left with Nathan's Move in place (487 / 513): the state the
  plan's A.4 walk starts by undoing.

## Next — Phase A, S1: A.1 + A.2, on Opus, in `plugin`, from a fresh session

Card, to `plugin/.harness/active.json` before the first edit:

```json
{
  "phase": "Every picture a home — Phase A, S1: A.1 one filing path + A.2 the residue, grouped and named",
  "model": "opus",
  "plan": "plans/every-picture-a-home.md",
  "spec": "docs/superpowers/specs/2026-09-14-every-picture-a-home.md",
  "scope": [
    "core/filing.php",
    "core/folder-talk.php",
    "core/guide.php",
    "tests/filing/**",
    "tools/verify.mjs",
    "../service/app/api/ai/name-group/**",
    "../service/lib/anthropic.ts",
    "../service/lib/name-group.test.ts",
    "docs/**",
    "plans/**"
  ],
  "readFirst": [
    "docs/handoffs/2026-09-14-box-as-a-user.md",
    "docs/superpowers/specs/2026-09-14-every-picture-a-home.md",
    "plans/every-picture-a-home.md",
    "core/filing.php",
    "core/folder-talk.php",
    "tools/box-refile-all.php"
  ],
  "handoffDir": "docs/handoffs",
  "stopPoints": [
    "The floor and margin values do not change; the outcomes around them do",
    "Residue naming is metered (free to the licence) unless Nathan says a credit",
    "Nothing on the box is filed by this session; A.4 is the walk, next session",
    "No screen work: js/ and css/ are Phase B"
  ],
  "gates": [
    "node tools/verify.mjs filing → 12/12 (pick) and green (residue); each mutation named in the plan turns its own row red",
    "../service: pnpm test green with lib/name-group.test.ts; typecheck clean",
    "node tools/verify.mjs surface roles escaping → still green (escaping 9/10)",
    "tools/box-refile-all.php dry run on the box prints outcomes, not just stays: fits / siblings / nothing counts sum to the described total"
  ]
}
```

Opener, cwd `plugin`:

```
Read docs/handoffs/2026-09-14-box-as-a-user.md, then
plans/every-picture-a-home.md tasks A.1 and A.2 and the spec's §2 Step 3.
State which model you are. This session is Phase A S1 of every-picture-a-home.
Write the card from the handoff to .harness/active.json before the first
edit. Lean: build inline, test first, one mutation check per task, no
subagents. Stop points and gates are in the card. End with a handoff in
docs/handoffs/ carrying the S2 card (A.3 + A.4).
```

Phase B's S3 (the two mocks, B.1) can run in parallel in its own session;
its card is the plan's B.1 with scope `docs/superpowers/mocks/**` only.
