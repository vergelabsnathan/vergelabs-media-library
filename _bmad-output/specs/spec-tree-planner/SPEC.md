---
id: SPEC-tree-planner
companions: [lab-results.md, rules.md]
sources: []
---

> **Canonical contract.** This SPEC and the files in `companions:` are the complete contract for what to build, test and validate.

# Bottom-up folder planner

## Why

A pain to solve. The folder tree decides how well everything after it works, and today's planner builds it from a sample. It reads a summary (10 groups from the oldest 600 pictures with 2 captions each, 40 sample captions, the 24 most common words), asks a question before it proposes anything, then proposes 11–19 flat folders. On the shop those recover 2–8 of 47 real folders and leave 30–67 % of pictures unfiled, and two runs never agree. Nathan (2026-09-26) wants the tree built from every picture, with explicit rules, and both maximum quality and a predictable result.

## Capabilities

- **CAP-1**
  - **intent:** The planner builds a folder tree from every picture's labels (the describer's object and class, plus kind and audience counts), taken as an inventory of distinct labels with their counts. The model arranges them fifteen times and the rules are enforced in code on every run. On the site, by the pictures' own vectors, the run whose pictures sit closest to their folder's centre is kept, and a label the model left out joins its nearest folder at a cosine of 0.5 or more (Nathan, 2026-09-27).
  - **success:** On the tree score, in every plan, the shop recovers ≥ 35 of 47 real folders with purity ≥ 75 % and ≤ 10 % unfiled; tech after the fill reaches purity ≥ 75 % and never ends below its own purity before the plan (was ≥ 9 of 11 at ≥ 78 %, out of reach at 1,000 pictures; Nathan, 2026-09-29). Lab (improve mode, 2026-09-27): shop 41 and 43 of 47 at 77–80 %, 9 % unfiled; tech 9/11 at 81–82 %, 4–6 % unfiled. The ceiling -- each label in its best real folder -- is 47/47. How much two plans agree is not a bar: an owner sees one plan, and accepting it freezes it (Nathan, 2026-09-27). Stretch line, not a gate: the shop's plan -- which since story 4 is also its fill -- reaches pair F1 leaf ≥ 68 %. Today's fifteen-run plans give 64-68 %. The lab's 71-75 % came partly from label order that followed the truth (story 5) (moved from CAP-3, 2026-09-29, pending Nathan's review).
- **CAP-2**
  - **intent:** Once the owner accepts a tree, the tree and every label-to-folder match are frozen. Planning again returns the same tree, and new pictures are placed into it. A new folder is proposed only when a label reaches the minimum, and nothing existing moves without the owner's yes.
  - **success:** A suite plans the same library twice and gets an identical tree. After new pictures are added, every existing folder keeps its pictures and name.
- **CAP-3**
  - **intent:** Each folder is matched on the pictures that formed it (the labels and vectors of its members), not on words the model wrote.
  - **success:** New pictures filed into a planned tree go where their kind already sits. The measure is `tools/box-profile-heldout.php`, run on a real plan: the labels are held out a quarter at a time, and a held-out picture that is filed counts when it lands in the folder holding most of its truth folder. The shop's share must not fall below today's, at least 79 %: 80, 79 and 80 % on three live plans and 80 % on a fourth (restated 2026-09-29, pending Nathan's review; the fill's pair F1 moved to CAP-1 as a stretch line).
- **CAP-4**
  - **intent:** A split the pictures cannot show, such as men/women/kids or the owner's own axis, comes from the owner as one question, or from the shop's product categories. It is never guessed.
  - **success:** With no audience evidence, the proposed tree holds no audience folders, and the conversation offers the split as a question.
- **CAP-5**
  - **intent:** A library whose label inventory is too large for one call folds its rare labels into their broader class first, so a plan is still one request of fifteen runs.
  - **success:** A synthetic library of 500,000 pictures plans within the call limit, at a cost stated before the owner presses. No library is ever refused for its size (Nathan, 2026-09-28).
- **CAP-6**
  - **intent:** A tree score (pair F1 at leaf and top, purity, real folders recovered, unfiled, folder count, depth, same names) gates the planner the way the truth score gates filing.
  - **success:** `tools/tree-lab.mjs score` runs on shop and tech and its line is in every story's proof.

## Constraints

- The rules in `rules.md` run in code after the model and are never left to the prompt alone: 5 pictures minimum, 3 levels, 2–12 children per parent including the top level, unique names, one-child parents collapse.
- The model never decides a count, and never moves or deletes a picture or folder. The owner accepts a tree before anything is filed.
- Folders stay terms of `media_category` (AGENTS.md policy). Existing folders and product categories are kept, and new folders only fill gaps.
- Sonnet 5 through OpenRouter, with reasoning off and structured output (the lab's failure modes: all tokens spent reasoning, and think-text instead of JSON).
- Anything newly sent to the service updates readme.txt's External services section and the outbound audit in the same commit.
- Price: 10 credits + 6 per 100 distinct labels, rounded up (at least 35 % margin on the 20,000 pack after VAT; model cost about $0.25 for a 600-picture shop). The customer sees one number on the button ("Plan my folders · 40 credits"), counted on the site with no service call; the press charges exactly that; a failed plan (fewer than 3 valid runs) and a re-plan of a frozen tree are free; a low balance says so on the button.

## Non-goals

- A fixed reference taxonomy as the structure. Measured: 9 fewer real shop folders and 20 points lower purity.
- Audience folders inferred from pictures (3 of 88 carry the right audience).
- Changing the filing matcher's thresholds, or the second-ask filing work on branch `filing-max`.

## Success signal

On the box, the shop and tech libraries planned from scratch give trees that meet CAP-1's numbers. A second plan returns the identical frozen tree. Filing into the tree meets CAP-3.

## Assumptions

- The describer's two-level object labels are present on every picture (625 of 626 on the shop, 199 of 200 on tech).
- The lab's truth trees stand for real owners' intent closely enough to gate on.

