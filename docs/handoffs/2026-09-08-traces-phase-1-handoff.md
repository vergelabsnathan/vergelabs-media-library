# Session handover — 2026-09-08, traces Phase 1: the reason is kept (Opus 5)

Phase 1 of `plans/traces.md` is built and green. A move now records why it
happened, an abstention is a row of its own, and nothing about where a picture
goes has changed — which is the assertion the phase exists to keep, and the
baseline below is the thing that will prove it after Phase 5.

Read in this order:

1. `docs/superpowers/specs/2026-09-08-traces.md` — the contract.
2. `docs/superpowers/specs/2026-09-08-traces-diagnosis.md` — the argument.
3. `plans/traces.md` — the five phases. Phase 2 is next.
4. This handoff.

## The baseline the last gate needs

`tests/tree/filing-baseline.txt`, taken on the box **before any edit**, with
`tools/box-filing-baseline.php` (run through `tools/box-filing-baseline.sh`).
It is the matcher run dry over the whole library against today's folders: one
line per picture, sorted by attachment id, six decimals.

    641 pictures, 31 folders
    346 ok · 263 floor · 17 margin · 15 gated

**After Phase 5, run the same tool and diff the two files. They must be
identical.** Not "close" — identical. The script writes nothing but a folder's
own profile cache, which any real run fills the same way.

That file also says what the trail costs on this box: a full re-filing pass
writes 641 rows, 346 of them placements and 295 abstentions.

## What changed

| file | what |
|---|---|
| `core/librarian.php` | six columns on `vergeml_librarian_moves`; `vergeml_librarian_move_reason()`; the insert carries a fifth element; schema version 1 → 2; undo skips rows with no term |
| `core/guide.php` | `vergeml_guide_rule_fit()` keeps the whole pick, placed and abstained alike; the draft and the apply plan carry it |
| `core/folder-talk.php` | the re-filing pass writes the trail: a row per move, a row per abstention, one insert per pass |
| `core/auto-file.php` | the suggestion keeps its pick; `vergeml_autofile_file()` takes a reason; a fourth batch scheme, `refile` |
| `core/nl-commands.php` | a spoken move writes `by hand` |
| `tests/tree/filing-trail.php` | new suite, 47 checks |
| `tools/verify.mjs`, `docs/testing.md` | the suite registered as `filing-trail` |
| `tools/box-filing-baseline.php`, `.sh` | the baseline tool |

### The six columns

`why varchar(16)`, `score float NULL`, `runner_up bigint`, `runner_score float
NULL`, `prompt_hash varchar(64)`, `model_version varchar(64)`. dbDelta,
additive, nothing backfilled: an empty `why` means the move happened before
this shipped, and the suite holds that open.

`score` and `runner_score` are nullable **on purpose**. A null says nobody
computed a score; a 0.0 would say the matcher looked and found nothing, which
is a different claim and a false one. A null cannot travel through
`$wpdb->prepare` as `%f` — it arrives as `0.000000` — so the literal `NULL`
goes into the placeholder list instead, beside the placeholders that file was
already interpolating.

### The vocabulary, six words

| word | what decided the folder | scores |
|---|---|---|
| `ok` `floor` `margin` `gated` | the matcher, and this is its own word | yes |
| `plan` | a proposal chose it and a person approved it | null |
| `by hand` | a person named the folder outright | null |
| *(empty)* | before any of this shipped | null |

`plan` is the fifth word and it was your call on 2026-09-08. It covers the tree
Apply (`core/librarian.php:1592` — the clustering proposed it, and the matcher
there only ever has a veto), the guide's subject/kind/date rules, and an
accepted auto-file suggestion.

**Where `accepted` is concerned there is a judgement worth knowing about.** The
accept endpoint receives a file and a folder from the browser, not a pick, and
re-running the matcher there could answer about a different folder than the one
being accepted — so it writes `plan` with null scores rather than numbers it
would have had to make up.

## The thing the brief had wrong, and what it meant

The opener said the guide's picks reach the move through `$work[]` in
`core/librarian.php:1228`. They do not. The Folders screen's Move goes
`vergeml_guide_rule_fit()` → `assign` → `vergeml_talk_apply()` →
`vergeml_talk_refile_run()` in `core/folder-talk.php`, and **that path wrote no
row to `vergeml_librarian_moves` at all**. The librarian's `$work` pairs come
from an organize-run tree; no `filing_pick` ever made them.

You chose (2026-09-08) that folder-talk writes the rows, which is what the
plan's own Phase 1 file list and the spec's "a move made by any route" already
implied. So:

- The reasons ride to the Move packed as `[ why, score, runner_up,
  runner_score ]` per attachment, in the option beside the assignments —
  packed rather than keyed because a library of fifty thousand pays for every
  byte of it twice a pass.
- `vergeml_talk_trail_write()` asks `vergeml_autofile_batch( 'refile' )` for a
  batch only when there is a first row to put in it, and inserts once per pass.
- It is best-effort by design: it records, it decides nothing, and a site whose
  librarian tables are missing goes on filing exactly as before.

**A batch with the new `refile` scheme now appears in `vergeml_librarian_batches`.**
Nothing in the admin lists batches for undo (no JS calls `librarian-undo-step`),
so nobody can reach it, and `vergeml_librarian_moves_pending()` now excludes
`term_id = 0` so an abstention could never be reported as "moved, or gone".

## Proof

`tests/tree/filing-trail.php`, on the box because this is about dbDelta, a
FLOAT that has to come back null, and an ALTER that puts six columns back on a
table with rows in it. SQLite answers a different question about all three.

The fixture is built so each of the four outcomes is the only answer available,
and **every class comparison in it is an exact match or a substring** — the two
answers `vergeml_filing_class_match()` gives without asking the service. The
suite spends nothing.

    47/47 passed

**The mutation check ran.** `vergeml_filing_pick()` was changed to return
`why => 'floor'` on the branch that places a picture — a word disagreeing with
what it decided — deployed, and the suite went red:

      FAIL  the ok picture comes back ok  -- said floor, scored 1.0000
      FAIL  the ok row says ok  -- said floor
    45/47 passed
    suite exit=1

Reverted and redeployed; `git status` shows `core/filing.php` unmodified.

It fails that way because each fixture's expected word is **named in the
suite**. An equality against a freshly-taken pick would have passed the
mutation happily, since both sides would have been wrong together.

### Gates

| gate | result |
|---|---|
| `node tools/verify.mjs filing-trail` | 47/47 passed |
| `node tools/verify.mjs copy journey guide` | 62/62 passed |
| `node tools/verify.mjs tree` (`t0-endpoints.js`, Playground) | 21/21 passed |
| `npx playwright test --config tests/ui/playwright.config.mjs modes.spec shell.spec shots.spec folders.spec` | 32 passed, 3 skipped, **2 failed** — see below |

**The two failures are the `ROW_ALARM` canary, and they are not this phase's.**
Both are `modes.spec.mjs:506`, once arriving in grid and once in list, and both
say the same thing:

    as the screen ships, with every other plugin's column on, arriving in list:
    the tallest of 20 rows is 337px over 8 columns
    (title, author, date, fb_folder, fb_filesize, qode-optimizer,
     seopress_alt_text, aioseo-details)
    Expected: <= 300   Received: 337

Eight columns: three core, five belonging to FileBird, Qode, SEOPress and
AIOSEO. **None of ours** — that set hides our four by design, and it is
measured against `ROW_ALARM` (300) rather than `ROW_CEILING` (100). The two
sets that do measure our columns — ours off, and all four of ours on — both
passed under 100px. Nothing in this phase adds or changes a column.

Re-run alone afterwards to be sure it was not a flake: 3 passed, the same 2
failed, identical message and identical column list. The box's other plugins
are what is over the alarm; the nightly watch updates them.

`t0-endpoints.js` cannot be run directly — it needs Playground on
`127.0.0.1:8899`. `node tools/play.mjs --port 8899` first, and rebuild the zip
(`node tools/deploy.mjs --zip`) or Playground tests the last committed one.

A throwaway administrator was made with `tools/box-ui-user.sh` for the browser
specs and deleted at the end.

## Found, not done

- **The negative query has an answer, but only from the suite's own rows.**
  `SELECT ... WHERE term_id = 0 AND why <> ''` returned 3 on the box — real
  rows in the real table, written by the real pass, for pictures the suite
  made and removed. Giving it a real-library answer means running a re-filing
  pass over the box's 641 pictures and undoing it, which was not a gate on this
  phase. Phase 2's guide walk will populate it as a byproduct.
- **No index on `why`.** The negative query scans. The table is bounded by
  `vergeml_librarian_prune()`, so it is not urgent, and the phase named six
  columns and no key. `KEY why (why)` is one additive dbDelta line if a surface
  in Phase 3 wants it.
- **`vergeml_librarian_prune()` deletes by `batch_id <= cutoff`.** With the
  re-filing pass now making a batch a day, the trail ages out faster than it
  did when only the Librarian made them. Worth a number before Phase 4.
- **The media list is 37px over its alarm on the box**, from other plugins'
  columns (see the gate table). Not this phase's, not looked into.

## What Phase 2 is

The model stops counting. `lib/guide.ts` and `lib/guide-stream.ts` in the
service, the guide's REST path in `core/guide.php`, `js/vergeml-folders.js`.
The plugin runs the dry run before the turn is returned and the screen carries
the matcher's numbers; `guideRules` gains the rule from the spec, verbatim.
It spends one guide turn per walk — a few cents, and say the number before
running it.

Phase 2 is Opus. One phase per fresh session, gates after every task, no
compaction.
