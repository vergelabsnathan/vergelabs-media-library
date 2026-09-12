# Handover — Phase 3, S1: 3.1, the Folders mock approved (Opus 5)

2026-09-12, late, at the end of the S5/S6 session. Nathan looked at the
four-way mock and changed the design; the mock and spec were redone to his
decision, screenshotted, and he moved on — "can we move on now" after the
screenshot is the approval 3.1 asked for.

## The decision, verbatim

"No I want only one way and not the indentation it doesnt work what is the
best single way to facilitate parent child" → recommended and taken: **one
text box, one folder per line, the full path with `>` between levels**
(`Hardware > Components > Chips`). No indentation, no `/`, no Rules entry,
no file upload. The conversation stays as the other tab — it is the
product; paste is the one manual way beside it. (If he meant paste *only*,
it is a two-line change; asked, not answered.)

## What changed — commit `30a5d7c`, pushed

- `docs/superpowers/specs/2026-09-10-folders-ways-in.md` — a dated
  "Decision, 2026-09-12" section with the four reasons; the contract is
  "One draft, two ways to fill it"; the reading rule is one rule (split on
  `>`, trim, missing parents created, same path once, case-insensitive
  match to an existing folder); upload and rules moved to out of scope;
  the proof line rewritten (any order, a repeated path, a missing parent;
  an indented paste is top-level lines, never depth).
- `docs/superpowers/mocks/2026-09-10-folders-ways-in.html` — two tabs, the
  paste box in path form, "read as paths", the fact line `19 folders, 3
  levels deep. None of them exist yet.` **The mock's DOM was broken** (a
  stray unstyled tree and an extra `</div>` closing the left column early),
  which is why the 10 Sept screenshot showed the tree full-width under the
  paste instead of in the right column. Fixed; the columns hold.
- `docs/superpowers/mocks/shots/2026-09-12-folders-one-way.png` — 1600×1000,
  the state Nathan saw. Also published as an artifact (version 2):
  https://claude.ai/code/artifact/6169698f-91f0-4cf6-a727-f914822e5dd6

3.1 asked for four states; one exists (paste with preview). The
conversation state is the screen as built (f3497e9), and the upload state
no longer exists. That leaves one more worth a board before 3.2 builds: the
paste with a folder that **already exists** (`is-change`, "2 already exist
and will be reused").

## Copy on the screen (verbatim, for 3.2/3.3)

- Tab: `Paste folders`.
- Under it: "One folder per line, the full path with `>` between levels:
  `Hardware > Phones`. A parent that is not listed is created. The preview
  shows what was understood before anything is made."
- Preview head: `What that makes` · `read as paths`.
- Fact line: `19 folders, 3 levels deep. None of them exist yet.` /
  `… 2 already exist and will be reused.`
- Refusals (spec): an empty name, deeper than five levels, more than 500
  folders. A line with no `>` is a top-level folder, not an error.

## Next

- **Phase 3 S2 is Fable** per `plans/four-yesses/phase-3.md`: 3.2 app shell
  + 3.3 draft as tree + 3.4 bring a structure — now "paste paths" only.
  Fresh session, Fable, a card in `plugin/.harness/active.json`.
- Or Phase 4 S1 on Opus (prove `/api/health`, release check) — needs
  nothing from Nathan.
- Everything else open is in the S6 handoff's list.
