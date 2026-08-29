# Benchmarks

Measured, not asserted. Method is at the bottom so anyone can re-run it and disagree.

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
