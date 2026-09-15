# Handover — 2026-09-15, every-picture-a-home Phase B, S3: B.1 the mocks, approved (Opus 5)

One session in `plugin`, lean, no subagents, no `js/` or `core/` change,
nothing deployed, 0 credits. Three mocks (the card asked for two; Nathan
asked for the rest of the rail), six states, twelve shots, all shown to
Nathan on his screen and approved in the conversation:

> "okay go ahead approved" — Nathan, 2026-09-15, for all three mocks after
> the last round of changes.

Earlier in the same conversation, per mock: the rail mock got "okay move on
then" after its third pass; the fill mock and the other-steps mock were
changed once more (the "no gate" rule below) and then covered by the quote.

## The mocks

`docs/superpowers/mocks/`, each loads the plugin's own CSS and shows one
board alone with `?state=<name>`; shots in `shots/` at 1600×1000 and
1280×800, thumbnails in `thumbs/` (20 files, 216 KB, the box's own 150 px
thumbnails, fetched over `wp/v2/media`).

| mock | board | what it is |
|---|---|---|
| `2026-09-15-step-rail.html` | `tree-step` | Step 2: the glance tree with the rail over it, one column, the change line under the tree, `This is my tree` |
| `2026-09-15-fill-questions.html` | `asking` | Step 3 after the run: pill row, tree with counts, three question cards on the right, `35 more` · `Leave the rest` |
| | `done` | Step 3 done: `1,000 in folders` · `0 to sort`, parents closed, what the answers made as `new`, `Next: Alt text`, `Undo` |
| `2026-09-15-other-steps.html` | `describe` | Step 1: `996 described` · `4 not yet`, `Describe 4 pictures` + `4 credits`, `Skip` |
| | `alt-text` | Step 4: `900 without alt text` · `100 have one`, `Write alt text for 900` + `0 credits`, `Skip` |
| | `rename` | Step 5: `Not available yet`, one line, `Rename files` disabled |

## What Nathan decided, in order (each is a rule for S4/S5)

1. **No "Change it" card.** "If I have to ask, it's not right." Step 2 is one
   column: the tree, then under it one input, three chips, one button, then
   the primary. The glance mock's side card and its title are gone; `184 stay
   unfiled` joined the two facts in the card head.
2. **The placeholder is built from the tree**, never fixed text: the largest
   parent's name ("split Hardware by brand") and the dry run's biggest residue
   class ("add 3D printers"). Marked `data-placeholder-from="tree"` in the mock.
3. **"Paste or upload a list" is a button**, not a link — a chip in the accent
   tint, set right. The upload reads `.txt` / `.csv` in the browser and hands
   the lines to the paste parser that exists (`readPaste` in
   `js/vergeml-folders.js`). **PDF is not in it** — text extraction first; an
   open item below.
4. **No step is a gate.** "It must be apparent the process or any step can be
   abandoned, it's not that it must be done." Every rail pill is a `<button>`,
   hollow ones included, with a hover; each step has a quiet way past it:
   `Skip` beside the primary on Describe, Tree and Alt text; `Leave the rest`
   under the question cards (answers every open question with `leave`, so the
   pictures land in To sort, never in nothing). Done and Rename have nothing to
   skip. **This contradicts spec §2, "the next is disabled until the previous
   is done" — S4 amends that line.**
5. **All five steps get a mock**, not two — the third file.
6. `0 to sort` stays as the spec's line even though the tree shows
   `To sort 234` under it (raised; not changed).

## Word counts

Tokens of `innerText` carrying a letter or digit (ticks, twisties and arrows
do not count), by the shoot at 1600×1000, once with the tree component
(`.vgml-tv`) and once without:

| state | without the tree | with it |
|---|---|---|
| tree-step | 42 | 77 |
| asking | 76 | 107 |
| done | 25 | 68 |
| describe | 37 | 37 |
| alt-text | 38 | 38 |
| rename | 41 | 41 |

**Proposed rule for the suite (B.2):** the ≤ 80 budget counts the screen's
own words and excludes `.vgml-tv` — the tree is the person's data and a
20-folder tree alone breaks 80. Nathan saw both numbers each round and did
not object; treat as accepted unless he says otherwise. `asking` is the only
state over 80 with the tree counted.

## Real data used

The box's fill walk of 2026-09-15 (S2 handoff): 520 placed = fits 510 +
siblings 10; sure 444, likely 76; 480 nothing; 38 questions. The three cards
are `4 pictures fit both Server racks and Cooling.` (keep-parent · split ·
show-me), `46 look like 3d printers` (new-folder `3D Printers` · put-in
Hardware · leave · show-me), `45 I can't read` (leave · show-me) — sentences
from `vergeml_talk_question_text()`, answer sets from
`vergeml_filing_questions()`. The done tree holds the walk's eight new
folders (3D Printers 46, Data Center 44, Infrastructure 37, Renewable Energy
27, Office Spaces 26, EV Charging 26, Trade Show 20, E-Waste 20) and To sort
234 — total 1,000. **The per-folder counts inside the four parents are
proportioned to 520** (137 / 194 / 155 / 34): the walk did not print
`report.tally.by_term`. Describe uses 996 / 1,000 (the box on the 14th); alt
text uses the AI screen's own 900 (`core/ai-screen.php:283`).

## Found, not done

- **PDF upload** for a tree: Nathan asked "where is the upload pdf, csv etc
  option". Nothing in the plugin reads a file today. CSV/TXT is in B.2's card;
  PDF needs extraction (pdf.js in the browser, or a model call through the
  service) — its own card, Nathan to say when.
- **Spec §2** still says the next step is disabled until the previous is done.
  S4 rewrites that line to the no-gate rule and adds "Skip" / "Leave the rest"
  to §3.
- **Plan B.1** still carries the pre-walk numbers (232 siblings, 61 robot
  arms, 18 can't read) and "two mocks". S4 has `plans/**` in scope; a
  three-line correction.
- **`tools/shoot-mock.mjs`** shoots boards at one viewport and counts nothing.
  This session's shooter (scratchpad, not committed) loads each board alone
  with `?state=`, shoots the **viewport** at 1600×1000 and 1280×800, and
  counts words with/without `.vgml-tv` — the logic is twelve lines:
  `t.split(/\s+/).filter(x => /[\p{L}\p{N}]/u.test(x)).length` on
  `el.innerText`, then again on a clone with `.vgml-tv` removed. S4 folds it
  into `tools/shoot-mock.mjs` (`--viewports`, `--words`) so B.2's suite and
  the mocks count the same way.
- **The naming call**: `Data Center` (44) beside `Data centres`, and `Office
  Spaces` that are road bikes — shown as the walk produced them. A.5 judges
  with Nathan; a "Put in Data centres" answer exists for the first.
- **Screenshots in this harness**: a `Read` of a PNG shows it to the model,
  not to Nathan; file links in the reply did not open for him. What worked:
  `Start-Process <png>` from PowerShell opens it in his viewer. A file the
  viewer holds open cannot be overwritten (`UNKNOWN` on write) — shoot to a
  fresh folder, copy after he closes it.
- The rail mock keeps `html, body { height: 100% }` from the glance; the other
  two do not (stacked boards). Cosmetic.

## Next — Phase B, S4: B.2 + B.3, in `plugin`, from a fresh session

Model: B.2 on Opus. B.3 (the tree rows as pills) is the Fable candidate if
Nathan's Fable budget allows (~8 % on the 15th); otherwise Opus for both.

Card, to `plugin/.harness/active.json` before the first edit:

```json
{
  "phase": "Every picture a home — Phase B, S4: B.2 the rail, confirm, no talking first + B.3 the tree with pills",
  "model": "opus",
  "plan": "plans/every-picture-a-home.md",
  "spec": "docs/superpowers/specs/2026-09-14-every-picture-a-home.md",
  "scope": [
    "core/guide.php",
    "js/vergeml-folders.js",
    "js/vergeml-talk.js",
    "js/vergeml-tree-view.js",
    "css/vergeml-folders.css",
    "css/vergeml-talk.css",
    "css/vergeml-tree-view.css",
    "tests/ui/folders.spec.mjs",
    "tests/tree/**",
    "tools/shoot-mock.mjs",
    "tools/verify.mjs",
    "docs/**",
    "plans/**"
  ],
  "readFirst": [
    "docs/handoffs/2026-09-15-phase-b-s3-the-mocks-approved.md",
    "docs/superpowers/mocks/2026-09-15-step-rail.html",
    "docs/superpowers/mocks/2026-09-15-fill-questions.html",
    "docs/superpowers/mocks/2026-09-15-other-steps.html",
    "plans/every-picture-a-home.md (B.2, B.3)",
    "docs/superpowers/specs/2026-09-14-every-picture-a-home.md (§2 Step 2, §3)",
    "js/vergeml-folders.js (buildPaste, readPaste, setMethod, renderMove, the auto-open turn)",
    "js/vergeml-tree-view.js (render)",
    "core/guide.php (the page markup; /guide/confirm, /guide/unconfirm from S2)",
    "tests/ui/folders.spec.mjs",
    "css/vergeml-shell.css (the nav's count pill)"
  ],
  "handoffDir": "docs/handoffs",
  "stopPoints": [
    "The approved mocks are the design; the glance mock's side card is gone — one column on Step 2, the change line under the tree, the primary last",
    "No step is a gate: every rail pill is a button (hollow ones too), Skip beside the primary on Describe / Tree / Alt text; spec §2's 'disabled until the previous is done' line is rewritten, not kept",
    "The Folders page opens on the session's step and fires no model route on load — Propose folders is a button with its cost (10 credits)",
    "The placeholder is built from the tree on screen (largest parent; biggest residue class from the dry run), never a fixed string",
    "Paste or upload a list is a button: .txt / .csv read in the browser into readPaste; no PDF; no server upload",
    "Word budget in the suite: innerText tokens with a letter or digit, excluding .vgml-tv, ≤ 80 per step",
    "B.3 changes no aria-* on rows and no drag handler; the row geometry outside the Folders screen does not change",
    "Specs restore what they write on the box (memory: tests never touch live state); folders.spec plants a turn first (20 credits a visit otherwise)",
    "Fable for B.3 only if the budget is there; say which model did which task in the handoff"
  ],
  "gates": [
    "folders.spec.mjs: open the page on an empty session → no POST to /guide/turn or /guide/propose (assert on the request log); the rail shows five .g-step buttons; mutation: re-enable the auto-open → red",
    "folders.spec.mjs: confirm → rail advances, Fill current; /guide/rule answers 409; Unconfirm → 200; Skip on Tree → Fill current with the tree unconfirmed",
    "folders.spec.mjs: the placeholder contains the largest parent's name from the planted draft; the paste/upload button accepts a .txt file (setInputFiles) and the draft gains its folders; a .pdf is refused with one line",
    "folders.spec.mjs: innerText word count per step ≤ 80 with .vgml-tv excluded, at 1600×1000 and 1280×800",
    "tests/tree/*.mjs: every count in the component is a .g-pill (or the component's pill class); new is the brand-yellow pill; a parent with ≤ 3 children renders the sibling row on the Folders surface only; mutation: the pill class dropped → red",
    "tests/tree/shots.mjs before/after in the handoff; shots.spec.mjs still green",
    "node tools/shoot-mock.mjs docs/superpowers/mocks/2026-09-15-step-rail.html --viewports 1600x1000,1280x800 --words prints the same counts as this handoff's table",
    "node tools/verify.mjs surface roles escaping → green (escaping 9/10 known)",
    "Spec §2 and plan B.1 corrected as listed under Found, not done; node tools/deploy.mjs --check after deploy to the box"
  ]
}
```

Opener, cwd `plugin`:

```
Read docs/handoffs/2026-09-15-phase-b-s3-the-mocks-approved.md, then the
three mocks it names, then plans/every-picture-a-home.md B.2 and B.3 and
the spec's §2 Step 2 and §3. State which model you are. This session is
Phase B S4 of every-picture-a-home: B.2 (the rail, confirm, no talking
first) and B.3 (the tree with pills), built to the approved mocks. Write
the card from the handoff to .harness/active.json before the first edit.
Lean. Screenshots of the built screen at both sizes beside the mock's shot
before you call a step done. End with a handoff in docs/handoffs/ carrying
the S5 card (B.4 + B.5).
```

What S4 inherits, concretely: the rail is `.g-rail > button.g-step` with
`is-done` (tick via `::before`), `is-current`, and hollow; the pills are
`.g-pill` with `is-accent`, `is-quiet`, `is-new` (yellow, 11.5 px) and
`is-ask` (yellow, full size, "this needs you"); the change line is
`.g-change > .g-ask + .g-chips` with `.g-chip.is-way-in` for the upload
button; question cards are `.g-card.g-q > .g-q-text + .g-q-strip + .g-q-answers
> .g-answer(.is-first)`; the quiet buttons are `.g-quiet`. Every class is in
the mocks' `<style>`; the real CSS goes in `css/vergeml-folders.css` (and
`css/vergeml-tree-view.css` for the row pills), with the names kept or
mapped one-to-one so the mocks stay readable against the screen.
