# Feature: the media library stays usable at a quarter of a million pictures

## Feature Description

The five smart-folder counts shown beside the folder tree cost **10,038ms of
query time at 251,000 attachments** and 21ms at 1,000. They run on every admin
page load, cached only in a per-request `static`. This makes the counts cheap
to ask for and correct to show, at any library size.

## User Story

As an agency running a client library of hundreds of thousands of pictures
I want the media library to open at the speed it opens on a small site
So that the plugin is something I can install for a client rather than something
I have to uninstall.

## Problem Statement

Measured on the box, 2026-09-10, against a 251,000-attachment / 775,587-postmeta
library (`tools/box-scale-fixture.php`):

| branch | 1,000 | 251,000 | plan at scale |
|---|---|---|---|
| `no-alt` | 5.5ms | **4,341ms** | `posts` ALL, NO KEY |
| `large` | 3.1ms | **2,627ms** | `postmeta` ALL, NO KEY, 735k rows |
| `unused` | 6.8ms | **2,469ms** | `postmeta` ALL, NO KEY, 735k rows |
| `unattached` | 1.0ms | **434ms** | `posts` ALL, NO KEY |
| `recent` | 1.1ms | **158ms** | range, but reads 114,714 rows |
| **total** | **21ms** | **10,038ms** | |

Three separate faults:

1. **No cache outlives the request.** `core/smart-folders.php:205` holds a
   `static $cache` per blog. Every page view recomputes.
2. **Two branches abandon their index at scale.** `unused` and `large` use
   `key=meta_key` at 1,000 rows and full-scan 735,532 postmeta rows at 251,000.
   `large` also wraps the value in `CAST( meta_value AS UNSIGNED )`, which no
   index can serve at any size.
3. **`recent` is not sargable.** `YEAR(post_date) = 2026 AND MONTH(post_date) = 9`
   reads 114,714 rows in 158ms. The identical answer as a half-open date range
   reads **6,218 rows in 5.8ms — 27x faster**.

At a million pictures this is roughly forty seconds of query time per admin page
on this hardware. Better hardware moves the cliff; it does not remove it,
because the plans are O(library).

## Solution Statement

Extend what the plugin already has rather than build beside it:

- the counts are cached in the object cache **and** a transient, so they survive
  a page load on a site with no persistent object cache;
- `recent` becomes a half-open date range;
- `large` stops casting: the filesize meta is written zero-padded so the string
  comparison the index can serve *is* the numeric comparison;
- above a threshold, a count nobody has computed reports `null` — which the tree
  already renders as "not looked" rather than "none";
- the tree's own totals come from `term_taxonomy.count`, which core maintains.

## Out of Scope / Non-Goals

- **Not changing what any number means.** Every count must return the same value
  it returns today. This is the one hard constraint.
- **Not touching the describe pipeline, filing, or the folder tree's contents.**
- **Not adding a database index** via `dbDelta` on core tables. Indexing
  `wp_postmeta` is a decision with consequences for the whole site, not ours.
- **Not fixing `unattached` or `no-alt` at the query level.** They full-scan
  `posts` and there is no index to give them without touching core tables; the
  cache and the threshold are their answer.
- Not the modal/sidebar slowness Nathan reported separately. Different fault.

## Feature Metadata

**Feature Type**: Bug Fix (performance)
**Estimated Complexity**: Medium
**Primary Systems Affected**: `core/smart-folders.php`, `core/rest-tree.php`
**Dependencies**: none new

## Related Work

**Measured by**: `tools/box-scale-fixture.php` (this session), the perf probe pattern
**Back-references**: `tests/perf/bench.mjs` — why the gate asserts queries, not milliseconds

---

## CONTEXT REFERENCES

### Relevant Codebase Files — READ THESE FIRST

- `core/smart-folders.php:182-300` — `vergeml_smart_counts()`, the `static $cache`,
  the five-branch UNION and its positional argument list. **The whole fault.**
- `core/smart-folders.php:361-420` — `vergeml_smart_scan_state()` and
  `vergeml_smart_scan_step()`: the resumable chunked scan, option-backed. This is
  the existing mechanism for "expensive question, answered in the background".
- `core/rest-tree.php:425-460` — `vergeml_count_unassigned()`: the plugin's
  existing cache pattern, `wp_cache_get( 'vergeml_unassigned_' . $taxonomy, 'vergeml' )`.
  **Mirror this naming.**
- `core/rest-tree.php:340-415` — the two calls into `vergeml_smart_counts()`, and
  why the per-request static exists (the endpoint's query budget is six).
- `tests/perf/bench.mjs:1-18` — the argument for asserting query counts rather
  than wall-clock.
- `tools/box-scale-fixture.php` — builds and removes the 251k library.

### New Files to Create

- `tests/perf/counts.mjs` — the performance gate.

### Patterns to Follow

**Caching**, from `core/rest-tree.php:437`:

```php
$cached = wp_cache_get( 'vergeml_unassigned_' . $taxonomy, 'vergeml' );
if ( false !== $cached )
    return (int) $cached;
```

**"Not looked" vs "none"**, from `core/smart-folders.php:184`: a scan-backed
count reports `null` when the scan has never run, and the tree draws that
differently from `0`. The threshold behaviour reuses this exactly — no new state.

**Query comments**: every direct query carries its `phpcs:ignore` with a reason.

---

## IMPLEMENTATION PLAN

### Phase 1 · Make the numbers cheap to keep

Cache `vergeml_smart_counts()` beyond the request, and invalidate it when the
library changes.

### Phase 2 · Make the queries cheap to run

**Depends on:** Phase 1 (so a regression shows up as a cache miss, not a page hang)

`recent` as a range; `large` without a CAST; the tree's totals from
`term_taxonomy.count`.

### Phase 3 · Never pay the worst case on a page load

**Depends on:** Phase 1

Above a threshold, an uncomputed count is `null` and the scan fills it in.

### Phase 4 · A gate that keeps it

**Independent of:** Phase 3 (can be written against Phases 1-2 alone)

---

## STEP-BY-STEP TASKS

### UPDATE `core/smart-folders.php` — cache the five counts beyond the request

- **IMPLEMENT**: after the `static $cache` check, read
  `wp_cache_get( 'vergeml_smart_counts', 'vergeml' )`, then
  `get_transient( 'vergeml_smart_counts' )`. On a miss, compute, then write both
  (`wp_cache_set` and `set_transient` with a 12-hour TTL as a backstop). `$fresh`
  skips all three reads and rewrites all three.
- **PATTERN**: `core/rest-tree.php:437`.
- **GOTCHA**: keyed per blog — the existing static is `$cache[ $blog ]` for a
  reason recorded in its comment. Transient names must carry the blog id on
  multisite, or a network job hands site B site A's numbers.
- **GOTCHA**: a count of `null` (scan never ran) must round-trip through the
  transient as `null`, not `0`. Serialise the array as-is; do not `(int)` it on
  the way out.
- **VALIDATE**: `node tools/verify.mjs copy journey guide`
- **SATISFIES**: AC #1, AC #4

### ADD `core/smart-folders.php` — invalidation on the events that move the numbers

- **IMPLEMENT**: `vergeml_smart_counts_forget()` deleting both cache entries,
  hooked to `add_attachment`, `delete_attachment`, `attachment_updated`, and to
  `updated_post_meta`/`added_post_meta`/`deleted_post_meta` filtered to the three
  meta keys that matter (`_wp_attachment_image_alt`, `VERGEML_META_FILESIZE`,
  `VERGEML_META_UNUSED`).
- **GOTCHA**: an import writing ten thousand attachments would clear the cache
  ten thousand times. Set a static "already forgotten this request" flag so the
  work happens once per request.
- **VALIDATE**: `node tests/tree/t0-endpoints.js`
- **SATISFIES**: AC #1

### UPDATE `core/smart-folders.php` — `recent` as a half-open date range

- **IMPLEMENT**: replace `YEAR( p.post_date ) = %d AND MONTH( p.post_date ) = %d`
  with `p.post_date >= %s AND p.post_date < %s`, computed from
  `current_time( 'Y' )`/`current_time( 'n' )` with December rolling to January of
  the next year.
- **GOTCHA**: `post_date` is site-local and `post_date_gmt` is not. The current
  query uses `post_date` with `current_time()`, so the range must too, or the
  count changes at month boundaries — and **the number must not change**.
- **VALIDATE**: measured on the box: reads 6,218 rows rather than 114,714, and
  returns the identical count.
- **SATISFIES**: AC #2, AC #3

### UPDATE `core/smart-folders.php` — `large` without a CAST

- **IMPLEMENT**: write the filesize meta zero-padded to 12 characters, and
  compare as a string: `f.meta_value > %s` with the threshold padded the same
  way. 12 digits covers 999GB.
- **IMPLEMENT**: a migration in the existing chunked scan that pads rows written
  before this change; until a row is padded it must still be counted correctly,
  so the query is `LENGTH( f.meta_value ) = 12 AND f.meta_value > %s` OR the
  unpadded comparison, until the scan reports the migration finished.
- **GOTCHA**: this is the one task that changes stored data. It must be
  idempotent and it must never make a large file read as small — a half-migrated
  library still has to give the right number.
- **VALIDATE**: the count at 251k is identical before and after.
- **SATISFIES**: AC #2, AC #3

### UPDATE `core/rest-tree.php` — folder totals from `term_taxonomy.count`

- **IMPLEMENT**: read `count` from the `get_terms()` result the tree already
  fetches instead of grouping over `term_relationships`.
- **GOTCHA**: core's `count` is per-term, not descendant-inclusive, and the tree
  shows descendant-inclusive totals (`js/vergeml-tree-view.js:1043` says so).
  Roll the tree up in PHP from core's numbers rather than asking the database.
- **GOTCHA**: core's count excludes attachments whose `post_status` is not
  `inherit`. Confirm on the box that the totals are unchanged before keeping it.
- **VALIDATE**: `node tests/tree/t0-endpoints.js` — 21/21
- **SATISFIES**: AC #2, AC #5

### ADD `core/smart-folders.php` — a threshold, and `null` above it

- **IMPLEMENT**: `vergeml_smart_counts_threshold()` (filterable,
  `vergeml_smart_counts_threshold`, default 50,000 attachments). Above it, a cold
  cache returns the array with `null` for the four expensive branches rather than
  computing them, and schedules the scan to fill them in.
- **GOTCHA**: `null` already means "not looked" throughout this file and the tree
  renders it. Do not invent a second spelling.
- **VALIDATE**: at 251k with a cold cache, the page issues no count query.
- **SATISFIES**: AC #4

### CREATE `tests/perf/counts.mjs` — the gate

- **IMPLEMENT**: against a box, assert the **query count** for
  `vergeml_smart_counts()` cold and warm, and report milliseconds as a canary
  with a stated band. Red on a query count that moves; loud but not red on time.
- **PATTERN**: `tests/perf/bench.mjs` for the argument and the shape;
  `tools/filing-baseline-check.mjs` for "assert the placement, report the score".
- **VALIDATE**: `node tests/perf/counts.mjs http://46.225.66.194`
- **SATISFIES**: AC #6

---

## TESTING STRATEGY

**The measurement is the test.** Re-run the probe at 251,000 rows and compare
against the table in Problem Statement. A fix that does not move those numbers
did not happen.

**Correctness before speed**: for each of the five counts, the value at 251k
before and after must be **identical**. Record both.

**Edge cases**: an empty library; a library where the scan has never run (all
scan-backed counts `null`); the last day of a month for `recent`; a filesize meta
row written before the padding migration; multisite, where the numbers must not
leak between blogs.

---

## VALIDATION COMMANDS

### Level 1 · Syntax
`node tools/deploy.mjs --check` (php -l over every file)

### Level 2 · The plugin's own suites
```
node tools/verify.mjs copy journey guide
node tests/tree/t0-endpoints.js
```

### Level 3 · The box, at scale
```
ssh -i ~/.ssh/hetzner_vgml root@46.225.66.194 'bash -s' < tools/box-perf-counts.sh
```

### Level 4 · The regression gate
`node tools/filing-baseline-check.mjs` — this work must not move a single placement.

---

## ACCEPTANCE CRITERIA

- [ ] **AC #1** A warm page load issues **zero** count queries at any library size.
- [ ] **AC #2** At 251,000 attachments a cold computation is **under 1,000ms**, from 10,038ms.
- [ ] **AC #3** Every one of the five counts returns the **same number** as before, at 1,000 and at 251,000.
- [ ] **AC #4** Above the threshold a cold cache computes nothing on the page load; the counts read `null` and the tree shows "not looked".
- [ ] **AC #5** `node tests/tree/t0-endpoints.js` — 21/21.
- [ ] **AC #6** `tests/perf/counts.mjs` fails when the query count moves, and is mutation-checked.
- [ ] **AC #7** `node tools/filing-baseline-check.mjs` — 3 of 3, no placement moved.

---

## OPEN QUESTIONS / ASSUMPTIONS

- **Decided 2026-09-10 (Nathan)**: transient behind `wp_cache`; the row with no
  number above the threshold; zero-padded filesize; the gate asserts queries hard
  and reports milliseconds as a canary.
- **Assumed** — the 50,000 threshold is a starting number, filterable, and should
  be revisited once there is a real agency library to watch.
- **Assumed** — `updated_post_meta` filtered to three keys is enough
  invalidation. A plugin writing alt text directly with `$wpdb` would bypass it;
  the 12-hour TTL is the backstop for that.

## NOTES

**Why not simply index `wp_postmeta`.** It is the fix a DBA would reach for and
it is not ours to make: `wp_postmeta` belongs to the whole site, an index on
`meta_key(191), meta_value(20)` costs write throughput on every post save, and a
media plugin that slows down publishing has traded one complaint for a worse one.
The cache plus the threshold gets the same result inside our own boundary.

**Why the counts exist at all** is worth asking during implementation. Four of
the five are answers to "what needs attention", and at agency scale the useful
form of that question is probably not a number in a sidebar. If the threshold
work lands well, the follow-up is whether these become a report rather than an
ornament.

## AMENDMENTS

- (none yet)
