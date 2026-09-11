# Handover — Phase 1, session S1: tasks 1.3 and 1.6 (Opus 5)

Run on 2026-09-11 in the same session as task 1.2 and the plan preparation,
at Nathan's instruction ("build on now") — against the one-task-per-session
rule, and said so. Two tasks, two commits, both gates green. No plugin
change. Nothing reached a model.

## Decisions Nathan took at the start (recorded in the plan's table)

- Retire `librarian-ui` — yes.
- Languages — English strings; the locale and RTL sheets are the axis.
- Box mock — stays on until Phase 2.3.
- WordPress.org username — **VergelabsDev**.

## 1.3 · librarian-ui retired — `a97e420`

`tests/librarian/librarian.mjs` deleted, its `verify.mjs` entry removed, the
sentence in `docs/testing.md` and the commit. The board is **38 entries**
(it was 39 — the plan's "33" was 09-10's count; Phase 5 added suites).
`vgml-lib-stage` is referenced only by `js/vergeml-autofile.js` now — Phase 3.

## 1.6 · uninstall proven both ways — `b06c90b`

`node tools/uninstall-walk.mjs` → **18/18**. Two Playgrounds from a clean
`git archive`, three folders (one nested), four images (three filed), the
plugin deleted through the Plugins screen's confirmation form.

- Default: folders survive as `media_category` terms with nesting; 3 of 3
  filed pictures keep their folder; 4 of 4 attachments and files remain; 12
  of 12 options and all four tables stay for a reinstall; transients and cron
  cleared; nothing of ours in the debug log.
- Remove everything: 0 terms, 0 links, 0 options, 0 usermeta, no table;
  attachments and files untouched.
- Mutation: `VGML_UNINSTALL_MUTATE=1` (wipe unconditional) → run 1 goes
  6/9 with the folders gone.

The FileBird argument holds: deleting the plugin does not cost the folders.

Three traps in the file's comments: Windows `tar` reads `C:` as a host
(pipe `git archive` with relative paths); mounting a directory over
`mu-plugins` hides Playground's own login (write the probe in with a
blueprint `writeFile` step); clicking Delete before the ajax script attaches
follows the plain link — so the walk takes the confirmation-form route on
purpose. WordPress then reports "Could not fully remove the plugin" on a
mounted directory it has emptied — the mount point, not the plugin; the
check asserts the directory is empty.

## Gates

- `copy 52/52 · surface 26/26 · db-calls 11/11 · escaping 10/10 · globals 7/7`.
- `node tools/filing-baseline-check.mjs` — **cannot run**: the box's library
  is all mock (0 pictures for the baseline script), and
  `tests/tree/filing-baseline.txt` is from the pre-reset library (641
  pictures, 31 folders; the box holds 1,000 and 20). Needs a real describe
  (≈ €4.83) and a re-taken baseline. Added to the stop-point table.
- The full board was not run this session (it is the phase-end gate, and it
  must exclude `librarian-schema`, which drops the box's librarian tables).

## Box state

Unchanged by this session: mock on, library mock-described, 1,000 pictures
in 20 folders. No admin was created (nothing ran in a browser against the
box).

## Next

Phase 1 S2 is 1.4, the matrix — its own session, Fable if available. Or, if
Nathan prefers the plan's order, Phase 4 S1 (prove health + release-check)
or Phase 5 S1 (paths + hosts). Opener:

> Read `docs/handoffs/2026-09-11-phase-1-s1-librarian-ui-and-uninstall.md`,
> then `plans/four-yesses/phase-1.md`. State which model you are and follow
> that profile in `~/.claude/harness/model-profiles.md`. This session is
> Phase 1, session S2: task 1.4. Stop points and gates are at the top of the
> file; the decisions of 2026-09-11 are in the plan's table. End with a
> handoff in `docs/handoffs/`.
