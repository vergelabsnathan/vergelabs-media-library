# Handover — Phase 1, task 1.2: the seven suites brought back to the code (Opus 5)

Run on 2026-09-11, one task, one session. Every suite named in the 1.1 handoff
was run before and after its fix, each fix touched the suite or its fixture
only, and each has a mutation check. Voice's two strings were fixed. Nothing
retired; `librarian-ui` waits for Phase 3. No plugin change beyond the two
strings.

**Spend.** `ai` ran three times in mock mode; `search-try` made one `/embed`
call per run (three runs, one short word each), which the service does not
charge and the provider bills at a fraction of a cent. Nothing else reached a
model. `ai-background` was not run (already green).

## The board

| suite | before | after | commit | what moved |
|---|---|---|---|---|
| `voice` | 1/2 | **2/2** | 98343dd | the two strings, plugin |
| `ai` | timeout | **10/10** | 4bfb513, 23d7b84 | suite |
| `smart` | 9/11 | **11/11** | 9e168e2 | suite |
| `search-try` | fatal | **9/9** | 3723b57 | suite |
| `ai-folders` | 35/38 | **38/38** | b81c46d | suite |
| `organize` | 61/66 | **67/67** | 2a2c711 | suite (+1 check) |
| `librarian` | 65/68 | **68/68** | 3f98e9a | fixture, in the suite |
| `auto-file` | 16/23 | **23/23** | 6f89fc5 | fixture, in the suite; verify.mjs label |

Gates on the committed tree: `copy 52/52 · surface 26/26 · db-calls 11/11 ·
escaping 10/10 · globals 7/7`.

`surface` and `db-calls` were red at the session's start on the untouched
tree — the two generated docs had drifted by line numbers only (b9fdc25 moved
`core/smart-folders.php` by 16 lines; same hashes, 202 calls, findings 0).
Regenerated in the voice commit.

## Each fix, and the mutation that proves it

**voice.** `core/admin-menu.php:220` "The tree answers." → "The folders
answer."; `core/admin-shell.php:69` "Build the folder tree" → "Build the
folders". Nathan chose the mechanical replacement from the suite's own table
over keeping the mock's approved copy. Mutation: the word put back on the box
copy → 1/2.

**ai.** The wait for a note starting "Done" became a wait for `finish()` to
write anything, then an assertion of the completed form
`N pictures described · K failed · HH:MM`. Past that wait, three checks assumed
the run leaves the whole library with alt text; on the reset fixture 997
described pictures carry none, and "Describe new images" only touches the 3
undescribed. Now: alt filled for exactly the pictures described (`1000 -> 997,
3 described`), the search hit read from the `query-attachments` response
instead of the tiles after a 3 s sleep (`80 results`), and the Missing alt
text badge agreeing with the status endpoint (`badge 997, status 997`).
Mutation: the regex rejects the stopped line, the kept-failing line and the
error line. Second commit: the suite now reads the mock/enrich flags from
`ai-status` first and puts them back at the end.

**smart.** The two `waitForTimeout( 2500 )` counts use the wait the unused
check already had (no spinner, tile count equal to the badge). The
assertions are unchanged; the before-run's `0 tiles for 998` is the evidence
they discriminate.

**search-try.** `min()` on an empty score list was the fatal. The word was
never the problem — the suite already picks the library's most-used tag. The
guard tells two cases apart: with only mock rows in the index an empty
meaning answer passes as the mock being what it is (C2–C5 skipped and said);
with any row from a real model it stays red. Mutation: one row's `model` set
to "mutant" on the box → 8/9, restored.

**ai-folders.** `$wpdb->last_query` held the transient write the 10 Sept
counts cache does after the recount. `af_counts_statement()` collects every
statement through the `query` filter and returns the one naming the unused
branch, `last_query` as fallback. The before-run's three reds are the
evidence.

**organize.** `ini_set( 'memory_limit', '128M' )` returned false because PHP
refuses a limit below what the process holds, and WP-CLI on the box with 28
plugins sits at 369 MB. The suite now walks 128M → 2G until one takes,
asserts the pin and that `vergeml_organize_memory_limit()` reads it (`512M
with 369MB in use`), and sizes the narrowed and refused runs from the
plugin's own per-file cost at that limit (`32 dims for 195,652 files`,
`445,430 files … failed`). Mutation: offering only 128M → the pin check red
with the cause named, 61/67.

**librarian.** The by-date preflight planted eight files in March 2026 and two
in July, then looked for a branch of exactly eight; the reset put 28 in every
month of 2026. The files now go in the year before the library's oldest
upload (2022 on the box), and the month branch is found by holding every
planted id, asserted to hold only those. Mutation: `min_branch` forced to 1
through the plugin's own filter → the fold check red, 67/68.

**auto-file.** The planted rows had no object classes and no embedding on the
row; the 5 Sept matcher reads both off the row and abstains below the floor,
so `null` was right. Rows now carry `filing.object` and `embedding`; the three
folders get profiles as term meta (version 5, name as class, the fixture's
corner as vector) so no score depends on the service. "between" shows both
classes, "far" none. Only the planted picture is offered on the box ("1
suggested"): the box's own unfiled mock pictures stay below the floor.
Mutation: "between" with one class → the neither check red, 22/23.
`tools/verify.mjs` now labels the suite `box` — every PHP suite ships over
SSH anyway, and packed floats are an INSERT Playground refuses.

## Box state, for Nathan

- **Every description on the box is mock.** All 1,000 index rows are
  `model = mock` as of 10:09 today: the 1.1 session's `ai` run timed out in
  the runner but the page loop kept going and mock-described the reset
  library. The catalogue descriptions from the re-describe work are gone. A
  real re-describe of 1,000 is ≈ €4.83 (0.00483/image); a subset is cheaper.
  Until then `search-try` C2–C5 are skipped by the guard, and meaning search
  on the box answers nothing.
- **`mock` is on in the box's AI settings**, left there by that run. The
  `ai` suite now restores what it found, so it stays on until somebody turns
  it off. Left as found; it makes every describe on the box free, and also
  fake. Say the word and it goes off.
- The throwaway admin `vgml-t12` was created for the browser suites and
  deleted at the end. The password lived only in this session's scratchpad
  and is gone.
- `tests/tree/filing-baseline.txt` untouched; `gate7-schema.php` not run; no
  plugin deactivated.

## Found, not done

- `js/vergeml-autofile.js` still references `#vgml-lib-stage`, which no PHP
  renders — noted in 1.1, Phase 3's.
- `docs/security-surface.md` and `docs/security-db-calls.md` drift on any
  line move in a file they name; regenerating them is two commands, but a
  gate that goes red on a line number is a gate that will keep going red.
- `tools/verify.mjs` runs every `php: true` suite on the box whatever its
  `env` says; the label only gates on reachability. Two other PHP suites may
  carry the same stale label.

## Next

**Phase 1, task 1.3** is the sentence for `librarian-ui` (retire or rebuild
after Phase 3), then **1.4 the matrix**, 1.5 size, 1.6 uninstall, 1.7
rollback. A fresh session, and it needs the plan to name the model per task.

Opener:

> Read `docs/handoffs/2026-09-11-phase-1-task-1-2-seven-suites-back-to-the-code.md`,
> then `plans/four-yesses.md`. State which model you are and follow that
> profile in `~/.claude/harness/model-profiles.md`. This session is Phase 1,
> task 1.3 and 1.4: …
