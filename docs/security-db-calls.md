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
| lines mentioning `$wpdb->` | 476 |
| `$wpdb->prepare()` calls | 127 |
| **calls that reach the server** | **202** |
| · prepared | 109 |
| · a literal, nothing interpolated | 1 |
| · only a `$wpdb` table name | 52 |
| · only integers it cast itself | 8 |
| · built and escaped by `$wpdb` (insert/update/delete) | 21 |
| · read by hand, with the reason | 11 |
| · **findings** | **0** |

Read over 66 shipped PHP files.

## Findings

None. Every call that reaches the server is prepared, is a literal, interpolates
only a `$wpdb` table name, interpolates only a value proven integer where it is
written, or is built by `$wpdb` itself.

## Read by hand

The tool proves 191 of 202 calls on its own. These 11 assemble their
SQL from fragments across a nested loop or a function boundary, which is further
than a static reader should be trusted to follow, so each was read and the reason
written down. Each is keyed by a hash of its own SQL, not by its line, so editing
one of these queries expires its review and turns the suite red.

| where | fingerprint | method | why it is safe |
|---|---|---|---|
| core/ai-index.php:259 | `77b42d1645` | `get_results` | $in is implode of an array_chunk of $ids, and $ids is array_map( 'intval', (array) $ids ) on the function's first line -- integers, every one of them |
| core/ai-screen.php:136 | `56dead9d32` | `get_row` | every $sums[] element is its own $wpdb->prepare() fragment, and the column alias after AS comes from a literal list of eight field names in the foreach header above it |
| core/guide.php:2094 | `f8e582bd4e` | `get_results` | $chunk comes from array_chunk( array_map( function ( $r ) { return (int) $r['attachment_id']; }, $rows ), 500 ) by way of $chunks -- integers by construction |
| core/search-try.php:80 | `36af7892eb` | `get_var` | each $any[] is $wpdb->prepare( "{$column} LIKE %s", $like ); $column comes from vergeml_search_try_fields(), a literal array of seven, and the search term goes through esc_like() and a placeholder |
| core/search-try.php:84 | `3e7ba748c4` | `get_var` | each $where_all[] is "( " . implode( " OR ", $any ) . " )" over the prepared fragments above, and {$from} interpolates two $wpdb table names and nothing else |
| core/search-try.php:85 | `6a95da9eb6` | `get_var` | the same as the row above, restricted to the three columns WordPress itself searches |
| core/search-try.php:87 | `db4c1ee30a` | `get_results` | the rows query behind the same two assembled lists; LIMIT 30 is a literal |
| core/seo-context.php:306 | `1f45eb3c01` | `get_col` | vergeml_seo_gap_sql() interpolates its $select argument, and both of its two call sites pass a hardcoded literal; the $keys and $stars lists inside it are array_map( 'esc_sql', ... ) |
| core/seo-context.php:332 | `ace3ecc40b` | `get_var` | the same function, the other call site, also a hardcoded literal |
| core/smart-folders.php:354 | `77f8356ddc` | `get_results` | the interpolated {$exclude} and each appended $branch['sql'] come from two filters, not from a request; both of our own implementations build from $wpdb table names and constants, and every value is bound through prepare(). An extension point that accepts SQL -- see "Two filters that accept SQL" in the doc |
| core/smart-folders.php:374 | `043d77dfa1` | `get_results` | the same $core_sql, read a second time for the extended flag |

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
| 932 | — | `get_var` | prepared | $wpdb->postmeta — a $wpdb table name; $wpdb->vergeml_ai_index — a $wpdb table name |
| 1273 | — | `get_col` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->postmeta — a $wpdb table name |
| 1289 | — | `get_col` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->vergeml_ai_index — a $wpdb table name |
| 1332 | — | `get_var` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->postmeta — a $wpdb table name |
| 1341 | — | `get_var` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->vergeml_ai_index — a $wpdb table name |
| 1736 | — | `get_col` | prepared | $wpdb->vergeml_ai_index — a $wpdb table name; $wpdb->postmeta — a $wpdb table name |
| 1992 | — | `get_var` | prepared | $wpdb->posts — a $wpdb table name |
| 1993 | — | `get_var` | only a `$wpdb` table name | $wpdb->vergeml_ai_index — a $wpdb table name |

### core/auto-file.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 97 | — | `get_col` | prepared | $wpdb->term_relationships — a $wpdb table name; $wpdb->term_taxonomy — a $wpdb table name |
| 265 | — | `get_row` | prepared | $wpdb->vergeml_ai_index — a $wpdb table name |
| 464 | — | `get_var` | prepared | $wpdb->vergeml_librarian_batches — a $wpdb table name |
| 491 | — | `insert` | built and escaped by `$wpdb` | values escaped by $wpdb; table: vergeml_librarian_batches_table() returns only a $wpdb table name |
| 541 | — | `get_col` | prepared | $wpdb->posts — a $wpdb table name; $table — $table is only ever vergeml_index_table() returns only a $wpdb table name; $wpdb->term_relationships — a $wpdb table name; $tt — $tt is only ever vergeml_autofile_tt_ids() returns only a string literal in our own source / implode of array_map( 'intval', ... ) |
| 733 | — | `get_var` | only integers it cast itself | $wpdb->posts — a $wpdb table name; $described — $described is only ever both arms of a ternary: text holding only $table is only ever vergeml_index_table() returns only a $wpdb table name / a string literal in our own source; $wpdb->term_relationships — a $wpdb table name; $tt — $tt is only ever vergeml_autofile_tt_ids() returns only a string literal in our own source / implode of array_map( 'intval', ... ) |

### core/brief.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 195 | — | `get_results` | only a `$wpdb` table name | $wpdb->vergeml_ai_index — a $wpdb table name |
| 199 | — | `get_col` | only a `$wpdb` table name | $wpdb->vergeml_ai_index — a $wpdb table name |
| 379 | — | `get_var` | prepared | $wpdb->vergeml_ai_index — a $wpdb table name; $wpdb->term_relationships — a $wpdb table name |
| 396 | — | `get_col` | prepared | $wpdb->vergeml_ai_index — a $wpdb table name |

### core/folder-talk.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 159 | — | `get_var` | only a `$wpdb` table name | $wpdb->vergeml_ai_index — a $wpdb table name |
| 169 | — | `get_col` | prepared | $wpdb->vergeml_ai_index — a $wpdb table name |
| 211 | — | `get_var` | only a `$wpdb` table name | $wpdb->vergeml_ai_index — a $wpdb table name |
| 212 | — | `get_var` | only a `$wpdb` table name | $wpdb->vergeml_ai_index — a $wpdb table name |
| 247 | — | `get_var` | only a `$wpdb` table name | $wpdb->vergeml_ai_index — a $wpdb table name |
| 256 | — | `get_results` | prepared | $wpdb->vergeml_ai_index — a $wpdb table name |
| 911 | — | `get_var` | only a `$wpdb` table name | $wpdb->vergeml_ai_index — a $wpdb table name |
| 1027 | — | `get_results` | prepared | $wpdb->vergeml_ai_index — a $wpdb table name |

### core/guide.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 205 | — | `get_row` | prepared | $wpdb->term_relationships — a $wpdb table name; $wpdb->term_taxonomy — a $wpdb table name; $t — $t is only ever a $wpdb table name |
| 215 | — | `get_row` | only a `$wpdb` table name | $t — $t is only ever a $wpdb table name |
| 304 | — | `get_var` | only a `$wpdb` table name | $wpdb->vergeml_ai_index — a $wpdb table name |
| 458 | — | `get_var` | only a `$wpdb` table name | $t — $t is only ever a $wpdb table name |
| 459 | — | `get_var` | only a `$wpdb` table name | $t — $t is only ever a $wpdb table name |
| 460 | — | `get_results` | only a `$wpdb` table name | $t — $t is only ever a $wpdb table name |
| 462 | — | `get_var` | only a `$wpdb` table name | $t — $t is only ever a $wpdb table name |
| 463 | — | `get_var` | only a `$wpdb` table name | $t — $t is only ever a $wpdb table name |
| 464 | — | `get_var` | only a `$wpdb` table name | $t — $t is only ever a $wpdb table name |
| 465 | — | `get_results` | prepared | $t — $t is only ever a $wpdb table name |
| 506 | — | `get_var` | prepared | $t — $t is only ever a $wpdb table name; $wpdb->term_relationships — a $wpdb table name; $wpdb->term_taxonomy — a $wpdb table name |
| 962 | — | `get_results` | only integers it cast itself | $wpdb->vergeml_ai_index — a $wpdb table name; implode( ',', $chunk ) — implode of $chunk is only ever each chunk of a map whose closure returns (int) |
| 1661 | — | `get_results` | only integers it cast itself | $select — $select is only ever a string literal in our own source / appended a string literal in our own source; $t — $t is only ever a $wpdb table name; $join — $join is only ever a string literal in our own source / appended text holding only a $wpdb table name / appended a $wpdb->prepare() fragment whose format string holds only a $wpdb table name; $where — $where is only ever a string literal in our own source / appended a $wpdb->prepare() fragment whose format string holds only a $wpdb table name; $group — $group is only ever a string literal in our own source |
| 2094 | — | `get_results` | read by hand — see the reason | $wpdb->vergeml_ai_index — a $wpdb table name |

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
| 451 | — | `get_col` | prepared | $wpdb->posts — a $wpdb table name; $marks — $marks is only ever implode of a generated %d placeholder list |

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
| 286 | — | `get_col` | prepared | — |
| 360 | — | `get_row` | prepared | $wpdb->vergeml_librarian_batches — a $wpdb table name |
| 411 | — | `query` | prepared | $wpdb->vergeml_librarian_batches — a $wpdb table name |
| 486 | — | `get_results` | only a `$wpdb` table name | $wpdb->posts — a $wpdb table name; $wpdb->vergeml_ai_index — a $wpdb table name |
| 718 | — | `get_row` | only a `$wpdb` table name | $wpdb->vergeml_organize_runs — a $wpdb table name |
| 935 | — | `get_col` | prepared | $wpdb->term_relationships — a $wpdb table name; $wpdb->term_taxonomy — a $wpdb table name; $placeholders — $placeholders is only ever implode of a generated %d placeholder list |
| 1317 | — | `insert` | built and escaped by `$wpdb` | values escaped by $wpdb; table: vergeml_librarian_batches_table() returns only a $wpdb table name |
| 1573 | — | `get_results` | only integers it cast itself | $wpdb->vergeml_ai_index — a $wpdb table name; $in — $in is only ever implode of array_map( 'intval', ... ) |
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
| 3046 | — | `get_var` | prepared | $wpdb->vergeml_librarian_batches — a $wpdb table name |

### core/options-pages.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 1449 | — | `get_var` | prepared | — |
| 1866 | — | `delete` | built and escaped by `$wpdb` | values escaped by $wpdb; the table is a $wpdb property |
| 1874 | — | `get_results` | prepared | $wpdb->term_relationships — a $wpdb table name; $wpdb->posts — a $wpdb table name; $rows2remove_format — $rows2remove_format is only ever implode of a generated %d placeholder list |
| 1895 | — | `query` | prepared | $wpdb->term_relationships — a $wpdb table name; $wpdb->posts — a $wpdb table name; $rows2remove_format — $rows2remove_format is only ever implode of a generated %d placeholder list |
| 1938 | — | `query` | only integers it cast itself | $wpdb->prefix — a $wpdb table name; $table — $table is only ever each item of a literal array in our own source |
| 1961 | — | `get_col` | prepared | $id_column — $id_column is only ever a string literal in our own source; $table — $table is only ever a core table name from _get_meta_table() |
| 1976 | — | `query` | prepared | $table — $table is only ever a core table name from _get_meta_table(); $id_column — $id_column is only ever a string literal in our own source; $placeholders — $placeholders is only ever implode of a generated %d placeholder list |
| 2026 | — | `query` | only a `$wpdb` table name | $wpdb->options — a $wpdb table name |
| 2080 | — | `query` | only a `$wpdb` table name | $wpdb->options — a $wpdb table name |

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
| 443 | — | `get_var` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->term_relationships — a $wpdb table name; $wpdb->term_taxonomy — a $wpdb table name |

### core/search-meaning.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 244 | — | `get_results` | prepared | $wpdb->vergeml_ai_index — a $wpdb table name |
| 271 | — | `get_var` | only a `$wpdb` table name | $wpdb->vergeml_ai_index — a $wpdb table name |
| 323 | — | `get_results` | only integers it cast itself | $wpdb->vergeml_ai_index — a $wpdb table name; implode( ',', array_map( 'intval', array_keys( $shortlist ) ) ) — implode of array_map( 'intval', ... ) |
| 390 | — | `get_results` | prepared | $wpdb->vergeml_ai_index — a $wpdb table name |
| 410 | — | `update` | built and escaped by `$wpdb` | values escaped by $wpdb; the table is a $wpdb property |
| 467 | — | `get_var` | only a `$wpdb` table name | $wpdb->vergeml_ai_index — a $wpdb table name |

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
| 354 | — | `get_results` | read by hand — see the reason | — |
| 374 | — | `get_results` | read by hand — see the reason | — |
| 562 | — | `get_var` | prepared | $wpdb->posts — a $wpdb table name |
| 671 | — | `get_var` | prepared | $wpdb->postmeta — a $wpdb table name |
| 757 | — | `get_results` | prepared | $wpdb->posts — a $wpdb table name |
| 792 | — | `get_results` | prepared | $wpdb->postmeta — a $wpdb table name; $placeholders — $placeholders is only ever implode of a generated %d placeholder list |
| 831 | — | `get_var` | only a `$wpdb` table name | $wpdb->posts — a $wpdb table name |
| 857 | — | `get_col` | prepared | $wpdb->options — a $wpdb table name |
| 898 | — | `get_col` | prepared | $wpdb->posts — a $wpdb table name |
| 935 | — | `get_var` | only a `$wpdb` table name | $wpdb->posts — a $wpdb table name |

### core/taxonomies.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 1030 | — | `get_results` | prepared | $wpdb->term_taxonomy — a $wpdb table name |
| 1043 | — | `get_results` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->term_relationships — a $wpdb table name; $terms_format — $terms_format is only ever implode of a generated %d placeholder list |
| 1415 | — | `query` | a literal, nothing interpolated | — |
| 1417 | — | `query` | prepared | $wpdb->posts — a $wpdb table name; $order_format — $order_format is only ever implode of a generated %d placeholder list |
| 1535 | — | `get_var` | prepared | $wpdb->term_relationships — a $wpdb table name; $wpdb->posts — a $wpdb table name |
| 1545 | — | `update` | built and escaped by `$wpdb` | values escaped by $wpdb; the table is a $wpdb property |
| 1591 | — | `get_var` | prepared | $wpdb->term_relationships — a $wpdb table name; $wpdb->posts — a $wpdb table name; $placeholders — $placeholders is only ever implode of a generated %d placeholder list |
| 1608 | — | `update` | built and escaped by `$wpdb` | values escaped by $wpdb; the table is a $wpdb property |

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

