# Handover — 2026-09-15, every-picture-a-home Phase B, S4: B.2 + B.3 (Opus 5)

One session in `plugin`, lean, no subagents. Both tasks on Opus 5 — B.3 in
the same session as B.2, no switch to Fable (the S3 handoff had it at ~8 %).
Deployed to the box after every change (`node tools/deploy.mjs --box`, every
file re-hashed there). Spend: **one opening turn (~10 credits)**, spent by
the mutation that puts the auto-open back to prove the suite catches it;
everything else was dry runs (embeds, cached) and no model call.

> "yes approved" — Nathan, 2026-09-15, on the eight shots (the mocks beside
> the built screen) opened on his screen at the end of the session.

## What the screen is now

`core/guide.php` renders the head (title + three pills: pictures, described,
folders), the rail (`.g-rail > button.g-step` × 5, every one a button) and
the root; `js/vergeml-folders.js` draws one card per step from the data the
page carries, and shows the step the session is at: Describe when nothing
is described, Fill when the tree is confirmed or a run is going, Tree
otherwise. **No model route fires on load** — the auto-open turn is gone
from the boot and from Start over; *Propose folders* is a button with
`10 credits` beside it (`VERGEML_GUIDE_PROPOSE_CREDITS`).

| step | card, as the approved mocks | primary · beside it |
|---|---|---|
| Describe | `996 described` · `4 not yet`; one line | `Describe 4 pictures` (a link to the AI screen's run) · `4 credits` · Skip; `Next: Tree` when nothing is left |
| Tree | `21 folders` · `513 placed` · `487 stay unfiled` from the fit; the tree; the change line | `This is my tree` · `Propose folders` `10 credits` · Skip; confirmed: `Next: Fill` · Unconfirm |
| Fill | from the fit's tally `placed · sure · likely · to sort`, else `N in folders · M to sort` (`vergeml_talk_fill_status`); the same tree | `Fill 1,000 pictures` (`/guide/apply`) · Stop · Undo · Unconfirm; unconfirmed: `This is my tree` · Back to the tree |
| Alt text | `900 without alt text` · `100 have one`; one line | `Write alt text for 900` (a link to the AI screen; B.5 wires the route) · `0 credits` · Skip |
| Rename | `Not available yet`; one line | `Rename files`, disabled |

The change line (`.g-change`): the talk component's composer restyled as
the mock's round input with the arrow inside; its placeholder is built from
the tree on screen (`placeholder()`: the largest parent by branch total,
plus `add <class>` when the fit carries `residue` — new on the fit, the
biggest first class among the dry run's `nothing` picks, in the describer's
own words); three chips (Fewer folders · Split by kind · By year first) that
send a choice turn, hidden while the model's own chips stand; *Paste or
upload a list* (`.g-chip.is-way-in`, `aria-expanded`) opens the paste panel:
a `.txt` is the paste, a `.csv` row's cells are one path's levels, anything
else is refused in one line (`tree.pdf is not a .txt or .csv file`), read
with `FileReader` into `readPaste`. The 25-turn log is still in the page but
CSS shows only its last message — and of a reply in paragraphs, only the
last paragraph — with its chips: the spec's "one line plus chips", enforced
on the screen because the service's `guideRules` still writes three
paragraphs (see below).

The tree (B.3, `js/vergeml-tree-view.js`): on the Folders surface every
count is `.vgml-count.g-pill` (rows, chips, the fold row) and `new` is
`.vgml-tag.g-pill.is-new`, a sibling of the name rather than inside it (the
name clips on overflow). New option `siblings: true` (`data-siblings="row"`
on the root; the Folders screen alone sets it): `siblingRows()` folds a
parent's one-to-three leaf children into one `li.vgml-tv-sibs` of
`span.vgml-sib` chips — never a parent with a removed / moved / renamed
child (those carry a line), never while a Move paints its bars, never the
child being renamed in place — and the parent then reads for the branch.
No `aria-*` on a row changed, no drag handler touched; the library's rows
are asserted unchanged (G7). The Folders view runs `openAll`, `fold: false`,
mode `all` (the Changes / All switch is hidden by CSS): one list.

Mock → screen names: `.g-pill.g-count` → `.vgml-count.g-pill`;
`.g-pill.is-new` → `.vgml-tag.g-pill.is-new`; `.g-siblings` → `.vgml-tv-sibs`;
`.g-sib` → `.vgml-sib`; `.g-ask input` → `.vgml-composer-text`
(`data-placeholder-from="tree"`); everything else keeps the mock's class.
The old two-column app shell (`vgml-app-folders`, the sticky pane) is gone;
the page flows.

## Gates

- `npx playwright test --config tests/ui/playwright.config.mjs tests/ui/folders.spec.mjs`
  on the box → **7 passed, 1 skipped (the walk)**. Rewritten for the new
  screen; the old thread-region, segmented-tab and dry-run-lines tests are
  gone with the things they tested. What it asserts: no POST to
  `/guide/turn|token|stream|propose` on an empty session, five `.g-step`
  buttons, Tree current, Describe done, `10 credits` on the button; the
  placeholder names the largest parent (`aria-label`, since at the cap the
  visible placeholder is the cap's); `.txt` and `.csv` uploads land in the
  draft as chips under their new parent, `.pdf` refused in one line, the
  confirm waits for the dry run; Skip → Fill current with `tree: editing`;
  confirm → 200, `profiled: 0`, Tree ticked, Fill current, `Fill N
  pictures` enabled, `/guide/rule` + `/guide/turn` + a draft → 409, the
  change line hidden; Unconfirm → 200; ≤ 80 words per step at 1600×1000 and
  1280×800 (`.vgml-tv` excluded, no horizontal scroll); every painted
  number — head pills, row pills, chip pills — is the dry run's;
  `add <residue>` in the placeholder when the fit has one; a hand rename is
  one line under the input and survives a reload by id.
- **Mutation** (run, red, restored): the auto-open put back at the end of
  `js/vergeml-folders.js` → "no model route fired on load" red (that is the
  ~10 credits). `g-pill` dropped from the count class → `tests/tree/tree-view.mjs`
  G1 red (24 bare).
- `node tests/tree/tree-view.mjs` → 50/50 (G1–G7 new: pills, the yellow tag
  beside the name, chips with the option on and rows without, no treeitem on
  a chip, the parent reads for the branch, the library's rows untouched).
- `node tools/verify.mjs guide` (box) → 31/31; A1's budget is now eleven
  queries as measured (was nine: the rail's steps cost images, not
  described, without alt, the fill state and its unfiled count), A4–A6 read
  the new markup.
- `node tools/verify.mjs surface escaping` → 26/26; 9/10 (the four-to-one
  ratio, known). The one unread JS HTML sink (the paste sentence's
  `innerHTML`) is gone with the sentence; `docs/security-*.md` regenerated.
- `node tools/shoot-mock.mjs docs/superpowers/mocks/2026-09-15-step-rail.html --viewports 1600x1000,1280x800 --words`
  → 42 (77 with the tree) at both sizes; the fill mock 76/107 and 25/68; the
  other steps 37, 38, 41 — the S3 table exactly.
- `tests/ui/shots.spec.mjs` → folders green (asserts the rail, one current
  step, a primary on the shown card, no service call); **duplicates red on
  the committed suite too** (`.vgml-health-list.is-related` absent: the
  box's Duplicates fixture holds no look-alike set — not this session's).
- `tests/tree/shots.mjs` (box): first run 40/48 — every miss the same core
  error, `user-profile.min.js` "reading 'serialize'", which the suite
  excluded by its unminified name; the exclusion now matches both (a
  one-line suite fix). Rerun: still 40/48, but now every miss is another
  plugin's — HubSpot's `js.hsforms.net/forms/embed/.js` failing to load on
  every page, and WooCommerce Payments' `admin-rtl.css` 404 on the RTL
  ones. The box's plugins, not the tree; whether the suite should ignore
  third-party URLs is its owner's call. The library panel's rows are not this session's to change:
  G7 in the harness proves them untouched, and the after-shot
  (`tests/tree/shots/list-light-ltr.png`) shows the panel's own count pill
  as it was.
- `node tools/verify.mjs roles` (box) → 19/19.
- `node tools/deploy.mjs --check` after the last deploy: box up to date
  (the zip is rebuilt at the commit).

Screenshots (the built screen beside the mock's): `docs/superpowers/mocks/shots/2026-09-15-built-*.png`
(tree, describe, fill, alt-text, rename at 1600×1000; tree at 1280×800) —
from `tests/ui/shots/folders-*` written by the suite on the box's real data.

## What Nathan should know

- **The service's reply is still three paragraphs and two twelve-word
  chips** (the box's real last turn: "Should I build new topic folders for
  the largest unfiled clusters (…)?" with "Add new folders for wearables,
  VR/gaming, vintage graphics cards, keyboards, events"). The screen shows
  the last paragraph and the chips; the spec's "one line, chips of ≤ 3
  words" is the service's `guideRules` to enforce — a `service` card, not
  a plugin one.
- **Describe and Alt text hand over to the AI screen** (a link with the
  count and the cost). B.5 wires Alt text's own route; Describe's run stays
  the AI screen's, per the plan.
- **Fill in B.2 is the plain run**: `Fill 1,000 pictures` → `/guide/apply`
  → "Filling 128 of 1,000" (seen of total) → the tree re-read, the fill
  status re-read (`/guide/questions`). No live counts on the rows yet, no
  question cards: B.4.
- Confirm on the box with a draft that gives a folder no classes asks the
  planner and **writes a profile onto that real folder** (`2026`,
  `Robotics`, `September` carry `name` profiles today). The suite avoids
  it with a draft of new folders only; a person pressing *This is my tree*
  on the box does spend that call. As designed in A.3.
- The Fill card's "in folders" = described − unfiled; on the box today
  `487 in folders · 513 to sort` (the walk's Move was undone).

## Found, not done

- `guideRules` in the service: one line plus chips of ≤ 3 words (above).
- The composer's *Start over* at the cap sits inside the round input's bar
  (works; looks crowded at 1280 with a long cap notice).
- `.vgml-tv-more` (the fold row) keeps its pill but the Folders view no
  longer folds; the class stays for the harness.
- `tests/tree/guide.php` A1 budget raised to eleven; the fill status's
  unfiled count duplicates `vergeml_folders_facts`' — one query to save.
- `tests/ui/shots.spec.mjs` duplicates: the box's fixture (above).
- The walk test in `folders.spec.mjs` (`GUIDE_WALK=1`) now covers Propose →
  stream → Stop only; the Move / undo walk is A.5's, through the screen.
- Word counts on the box's real session: the Tree step reads 134 with the
  tree, ~45 without (chips included); the planted suite session ~30–45.

## Next — Phase B, S5: B.4 + B.5, in `plugin`, from a fresh session

Model: Opus for both (B.4 was the Fable candidate; say in the handoff if
Fable was used).

Card, to `plugin/.harness/active.json` before the first edit:

```json
{
  "phase": "Every picture a home — Phase B, S5: B.4 the Fill step (live counts, the questions) + B.5 Alt text and Rename as steps, confidence on a picture",
  "model": "opus",
  "plan": "plans/every-picture-a-home.md",
  "spec": "docs/superpowers/specs/2026-09-14-every-picture-a-home.md",
  "scope": [
    "core/guide.php",
    "core/folder-talk.php",
    "core/ai.php",
    "core/traces.php",
    "core/media-list.php",
    "js/vergeml-folders.js",
    "js/vergeml-tree-view.js",
    "js/vergeml-media-list.js",
    "css/vergeml-folders.css",
    "css/vergeml-tree-view.css",
    "tests/ui/folders.spec.mjs",
    "tests/ui/modes.spec.mjs",
    "tests/tree/**",
    "docs/**",
    "plans/**"
  ],
  "readFirst": [
    "docs/handoffs/2026-09-15-phase-b-s4-the-rail-confirm-pills.md",
    "docs/superpowers/mocks/2026-09-15-fill-questions.html (asking, done)",
    "docs/superpowers/mocks/2026-09-15-other-steps.html (alt-text, rename)",
    "plans/every-picture-a-home.md (B.4, B.5)",
    "docs/superpowers/specs/2026-09-14-every-picture-a-home.md (§2 Step 3, Step 4, §3)",
    "js/vergeml-folders.js (renderFill, took, renderMove, the Fill card)",
    "core/guide.php (/guide/questions, /guide/answer, /guide/progress; vergeml_guide_progress_out)",
    "core/folder-talk.php (vergeml_talk_questions, vergeml_talk_answer, vergeml_talk_fill_status, vergeml_talk_question_text)",
    "core/filing.php (vergeml_filing_questions, vergeml_filing_answer_plan)",
    "core/ai.php (vergeml_ai_apply_alt)",
    "tests/ui/folders.spec.mjs (plant, liveDraft, WORDS)",
    "grep -rn \"why is it here\" core js"
  ],
  "handoffDir": "docs/handoffs",
  "stopPoints": [
    "The approved fill mock is the design: the tree left, the questions right at 1600, one column under 1000px; a card per group, the sentence, eight thumbnails at most, the answers as buttons, the engine's answer first and tinted; Leave the rest under them",
    "No question before the run ends; the counts climb on the rows and in the pill row while it runs",
    "Done when questions = 0 and unfiled = 0: 'N in folders · 0 to sort', parents closed, what the answers made marked new, Next: Alt text, Undo",
    "Leave the rest answers every open question with leave: the pictures land in To sort, never in nothing",
    "Alt text: pressing writes the catalogue's alt onto pictures with none and never overwrites; Rename stays gated (VERGEML_FILE_RENAME), its button disabled",
    "The grid modal and the list row show sure / likely / by you as a pill next to the folder, beside 'Why is it here'",
    "Word budget ≤ 80 per step without .vgml-tv, at both sizes, in the suite",
    "Specs restore what they write on the box; a planted state with two questions goes through /guide/session as drafts do — or, if the questions live in the talk state option, through a fixture that puts it back",
    "Never open the conversation without a press; never spend a describe in a spec"
  ],
  "gates": [
    "folders.spec.mjs: a planted confirmed tree and a state with two questions → the two cards with their buttons; click new-folder → the tree shows the folder and the card its result line; Leave the rest → 0 open; the done state's text; mutation: the done condition without unfiled = 0 → a planted state with unfiled 3 shows done → red",
    "folders.spec.mjs: Step 4 on a planted state with 3 missing → press → 0 missing, one picture with an existing alt untouched (assert its text); Step 5 disabled with its line; mutation: the never-overwrites guard removed → red",
    "tests/ui/modes.spec.mjs: the modal's pill (sure / likely / by you)",
    "folders.spec.mjs: ≤ 80 words per step, both sizes, still green; screenshots of asking and done beside the mock's shots",
    "node tools/verify.mjs surface roles escaping guide → green (escaping 9/10 known)",
    "node tools/deploy.mjs --check after deploy to the box"
  ]
}
```

Opener, cwd `plugin`:

```
Read docs/handoffs/2026-09-15-phase-b-s4-the-rail-confirm-pills.md, then
the fill and other-steps mocks it names, then plans/every-picture-a-home.md
B.4 and B.5 and the spec's §2 Step 3 and Step 4. State which model you are.
This session is Phase B S5 of every-picture-a-home: B.4 (the Fill step:
live counts and the questions) and B.5 (Alt text and Rename as steps,
confidence on a picture), built to the approved mocks. Write the card from
the handoff to .harness/active.json before the first edit. Lean.
Screenshots of the built asking and done states at both sizes beside the
mock's shot before you call B.4 done. End with a handoff in docs/handoffs/
carrying the S6 card (A.5, the walk by Nathan).
```

What S5 inherits, concretely: the Fill card is `dom.cards.fill` with its
pill row `dom.cards.fill.pills` and the tree slot `dom.slots.fill`; the run
is `onMove` → `took()` (polls `/guide/progress` every 2 s, `view.setProgress`
paints the bars by draft key, the end re-reads the tree and
`/guide/questions` into `state.fill`); `renderFill()` draws the pills from
`state.fit.tally` / `state.fill`; `.g-cols` is one column — the asking state
adds a `.g-qs` column to its right (the mock's grid) and `.g-cols.is-done`
tightens the rows. The question cards' classes are in the mock
(`.g-card.g-q > .g-q-text + .g-q-strip + .g-q-answers > .g-answer(.is-first)`),
and `/guide/questions` already serves `{ id, kind, count, name, text,
answers, sample: [{ id, thumb }], answered, result }`.
