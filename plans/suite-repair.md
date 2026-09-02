# Suite repair — three red suites, and a harness that can no longer hide them

## Problem

On 28-08-2026 a full validation run at 3.8.0 found that four PHP suites had been
reporting `passed` while their assertions were never counted.

`wp eval-file` evaluates a file **inside a function**. Variables declared at the
top of the suite are therefore that function's locals and never globals, so a
helper doing `global $u_pass, $u_fail;` bound to a second, empty pair that
nothing incremented. The summary printed `0/0 passed` however many checks ran,
and the terminating `if ( $u_fail > 0 ) { exit( 1 ); }` could not fire — so the
suite exited 0 whatever failed, and `tools/verify.mjs` reported it green.

That is fixed (`8a1f141`): `utilities`, `auto-file`, `nl-commands` and
`quarantine` now use `$GLOBALS`, matching `tests/librarian` and `tests/organize`,
which were correct all along and are why those two were trustworthy.

What the fix revealed, on a freshly seeded box:

```
say          32/32   genuinely green
quarantine   30/31
utilities    23/24
auto-file    14/23
```

Three suites are red, for three distinct reasons, none of which is a product
bug:

1. **`auto-file` is not isolated from the site's own folders.**
   `tests/tree/auto-file.php` line 65 takes `vergeml_librarian_taxonomy()` —
   the site's live primary taxonomy — and seeds `zzInvoices` / `zzHarbour`
   into it. `vergeml_autofile_folders()` in `core/auto-file.php` (~line 201)
   returns *every* term that has a centroid, so the suite's `auto-me` file
   competes against the 33 folders `tools/fixtures.php realistic` creates.
   A fixture folder wins `$best`, has earned nothing, and the file is only
   suggested — which is why "but it is offered" passes while "now a sweep
   files into it" reports `0 filed`, along with the three assertions
   downstream of it.

2. **`quarantine` asserts a site-wide count.**
   `tests/tree/quarantine.php` line 162 checks
   `1 === (int) $q_manifest['count']`, but `vergeml_quarantine_manifest()` in
   `core/quarantine.php` (~line 197) counts everything set aside anywhere on
   the site via `vergeml_quarantine_list( 5000 )`. Any other file left
   quarantined — `utilities` sets one aside when it tests merging — makes this
   fail. It reported `4 listed`.

3. **`utilities` leaves its seeds behind.**
   Its teardown check counts `zz util%` site-wide, and the run before it left
   files under that prefix. This is the same shape already fixed in
   `tests/librarian/test-librarian.php` (`d85042e`), where the check now asks
   whether the ids *it recorded* still exist.

Underneath all three: `tools/verify.mjs` cannot see a suite's output at all.
`runPhp()` spawns with `stdio: 'inherit'`, so the runner judges only the exit
code. A suite that stops counting is invisible to it — which is exactly how
four suites stayed green for as long as they did.

## User story

As the person who has to trust this battery before submitting the plugin, I
want a suite that fails to say so and a runner that cannot report a suite it
did not really check, so that a green run means the plugin works rather than
that the harness was quiet.

## Decisions taken

- **The auto-file feature is correct and does not change.** The concern raised
  during `/prime` — that the nearest folder wins before autonomy is checked, so
  an unearned folder could block an earned one — does not hold. The margin
  guard in `vergeml_autofile_suggest()` (`core/auto-file.php` ~line 305) returns
  null unless the runner-up is at least `VERGEML_AUTOFILE_MARGIN` (1.25×)
  further away, so an unearned folder only ever wins *clearly*; filing into a
  further earned folder in that case would be wrong. The clear winner becomes a
  suggestion, which is how that folder earns its five acceptances. Conservative
  and self-bootstrapping, by design. **No change to `core/auto-file.php`.**
- **`auto-file` gets its own taxonomy**, registered by the suite and pointed at
  through the existing `vergeml_librarian_taxonomy` filter — the product seam
  `tests/librarian/test-librarian.php` line 58 already uses. Not a new
  test-only hook, and not a wipe of the box.
- **Every suite tidies its own seeded ids**, and asserts against those recorded
  ids rather than a shared title prefix. `verify.mjs` does not wipe between
  suites: a suite that leaks must fail rather than be cleaned up after.
- **`verify.mjs` treats a PHP suite reporting `0/0` as FAILED**, on the same
  reasoning the validate skill already applies to gate 1 — "a count of zero is a
  failure, not a pass". This requires capturing suite output, which the runner
  does not currently do.
- **Only `auto-file` is converted.** An audit of the other box suites found it
  is the only one that takes the live taxonomy: `tests/tree/utilities.php` and
  `tests/tree/quarantine.php` reference no taxonomy at all, and
  `tests/tree/smart.mjs` is a browser suite naming `media_category` deliberately
  because it is testing the real tree. Convert where results depend on it,
  which is one suite.
- Scope is all three red suites in one pass, so the battery is trustworthy end
  to end rather than in parts.

## Out of scope

- **Any change to `core/auto-file.php`, `core/quarantine.php` or
  `core/utilities.php`.** This is harness repair. If a suite goes green only by
  changing product code, that is a deviation — stop and say so.
- The `quarantine` 30/31 and `utilities` 23/24 failures are to be fixed as the
  scoping bugs described above. If either turns out to be real product
  behaviour once isolated, stop and report rather than adjusting the feature.
- `wp_delete_post()` vs `wp_delete_attachment()` semantics, MEDIA_TRASH, and
  anything else about how WordPress deletes — the suites already delete
  correctly.
- The six `/evolve` items from the 28-08 run (stale `docs/testing.md`,
  `docs/benchmarks.md` describing a dataset the box no longer holds, the
  missing-alt backlog that cannot drain, the AI page vs smart folder count
  disagreement, `verify.mjs`'s single-blueprint assumption, the wordpress.org
  submission doc being written as of 3.3.0). None are this plan.
- The AI service going live, and the DPA / sub-processor / retention work that
  the roadmap's standing rules attach to it. Decided as the next *product*
  step, but it is infrastructure and legal work, not this.

## Context

- **Files to read first:**
  - `tests/librarian/test-librarian.php` — the suite that was right all along.
    Line 58 registers its own taxonomy and hooks
    `vergeml_librarian_taxonomy`; line 22 onward shows the `$GLOBALS` counter
    pattern; the teardown near the end is the id-scoped check to copy.
  - `tests/tree/auto-file.php` — line 65 takes the live taxonomy, ~line 149
    seeds its terms, ~line 258 onward is the sweep the four failures come from.
  - `tests/tree/quarantine.php` — line 162 is the site-wide manifest
    assertion; the teardown is at the end.
  - `tests/tree/utilities.php` — teardown near the end, and the
    `VERGEML_FILE`-based source read added in `8a1f141`.
  - `tools/verify.mjs` — `runPhp()` around line 236 (the `stdio: 'inherit'`
    that must become a capture), and the result handling around line 322 where
    exit 2 is already treated as skipped.
  - `core/auto-file.php` — `vergeml_autofile_folders()` (~201),
    `vergeml_autofile_suggest()` (~241), `vergeml_autofile_sweep()` (~516).
    Read to understand, **not** to change.
  - `core/quarantine.php` — `vergeml_quarantine_manifest()` (~197). Same.
  - the internal validation gates — the gates this must pass.
- **Files that change:**
  - `tests/tree/auto-file.php` — own taxonomy, id-scoped teardown.
  - `tests/tree/quarantine.php` — manifest assertion scoped to its own seeds,
    id-scoped teardown.
  - `tests/tree/utilities.php` — id-scoped teardown.
  - `tools/verify.mjs` — capture PHP suite output; fail on `0/0`.
- **Files created:** none.
- **Prior art in this repo:**
  - Own-taxonomy isolation: `tests/librarian/test-librarian.php:58`.
  - `$GLOBALS` counters: `tests/librarian/test-librarian.php:22`,
    `tests/organize/test-organize.php:25`.
  - Id-scoped teardown: `tests/librarian/test-librarian.php`, as fixed in
    `d85042e`.
  - Exit-code handling with a stated convention: `tools/verify.mjs` ~line 322,
    where exit 2 became SKIPPED in `c3792b0`.
- **External docs:** none.

## Tasks

1. `tests/tree/auto-file.php`: register a hierarchical taxonomy of the suite's
   own and return it from a function hooked to `vergeml_librarian_taxonomy`,
   following `tests/librarian/test-librarian.php:58`. Replace the
   `vergeml_librarian_taxonomy()` call at line 65 so `$uf_tax` is that
   taxonomy. Everything downstream already reads `$uf_tax`.
2. `tests/tree/auto-file.php`: run it and confirm the four failures — "now a
   sweep files into it", "and the file landed in that folder", "the automatic
   file is in the moves log", "in a batch of its own" — are green with no
   change to `core/auto-file.php`. **If any of them still fails once the suite
   is isolated, stop: that is a product bug and this plan does not cover it.**
3. `tests/tree/auto-file.php`: teardown deletes the ids the suite recorded and
   asserts those are gone, not a `zz %` count. Delete the terms it registered
   too.
4. `tests/tree/quarantine.php`: scope the manifest assertion at line 162 to the
   suite's own seeds — assert that its one set-aside file appears in
   `$q_manifest['files']`, rather than that the site holds exactly one. Do not
   change `vergeml_quarantine_manifest()`.
5. `tests/tree/quarantine.php`: teardown asserts against recorded ids; also
   release anything it set aside, so it does not leave quarantined files for
   the next suite's manifest to count.
6. `tests/tree/utilities.php`: teardown asserts against recorded ids rather
   than the `zz util%` count.
7. `tools/verify.mjs`: change `runPhp()` from `stdio: 'inherit'` to a pipe that
   **echoes each chunk to stdout as it arrives** and accumulates it. Streaming
   must be preserved — buffering until exit makes a long suite look hung.
8. `tools/verify.mjs`: after a PHP suite exits 0, parse its `N/M passed` line.
   Treat a missing line, or `M === 0`, as FAILED with a message naming the
   cause ("reported no checks — see the `global` trap in the 28-08 run"). Leave
   browser suites alone; they exit non-zero on failure already.
9. `tools/verify.mjs`: prove the guard by making one suite report zero
   temporarily and confirming the runner fails it, then revert that.
10. Audit `tests/tree/utilities.php`, `tests/tree/quarantine.php` and
    `tests/tree/smart.mjs` for any other dependence on unrelated site state.
    Record what is found as a note for `/evolve`; convert only what is red.
11. Full validate run (all seven gates) plus the standing battery on the box,
    from a freshly seeded library (`wp eval-file /tmp/fixtures.php realistic`).

No task in this plan is irreversible. Task 9 temporarily breaks a suite and
must be reverted in the same task.

## Validation strategy

- **Gates 1–5 always.** No new REST endpoint, so there is no new query-count
  budget; the existing budgets in the internal validation gates must not
  move — `vergeml/v1/tree` is **7** in both environments as of `f716620`, and
  `health-report` is 3 with nothing to show and 5 with groups shown.
- **Gates 6 and 7 are formally skippable** — `tests/` and `tools/` are
  `export-ignore` in `.gitattributes`, so the clean archive Plugin Check reads
  does not change, and no option or schema is touched. **Run them anyway.** The
  whole subject of this plan is a harness that reported green while checking
  nothing; a run that skips gates to save time is the same mistake in a
  different place.
- The battery must be run **twice in a row without reseeding** between the two
  runs. One green run proves the suites pass; two consecutive green runs prove
  they no longer contaminate each other, which is the actual deliverable.
- Expected end state: `say` 32/32, `quarantine` 31/31, `utilities` 24/24,
  `auto-file` 23/23, and every currently-green suite unchanged.

## Risks

- **A suite registering a taxonomy affects only its own request**, but
  `vergeml_librarian_taxonomy` is a real product filter — adding it in a suite
  that runs through `wp eval-file` is per-request and cannot leak, which is why
  `test-librarian.php` is safe doing it. Confirm the same is true for the
  auto-file suite rather than assuming it.
- **`vergeml_autofile_folders()` uses `hide_empty => true`.** A suite taxonomy
  whose term counts have not been updated returns no folders at all, and the
  suite would fail for a reason that looks identical to the one being fixed.
  If folders come back empty, check term counting before suspecting anything
  else.
- **Capturing stdout in `verify.mjs` changes how output reaches the terminal.**
  Get this wrong and either nothing prints until a suite finishes, or output
  interleaves badly across the two streams. Echo per chunk.
- **The `0/0` guard must not fire on browser suites**, which do not print that
  line at all. Scope it to `suite.php === true`.
- PHP 7.4 floor: no `match`, no arrow functions, no `str_contains`, no nullsafe
  operator, in any suite file.
- Existing installs and packaging are untouched, so the usual options and
  safe-mode risks do not apply here — which is itself worth stating, because it
  is the reason gates 6 and 7 are being run out of discipline rather than need.
