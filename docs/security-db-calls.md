# Every database call, classified

**Generated. Do not edit.** `node tools/db-calls.mjs` writes this file;
`node tools/db-calls.mjs --check` fails when the code has moved and this file has
not. Phase 5.3 of `plans/four-yesses.md`.

One row per call, not per file. A call is a **finding** unless something in the
code proves it is not the caller's text — the burden is that way round on purpose,
because twelve of these were read and annotated safe on 2026-09-10 and that is
exactly how a real one gets waved through.

A `prepare()`d call is read too. `$wpdb->prepare( "… WHERE x = $id" )` is prepared
and still interpolated, which is the `UnescapedDBParameter` shape and the one that
looks safest in a diff, so what is examined is `prepare()`'s own format string.

## The count

| | count |
|---|---|
| lines mentioning `$wpdb->` | 518 |
| `$wpdb->prepare()` calls | 133 |
| **calls that reach the server** | **215** |
| · prepared | 112 |
| · a literal, nothing interpolated | 1 |
| · only a `$wpdb` table name | 59 |
| · only integers it cast itself | 8 |
| · built and escaped by `$wpdb` (insert/update/delete) | 21 |
| · read by hand, with the reason | 10 |
| · **findings** | **4** |

Read over 68 shipped PHP files.

## Findings

Each row holds an expression the tool could not prove. That is not the same as a
vulnerability — it is the list a person must read, and nothing else needs reading.

| where | method | could not prove | SQL |
|---|---|---|---|
| core/auto-file.php:269 | `get_row` | `$words['select']`, `$words['join']` | `"SELECT i.attachment_id, i.embedding, i.kind, i.filing, i.prompt_hash, i.model_version, {$words['select']} FROM {$wpdb->vergeml_ai_index} i {$words['join']} WHE` |
| core/folder-talk.php:1121 | `get_results` | `$words['select']`, `$product['select']`, `$words['join']`, `$product['join']` | `"SELECT i.attachment_id, i.embedding, i.kind, i.filing, i.caption, i.tags, i.prompt_hash, i.model_version, pm.meta_value AS placed_by, {$words['select']}, {$pro` |
| core/guide.php:1887 | `get_results` | `$words['select']`, `$product['select']`, `$words['join']`, `$product['join']` | `"SELECT i.attachment_id, i.embedding, i.tags, pm.meta_value AS placed_by, {$words['select']}, {$product['select']} FROM {$wpdb->vergeml_ai_index} i LEFT JOIN {$` |
| core/guide.php:3160 | `get_results` | `$words['select']`, `$words['join']`, `implode( ',', $chunk )` | `"SELECT i.attachment_id, i.embedding, i.tags, {$words['select']} FROM {$wpdb->vergeml_ai_index} i {$words['join']} WHERE i.attachment_id IN (" . implode( ',', $` |

## Read by hand

The tool proves 201 of 215 calls on its own. These 10 assemble their
SQL from fragments across a nested loop or a function boundary, which is further
than a static reader should be trusted to follow, so each was read and the reason
written down. Each is keyed by a hash of its own SQL, not by its line, so editing
one of these queries expires its review and turns the suite red.

| where | fingerprint | method | why it is safe |
|---|---|---|---|
| core/ai-index.php:259 | `77b42d1645` | `get_results` | $in is implode of an array_chunk of $ids, and $ids is array_map( 'intval', (array) $ids ) on the function's first line -- integers, every one of them |
| core/ai-screen.php:136 | `56dead9d32` | `get_row` | every $sums[] element is its own $wpdb->prepare() fragment, and the column alias after AS comes from a literal list of eight field names in the foreach header above it |
| core/search-try.php:80 | `36af7892eb` | `get_var` | each $any[] is $wpdb->prepare( "{$column} LIKE %s", $like ); $column comes from vergeml_search_try_fields(), a literal array of seven, and the search term goes through esc_like() and a placeholder |
| core/search-try.php:84 | `3e7ba748c4` | `get_var` | each $where_all[] is "( " . implode( " OR ", $any ) . " )" over the prepared fragments above, and {$from} interpolates two $wpdb table names and nothing else |
| core/search-try.php:85 | `6a95da9eb6` | `get_var` | the same as the row above, restricted to the three columns WordPress itself searches |
| core/search-try.php:87 | `db4c1ee30a` | `get_results` | the rows query behind the same two assembled lists; LIMIT 30 is a literal |
| core/seo-context.php:306 | `1f45eb3c01` | `get_col` | vergeml_seo_gap_sql() interpolates its $select argument, and both of its two call sites pass a hardcoded literal; the $keys and $stars lists inside it are array_map( 'esc_sql', ... ) |
| core/seo-context.php:332 | `ace3ecc40b` | `get_var` | the same function, the other call site, also a hardcoded literal |
| core/smart-folders.php:370 | `77f8356ddc` | `get_results` | the interpolated {$exclude} and each appended $branch['sql'] come from two filters, not from a request; both of our own implementations build from $wpdb table names and constants, and every value is bound through prepare(). An extension point that accepts SQL -- see "Two filters that accept SQL" in the doc |
| core/smart-folders.php:390 | `043d77dfa1` | `get_results` | the same $core_sql, read a second time for the extended flag |

### Two filters that accept SQL

`core/smart-folders.php` builds the counts panel from two filters:

- `vergeml_smart_count_exclude` — a string appended to each `WHERE`.
- `vergeml_smart_count_branches` — each branch contributing its own `sql` and `args`.

Neither carries request input and neither is reachable by a visitor: a filter can
only be added by code already running on the site, and our own two implementations
(`core/quarantine.php`, `core/ai-folders.php`) build from `$wpdb` table names and
constants with every value bound through `prepare()`.

It is still an extension point that accepts SQL, and a third-party plugin could pass
request text into it without realising where it lands. The contract is not documented
anywhere a plugin author would read it. **Found, not done:** say so in the docblock,
and prefer a branch that passes `args` over one that interpolates.

## Every call

Sorted by file. The proof column says why the tool is satisfied; where it names a
variable, every assignment to that variable inside the enclosing function was
checked, and one unproven assignment is enough to make the whole call a finding.


### core/ai-index.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 55 | — | `query` | only a `$wpdb` table name | $wpdb->prefix — a $wpdb table name |
| 187 | — | `get_var` | prepared | — |
| 259 | — | `get_results` | read by hand — see the reason | $wpdb->vergeml_ai_index — a $wpdb table name |
| 286 | — | `get_row` | prepared | $wpdb->vergeml_ai_index — a $wpdb table name |
| 392 | — | `update` | built and escaped by `$wpdb` | values escaped by $wpdb; table: vergeml_index_table() returns only a $wpdb table name |
| 402 | — | `insert` | built and escaped by `$wpdb` | values escaped by $wpdb; table: vergeml_index_table() returns only a $wpdb table name |
| 415 | — | `delete` | built and escaped by `$wpdb` | values escaped by $wpdb; table: vergeml_index_table() returns only a $wpdb table name |
| 666 | — | `get_row` | only a `$wpdb` table name | $wpdb->vergeml_ai_index — a $wpdb table name |
| 754 | — | `get_col` | prepared | $wpdb->vergeml_ai_index — a $wpdb table name |
| 800 | — | `get_col` | prepared | $wpdb->postmeta — a $wpdb table name; $wpdb->vergeml_ai_index — a $wpdb table name |

### core/ai-screen.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 117 | — | `get_var` | only a `$wpdb` table name | $wpdb->posts — a $wpdb table name |
| 119 | — | `get_var` | only a `$wpdb` table name | $wpdb->posts — a $wpdb table name; $wpdb->postmeta — a $wpdb table name |
| 122 | — | `get_var` | only a `$wpdb` table name | $t — $t is only ever text holding only text holding only text holding only both arms of a ternary: a $wpdb table name / a string literal in our own source |
| 123 | — | `get_var` | only a `$wpdb` table name | $t — $t is only ever text holding only text holding only text holding only both arms of a ternary: a $wpdb table name / a string literal in our own source; $wpdb->postmeta — a $wpdb table name |
| 124 | — | `get_var` | only a `$wpdb` table name | $t — $t is only ever text holding only text holding only text holding only both arms of a ternary: a $wpdb table name / a string literal in our own source; $wpdb->posts — a $wpdb table name |
| 125 | — | `get_results` | only a `$wpdb` table name | $t — $t is only ever text holding only text holding only text holding only both arms of a ternary: a $wpdb table name / a string literal in our own source |
| 128 | — | `get_row` | only a `$wpdb` table name | $t — $t is only ever text holding only text holding only text holding only both arms of a ternary: a $wpdb table name / a string literal in our own source |
| 136 | — | `get_row` | read by hand — see the reason | $t — $t is only ever text holding only text holding only text holding only both arms of a ternary: a $wpdb table name / a string literal in our own source |
| 142 | — | `get_results` | only a `$wpdb` table name | $t — $t is only ever text holding only text holding only text holding only both arms of a ternary: a $wpdb table name / a string literal in our own source |
| 143 | — | `get_var` | only a `$wpdb` table name | $t — $t is only ever text holding only text holding only text holding only both arms of a ternary: a $wpdb table name / a string literal in our own source |

### core/ai.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 971 | — | `get_var` | prepared | $wpdb->postmeta — a $wpdb table name; $wpdb->vergeml_ai_index — a $wpdb table name |
| 1314 | — | `get_col` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->postmeta — a $wpdb table name |
| 1330 | — | `get_col` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->vergeml_ai_index — a $wpdb table name |
| 1373 | — | `get_var` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->postmeta — a $wpdb table name |
| 1382 | — | `get_var` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->vergeml_ai_index — a $wpdb table name |
| 1784 | — | `get_col` | prepared | vergeml_ai_alt_pending_from() — vergeml_ai_alt_pending_from() returns only text holding only a $wpdb table name |
| 1797 | — | `get_var` | only a `$wpdb` table name | vergeml_ai_alt_pending_from() — vergeml_ai_alt_pending_from() returns only text holding only a $wpdb table name |
| 2046 | — | `get_var` | prepared | $wpdb->posts — a $wpdb table name |
| 2047 | — | `get_var` | only a `$wpdb` table name | $wpdb->vergeml_ai_index — a $wpdb table name |

### core/auto-file.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 100 | — | `get_col` | prepared | $wpdb->term_relationships — a $wpdb table name; $wpdb->term_taxonomy — a $wpdb table name |
| 269 | — | `get_row` | **a finding** | could not prove `$words['select']`, `$words['join']` |
| 484 | — | `get_var` | prepared | $wpdb->vergeml_librarian_batches — a $wpdb table name |
| 511 | — | `insert` | built and escaped by `$wpdb` | values escaped by $wpdb; table: vergeml_librarian_batches_table() returns only a $wpdb table name |
| 561 | — | `get_col` | prepared | $wpdb->posts — a $wpdb table name; $table — $table is only ever vergeml_index_table() returns only a $wpdb table name; $wpdb->term_relationships — a $wpdb table name; $tt — $tt is only ever vergeml_autofile_tt_ids() returns only a string literal in our own source / implode of array_map( 'intval', ... ) |
| 753 | — | `get_var` | only integers it cast itself | $wpdb->posts — a $wpdb table name; $described — $described is only ever both arms of a ternary: text holding only $table is only ever vergeml_index_table() returns only a $wpdb table name / a string literal in our own source; $wpdb->term_relationships — a $wpdb table name; $tt — $tt is only ever vergeml_autofile_tt_ids() returns only a string literal in our own source / implode of array_map( 'intval', ... ) |

### core/brief.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 197 | — | `get_results` | only a `$wpdb` table name | $wpdb->vergeml_ai_index — a $wpdb table name |
| 201 | — | `get_col` | only a `$wpdb` table name | $wpdb->vergeml_ai_index — a $wpdb table name |
| 381 | — | `get_var` | prepared | $wpdb->vergeml_ai_index — a $wpdb table name; $wpdb->term_relationships — a $wpdb table name |
| 398 | — | `get_col` | prepared | $wpdb->vergeml_ai_index — a $wpdb table name |

### core/filing.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 517 | — | `get_col` | only a `$wpdb` table name | $wpdb->vergeml_ai_index — a $wpdb table name |
| 1147 | — | `get_results` | prepared | $wpdb->term_relationships — a $wpdb table name; $wpdb->term_taxonomy — a $wpdb table name; $wpdb->vergeml_ai_index — a $wpdb table name; $wpdb->termmeta — a $wpdb table name; $in — $in is only ever implode of $term_ids is only ever array_values() over 1 proven argument(s) / implode of array_keys() over 1 proven argument(s) |
| 1178 | — | `get_results` | prepared | $wpdb->term_relationships — a $wpdb table name; $wpdb->term_taxonomy — a $wpdb table name; $wpdb->vergeml_ai_index — a $wpdb table name; $in — $in is only ever implode of $term_ids is only ever array_values() over 1 proven argument(s) / implode of array_keys() over 1 proven argument(s) |

### core/folder-talk.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 183 | — | `get_var` | only a `$wpdb` table name | $wpdb->vergeml_ai_index — a $wpdb table name |
| 193 | — | `get_results` | prepared | $wpdb->vergeml_ai_index — a $wpdb table name |
| 238 | — | `get_var` | only a `$wpdb` table name | $wpdb->vergeml_ai_index — a $wpdb table name |
| 239 | — | `get_var` | only a `$wpdb` table name | $wpdb->vergeml_ai_index — a $wpdb table name |
| 274 | — | `get_var` | only a `$wpdb` table name | $wpdb->vergeml_ai_index — a $wpdb table name |
| 283 | — | `get_results` | prepared | $wpdb->vergeml_ai_index — a $wpdb table name |
| 949 | — | `get_var` | only a `$wpdb` table name | $wpdb->vergeml_ai_index — a $wpdb table name |
| 1121 | — | `get_results` | **a finding** | could not prove `$words['select']`, `$product['select']`, `$words['join']`, `$product['join']` |
| 2247 | — | `get_results` | only integers it cast itself | $wpdb->vergeml_ai_index — a $wpdb table name; implode( ',', array_map( 'intval', $chunk ) ) — implode of array_map( 'intval', ... ) |
| 2525 | — | `get_var` | prepared | $wpdb->vergeml_ai_index — a $wpdb table name; $wpdb->term_relationships — a $wpdb table name; $wpdb->term_taxonomy — a $wpdb table name |

### core/guide.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 270 | — | `get_col` | only a `$wpdb` table name | $wpdb->postmeta — a $wpdb table name; $wpdb->posts — a $wpdb table name |
| 366 | — | `get_var` | only a `$wpdb` table name | $wpdb->posts — a $wpdb table name |
| 405 | — | `get_row` | prepared | $wpdb->term_relationships — a $wpdb table name; $wpdb->term_taxonomy — a $wpdb table name; $t — $t is only ever a $wpdb table name |
| 415 | — | `get_row` | only a `$wpdb` table name | $t — $t is only ever a $wpdb table name |
| 521 | — | `get_var` | only a `$wpdb` table name | $wpdb->vergeml_ai_index — a $wpdb table name |
| 720 | — | `get_var` | only a `$wpdb` table name | $t — $t is only ever a $wpdb table name |
| 721 | — | `get_var` | only a `$wpdb` table name | $t — $t is only ever a $wpdb table name |
| 722 | — | `get_results` | only a `$wpdb` table name | $t — $t is only ever a $wpdb table name |
| 724 | — | `get_var` | only a `$wpdb` table name | $t — $t is only ever a $wpdb table name |
| 725 | — | `get_var` | only a `$wpdb` table name | $t — $t is only ever a $wpdb table name |
| 726 | — | `get_var` | only a `$wpdb` table name | $t — $t is only ever a $wpdb table name |
| 727 | — | `get_results` | prepared | $t — $t is only ever a $wpdb table name |
| 768 | — | `get_var` | prepared | $t — $t is only ever a $wpdb table name; $wpdb->term_relationships — a $wpdb table name; $wpdb->term_taxonomy — a $wpdb table name |
| 1887 | — | `get_results` | **a finding** | could not prove `$words['select']`, `$product['select']`, `$words['join']`, `$product['join']` |
| 2726 | — | `get_results` | only integers it cast itself | $select — $select is only ever a string literal in our own source / appended a string literal in our own source; $t — $t is only ever a $wpdb table name; $join — $join is only ever a string literal in our own source / appended text holding only a $wpdb table name / appended a $wpdb->prepare() fragment whose format string holds only a $wpdb table name; $where — $where is only ever a string literal in our own source / appended a $wpdb->prepare() fragment whose format string holds only a $wpdb table name; $group — $group is only ever a string literal in our own source |
| 3160 | — | `get_results` | **a finding** | could not prove `$words['select']`, `$words['join']`, `implode( ',', $chunk )` |

### core/health-delete.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 79 | — | `get_col` | prepared | $wpdb->postmeta — a $wpdb table name |
| 219 | — | `get_results` | prepared | $wpdb->posts — a $wpdb table name |
| 240 | — | `update` | built and escaped by `$wpdb` | values escaped by $wpdb; the table is a $wpdb property |
| 257 | — | `get_col` | prepared | $wpdb->postmeta — a $wpdb table name |
| 290 | — | `get_results` | prepared | $wpdb->postmeta — a $wpdb table name |
| 312 | — | `update` | built and escaped by `$wpdb` | values escaped by $wpdb; the table is a $wpdb property |
| 320 | — | `get_results` | prepared | $wpdb->postmeta — a $wpdb table name |
| 345 | — | `update` | built and escaped by `$wpdb` | values escaped by $wpdb; the table is a $wpdb property |

### core/health-keep.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 151 | — | `get_col` | prepared | $wpdb->postmeta — a $wpdb table name |
| 349 | — | `get_col` | prepared | $wpdb->postmeta — a $wpdb table name |
| 404 | — | `update` | built and escaped by `$wpdb` | values escaped by $wpdb; the table is a $wpdb property |
| 409 | — | `get_results` | prepared | $wpdb->postmeta — a $wpdb table name |
| 436 | — | `update` | built and escaped by `$wpdb` | values escaped by $wpdb; the table is a $wpdb property |
| 444 | — | `update` | built and escaped by `$wpdb` | values escaped by $wpdb; the table is a $wpdb property |
| 449 | — | `update` | built and escaped by `$wpdb` | values escaped by $wpdb; the table is a $wpdb property |
| 741 | — | `update` | built and escaped by `$wpdb` | values escaped by $wpdb; the table is a $wpdb property |
| 750 | — | `get_var` | prepared | $wpdb->postmeta — a $wpdb table name |
| 752 | — | `update` | built and escaped by `$wpdb` | values escaped by $wpdb; the table is a $wpdb property |
| 764 | — | `update` | built and escaped by `$wpdb` | values escaped by $wpdb; the table is a $wpdb property |

### core/health.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 441 | — | `get_col` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->postmeta — a $wpdb table name |
| 493 | — | `get_var` | only a `$wpdb` table name | $wpdb->posts — a $wpdb table name |
| 527 | — | `delete` | built and escaped by `$wpdb` | values escaped by $wpdb; the table is a $wpdb property |
| 610 | — | `get_results` | prepared | $wpdb->postmeta — a $wpdb table name |
| 832 | — | `get_results` | prepared | $wpdb->postmeta — a $wpdb table name |
| 882 | — | `get_results` | prepared | $wpdb->posts — a $wpdb table name; $placeholders — $placeholders is only ever implode of a generated %d placeholder list |
| 903 | — | `get_results` | prepared | $wpdb->postmeta — a $wpdb table name; $placeholders — $placeholders is only ever implode of a generated %d placeholder list |

### core/import-csv.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 478 | — | `get_col` | prepared | $wpdb->posts — a $wpdb table name; $marks — $marks is only ever implode of a generated %d placeholder list |

### core/import-sources.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 151 | — | `get_results` | prepared | $wpdb->term_taxonomy — a $wpdb table name; $wpdb->terms — a $wpdb table name |
| 174 | — | `get_results` | prepared | $wpdb->term_relationships — a $wpdb table name; $wpdb->term_taxonomy — a $wpdb table name; $wpdb->posts — a $wpdb table name |
| 229 | — | `get_results` | prepared | — |
| 245 | — | `get_results` | prepared | — |
| 286 | — | `get_col` | prepared | — |
| 303 | — | `get_results` | prepared | — |
| 310 | — | `get_results` | prepared | — |
| 333 | — | `get_col` | prepared | — |
| 339 | — | `get_results` | prepared | — |
| 375 | — | `get_var` | prepared | — |

### core/import-ui.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 230 | — | `get_var` | only a `$wpdb` table name | $wpdb->posts — a $wpdb table name |
| 231 | — | `get_var` | only a `$wpdb` table name | $wpdb->posts — a $wpdb table name |

### core/instrument.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 79 | — | `get_var` | only a `$wpdb` table name | $wpdb->posts — a $wpdb table name |
| 83 | — | `get_results` | only a `$wpdb` table name | $wpdb->posts — a $wpdb table name |
| 90 | — | `get_var` | prepared | $wpdb->posts — a $wpdb table name |

### core/journey.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 89 | — | `get_var` | prepared | $wpdb->posts — a $wpdb table name |
| 116 | — | `get_var` | only a `$wpdb` table name | $wpdb->vergeml_ai_index — a $wpdb table name |
| 122 | — | `get_row` | prepared | $wpdb->vergeml_ai_index — a $wpdb table name |
| 135 | — | `get_var` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->postmeta — a $wpdb table name |
| 204 | — | `get_results` | only a `$wpdb` table name | $wpdb->vergeml_ai_index — a $wpdb table name |

### core/librarian.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 296 | — | `get_col` | prepared | — |
| 370 | — | `get_row` | prepared | $wpdb->vergeml_librarian_batches — a $wpdb table name |
| 421 | — | `query` | prepared | $wpdb->vergeml_librarian_batches — a $wpdb table name |
| 496 | — | `get_results` | only a `$wpdb` table name | $wpdb->posts — a $wpdb table name; $wpdb->vergeml_ai_index — a $wpdb table name |
| 728 | — | `get_row` | only a `$wpdb` table name | $wpdb->vergeml_organize_runs — a $wpdb table name |
| 926 | — | `get_col` | prepared | $wpdb->term_relationships — a $wpdb table name; $wpdb->term_taxonomy — a $wpdb table name; $placeholders — $placeholders is only ever implode of a generated %d placeholder list |
| 1308 | — | `insert` | built and escaped by `$wpdb` | values escaped by $wpdb; table: vergeml_librarian_batches_table() returns only a $wpdb table name |
| 1564 | — | `get_results` | only integers it cast itself | $wpdb->vergeml_ai_index — a $wpdb table name; $in — $in is only ever implode of array_map( 'intval', ... ) |
| 1878 | — | `query` | prepared | $wpdb->vergeml_librarian_moves — a $wpdb table name; $placeholders — $placeholders is only ever implode of $rows is only ever an empty array initialiser / text holding only both arms of a ternary: a string literal in our own source / a string literal in our own source |
| 2157 | — | `get_results` | prepared | $wpdb->vergeml_librarian_moves — a $wpdb table name |
| 2197 | — | `get_results` | prepared | $wpdb->term_taxonomy — a $wpdb table name; $wpdb->term_relationships — a $wpdb table name; $placeholders — $placeholders is only ever implode of a generated %d placeholder list |
| 2230 | — | `query` | prepared | $wpdb->vergeml_librarian_moves — a $wpdb table name; $placeholders — $placeholders is only ever implode of a generated %d placeholder list |
| 2276 | — | `query` | prepared | $wpdb->vergeml_librarian_moves — a $wpdb table name; $batches — $batches is only ever implode of a generated %d placeholder list; $files — $files is only ever implode of a generated %d placeholder list |
| 2421 | — | `get_results` | prepared | $wpdb->vergeml_librarian_batches — a $wpdb table name |
| 2472 | — | `get_var` | prepared | $wpdb->vergeml_librarian_batches — a $wpdb table name |
| 2481 | — | `query` | prepared | $wpdb->vergeml_librarian_moves — a $wpdb table name |
| 2486 | — | `query` | prepared | $wpdb->vergeml_librarian_batches — a $wpdb table name |
| 2883 | — | `get_var` | only a `$wpdb` table name | $wpdb->vergeml_ai_index — a $wpdb table name |
| 2980 | — | `get_results` | prepared | $wpdb->vergeml_librarian_moves — a $wpdb table name |
| 3058 | — | `get_var` | prepared | $wpdb->vergeml_librarian_batches — a $wpdb table name |

### core/media-list.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 523 | — | `get_var` | prepared | $wpdb->postmeta — a $wpdb table name; $wpdb->posts — a $wpdb table name |

### core/options-pages.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 1449 | — | `get_var` | prepared | — |
| 1868 | — | `delete` | built and escaped by `$wpdb` | values escaped by $wpdb; the table is a $wpdb property |
| 1876 | — | `get_results` | prepared | $wpdb->term_relationships — a $wpdb table name; $wpdb->posts — a $wpdb table name; $rows2remove_format — $rows2remove_format is only ever implode of a generated %d placeholder list |
| 1897 | — | `query` | prepared | $wpdb->term_relationships — a $wpdb table name; $wpdb->posts — a $wpdb table name; $rows2remove_format — $rows2remove_format is only ever implode of a generated %d placeholder list |
| 1940 | — | `query` | only integers it cast itself | $wpdb->prefix — a $wpdb table name; $table — $table is only ever each item of a literal array in our own source |
| 1963 | — | `get_col` | prepared | $id_column — $id_column is only ever a string literal in our own source; $table — $table is only ever a core table name from _get_meta_table() |
| 1978 | — | `query` | prepared | $table — $table is only ever a core table name from _get_meta_table(); $id_column — $id_column is only ever a string literal in our own source; $placeholders — $placeholders is only ever implode of a generated %d placeholder list |
| 2028 | — | `query` | only a `$wpdb` table name | $wpdb->options — a $wpdb table name |
| 2082 | — | `query` | only a `$wpdb` table name | $wpdb->options — a $wpdb table name |

### core/organize.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 355 | — | `get_row` | prepared | $wpdb->vergeml_organize_runs — a $wpdb table name |
| 376 | — | `get_row` | only a `$wpdb` table name | $wpdb->vergeml_organize_runs — a $wpdb table name |
| 462 | — | `insert` | built and escaped by `$wpdb` | values escaped by $wpdb; table: vergeml_organize_table() returns only a $wpdb table name |
| 515 | — | `query` | prepared | $wpdb->vergeml_organize_runs — a $wpdb table name |
| 545 | — | `get_var` | only a `$wpdb` table name | $wpdb->vergeml_ai_index — a $wpdb table name |
| 680 | — | `get_results` | prepared | $wpdb->vergeml_ai_index — a $wpdb table name; $placeholders — $placeholders is only ever implode of a generated %d placeholder list |
| 693 | — | `get_results` | prepared | $wpdb->vergeml_ai_index — a $wpdb table name |
| 2757 | — | `get_var` | prepared | $wpdb->vergeml_organize_runs — a $wpdb table name |
| 2766 | — | `query` | prepared | $wpdb->vergeml_organize_runs — a $wpdb table name |
| 2805 | — | `get_var` | prepared | $wpdb->posts — a $wpdb table name |
| 2810 | — | `get_var` | only a `$wpdb` table name | $wpdb->vergeml_ai_index — a $wpdb table name |
| 2839 | — | `get_var` | prepared | $wpdb->vergeml_ai_index — a $wpdb table name; $placeholders — $placeholders is only ever implode of a generated %d placeholder list |
| 3068 | — | `get_var` | prepared | $wpdb->vergeml_organize_runs — a $wpdb table name |
| 3083 | — | `query` | prepared | $wpdb->vergeml_organize_runs — a $wpdb table name |

### core/plan-tree.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 53 | — | `get_row` | only a `$wpdb` table name | $t — $t is only ever a $wpdb table name |
| 61 | — | `get_results` | only a `$wpdb` table name | $t — $t is only ever a $wpdb table name |
| 125 | — | `get_var` | only a `$wpdb` table name | $t — $t is only ever a $wpdb table name |
| 130 | — | `get_results` | prepared | $t — $t is only ever a $wpdb table name |

### core/post-folders.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 132 | — | `get_results` | prepared | $wpdb->term_relationships — a $wpdb table name; $wpdb->term_taxonomy — a $wpdb table name; $wpdb->posts — a $wpdb table name |
| 176 | — | `get_var` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->term_relationships — a $wpdb table name; $wpdb->term_taxonomy — a $wpdb table name |

### core/rename-file.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 134 | — | `get_col` | only a `$wpdb` table name | $wpdb->vergeml_ai_index — a $wpdb table name |
| 187 | — | `get_col` | prepared | $wpdb->vergeml_ai_index — a $wpdb table name |

### core/rename.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 96 | — | `get_col` | only a `$wpdb` table name | $wpdb->vergeml_ai_index — a $wpdb table name; $wpdb->posts — a $wpdb table name |
| 146 | — | `get_var` | only a `$wpdb` table name | $wpdb->vergeml_ai_index — a $wpdb table name; $wpdb->posts — a $wpdb table name |

### core/rest-tree.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 446 | — | `get_var` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->term_relationships — a $wpdb table name; $wpdb->term_taxonomy — a $wpdb table name |

### core/search-meaning.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 338 | — | `get_results` | prepared | $wpdb->vergeml_ai_index — a $wpdb table name |
| 365 | — | `get_var` | only a `$wpdb` table name | $wpdb->vergeml_ai_index — a $wpdb table name |
| 417 | — | `get_results` | only integers it cast itself | $wpdb->vergeml_ai_index — a $wpdb table name; implode( ',', array_map( 'intval', array_keys( $shortlist ) ) ) — implode of array_map( 'intval', ... ) |
| 484 | — | `get_results` | prepared | $wpdb->vergeml_ai_index — a $wpdb table name |
| 504 | — | `update` | built and escaped by `$wpdb` | values escaped by $wpdb; the table is a $wpdb property |
| 561 | — | `get_var` | only a `$wpdb` table name | $wpdb->vergeml_ai_index — a $wpdb table name |

### core/search-try.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 80 | — | `get_var` | read by hand — see the reason | $from — $from is only ever text holding only a $wpdb table name |
| 84 | — | `get_var` | read by hand — see the reason | $from — $from is only ever text holding only a $wpdb table name |
| 85 | — | `get_var` | read by hand — see the reason | $from — $from is only ever text holding only a $wpdb table name |
| 87 | — | `get_results` | read by hand — see the reason | $from — $from is only ever text holding only a $wpdb table name |
| 142 | — | `get_results` | only integers it cast itself | $wpdb->vergeml_ai_index — a $wpdb table name; implode( ',', array_map( 'intval', $ids ) ) — implode of array_map( 'intval', ... ) |

### core/seo-context.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 80 | — | `get_row` | prepared | $wpdb->prefix — a $wpdb table name |
| 306 | — | `get_col` | read by hand — see the reason | — |
| 332 | — | `get_var` | read by hand — see the reason | — |

### core/smart-folders.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 370 | — | `get_results` | read by hand — see the reason | — |
| 390 | — | `get_results` | read by hand — see the reason | — |
| 578 | — | `get_var` | prepared | $wpdb->posts — a $wpdb table name |
| 687 | — | `get_var` | prepared | $wpdb->postmeta — a $wpdb table name |
| 773 | — | `get_results` | prepared | $wpdb->posts — a $wpdb table name |
| 808 | — | `get_results` | prepared | $wpdb->postmeta — a $wpdb table name; $placeholders — $placeholders is only ever implode of a generated %d placeholder list |
| 847 | — | `get_var` | only a `$wpdb` table name | $wpdb->posts — a $wpdb table name |
| 873 | — | `get_col` | prepared | $wpdb->options — a $wpdb table name |
| 914 | — | `get_col` | prepared | $wpdb->posts — a $wpdb table name |
| 951 | — | `get_var` | only a `$wpdb` table name | $wpdb->posts — a $wpdb table name |

### core/taxonomies.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 1044 | — | `get_results` | prepared | $wpdb->term_taxonomy — a $wpdb table name |
| 1057 | — | `get_results` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->term_relationships — a $wpdb table name; $terms_format — $terms_format is only ever implode of a generated %d placeholder list |
| 1429 | — | `query` | a literal, nothing interpolated | — |
| 1431 | — | `query` | prepared | $wpdb->posts — a $wpdb table name; $order_format — $order_format is only ever implode of a generated %d placeholder list |
| 1549 | — | `get_var` | prepared | $wpdb->term_relationships — a $wpdb table name; $wpdb->posts — a $wpdb table name |
| 1559 | — | `update` | built and escaped by `$wpdb` | values escaped by $wpdb; the table is a $wpdb property |
| 1605 | — | `get_var` | prepared | $wpdb->term_relationships — a $wpdb table name; $wpdb->posts — a $wpdb table name; $placeholders — $placeholders is only ever implode of a generated %d placeholder list |
| 1622 | — | `update` | built and escaped by `$wpdb` | values escaped by $wpdb; the table is a $wpdb property |

### core/utilities.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 280 | — | `get_col` | prepared | $wpdb->postmeta — a $wpdb table name |
| 345 | — | `get_col` | prepared | $table — $table is only ever vergeml_index_table() returns only a $wpdb table name; $wpdb->posts — a $wpdb table name |

### uninstall.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 35 | — | `query` | only a `$wpdb` table name | $wpdb->options — a $wpdb table name |
| 62 | — | `get_results` | prepared | $wpdb->term_taxonomy — a $wpdb table name |
| 82 | — | `query` | prepared | $wpdb->term_relationships — a $wpdb table name; $tt_in — $tt_in is only ever implode of a generated %d placeholder list |
| 83 | — | `query` | prepared | $wpdb->termmeta — a $wpdb table name; $term_in — $term_in is only ever implode of a generated %d placeholder list |
| 84 | — | `query` | prepared | $wpdb->term_taxonomy — a $wpdb table name |
| 85 | — | `query` | prepared | $wpdb->terms — a $wpdb table name; $term_in — $term_in is only ever implode of a generated %d placeholder list |
| 95 | — | `query` | prepared | $wpdb->term_relationships — a $wpdb table name; $wpdb->posts — a $wpdb table name; $tt_in — $tt_in is only ever implode of a generated %d placeholder list |
| 109 | — | `query` | only integers it cast itself | $wpdb->prefix — a $wpdb table name; $table — $table is only ever each item of a literal array in our own source |
| 118 | — | `query` | only a `$wpdb` table name | $wpdb->options — a $wpdb table name |
| 163 | — | `query` | prepared | $wpdb->usermeta — a $wpdb table name |
| 184 | — | `query` | only a `$wpdb` table name | $wpdb->usermeta — a $wpdb table name |

## The helpers this classification trusts

Named, with the reason. `tests/security/db-calls.mjs` asserts each still casts: a
helper that loses its `absint()` while this list still trusts it is the quietest way
for every row above to become wrong at once.

| helper | why it is trusted |
|---|---|
| `vergeml_ids()` | maps absint over the array and drops anything falsy |
| `absint()` | core |
| `intval()` | core |

