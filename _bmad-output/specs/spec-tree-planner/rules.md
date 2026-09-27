# Planner rules and edge cases

Enforced in code after the model. Values measured in the tree lab on 2026-09-26, where noted.

| Rule | Value | Evidence |
|---|---|---|
| Minimum pictures per folder | 5 (small folders hand their pictures to the parent, or leave them unfiled at the top) | 3 ≈ 5 on quality with more folders; 8 lost real folders (tech 9 → 7) |
| Depth | at most 3 | The shop truth is 3 deep; the third level took recovery from 22 to 39 |
| Children per parent | 2–12, the top level included; a one-child parent merges with its child | A run with 46 top-level folders recovered 28 of 47 |
| Names | unique in the whole tree, 3 words at most, the site's language, no slash | 0 duplicates in every lab tree |
| Audience split | only from the owner or from product categories | 3 of 88 pictures carry the right audience |
| Kinds (logo, screenshot, diagram, document) | a folder of their own only at the minimum | |
| Unfiled target | 10 % or less; a label that fits nothing stays unfiled, never forced | Lab 4–10 % with placement (2026-09-27) |
| Placement | a label the model left out joins the folder whose pictures its own pictures are nearest to, at a cosine of 0.5 or more; below that it stays unfiled | Shop unfiled 20 → 9 %, purity 81 → 77 % (core/plan-tree.php VERGEML_PLAN_PLACE) |
| Run selection | 15 runs; keep the tightest: the mean cosine of a picture to its folder's centre, by the site's vectors | Ranked the runs as their true scores did (shop 0.770 for 41/47, 0.729 for 26/47); with 5 runs one build fell to 36/47 at 73 %. Most-agreed (the first rule) kept a 29/47 run; voting across runs gained nothing |

## Edge cases

- **Under 30 pictures:** one level, no subfolders.
- **Very large libraries:** rare labels fold into their broader class before the call (CAP-5).
- **One dominant class:** it gets children only where its labels fall into kinds that each meet the minimum.
- **Undescribed pictures:** left out of the plan, and the screen says how many.
- **Existing folders and product categories:** kept as they are; new folders fill gaps only. A rename or delete is always the owner's.
- **Growth after freezing:** new pictures go into the frozen tree. A new folder is proposed once a label reaches the minimum, and a folder is removed only below half the minimum, with the owner's yes.
- **Invalid model answer:** a run that fails the schema is dropped. Fewer than 3 valid runs means the plan says so and costs nothing extra.
