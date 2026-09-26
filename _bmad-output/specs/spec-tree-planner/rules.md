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
| Unfiled target | 20 % or less; a label that fits nothing stays unfiled, never forced | Lab 10–24 % |
| Run selection | 5 runs; keep the one with the highest mean pair agreement with the others | Dropped a 14/47 run automatically |

## Edge cases

- **Under 30 pictures:** one level, no subfolders.
- **Very large libraries:** rare labels fold into their broader class before the call (CAP-5).
- **One dominant class:** it gets children only where its labels fall into kinds that each meet the minimum.
- **Undescribed pictures:** left out of the plan, and the screen says how many.
- **Existing folders and product categories:** kept as they are; new folders fill gaps only. A rename or delete is always the owner's.
- **Growth after freezing:** new pictures go into the frozen tree. A new folder is proposed once a label reaches the minimum, and a folder is removed only below half the minimum, with the owner's yes.
- **Invalid model answer:** a run that fails the schema is dropped. Fewer than 3 valid runs means the plan says so and costs nothing extra.
