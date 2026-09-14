# INITIAL — Every picture a home: the Folders flow rebuilt in the user's order

Asked by Nathan on 2026-09-14, after using the box as a customer: 513 of
1,000 pictures left in no folder after Move, the screen "not very Apple
Macintosh simple", the whole thing "unusable". The spec is
`docs/superpowers/specs/2026-09-14-every-picture-a-home.md`; the plan is
`plans/every-picture-a-home.md`. Handoffs go in `docs/handoffs/`.

## FEATURE

**Five steps in the user's order**, as one screen with a step rail:
Describe → Tree → Fill → Alt text → Rename. Each step a state; the next
disabled until the previous is done.

**The tree is confirmed before anything is filed.** Proposed by the planner
(a button, with its cost — never automatic) or brought by the user (paste,
CSV, by hand); edited by prompt or by hand; then *This is my tree*, locked.

**The fill has three outcomes, only one silent.** Fits → placed with a
confidence. Fits two siblings → the parent, and one question for the group.
Fits nothing → the residue, grouped by what the pictures show, one question
per group with "Leave them" always an answer; what is left goes into one
visible **To sort** folder. The step ends at 0 pictures in no folder.

**One filing path.** Preview and run score the same profiles with the same
matcher; the planner's re-profiling never runs mid-fill. A manual move is
sticky; a folder can be locked.

**Screens at a glance.** ≤ 80 words a step, pills for every fact, the
conversation demoted to one input and three chips, the approved glance mock
as the grammar (`docs/superpowers/mocks/2026-09-14-folders-glance.html`).

## WHY

The matcher was built to be right and the button promised to be done. On a
real library that is 487 placed, 513 abandoned, and a preview that promised
816. A person sorting a library needs every picture to land somewhere and to
be asked, once per group, about the ones the machine cannot place — not to
find half the library where it was. The screen, meanwhile, made the person
read to find out where things stood. Both are the same wrong idea: the
system talking instead of finishing.

## OUT OF SCOPE

A rule builder; file renaming (gated); the description prompt; the Dashboard
and AI screens' own de-texting; anything on other plugins' folders.

## DECIDED BY NATHAN, 2026-09-14

- The order: describe, tree, fill, alt text, rename.
- Residue is asked about, never forced into a folder on a low score.
- The tree is 100% confirmed by the user before filling — theirs or the
  proposal, altered by prompt or by hand.
- Folders without alt text must be possible (alt text is its own step).
