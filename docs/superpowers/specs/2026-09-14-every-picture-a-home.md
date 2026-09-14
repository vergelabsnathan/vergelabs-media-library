# Every picture a home — the Folders flow, rebuilt around the user's order

Written 2026-09-14, the evening Nathan used the box as a customer. Status:
**draft, for Nathan's approval.** The plan is `plans/every-picture-a-home.md`;
the ticket is `tickets/2026-09-14-every-picture-a-home.md`.

## 1. Diagnosis — why the current thing fails

Measured on the box, 1,000 real pictures, real descriptions, the plugin's own
proposal (20 folders), Move pressed by Nathan:

- **487 placed, 513 in no folder.** 247 under the floor (`VERGEML_FILING_FLOOR`
  0.55), 232 inside the margin (`VERGEML_FILING_MARGIN` 0.08), 34 gated.
- **The preview said 816.** The preview scores against the proposal's own
  classes; the run re-profiles every folder through the planner
  (`vergeml_filing_profile_existing`) and scores against that. Two paths, two
  answers; the number on the button is not the number that happens.
- **232 of the 513 are siblings.** Server racks / Cooling, Components /
  Laptops / Phones: a data-centre picture scores close on two children of one
  parent, lands inside the margin, and goes nowhere. The abstention is
  structural to any tree with siblings, which is every tree.
- **The screen reads as a document**: 439 words, the assistant's paragraphs
  on the left, a five-line question, chips of twelve words. Nathan: "not very
  Apple Macintosh simple", "editorial", "unusable" (memory
  `ui-less-text-pills`).
- **The order is wrong.** Today the page opens a conversation on its own
  (20 credits), proposes, and Move files against a draft nobody confirmed.
  Describe, tree, fill, alt text and rename are not five steps a person walks;
  they are tabs and buttons.

The matcher (`core/filing.php`, 2026-09-05) was built to be *right* — abstain
rather than guess, because the rule before it was a coin flip. The user
pressed a button that said *Move 1,000 pictures*. Being right about 487 of
them is the feature not working. Abstention is correct as a *signal*; it is
wrong as an *outcome*.

## 2. The flow — five steps, in the user's order

The screen walks these in order. Each step is a state; the next is disabled
until the previous is done. A returning user lands on the step they are at.

### Step 1 · Describe
The catalogue, nothing on the pictures. Required for everything after; a
picture described once is never described again for a tree change. Shows:
described / total as pills, one button, the credit cost before pressing.
Already built (`core/ai.php`, the AI screen); here it is the first step, not
another screen.

### Step 2 · The tree
Two ways in, equal:
- **Proposed** — the planner, from the whole library's summary
  (`vergeml_talk_propose`). Never automatic: a button, with its cost.
- **Yours** — paste (`Hardware > Phones` lines), CSV, or built by hand in
  the tree (add, rename, drag, delete).

Either is edited until confirmed: by prompt ("split Hardware by brand") or by
hand. **Confirm** is an explicit state — *This is my tree* — after which the
tree is locked: Step 3 runs against exactly it, and nothing is invented while
filling. Unconfirming returns to editing.

### Step 3 · Fill
Every described picture is scored against the confirmed tree with the
existing gates and scores (`vergeml_filing_pick`). Three outcomes; only the
first is silent:

| outcome | rule | what happens |
|---|---|---|
| **fits** | best score ≥ floor and margin over the runner-up ≥ margin | placed; confidence `sure` (≥ 0.70) or `likely` |
| **fits two** | best and runner-up are siblings under one parent, margin < margin | placed in the **parent**, confidence `likely`, counted as a *sibling question* for the group |
| **fits nothing** | best score < floor, or every folder gated | **residue**: not placed, not scattered — grouped and asked |

After the pass the screen shows the fill as counts (pills) and then **the
questions**, few and grouped:

- One per sibling group: *"232 pictures fit both Server racks and Cooling.
  Keep them in Data centres · Split them by best score · Let me look."*
- One per residue group: the residue is clustered by `filing.object` (and
  the vector where objects are sparse); each cluster is named by one cheap
  model call (metered, no credit): *"61 look like robot arms — New folder
  Robotics · Put in Hardware · Leave them."* *"18 I can't read — Leave them
  · Show me."* "Leave them" is always an answer, and what it leaves goes into
  one visible folder, **To sort**, never into nothing.

Answering a question is a click; a new folder made by an answer joins the
tree by the user's hand and the fill continues for that group. The step is
done when there are no open questions and 0 pictures in no folder.

Rules the engine keeps:
- **One filing path.** Preview and run use the same profiles — the ones the
  proposal or the user's tree carry — and the same `vergeml_filing_pick`.
  The planner's re-profiling call moves to Step 2 (when a tree is confirmed)
  and never runs during a fill.
- **A manual move is sticky.** A picture the user dragged (term meta
  `_vergeml_placed_by = user`) is never refiled or evicted.
- **A folder can be locked** (term meta `_vergeml_locked`): never refiled
  into or out of.
- **Undo** covers the whole step, as today.

### Step 4 · Alt text
Optional, one button, separate: write the catalogue's alt onto every picture
that has none (`vergeml_ai_apply_alt`, exists). Before or after Step 3 — the
step is offered after because a person sorting does not want to be asked
about alt text mid-sort. Shows: pictures missing alt as a pill, the button,
what it writes and what it never overwrites (one line).

### Step 5 · Rename files
Optional, last, and **off** until the reference rewrite is finished
(`VERGEML_FILE_RENAME`, memory `file-renamer-gated`). The step is shown as
"Not available yet" with one line, not hidden — a person should see the flow
has five steps.

## 3. The screens — what each step shows

Grammar: `css/vergeml-shell.css` tokens; pills as in
`docs/superpowers/mocks/2026-09-14-folders-glance.html` (approved
2026-09-14). Word budget **≤ 80 words per step on screen**, measured by the
suite. No caps eyebrows, no middot strings, no assistant paragraphs. The
conversation is one input with ≤ 3 chips of ≤ 3 words; a model's reply is
one line plus chips, never the opener.

- **Step rail** at the top: five pills — Describe · Tree · Fill · Alt text ·
  Rename — the current one filled, the done ones ticked, the not-yet ones
  quiet. Clicking a done step goes back to it.
- **Step 2 screen** = the glance mock: the tree as the screen, counts as
  pills, `new` in brand yellow, one primary button (*This is my tree*), the
  input and chips at the side, *Or paste a list*.
- **Step 3 screen**: the same tree filling live (counts climbing), a pill
  row — placed · sure · likely · questions · to sort — then the questions as
  cards, one per group, each with its answers as buttons and a strip of
  eight thumbnails from the group. Done state: *1,000 in folders · 0 to
  sort*, Undo.
- **Confidence on a picture**: a pill in the grid modal and the list row
  (`sure` / `likely` / `by you`), with *Why is it here* (exists).

## 4. Out of scope
- A rule builder. The folder's profile is visible as pills (classes, kinds,
  audience) and editable inline; nothing more.
- Renaming files (Step 5 stays gated).
- Changing the description prompt or the catalogue fields.
- The Dashboard and AI screens' own de-texting (their own card).

## 5. Acceptance, on the box
- Describe → propose → confirm → fill on 1,000 pictures ends with **0 in no
  folder**, every residue group answered by Nathan, the answers shown as
  folders in the tree.
- Preview count = run count on the same confirmed tree (one filing path).
- `tools/box-folder-quality.php` precision on a 60-picture sample, judged by
  Nathan: the `sure` pills right in ≥ 95 of 100, `likely` in ≥ 80.
- ≤ 80 words on each step's screen; no page scroll sideways; the Move
  request answers in < 5 s (it does since `49b3e88`).
