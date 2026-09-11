# INITIAL — Four yesses at two thousand, prepared for execution

Asked by Nathan on 2026-09-11, after Phase 1 task 1.2: "prepare — overprepare
is overperform." The plan `plans/four-yesses.md` reasons well at the phase
level and is not executable in the shape the model profile requires. This
ticket and the five task files under `plans/four-yesses/` are the preparation.

The plan is `plans/four-yesses.md`. The task files are
`plans/four-yesses/phase-1.md` … `phase-5.md`. The model profile is
`~/.claude/harness/model-profiles.md`. Handoffs go in `docs/handoffs/`.

## FEATURE

**Every task as a mini-spec.** Each of the plan's tasks rewritten with the six
fields an Opus session needs — Files, Behaviour, Proof, Mirror, Copy, Do not —
and a model named per task, not per phase. Where a task is design work that
needs a mock approved first, the task says so and stops there.

**The state corrected.** The plan was written 2026-09-10 and three of its
Phase 4 items were built the same day (`/api/health` with five checks,
`/api/cron/release-check`, per-licence rate limits in `lib/limits.ts`);
`SECURITY.md` exists; the credit spend already takes `FOR UPDATE`. Those tasks
become "prove", not "build". Phase 1 stands at 30 of 33 green after task 1.2.

**Sessions sized.** Each task file says which tasks make one session, in the
Opus size (two to four tasks), and which need a fresh session on their own.

**Stop points collected.** Every decision only Nathan makes, in one list at the
top of the plan, with the task it blocks.

## WHY

Task 1.2 worked because the opener supplied by hand what the plan did not:
the exact suite list, the do-nots, the spend rule, the gate command. The
profile says that is the plan author's job, and that a Fable-shaped plan run
on Opus produces patches. Thirty-odd tasks each interpreted by a fresh session
is thirty chances to drift; thirty mini-specs is none.

## OUT OF SCOPE

- Executing any task. This ticket produces documents.
- Changing what the plan decides — the yesses, the order, the rollout shape.
  Where the survey found a task already done, the task is re-pointed at
  proving it, which is the plan's own rule ("a yes is something showable").
- Rewriting `plans/folders-one-tree.md`, which Phase 3 leans on and which is
  already in the shape.

## OTHER CONSIDERATIONS

- **No translations exist.** `languages/` holds the `.pot` only. Phase 1.4's
  language axis and Phase 3.11 both assume Dutch and Arabic; there is no
  `nl_NL` or `ar` `.po`. Decision needed before either: machine-translate the
  `.pot` as a task (with a review pass), or define the axis as WordPress locale
  plus the RTL sheets with English strings. Listed as a stop point.
- **The box's descriptions are all mock** since 2026-09-11 10:09 (see the task
  1.2 handoff). Phase 2.3 describes 100 pictures through the screen and will
  put real descriptions back on those 100; anything that needs the whole
  library real (meaning-search proofs) needs a re-describe at ≈ €4.83.
- **Docker is not available** for the matrix (`tests/compat/matrix.sh` is
  wp-env). Playground boots per PHP and WP version; multisite is the box's
  `/var/www/ms`; real MySQL at size is Kamatera. The Phase 1 file says which
  runs where.
- **The mutation check is per new suite**, as the profile says. Tasks that
  produce a document (a matrix table, a runbook) carry a different proof: the
  thing was done once and the document says how it went.

## RULES THAT APPLY

The global rules in `~/.claude/CLAUDE.md`; the profile in
`~/.claude/harness/model-profiles.md`; the copy standard in `docs/voice.md`
and its suite `tests/copy/voice.php`; no compaction, a handoff per session;
tests restore what they write; every browser suite needs a throwaway admin
from `tools/box-ui-user.sh`, deleted at the end.
