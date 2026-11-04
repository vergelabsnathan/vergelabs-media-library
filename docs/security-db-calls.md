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
| lines mentioning `$wpdb->` | 567 |
| `$wpdb->prepare()` calls | 194 |
| **calls that reach the server** | **214** |
| · prepared | 183 |
| · a literal, nothing interpolated | 0 |
| · only a `$wpdb` table name | 20 |
| · only integers it cast itself | 0 |
| · built and escaped by `$wpdb` (insert/update/delete) | 11 |
| · read by hand, with the reason | 0 |
| · **findings** | **0** |

Read over 67 shipped PHP files.

## Findings

None. Every call that reaches the server is prepared, is a literal, interpolates
only a `$wpdb` table name, interpolates only a value proven integer where it is
written, or is built by `$wpdb` itself.

## Read by hand

The tool proves 214 of 214 calls on its own. These 0 assemble their
SQL from fragments across a nested loop or a function boundary, which is further
than a static reader should be trusted to follow, so each was read and the reason
written down. Each is keyed by a hash of its own SQL, not by its line, so editing
one of these queries expires its review and turns the suite red.

| where | fingerprint | method | why it is safe |
|---|---|---|---|

## Every call

Sorted by file. The proof column says why the tool is satisfied; where it names a
variable, every assignment to that variable inside the enclosing function was
checked, and one unproven assignment is enough to make the whole call a finding.


### core/ai-index.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 55 | — | `query` | prepared | — |
| 181 | — | `get_var` | prepared | — |
| 252 | — | `get_results` | prepared | implode( ', ', array_fill( 0, count( $chunk ), '%d' ) ) — implode of a generated %d placeholder list |
| 279 | — | `get_row` | prepared | — |
| 386 | — | `update` | built and escaped by `$wpdb` | values escaped by $wpdb; table: vergeml_index_table() returns only a $wpdb table name |
| 396 | — | `insert` | built and escaped by `$wpdb` | values escaped by $wpdb; table: vergeml_index_table() returns only a $wpdb table name |
| 409 | — | `delete` | built and escaped by `$wpdb` | values escaped by $wpdb; table: vergeml_index_table() returns only a $wpdb table name |
| 660 | — | `get_row` | prepared | — |
| 755 | — | `get_col` | prepared | — |
| 802 | — | `get_col` | prepared | $wpdb->postmeta — a $wpdb table name |

### core/ai-screen.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 117 | — | `get_var` | only a `$wpdb` table name | $wpdb->posts — a $wpdb table name |
| 119 | — | `get_var` | only a `$wpdb` table name | $wpdb->posts — a $wpdb table name; $wpdb->postmeta — a $wpdb table name |
| 122 | — | `get_var` | prepared | — |
| 123 | — | `get_var` | prepared | $wpdb->postmeta — a $wpdb table name |
| 124 | — | `get_var` | prepared | $wpdb->posts — a $wpdb table name |
| 125 | — | `get_results` | prepared | — |
| 128 | — | `get_row` | prepared | — |
| 132 | — | `get_row` | prepared | — |
| 159 | — | `get_results` | prepared | — |
| 160 | — | `get_var` | prepared | — |

### core/ai.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 816 | — | `get_var` | prepared | $wpdb->postmeta — a $wpdb table name |
| 1160 | — | `get_col` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->postmeta — a $wpdb table name |
| 1176 | — | `get_col` | prepared | $wpdb->posts — a $wpdb table name |
| 1220 | — | `get_var` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->postmeta — a $wpdb table name |
| 1229 | — | `get_var` | prepared | $wpdb->posts — a $wpdb table name |
| 1624 | — | `get_col` | prepared | $wpdb->postmeta — a $wpdb table name |
| 1643 | — | `get_var` | prepared | $wpdb->postmeta — a $wpdb table name |
| 1890 | — | `get_var` | prepared | $wpdb->posts — a $wpdb table name |
| 1891 | — | `get_var` | prepared | — |

### core/auto-file.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 100 | — | `get_col` | prepared | $wpdb->term_relationships — a $wpdb table name; $wpdb->term_taxonomy — a $wpdb table name |
| 252 | — | `get_row` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->postmeta — a $wpdb table name |
| 475 | — | `get_var` | prepared | — |
| 503 | — | `insert` | built and escaped by `$wpdb` | values escaped by $wpdb; table: vergeml_librarian_batches_table() returns only a $wpdb table name |
| 553 | — | `get_col` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->term_relationships — a $wpdb table name; implode( ', ', array_fill( 0, count( $tt_ids ), '%d' ) ) — implode of a generated %d placeholder list |
| 760 | — | `get_var` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->term_relationships — a $wpdb table name; implode( ', ', array_fill( 0, count( $tt_ids ), '%d' ) ) — implode of a generated %d placeholder list |
| 772 | — | `get_var` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->term_relationships — a $wpdb table name; implode( ', ', array_fill( 0, count( $tt_ids ), '%d' ) ) — implode of a generated %d placeholder list |

### core/brief.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 197 | — | `get_results` | prepared | — |
| 201 | — | `get_col` | prepared | — |
| 381 | — | `get_var` | prepared | $wpdb->term_relationships — a $wpdb table name |
| 399 | — | `get_col` | prepared | — |

### core/filing.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 517 | — | `get_col` | prepared | — |
| 1146 | — | `get_results` | prepared | $wpdb->term_relationships — a $wpdb table name; $wpdb->term_taxonomy — a $wpdb table name; $wpdb->termmeta — a $wpdb table name; implode( ', ', array_fill( 0, count( $term_ids ), '%d' ) ) — implode of a generated %d placeholder list |
| 1176 | — | `get_results` | prepared | $wpdb->term_relationships — a $wpdb table name; $wpdb->term_taxonomy — a $wpdb table name; implode( ', ', array_fill( 0, count( $stale_ids ), '%d' ) ) — implode of a generated %d placeholder list |

### core/folder-talk.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 183 | — | `get_var` | prepared | — |
| 194 | — | `get_results` | prepared | — |
| 240 | — | `get_var` | prepared | — |
| 241 | — | `get_var` | prepared | — |
| 276 | — | `get_var` | prepared | — |
| 286 | — | `get_results` | prepared | — |
| 958 | — | `get_var` | prepared | — |
| 1132 | — | `get_results` | prepared | $wpdb->postmeta — a $wpdb table name; $wpdb->posts — a $wpdb table name; $wpdb->term_relationships — a $wpdb table name; $wpdb->term_taxonomy — a $wpdb table name; $wpdb->termmeta — a $wpdb table name; implode( ', ', array_fill( 0, count( $only_list ), '%d' ) ) — implode of a generated %d placeholder list |
| 2273 | — | `get_results` | prepared | implode( ', ', array_fill( 0, count( $chunk ), '%d' ) ) — implode of a generated %d placeholder list |
| 2551 | — | `get_var` | prepared | $wpdb->term_relationships — a $wpdb table name; $wpdb->term_taxonomy — a $wpdb table name |

### core/guide.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 270 | — | `get_col` | only a `$wpdb` table name | $wpdb->postmeta — a $wpdb table name; $wpdb->posts — a $wpdb table name |
| 369 | — | `get_var` | only a `$wpdb` table name | $wpdb->posts — a $wpdb table name |
| 408 | — | `get_row` | prepared | $wpdb->term_relationships — a $wpdb table name; $wpdb->term_taxonomy — a $wpdb table name |
| 420 | — | `get_row` | prepared | — |
| 526 | — | `get_var` | prepared | — |
| 740 | — | `get_var` | prepared | — |
| 741 | — | `get_var` | prepared | — |
| 742 | — | `get_results` | prepared | — |
| 744 | — | `get_var` | prepared | — |
| 745 | — | `get_var` | prepared | — |
| 746 | — | `get_var` | prepared | — |
| 747 | — | `get_results` | prepared | — |
| 789 | — | `get_var` | prepared | $wpdb->term_relationships — a $wpdb table name; $wpdb->term_taxonomy — a $wpdb table name |
| 1918 | — | `get_results` | prepared | $wpdb->postmeta — a $wpdb table name; $wpdb->posts — a $wpdb table name; implode( ', ', array_fill( 0, count( $chunk ), '%d' ) ) — implode of a generated %d placeholder list |
| 1938 | — | `get_results` | prepared | $wpdb->postmeta — a $wpdb table name; $wpdb->posts — a $wpdb table name; implode( ', ', array_fill( 0, count( $chunk ), '%d' ) ) — implode of a generated %d placeholder list |
| 2811 | — | `get_results` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->term_relationships — a $wpdb table name; $wpdb->term_taxonomy — a $wpdb table name |
| 2833 | — | `get_results` | prepared | $wpdb->term_relationships — a $wpdb table name; $wpdb->term_taxonomy — a $wpdb table name |
| 2854 | — | `get_results` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->term_relationships — a $wpdb table name; $wpdb->term_taxonomy — a $wpdb table name |
| 2872 | — | `get_results` | prepared | $wpdb->term_relationships — a $wpdb table name; $wpdb->term_taxonomy — a $wpdb table name |
| 3336 | — | `get_results` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->postmeta — a $wpdb table name; implode( ', ', array_fill( 0, count( $chunk ), '%d' ) ) — implode of a generated %d placeholder list |

### core/health-delete.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 79 | — | `get_col` | prepared | $wpdb->postmeta — a $wpdb table name |
| 246 | — | `get_results` | prepared | $wpdb->posts — a $wpdb table name |
| 258 | — | `get_col` | prepared | $wpdb->postmeta — a $wpdb table name |
| 268 | — | `get_col` | prepared | $wpdb->postmeta — a $wpdb table name |
| 329 | — | `get_results` | prepared | $wpdb->posts — a $wpdb table name |
| 362 | — | `get_col` | prepared | $wpdb->postmeta — a $wpdb table name |
| 395 | — | `get_results` | prepared | $wpdb->postmeta — a $wpdb table name |
| 426 | — | `get_results` | prepared | $wpdb->postmeta — a $wpdb table name |

### core/health-keep.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 151 | — | `get_col` | prepared | $wpdb->postmeta — a $wpdb table name |
| 349 | — | `get_col` | prepared | $wpdb->postmeta — a $wpdb table name |
| 414 | — | `get_results` | prepared | $wpdb->postmeta — a $wpdb table name |
| 753 | — | `get_var` | prepared | $wpdb->postmeta — a $wpdb table name |

### core/health.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 436 | — | `get_col` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->postmeta — a $wpdb table name |
| 488 | — | `get_var` | only a `$wpdb` table name | $wpdb->posts — a $wpdb table name |
| 592 | — | `get_results` | prepared | $wpdb->postmeta — a $wpdb table name |
| 814 | — | `get_results` | prepared | $wpdb->postmeta — a $wpdb table name |
| 861 | — | `get_results` | prepared | $wpdb->posts — a $wpdb table name; implode( ', ', array_fill( 0, count( $ids ), '%d' ) ) — implode of a generated %d placeholder list |
| 882 | — | `get_results` | prepared | $wpdb->postmeta — a $wpdb table name; implode( ', ', array_fill( 0, count( $ids ), '%d' ) ) — implode of a generated %d placeholder list |

### core/import-csv.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 486 | — | `get_col` | prepared | $wpdb->posts — a $wpdb table name; implode( ', ', array_fill( 0, count( $chunk ), '%d' ) ) — implode of a generated %d placeholder list |

### core/import-sources.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 159 | — | `get_results` | prepared | $wpdb->term_taxonomy — a $wpdb table name; $wpdb->terms — a $wpdb table name |
| 182 | — | `get_results` | prepared | $wpdb->term_relationships — a $wpdb table name; $wpdb->term_taxonomy — a $wpdb table name; $wpdb->posts — a $wpdb table name |
| 237 | — | `get_results` | prepared | — |
| 253 | — | `get_results` | prepared | — |
| 294 | — | `get_col` | prepared | — |
| 311 | — | `get_results` | prepared | — |
| 318 | — | `get_results` | prepared | — |
| 341 | — | `get_col` | prepared | — |
| 347 | — | `get_results` | prepared | — |
| 383 | — | `get_var` | prepared | — |

### core/import-ui.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 230 | — | `get_var` | only a `$wpdb` table name | $wpdb->posts — a $wpdb table name |
| 231 | — | `get_var` | only a `$wpdb` table name | $wpdb->posts — a $wpdb table name |

### core/instrument.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 79 | — | `get_var` | only a `$wpdb` table name | $wpdb->posts — a $wpdb table name |
| 85 | — | `get_results` | only a `$wpdb` table name | $wpdb->posts — a $wpdb table name |
| 92 | — | `get_var` | prepared | $wpdb->posts — a $wpdb table name |

### core/journey.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 92 | — | `get_var` | prepared | $wpdb->posts — a $wpdb table name |
| 119 | — | `get_var` | prepared | — |
| 126 | — | `get_row` | prepared | — |
| 140 | — | `get_var` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->postmeta — a $wpdb table name |
| 209 | — | `get_results` | prepared | — |

### core/librarian.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 290 | — | `get_col` | prepared | — |
| 365 | — | `get_row` | prepared | — |
| 417 | — | `query` | prepared | — |
| 493 | — | `get_results` | prepared | $wpdb->posts — a $wpdb table name |
| 728 | — | `get_row` | prepared | — |
| 882 | — | `get_col` | prepared | $wpdb->term_relationships — a $wpdb table name; $wpdb->term_taxonomy — a $wpdb table name; implode( ', ', array_fill( 0, count( $ids ), '%d' ) ) — implode of a generated %d placeholder list |
| 1258 | — | `insert` | built and escaped by `$wpdb` | values escaped by $wpdb; table: vergeml_librarian_batches_table() returns only a $wpdb table name |
| 1495 | — | `get_results` | prepared | implode( ', ', array_fill( 0, count( $ids ), '%d' ) ) — implode of a generated %d placeholder list |
| 1786 | — | `query` | prepared | implode( ', ', array_fill( 0, count( $moves ), '( %d, %d, %d, %d, 0, %s, CASE WHEN %d = 1 THEN %f END, %d, CASE WHEN %d = 1 THEN %f END, %s, %s, %d, %s, %s )' ) ) — implode of a generated %d placeholder list |
| 2065 | — | `get_results` | prepared | — |
| 2104 | — | `get_results` | prepared | $wpdb->term_taxonomy — a $wpdb table name; $wpdb->term_relationships — a $wpdb table name; implode( ', ', array_fill( 0, count( $term_ids ), '%d' ) ) — implode of a generated %d placeholder list |
| 2135 | — | `query` | prepared | implode( ', ', array_fill( 0, count( $move_ids ), '%d' ) ) — implode of a generated %d placeholder list |
| 2178 | — | `query` | prepared | implode( ', ', array_fill( 0, count( $batch_ids ), '%d' ) ) — implode of a generated %d placeholder list; implode( ', ', array_fill( 0, count( $attachment_ids ), '%d' ) ) — implode of a generated %d placeholder list |
| 2323 | — | `get_results` | prepared | — |
| 2375 | — | `get_var` | prepared | — |
| 2385 | — | `query` | prepared | — |
| 2391 | — | `query` | prepared | — |
| 2777 | — | `get_var` | prepared | — |
| 2874 | — | `get_results` | prepared | — |
| 2954 | — | `get_var` | prepared | — |

### core/media-list.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 527 | — | `get_var` | prepared | $wpdb->postmeta — a $wpdb table name; $wpdb->posts — a $wpdb table name |

### core/options-pages.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 1095 | — | `get_var` | prepared | — |
| 1546 | — | `delete` | built and escaped by `$wpdb` | values escaped by $wpdb; the table is a $wpdb property |
| 1573 | — | `query` | prepared | — |
| 1596 | — | `get_col` | prepared | — |
| 1611 | — | `query` | prepared | implode( ', ', array_fill( 0, count( $meta_ids ), '%d' ) ) — implode of a generated %d placeholder list |
| 1660 | — | `query` | only a `$wpdb` table name | $wpdb->options — a $wpdb table name |
| 1713 | — | `query` | only a `$wpdb` table name | $wpdb->options — a $wpdb table name |

### core/organize.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 350 | — | `get_row` | prepared | — |
| 372 | — | `get_row` | prepared | — |
| 458 | — | `insert` | built and escaped by `$wpdb` | values escaped by $wpdb; table: vergeml_organize_table() returns only a $wpdb table name |
| 511 | — | `query` | prepared | — |
| 542 | — | `get_var` | prepared | — |
| 667 | — | `get_results` | prepared | implode( ', ', array_fill( 0, count( $scope ), '%d' ) ) — implode of a generated %d placeholder list |
| 680 | — | `get_results` | prepared | — |
| 2745 | — | `get_var` | prepared | — |
| 2755 | — | `query` | prepared | — |
| 2795 | — | `get_var` | prepared | $wpdb->posts — a $wpdb table name |
| 2800 | — | `get_var` | prepared | — |
| 2827 | — | `get_var` | prepared | implode( ', ', array_fill( 0, count( $copy_ids ), '%d' ) ) — implode of a generated %d placeholder list |
| 3048 | — | `get_var` | prepared | — |
| 3064 | — | `query` | prepared | — |

### core/plan-tree.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 109 | — | `get_row` | prepared | — |
| 134 | — | `get_results` | prepared | — |
| 383 | — | `get_var` | prepared | — |
| 388 | — | `get_results` | prepared | — |
| 563 | — | `get_col` | prepared | $wpdb->postmeta — a $wpdb table name |
| 566 | — | `get_var` | prepared | — |
| 1444 | — | `get_col` | prepared | $wpdb->postmeta — a $wpdb table name |
| 1447 | — | `get_var` | prepared | — |
| 1746 | — | `get_col` | prepared | $wpdb->postmeta — a $wpdb table name |

### core/post-folders.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 136 | — | `get_results` | prepared | $wpdb->term_relationships — a $wpdb table name; $wpdb->term_taxonomy — a $wpdb table name; $wpdb->posts — a $wpdb table name |
| 188 | — | `get_var` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->term_relationships — a $wpdb table name; $wpdb->term_taxonomy — a $wpdb table name |

### core/rename.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 96 | — | `get_col` | prepared | $wpdb->posts — a $wpdb table name |
| 150 | — | `get_var` | prepared | $wpdb->posts — a $wpdb table name |

### core/rest-tree.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 488 | — | `get_var` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->term_relationships — a $wpdb table name; $wpdb->term_taxonomy — a $wpdb table name |

### core/search-meaning.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 338 | — | `get_results` | prepared | — |
| 366 | — | `get_var` | prepared | — |
| 422 | — | `get_results` | prepared | implode( ', ', array_fill( 0, count( $short_ids ), '%d' ) ) — implode of a generated %d placeholder list |
| 493 | — | `get_results` | prepared | — |
| 514 | — | `update` | built and escaped by `$wpdb` | values escaped by $wpdb; the table is a $wpdb property |
| 571 | — | `get_var` | prepared | — |

### core/search-try.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 58 | — | `get_row` | prepared | $wpdb->posts — a $wpdb table name |
| 76 | — | `get_results` | prepared | $wpdb->posts — a $wpdb table name |
| 145 | — | `get_results` | prepared | implode( ', ', array_fill( 0, count( $ids ), '%d' ) ) — implode of a generated %d placeholder list |

### core/seo-context.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 81 | — | `get_row` | prepared | — |
| 281 | — | `get_col` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->postmeta — a $wpdb table name; implode( ', ', array_fill( 0, count( $star ), '%s' ) ) — implode of a generated %d placeholder list; implode( ', ', array_fill( 0, count( $keys ), '%s' ) ) — implode of a generated %d placeholder list |
| 303 | — | `get_col` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->postmeta — a $wpdb table name; implode( ', ', array_fill( 0, count( $star ), '%s' ) ) — implode of a generated %d placeholder list; implode( ', ', array_fill( 0, count( $keys ), '%s' ) ) — implode of a generated %d placeholder list |

### core/smart-folders.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 323 | — | `get_results` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->postmeta — a $wpdb table name |
| 398 | — | `get_results` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->postmeta — a $wpdb table name |
| 618 | — | `get_var` | prepared | $wpdb->posts — a $wpdb table name |
| 727 | — | `get_var` | prepared | $wpdb->postmeta — a $wpdb table name |
| 810 | — | `get_results` | prepared | $wpdb->posts — a $wpdb table name |
| 841 | — | `get_results` | prepared | $wpdb->postmeta — a $wpdb table name; implode( ', ', array_fill( 0, count( $ids ), '%d' ) ) — implode of a generated %d placeholder list |
| 879 | — | `get_var` | only a `$wpdb` table name | $wpdb->posts — a $wpdb table name |
| 905 | — | `get_col` | prepared | $wpdb->options — a $wpdb table name |
| 946 | — | `get_col` | prepared | $wpdb->posts — a $wpdb table name |
| 983 | — | `get_var` | only a `$wpdb` table name | $wpdb->posts — a $wpdb table name |

### core/taxonomies.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 1136 | — | `get_results` | prepared | $wpdb->term_taxonomy — a $wpdb table name |
| 1147 | — | `get_results` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->term_relationships — a $wpdb table name; implode( ', ', array_fill( 0, count( $terms ), '%d' ) ) — implode of a generated %d placeholder list |
| 1586 | — | `get_var` | prepared | $wpdb->term_relationships — a $wpdb table name; $wpdb->posts — a $wpdb table name |
| 1596 | — | `update` | built and escaped by `$wpdb` | values escaped by $wpdb; the table is a $wpdb property |
| 1639 | — | `get_var` | prepared | $wpdb->term_relationships — a $wpdb table name; $wpdb->posts — a $wpdb table name; implode( ', ', array_fill( 0, count( $object_types ), '%s' ) ) — implode of a generated %d placeholder list |
| 1655 | — | `update` | built and escaped by `$wpdb` | values escaped by $wpdb; the table is a $wpdb property |

### core/utilities.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 281 | — | `get_col` | prepared | $wpdb->postmeta — a $wpdb table name |
| 346 | — | `get_col` | prepared | $wpdb->posts — a $wpdb table name |

### uninstall.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 36 | — | `query` | only a `$wpdb` table name | $wpdb->options — a $wpdb table name |
| 74 | — | `get_results` | prepared | $wpdb->term_taxonomy — a $wpdb table name |
| 90 | — | `query` | prepared | $wpdb->term_relationships — a $wpdb table name; implode( ', ', array_fill( 0, count( $tt_ids ), '%d' ) ) — implode of a generated %d placeholder list |
| 91 | — | `query` | prepared | $wpdb->termmeta — a $wpdb table name; implode( ', ', array_fill( 0, count( $term_ids ), '%d' ) ) — implode of a generated %d placeholder list |
| 92 | — | `query` | prepared | $wpdb->term_taxonomy — a $wpdb table name |
| 93 | — | `query` | prepared | $wpdb->terms — a $wpdb table name; implode( ', ', array_fill( 0, count( $term_ids ), '%d' ) ) — implode of a generated %d placeholder list |
| 105 | — | `query` | prepared | — |
| 115 | — | `query` | only a `$wpdb` table name | $wpdb->options — a $wpdb table name |
| 123 | — | `query` | only a `$wpdb` table name | $wpdb->postmeta — a $wpdb table name |
| 125 | — | `query` | only a `$wpdb` table name | $wpdb->termmeta — a $wpdb table name |
| 166 | — | `query` | only a `$wpdb` table name | $wpdb->sitemeta — a $wpdb table name |
| 173 | — | `query` | prepared | $wpdb->usermeta — a $wpdb table name |
| 195 | — | `query` | only a `$wpdb` table name | $wpdb->options — a $wpdb table name |
| 199 | — | `query` | only a `$wpdb` table name | $wpdb->usermeta — a $wpdb table name |

### vergelabs-media-library.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 412 | — | `update` | built and escaped by `$wpdb` | values escaped by $wpdb; the table is a $wpdb property |
| 421 | — | `get_col` | prepared | — |

## The helpers this classification trusts

Named, with the reason. `tests/security/db-calls.mjs` asserts each still casts: a
helper that loses its `absint()` while this list still trusts it is the quietest way
for every row above to become wrong at once.

| helper | why it is trusted |
|---|---|
| `vergeml_ids()` | maps absint over the array and drops anything falsy |
| `absint()` | core |
| `intval()` | core |

