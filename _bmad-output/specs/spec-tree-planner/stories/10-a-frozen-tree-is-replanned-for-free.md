---
title: 'A frozen tree is re-planned for free, and only grows'
type: 'feature'
created: '2026-09-29'
status: 'done'
baseline_commit: '32f3b9c'
route: 'dispatch'
review_loop_iteration: 0
context: []
---

## Intent

**Problem:** Once a plan is accepted and filled, its label map is frozen (story 4), and pressing Plan again was refused (409). CAP-2 asks for the opposite: planning again returns the same tree, new pictures go into it, a new folder is proposed only when a label reaches the minimum, and nothing existing moves without the owner's yes. The spec's price line makes that re-plan free.

**Approach:** On a site with a frozen map, the press plans on the site alone, with no model and no service call. Only labels the frozen map does not hold, and only their pictures that wait in no folder or in To sort, are planned. A new label joins an existing folder when its pictures sit about as close to it as the folder's own pictures do. The rest are grouped by likeness into new folders of five or more, each under the nearest parent with room. The growth comes back as a draft for the owner's yes, and its fill files exactly those waiting pictures.

Nathan authorised the run and asked for decisions by quality, sensible cost and robustness, written here (2026-09-29).

## Commits

- Plugin (baseline `32f3b9c`): `24315c2` feat(tree): plan a filled tree again for free, and only grow it; `41ab577` test(tree): free box measures and proof for a frozen tree's growth; this story file.
- Service: none. Nothing new is sent, so readme.txt's External services and the outbound audit are unchanged.

## Decisions

- **No model call.** Growth on a frozen tree is small: a few new labels, mostly one or two pictures each on these libraries (the shop has 626 pictures under about 400 labels). The site's own vectors place them measurably well (the proof below). A model call would need a free path in the service that does not trust the client: the service would have to hold the frozen plan's id, or cap re-plans per site. It would also cost Nathan about $0.25 a re-plan and make two re-plans disagree. What the model does better is naming (see open risks). Because nothing reaches the service, "free" needs no abuse bound: an edited client can only plan its own site.
- **The joining rule is relative, not a bare cosine.** The plan's own placement cosine (0.5) placed 94 % of held-out labels on the shop at 70 % precision and never made a new folder: a label sits at 0.7-0.9 to half a department. The rule now: cosine 0.7 or more to the folder's centre (`VERGEML_PLAN_GROW_JOIN`), and the label's pictures sit within 0.05 (`VERGEML_PLAN_GROW_MARGIN`) of the folder's own leave-one-out tightness (`vergeml_plan_gain_folder`, story 9). A folder of one picture takes no label, because nothing says how close its pictures sit.
- **Leftovers group by likeness, not by class name.** Grouping by the describer's broader class fragmented a removed folder into groups under five. The rule now: largest label first, each label joins the first group of its kind whose centre is at 0.6 or more (`VERGEML_PLAN_GROW_GROUP`), else starts a group. A group of `VERGEML_PLAN_MIN` (5) or more is a new folder, named for the broader class most of its pictures share, with the kind added for a non-photo ("Software screenshots"), three words at most. A group named like an existing folder joins that folder, the plan's near-copy rule.
- **Parent:** the existing parent whose whole subtree the group's pictures sit nearest, at 0.5 or more (`VERGEML_PLAN_GROW_UNDER`). It must already have children (a one-child parent would collapse, rules.md), have fewer than 12 children, and keep the new folder within 3 levels. Failing that, the top level while it has room, else the nearest parent with room. Otherwise the group is left.
- **Only waiting pictures, and only labels the map does not hold.** A picture already in a folder stays there, whatever its label; a hand-placed, answered or product picture never counts as waiting. That is what "every existing folder keeps its pictures" means in code. Consequence: a label whose pictures the matcher already filed is not grown.
- **The owner's yes is the existing flow.** The growth becomes the session draft (origin `grow`) and the tree goes back to editing. There "This is my tree" confirms it. Existing folders are marked `asked`, so the confirm asks the planner nothing. Fill then files through the rule path's `assign`, so the fill neither re-files the library nor asks the text model. The dry run counts that same assignment (`vergeml_plan_grow_fit`).
- **Allowed from the confirmed tree.** Plan my folders now shows on a confirmed, frozen tree as "Plan my folders · free". Any other confirmed tree still answers 409.
- **Frozen means the map names folders that exist.** `vergeml_plan_frozen()` keeps only entries whose term still exists. The box's shop held 399 labels pointing at 49 deleted folders, left behind by an earlier proof, and would otherwise have planned "for free" against nothing.
- **Found and fixed on the way (story 4's code):**
  - A fill after a fill rebuilt the draft from the live folders without the map, froze an empty one and re-filed every picture by the matcher. The live-folders draft (in the confirm and in the apply) now carries the frozen map.
  - Undo deleted the map outright. It now puts back the map the run replaced: none after a plan's first fill, so filing is the matcher's again, as story 4 said; the plan's map after a growth's fill, so undoing a growth keeps the plan frozen. sticky K6/K7 still pass.
- **New folders get a profile from their labels' objects.** The rule path skips profile seeding, because its folders have no classes; a growth's folders have them. This costs one `/embed` per new folder at fill time, which is free to the customer (the service charges no credit for embeds) and was refused in the box proof.
- **The snapshot tool now carries the frozen map option.** It was left behind by restores before.

## I/O matrix

| Scenario | Input / state | Result |
|---|---|---|
| Press on a frozen, confirmed tree | map names existing folders | 200; job runs locally; price 0; nothing sent |
| Press on a confirmed tree, no map (or only stale entries) | | 409 as before |
| Nothing new | every waiting label below the bars | plan `kept`, "No new folders to propose.", tree untouched |
| New label close to a folder | cos ≥ 0.7 and within 0.05 of its tightness | label mapped to that folder; waiting pictures filed there |
| New labels alike, ≥ 5 waiting pictures | | one new folder under the nearest parent with room |
| Group named like an existing folder | | joins it |
| Parent full (12) or at level 3 | | next parent with room, else top |
| Picture already in a folder, new label | | stays |
| Hand-placed / answered / product picture waiting | | stays |
| Plan twice, nothing changed | | identical draft and label map |
| Plan after the growth is filled | | no new folder (a leftover label may now join a grown folder) |
| Undo the growth's fill | | pictures back, grown folders gone, the plan's map frozen again |
| A fill after a fill (not grown) | confirmed tree, no draft | the frozen map is kept, not emptied |

## Proof (2026-09-29, free)

- **Suites:** `node tools/verify.mjs plan-gain plan-choose plan-fold plan-audience plan-grow` gives 9/9, 15/15, 27/27, 9/9, 18/18. `plan-grow` is new: rows 2, 3 and 7 turn red when the margin is removed or the depth limit dropped (tried). `folders-shop` gives 14/14; its new F1-F3 press the free button on a confirmed, frozen tree in a headless browser and see the growth drawn with "This is my tree".
- **Box suites** on tech, through a wrapper that refuses every outbound request the suite does not answer itself: guide 61/61 (A1 boot at 14 queries, the cap), sticky 83/83. Nothing reached a service.
- **Deploy:** `node tools/deploy.mjs --box`, then `--check`: box up to date at `41ab577`, 139 files, digest 047b801452a0.
- **Growth quality** (`tools/box-grow-heldout.php`, read-only, the sites' own folders as the frozen tree):
  - Labels held out a quarter at a time: on the shop, 264 of 625 pictures placed, 87 % of them in their truth folder's home, against 57 % where the same pictures sit today; the new folders were 98 % pure. On tech, 75 of 127 placed, 83 % home (68 % today), new folders 81 % pure. The rest are left for the matcher.
  - A leaf folder taken away with its labels: 53 % (shop) and 55 % (tech) of its pictures come back in one new folder, under the old parent 7 of 31 (shop) and 10 of 12 (tech).
  - CAP-3's bar for filing new pictures is 79 %.
- **End to end** (`tools/box-grow-proof.php`: snapshot with `tools/box-tree-snapshot.php`, proof, score, undo, restore, score). Every outbound request was refused and counted: the plan, second plan and confirm sent none; the fill sent the new folders' `/embed` only (3 on the shop, 13 on tech).
  - Shop, 15/15 and undo 3/3. Freeze: 296 labels frozen; 150 pictures of 113 labels taken out as "new".
    - Plan (0.6 s): 3 new folders, 45 labels placed. Planned again: the identical draft and map.
    - Every existing folder in the draft unchanged (323 of 323). Fill: 93 moved, all waiting, none from a folder. Every existing folder kept its name, its parent and every picture.
    - Planned once more: no new folder (5 leftover labels placed).
    - Grown tree 36/47, purity 81 %, unfiled 11 %; with the new pictures out and no growth it was 26/47, 75 %, 24 %.
    - Restored: **34/47, purity 76 %, 125 folders**.
  - Tech, 15/15 and undo 3/3. Freeze: 288 labels frozen; 225 new labels (102 held out, 123 that already waited in To sort).
    - Growth: 13 new folders. Fill: 368 moved, all waiting.
    - Grown tree 7/11, purity 73 %, unfiled 8 %; without the growth 6/11, 68 %, 31 %.
    - Restored: **7/11, purity 67 %, 17 folders**.
- No paid call was made and nothing is pending. A service deploy is not needed.

## Copy for Nathan

- Button on a filled plan's tree: "Plan my folders · free"
- When nothing new reaches a folder: "No new folders to propose."
- New folder names are the describer's broader class, in English and singular ("Wearable electronics", "Doll clothing", "Unmanned aerial vehicle"). They are generated, not copy, but the owner reads them in the draft.

## Open risks

- **Names.** A model names folders better than the class word does ("Computer" for laptops, "Event" for conference talks). The owner can rename in the draft before confirming. A paid naming call for new folders only would be its own story, and would need a free path in the service.
- **Parent choice on the shop** is weak: a removed folder came back under its old parent 7 times in 31. The shop's many small folders make subtree centres alike.
- **Much is left.** On the shop, 107 of 152 new labels stayed waiting; most are one picture each. The matcher and Auto-file still see them; the growth only proposes what it can back.
- **A rule or conversation fill on a frozen site replaces the frozen map** with its own, usually empty. That was already true before this story. It is the owner's new tree, but it unfreezes the plan silently.
- **rules.md's removal edge is not built:** "a folder is removed only below half the minimum, with the owner's yes".
- **The shop's page boot is 16 queries** before this story's +2 (one when no map exists), against A1's cap of 14, which is measured on tech. Tech reads 14 with this story.
- **The UI was not operated on a real WordPress page.** The harness drives the real `js/vergeml-folders.js` against stand-in routes, and the box proof drives the real routes.
