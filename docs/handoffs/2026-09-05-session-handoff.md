# Session handover — 2026-09-05

For the next session. Read in this order, then start Phase 0 of the plan:

1. `plans/folders-one-tree.md` — the order and the gates (the harness:
   ticket → plan → execute → validate; Nathan calls it the Cole Medin method).
2. `docs/superpowers/specs/2026-09-05-folders-screen-design.md` — the
   contract, §1–§11. §11 is what the three mock rounds added.
3. `docs/superpowers/mocks/2026-09-05-folders-screen.html` — the approved
   mock; open it in a browser. Published at
   https://claude.ai/code/artifact/333b4d77-428e-4c20-af65-84d81945f541
   (republish from this path keeps the URL; it was published from session
   baa5cdeb, so from a new session pass `url`).
4. `tickets/folders-one-tree-and-the-nine.md` — Nathan's asks, condensed.

Both repos on `main`, clean at handover. Plugin last commit: see `git log`
(the docs commits of today); service last commit `e9a4337`. The nightly
watch commits to `main` around 05:17 UTC: `git pull --rebase` before pushing.

## What happened today

- **Runner fix** (`5c10872`): a pass killed mid-way left an active run with
  nothing booked and a five-minute lock; nothing ever ran it again. The lock
  is now a two-minute heartbeat renewed per batch, and
  `vergeml_ai_run_revive()` (admin_init + the status poll) re-books an
  active run once the lock lapses. Suite section G covers it; 34/34 on the
  box. Memory `redescribe-analysis` records it as the seventh defect.
- **Nathan's verdict on the guide**: unusable. Diagnosis and design agreed
  in conversation, then a static mock in three rounds, approved. The mock
  carries real box numbers (641 pictures, 19 folders, 268 unfiled; unfiled
  by kind 240/13/6/6/3; the evidence matcher files 0 of the unfiled).
- **Nine more items** listed by Nathan (dictated, see the ticket). Assessed
  and decided; in the plan as Phases 0 and 5–8.
- Nothing of the rebuild is built. Phase 0 (half a day) is first.

## Nathan's rules stated today, on top of the standing ones

- Copy: a fact, or an action with its consequence. Numbers where they
  inform. Every multi-fact statement a list with the brand mark as bullet.
  Conversational phrasing only inside the conversation.
- Show things to him: send screenshots into the conversation
  (`SendUserFile`), don't only link the artifact. He asked three times for
  the Rules tab before an image was sent.
- Do not open with agreement or blame; lead with the solution.
- Normal page flow; never pin our rail or scroll inside a box.
- Build and check through the harness; warn when a new session is warranted
  instead of relying on compaction.
- Settings collapsed under chevrons.
- Model profile: state the running model at the start of the phase and follow
  the profile in the global CLAUDE.md. Phase 0 fits Opus 5.1 or Sonnet 5 (one
  task at a time, gates after each); Phases 1–3 want Fable 5.1. The plan's
  "Model per phase" paragraph says which.
  Each model's needs, phase size and check cadence are in
  `~/.claude/harness/model-profiles.md`; Phase 0 in the plan is written in
  the Opus shape (Files, Behaviour, Proof, Mirror, Copy, Do not) as the
  reference. Open a phase with the paste-in "Phase opener" at the end of
  that file.
- The launch pivot to marketing (Sept 3) is still on record; Nathan chose to
  continue product work knowingly.

## How the mock is built (if it needs another round)

Source with placeholders in the session scratchpad is gone with the session;
the published file in `docs/superpowers/mocks/` is the source now. It inlines
`css/vergeml-shell.css` (font-face blocks stripped, Inter from Google Fonts)
and three base64 thumbnails. Mock-only classes are `mk-*`; the rail is
forced to `position: static`. Screenshot with Playwright from inside the
plugin dir (module resolution), `page.setContent` of the file wrapped in a
doctype, `section.mk-board` per board.

## Operating notes

- Local ssh to the box works: `~/.ssh/hetzner_vgml`, root@46.225.66.194.
  `wp` needs `--allow-root`; pipe through `grep -v Deprecated`.
- The deploy excludes `tests/`, `plans/`, `tickets/`, `docs/`, `tools/`. To
  run a PHP suite on the box: scp it to `/tmp`, `wp eval-file /tmp/x.php`.
- Box jobs (`box-fix.yml`, `job=`): `refile-all` (dry unless `apply=1`),
  `numbers`, `guide-walk`, `guide-reset`, `tree-restore`, `vps-*`.
- Vercel: verify by deployment age, never a status code.
- Playground for anything that does not need real MySQL; the box for the
  rest.

## Open, Nathan's

F8DC licence → his account (SQL in the 09-04 handoff); production cut-over
of the service to the box; rotate the dev DB password; the Elementor gallery
reference page.
