---
title: 'The tree score on the box'
type: 'feature'
created: '2026-09-26'
status: 'done'
route: 'oneshot'
review_loop_iteration: 0
context: []
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Every later planner story needs a number for the tree it produced on a real site. `tools/tree-lab.mjs` scores trees offline, but nothing scores the tree a site actually holds.

**Approach:** Add a read-only `tools/box-tree-score.php` that reads the truth the way `tools/box-truth-score.php` does (the seed stamp, or `VGML_TRUTH`) and where each labelled picture sits now in `media_category`. It prints the lab's metrics in one `TREE` line: pair F1 at the leaf and top level, purity, real folders of 5 or more recovered, unfiled, folders, depth and same names. A picture in several folders counts in its deepest one.

</frozen-after-approval>

## Implementation Notes

- The maths is the lab's `score()`, ported line for line, so a box tree and a lab tree read on the same scale.
- Proof: the box line equals the lab's `current` line on both sets. Shop: F1 leaf 37 %, top 67 %, purity 76 %, recovered 34/47, unfiled 0 %, 125 folders. Tech: F1 leaf 20 %, purity 67 %, recovered 7/11, belongs-nowhere placed 72/72, 17 folders. These are the baselines for the planner stories.

## Review Triage Log

- low, patched: lowercased keys differed from the lab's score().
- medium, patched: undescribed labelled pictures were scored here but not in the lab; now only indexed pictures, like the export.
- low, patched: tie order depended on SQL row order; the truth is sorted by id.
- low, patched: the lab's usage block did not list the current mode.
- low, rejected: path normalisation differs from the export; seed paths already use " > " and both lines are identical.
- low, rejected: a malformed VGML_TRUTH reads as no truth; a developer tool, same as box-truth-score.php.
