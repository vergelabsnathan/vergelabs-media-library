# Benchmarks

Measured, not asserted. Method is at the bottom so anyone can re-run it and disagree.

## Two library sizes on real MariaDB — 2026-09-11

Measured on the Hetzner box (4 vCPU, 8 GB, MariaDB 11.8.6, PHP 8.5.4-FPM, WordPress 7.1),
on its second WordPress at `/var/www/ms` — a network with this plugin network-active and
nothing else. The main site with 28 plugins active was off limits this session; the same
commands with `VGML_WP_DIR=/var/www/wp` give the numbers with those plugins in the way.
Every path timed reads the database and PHP only: the fixture has no files on disk, no
index rows are described, and nothing reaches a model.

Fixture: `tools/scale.php up n=<size> folders=500` — attachments across 500 folders under one
parent, 40% with a 768-dim embedding and its 64-dim projection, 10% sharing a hash.
Runner: `VGML_WP_DIR=/var/www/ms VGML_SCALE_N=<size> bash tools/box-benchmark.sh` — `up`, then
three rounds of `scale.php time`, `box-counts-cost.php` and `box-grid-speed.php`, then `down`.
Each number is the middle of the three rounds; the object cache is flushed before every
measurement, so these are cold. Wall-clock is ms; queries are `$wpdb->num_queries`.

| path | 10,000 · 500 folders | 250,000 · 500 folders |
|---|---|---|
| `GET /vergeml/v1/tree`, cold | 112 ms · 13 q | 1,071 ms · 13 q |
| `GET /vergeml/v1/tree`, warm | 27 ms · 0 q | 30 ms · 0 q |
| unfiled count (`NOT EXISTS`) | 36 ms · 1 q | 672 ms · 1 q |
| recount one folder (per assignment) | 2 ms · 3 q | 50 ms · 3 q |
| media grid, one folder, 40/page (`box-grid-speed.php`) | 11 ms | 63 ms |
| media grid, one folder, 40/page, our filter | 14 ms · 10 q | 52 ms · 10 q |
| media grid, all files, 40/page (core) | 35 ms · 9 q | 527 ms · 9 q |
| `GET /wp/v2/media?per_page=40` | 539 ms · 13 q | 717 ms · 13 q |
| media grid search, one tag word, widened | 121 ms · 9 q | 7,008 ms · 9 q |
| media grid search, a word in every title, widened | 99 ms · 9 q | 5,873 ms · 9 q |
| Try a query, word pass, one tag word | 254 ms · 68 q | 10,594 ms · 68 q |
| Try a query, word pass, two common words | 278 ms · 69 q | 15,786 ms · 69 q |
| search by meaning (scan + score + rank) | 200 ms · 6 q · all 4,000 scanned | 8,129 ms · 6 q · **5,000 of 100,000 scanned** |
| smart counts, cold (`box-counts-cost.php`) | 86 ms · 7 q | 6.2 ms · 4 q (over the 50,000 threshold: nothing counted) |
| duplicates report | 32 ms · 7 q | 526 ms · 7 q |
| duplicate scan, one step of 25 | 163 ms · 59 q | 4,138 ms · 59 q |
| AI backlog `pending('unindexed')` | 52 ms · 1 q | 3,558 ms · 1 q |
| AI backlog `pending('missing-alt')` | 53 ms · 1 q | 1,663 ms · 1 q |
| dashboard facts (journey) | 2 ms · 2 q (cached) | 4,756 ms · 20 q |
| dashboard screen, full render | 105 ms · 15 q | 8,349 ms · 22 q |
| peak memory across the run | 67 MB | **187 MB** |

Fixture up in 20 s and 155 s; down in 20 s and 45 s. The site was 0 attachments and 0 folders
before and after each size.

**What holds.** The tree is 13 statements at both sizes — flat in queries, as claimed since
the 20,000-file measurement above — and its warm answer is 30 ms at a quarter of a million.
The smart counts reproduce the 2026-09-10 figure: over the threshold nothing is counted on a
page load and the cold cost is 6 ms (8.6 ms then, on the main site). A folder's page of the
grid is 52 ms with our filter; core's own grid of every file, 40 a page, is 527 ms at this size
because it counts all 250,000 rows for the pagination. `wp/v2/media` grows by a third, not
by 25×.

**What does not, at 250,000 (found, not done — Phase 3 lines):**

- **The dashboard takes 8 s to render.** `vergeml_journey_facts()` is 4.8 s and 20 queries
  cold; the screen adds 3.5 s more. At 10,000 the facts were served from cache in 2 ms, so the
  cache works — the first load after it expires does not.
- **Search by meaning sees 5% of the library.** `vergeml_meaning_convert_batch()` runs
  `WHERE … projection IS NULL ORDER BY described_at DESC LIMIT 250` before the scan; with
  nothing left to convert that is one full scan and filesort of a 100,000-row, 3 KB-a-row
  table, which spends the 2 s budget before the first projection chunk is read. The search
  then reads one chunk of 5,000 and stops, `partial`. The fix is a cheap "nothing to convert"
  answer — an index on `projection`, or a flag the convert tick keeps.
- **The widened word search is 6–7 s a keystroke.** Core's own grid search on the same
  library is not in this table; the widening joins the index table's captions, alt and tags
  into a `LIKE` with no index that can serve it. Try a query's word pass is 10–16 s for the
  same reason, plus 68 statements.
- **The duplicate scan step is 4 s and 187 MB.** `vergeml_health_scan_step()` computes
  `remaining` with `count( vergeml_health_backlog( $cursor ) )` — every remaining id loaded to
  be counted; 171,404 of them here. The AI backlog `pending()` calls load every id the same
  way (150,000 and 250,000), which is where the peak memory comes from; on a host with the
  default 128M `memory_limit` the AI screen and the Duplicates screen would fatal at this size.
- **The unfiled count is 672 ms** and is inside every cold tree answer; the tree's other
  12 statements are ~400 ms together.

## Head to head against FileBird

Same box, same data, same probe: 20,000 attachments, 200 folders (20 roots × 9 children),
16,000 assignments. FileBird 6.5.8 seeded into its own `wp_fbv` tables with the identical
structure, so both trees answer the same question. Ubuntu 24.04, 2 vCPU, MariaDB, PHP 8.3-FPM.

> **Provenance, 29-08-2026.** These figures were taken on the Kamatera box, and that
> 20,000-attachment dataset no longer exists: the library was swept and reseeded to a few
> dozen files while chasing test debris. The numbers stand as recorded, but they cannot be
> re-run today without rebuilding the seed. Nothing here has been re-measured to replace
> them, deliberately — a smaller library gives different wall-clock, and quietly swapping
> one in would turn a real comparison into an incomparable one.

|  | media page (list) | media page (grid) | `wp/v2/media?per_page=40` |
|---|---|---|---|
| core only | 5 q · 14 ms | 5 q · 13 ms | 82 ms |
| **+ ours** | **7 q · 13 ms** | **7 q · 14 ms** | **116 ms** |
| + FileBird | 13 q · 20 ms | 13 q · 18 ms | 92 ms |
| both | 14 q · 19 ms | 14 q · 21 ms | 123 ms |

### Where we win

**Ours adds 2 queries to the media library screen. FileBird adds 8.** Four times fewer, on the
page every media task starts from, and our page time is indistinguishable from core's — we cost
roughly nothing to have installed, while FileBird costs about 5ms and 8 queries on every load.

### Where we lose

**We add 34ms to `wp/v2/media`; FileBird adds 10.** They are three times better than us on the
call the grid actually waits for.

The reason is not flattering but it is understandable: our taxonomies are REST-visible (T0), so
core assembles term data for all 40 attachments in the response. FileBird's folders live in
custom tables and are not in `wp/v2/media` at all, so there is nothing for them to slow down.
Their advantage here is the direct consequence of the thing we criticise them for — the folders
are invisible to the API.

That does not excuse 34ms. This is an N+1 shape worth investigating: term data for a page of
attachments should be one primed cache, not per-item lookups. **Open item, not yet fixed.**

## The folder tree itself

`vergeml/v1/tree`, 20,000 attachments:

| folders | queries | handler | payload |
|---|---|---|---|
| 200 | 4 | 22 ms | 18.8 KB |
| 2,000 | 4 | 79 ms | 189 KB |

Flat query count across a tenfold increase in folders: the tree is O(1) in queries. Handler time
grows with payload serialisation, which is linear and expected.

FileBird has no equivalent endpoint to compare against — it builds its tree into the page rather
than serving it, which is why the comparison above is page-level.

## Method

- Probe: `tests/perf/mu-perf.php` as an mu-plugin. Separates handler time from WordPress boot for
  REST, and logs queries/ms/memory for admin pages to `wp-content/perf-admin.log`.
- REST figures: `node tests/perf/bench.mjs <base> <user:app-password>`.
- Page figures: four requests, last three averaged, first discarded as cold.
- Plugins toggled with `wp plugin activate/deactivate` between runs on the same install, so the
  data never changes underneath the comparison.

**Query count is the number that transfers.** It is a property of the algorithm, so it is
identical in Playground and on real MariaDB — verified. Wall-clock in Playground is meaningless:
PHP-wasm spends ~2.4s booting WordPress on every request, so core's own endpoints time the same
as ours there.

## The boxes, and why the counts agreeing matters

| | Kamatera | Hetzner CX33 |
|---|---|---|
| OS | Ubuntu 24.04 | Ubuntu 26.04 |
| PHP | 8.3-FPM | **8.5.4**-FPM |
| Database | MariaDB | MariaDB 11.8.6 |
| WordPress | — | 7.1 |
| Cores / RAM | 2 vCPU | 4 vCPU / 8 GB |

Measured on both, 29-08-2026, on libraries of different sizes and on two PHP versions two
releases apart:

| Endpoint | Kamatera | Hetzner |
|---|---|---|
| `vergeml/v1/tree` | 7 | 7 |
| `organize-step` | 3 | 3 |
| `organize-run` | 1 | 1 |
| `organize-cancel` | 2 | 2 |
| `librarian-schemes` | 1 | 1 |
| `librarian-batches` | 1 | 1 |

That agreement is the whole claim. Different hardware, different OS, different PHP, different
row counts, same integers — which is what "a property of the algorithm" means, and why a
disagreement between environments is read here as a bug rather than a hardware difference.

The Hetzner box also runs a PHP the plugin had never been executed on, and that immediately
earned its keep: a single duplicate scan wrote 545 `imagedestroy()` deprecation lines into
`debug.log`, because 8.5 deprecates a call that 7.4 still needs. Fixed in 6c2f9f4. A test box
that only ever runs the version you already support cannot tell you that.
