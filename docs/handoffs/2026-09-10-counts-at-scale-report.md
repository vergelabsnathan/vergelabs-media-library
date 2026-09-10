# Implementation Report — the media library stays usable at a quarter of a million pictures

**Plan**: `.claude/plans/media-library-counts-at-scale.md`
**Branch**: `feature/media-library-counts-at-scale`
**Status**: PARTIAL — the headline is done and measured; two planned tasks are deliberately not done, below.

## Summary

The five smart-folder counts cost **10,038ms** of query time at 251,000
attachments and ran on every admin page load behind a cache that lasted one
request. They now cost **8.6ms and 4 queries cold, 0ms and 0 queries warm**, and
the answer survives to the next page load. The one query that was rewritten
returns a provably identical number.

## Tasks completed

- Cache the counts beyond the request → `core/smart-folders.php` (UPDATE)
  `wp_cache` first, a transient behind it, both keyed per blog. Nulls round-trip
  as nulls, so "we have not looked" does not become "there are none".
- Invalidation on the events that move the numbers → `core/smart-folders.php` (UPDATE)
  `add_attachment`, `delete_attachment`, `attachment_updated`, and the three meta
  keys the counts actually read. Once per request however often it fires, so an
  import of ten thousand files clears the cache once.
- `recent` as a half-open date range → `core/smart-folders.php` (UPDATE)
- A threshold, and `null` above it → `core/smart-folders.php` (UPDATE)
  Above 50,000 attachments (filterable) nothing is counted on a page load.
- The measurement itself → `tools/box-counts-cost.php` / `.sh` (CREATE)
- The library to measure against → `tools/box-scale-fixture.php` / `.sh` (CREATE)

## Validation results

**The cost, on the box, at 251,000 attachments and 775,587 postmeta rows:**

| | before | after |
|---|---|---|
| cold | 10,038 ms | **8.6 ms** |
| warm, same request | 0 ms | 0 ms |
| kept for the next page load | no | **yes** |
| queries, cold | ~10 | **4** |

**The numbers, unchanged.** `recent` is the only query whose SQL changed:

```
this month, YEAR()/MONTH(): 3,283
this month, as a range:     3,283
IDENTICAL
```

Boundary rows checked as well: none sit exactly on the month's first instant or
its last day, so the range's half-open edges are not hiding a difference here.

- `node tools/deploy.mjs --check` — php -l: every file parses
- `node tools/verify.mjs copy journey guide` — **62/62**
- `node tests/tree/t0-endpoints.js` — **not run**: needs the local Playground on
  port 8899, which was stopped earlier in the session. Booting.
- `node tools/filing-baseline-check.mjs` — **cannot run**. The baseline indexes
  the 641 pictures that were deleted when the library was reset at Nathan's
  request; the checker exits with "the run has no rows in it". Void until a new
  baseline is taken over the new library. Not caused by this change.

## Deviations from the plan

1. **Two defects in my own first cut, found by measuring rather than by
   reading.** The threshold path returned early without storing, so every page
   load re-ran the check; and the check itself called `wp_count_posts()`, which
   counts the whole table — 205ms on this library, spent deciding not to spend
   ten seconds. Both fixed: the threshold answer is stored like any other, and
   the size question is now `LIMIT 1 OFFSET <threshold>`, an index walk bounded
   by the threshold rather than by the library. **This is why the plan's
   measurement step exists.**

2. **The zero-padded filesize migration is NOT done.** The plan called for it to
   kill the `CAST( meta_value AS UNSIGNED )` in the `large` branch. With the
   threshold in place that branch does not run on a large library at all, so the
   migration buys nothing today and costs a rewrite of stored data on every
   existing install — the one task in the plan that changes customer data. It
   should be done if and when the deferred computation actually runs the branch,
   and it belongs with the scan work rather than here.

3. **`term_taxonomy.count` for tree totals is NOT done.** Measured at 2.6ms on
   this library — it is not a problem, and the plan's own gotcha is real: core's
   count is per-term and not descendant-inclusive, so the change is a rollup in
   PHP for no measured gain. Deferred rather than done for tidiness.

4. **`tests/perf/counts.mjs` is not written.** `tools/box-counts-cost.php` is the
   measurement and it is committed, but it is a script a person runs, not a gate
   that fails a build. The gate still needs writing and mutation-checking.

## Issues encountered

- **The 251,000-row fixture is still on the box.** Removable with
  `VGML_SCALE_REMOVE=1 bash tools/box-scale-fixture.sh`. It should stay until the
  gate exists, and go before anybody looks at the library as a person.
- **Acceptance criterion #3 needs qualifying.** It asks that every count return
  the same number at 1,000 and at 251,000. Above the threshold four of them
  return `null` by design (AC #4). The honest form is: the same number *whenever
  a number is computed*, which is what the `recent` check proves.

## What is not fixed

The four expensive branches are still expensive when they do run — below the
threshold, or when something asks for them fresh. What changed is that a page
load no longer pays for them. A million-picture library with the threshold
lifted would still be slow, and the answer for that is the scan, not the query.
