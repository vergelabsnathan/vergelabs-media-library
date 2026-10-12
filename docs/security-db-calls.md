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
| lines mentioning `$wpdb->` | 600 |
| `$wpdb->prepare()` calls | 198 |
| **calls that reach the server** | **226** |
| · prepared | 187 |
| · a literal, nothing interpolated | 0 |
| · only a `$wpdb` table name | 20 |
| · only integers it cast itself | 0 |
| · built and escaped by `$wpdb` (insert/update/delete) | 19 |
| · read by hand, with the reason | 0 |
| · **findings** | **0** |

Read over 68 shipped PHP files.

## Findings

None. Every call that reaches the server is prepared, is a literal, interpolates
only a `$wpdb` table name, interpolates only a value proven integer where it is
written, or is built by `$wpdb` itself.

## Read by hand

The tool proves 226 of 226 calls on its own. These 0 assemble their
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
| 815 | — | `get_var` | prepared | $wpdb->postmeta — a $wpdb table name |
| 1159 | — | `get_col` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->postmeta — a $wpdb table name |
| 1175 | — | `get_col` | prepared | $wpdb->posts — a $wpdb table name |
| 1219 | — | `get_var` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->postmeta — a $wpdb table name |
| 1228 | — | `get_var` | prepared | $wpdb->posts — a $wpdb table name |
| 1623 | — | `get_col` | prepared | $wpdb->postmeta — a $wpdb table name |
| 1642 | — | `get_var` | prepared | $wpdb->postmeta — a $wpdb table name |
| 1889 | — | `get_var` | prepared | $wpdb->posts — a $wpdb table name |
| 1890 | — | `get_var` | prepared | — |

### core/auto-file.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 100 | — | `get_col` | prepared | $wpdb->term_relationships — a $wpdb table name; $wpdb->term_taxonomy — a $wpdb table name |
| 269 | — | `get_row` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->postmeta — a $wpdb table name |
| 492 | — | `get_var` | prepared | — |
| 520 | — | `insert` | built and escaped by `$wpdb` | values escaped by $wpdb; table: vergeml_librarian_batches_table() returns only a $wpdb table name |
| 570 | — | `get_col` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->term_relationships — a $wpdb table name; implode( ', ', array_fill( 0, count( $tt_ids ), '%d' ) ) — implode of a generated %d placeholder list |
| 777 | — | `get_var` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->term_relationships — a $wpdb table name; implode( ', ', array_fill( 0, count( $tt_ids ), '%d' ) ) — implode of a generated %d placeholder list |
| 789 | — | `get_var` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->term_relationships — a $wpdb table name; implode( ', ', array_fill( 0, count( $tt_ids ), '%d' ) ) — implode of a generated %d placeholder list |

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
| 1137 | — | `get_results` | prepared | $wpdb->postmeta — a $wpdb table name; $wpdb->posts — a $wpdb table name; $wpdb->term_relationships — a $wpdb table name; $wpdb->term_taxonomy — a $wpdb table name; $wpdb->termmeta — a $wpdb table name; implode( ', ', array_fill( 0, count( $only_list ), '%d' ) ) — implode of a generated %d placeholder list |
| 2278 | — | `get_results` | prepared | implode( ', ', array_fill( 0, count( $chunk ), '%d' ) ) — implode of a generated %d placeholder list |
| 2556 | — | `get_var` | prepared | $wpdb->term_relationships — a $wpdb table name; $wpdb->term_taxonomy — a $wpdb table name |

### core/guide.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 270 | — | `get_col` | only a `$wpdb` table name | $wpdb->postmeta — a $wpdb table name; $wpdb->posts — a $wpdb table name |
| 366 | — | `get_var` | only a `$wpdb` table name | $wpdb->posts — a $wpdb table name |
| 405 | — | `get_row` | prepared | $wpdb->term_relationships — a $wpdb table name; $wpdb->term_taxonomy — a $wpdb table name |
| 417 | — | `get_row` | prepared | — |
| 523 | — | `get_var` | prepared | — |
| 737 | — | `get_var` | prepared | — |
| 738 | — | `get_var` | prepared | — |
| 739 | — | `get_results` | prepared | — |
| 741 | — | `get_var` | prepared | — |
| 742 | — | `get_var` | prepared | — |
| 743 | — | `get_var` | prepared | — |
| 744 | — | `get_results` | prepared | — |
| 786 | — | `get_var` | prepared | $wpdb->term_relationships — a $wpdb table name; $wpdb->term_taxonomy — a $wpdb table name |
| 1915 | — | `get_results` | prepared | $wpdb->postmeta — a $wpdb table name; $wpdb->posts — a $wpdb table name; implode( ', ', array_fill( 0, count( $chunk ), '%d' ) ) — implode of a generated %d placeholder list |
| 1935 | — | `get_results` | prepared | $wpdb->postmeta — a $wpdb table name; $wpdb->posts — a $wpdb table name; implode( ', ', array_fill( 0, count( $chunk ), '%d' ) ) — implode of a generated %d placeholder list |
| 2808 | — | `get_results` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->term_relationships — a $wpdb table name; $wpdb->term_taxonomy — a $wpdb table name |
| 2830 | — | `get_results` | prepared | $wpdb->term_relationships — a $wpdb table name; $wpdb->term_taxonomy — a $wpdb table name |
| 2851 | — | `get_results` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->term_relationships — a $wpdb table name; $wpdb->term_taxonomy — a $wpdb table name |
| 2869 | — | `get_results` | prepared | $wpdb->term_relationships — a $wpdb table name; $wpdb->term_taxonomy — a $wpdb table name |
| 3333 | — | `get_results` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->postmeta — a $wpdb table name; implode( ', ', array_fill( 0, count( $chunk ), '%d' ) ) — implode of a generated %d placeholder list |

### core/health-delete.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 79 | — | `get_col` | prepared | $wpdb->postmeta — a $wpdb table name |
| 226 | — | `get_results` | prepared | $wpdb->posts — a $wpdb table name |
| 238 | — | `get_col` | prepared | $wpdb->postmeta — a $wpdb table name |
| 248 | — | `get_col` | prepared | $wpdb->postmeta — a $wpdb table name |
| 309 | — | `get_results` | prepared | $wpdb->posts — a $wpdb table name |
| 342 | — | `get_col` | prepared | $wpdb->postmeta — a $wpdb table name |
| 375 | — | `get_results` | prepared | $wpdb->postmeta — a $wpdb table name |
| 397 | — | `update` | built and escaped by `$wpdb` | values escaped by $wpdb; the table is a $wpdb property |
| 407 | — | `get_results` | prepared | $wpdb->postmeta — a $wpdb table name |
| 432 | — | `update` | built and escaped by `$wpdb` | values escaped by $wpdb; the table is a $wpdb property |

### core/health-keep.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 151 | — | `get_col` | prepared | $wpdb->postmeta — a $wpdb table name |
| 349 | — | `get_col` | prepared | $wpdb->postmeta — a $wpdb table name |
| 414 | — | `get_results` | prepared | $wpdb->postmeta — a $wpdb table name |
| 441 | — | `update` | built and escaped by `$wpdb` | values escaped by $wpdb; the table is a $wpdb property |
| 449 | — | `update` | built and escaped by `$wpdb` | values escaped by $wpdb; the table is a $wpdb property |
| 454 | — | `update` | built and escaped by `$wpdb` | values escaped by $wpdb; the table is a $wpdb property |
| 755 | — | `get_var` | prepared | $wpdb->postmeta — a $wpdb table name |
| 757 | — | `update` | built and escaped by `$wpdb` | values escaped by $wpdb; the table is a $wpdb property |
| 769 | — | `update` | built and escaped by `$wpdb` | values escaped by $wpdb; the table is a $wpdb property |

### core/health.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 441 | — | `get_col` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->postmeta — a $wpdb table name |
| 493 | — | `get_var` | only a `$wpdb` table name | $wpdb->posts — a $wpdb table name |
| 527 | — | `delete` | built and escaped by `$wpdb` | values escaped by $wpdb; the table is a $wpdb property |
| 610 | — | `get_results` | prepared | $wpdb->postmeta — a $wpdb table name |
| 832 | — | `get_results` | prepared | $wpdb->postmeta — a $wpdb table name |
| 879 | — | `get_results` | prepared | $wpdb->posts — a $wpdb table name; implode( ', ', array_fill( 0, count( $ids ), '%d' ) ) — implode of a generated %d placeholder list |
| 900 | — | `get_results` | prepared | $wpdb->postmeta — a $wpdb table name; implode( ', ', array_fill( 0, count( $ids ), '%d' ) ) — implode of a generated %d placeholder list |

### core/import-csv.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 486 | — | `get_col` | prepared | $wpdb->posts — a $wpdb table name; implode( ', ', array_fill( 0, count( $chunk ), '%d' ) ) — implode of a generated %d placeholder list |

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
| 85 | — | `get_results` | only a `$wpdb` table name | $wpdb->posts — a $wpdb table name |
| 92 | — | `get_var` | prepared | $wpdb->posts — a $wpdb table name |

### core/journey.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 89 | — | `get_var` | prepared | $wpdb->posts — a $wpdb table name |
| 116 | — | `get_var` | prepared | — |
| 123 | — | `get_row` | prepared | — |
| 137 | — | `get_var` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->postmeta — a $wpdb table name |
| 206 | — | `get_results` | prepared | — |

### core/librarian.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 290 | — | `get_col` | prepared | — |
| 364 | — | `get_row` | prepared | — |
| 416 | — | `query` | prepared | — |
| 492 | — | `get_results` | prepared | $wpdb->posts — a $wpdb table name |
| 727 | — | `get_row` | prepared | — |
| 881 | — | `get_col` | prepared | $wpdb->term_relationships — a $wpdb table name; $wpdb->term_taxonomy — a $wpdb table name; implode( ', ', array_fill( 0, count( $ids ), '%d' ) ) — implode of a generated %d placeholder list |
| 1257 | — | `insert` | built and escaped by `$wpdb` | values escaped by $wpdb; table: vergeml_librarian_batches_table() returns only a $wpdb table name |
| 1494 | — | `get_results` | prepared | implode( ', ', array_fill( 0, count( $ids ), '%d' ) ) — implode of a generated %d placeholder list |
| 1785 | — | `query` | prepared | implode( ', ', array_fill( 0, count( $moves ), '( %d, %d, %d, %d, 0, %s, CASE WHEN %d = 1 THEN %f END, %d, CASE WHEN %d = 1 THEN %f END, %s, %s, %d, %s, %s )' ) ) — implode of a generated %d placeholder list |
| 2064 | — | `get_results` | prepared | — |
| 2103 | — | `get_results` | prepared | $wpdb->term_taxonomy — a $wpdb table name; $wpdb->term_relationships — a $wpdb table name; implode( ', ', array_fill( 0, count( $term_ids ), '%d' ) ) — implode of a generated %d placeholder list |
| 2134 | — | `query` | prepared | implode( ', ', array_fill( 0, count( $move_ids ), '%d' ) ) — implode of a generated %d placeholder list |
| 2177 | — | `query` | prepared | implode( ', ', array_fill( 0, count( $batch_ids ), '%d' ) ) — implode of a generated %d placeholder list; implode( ', ', array_fill( 0, count( $attachment_ids ), '%d' ) ) — implode of a generated %d placeholder list |
| 2322 | — | `get_results` | prepared | — |
| 2374 | — | `get_var` | prepared | — |
| 2384 | — | `query` | prepared | — |
| 2390 | — | `query` | prepared | — |
| 2776 | — | `get_var` | prepared | — |
| 2873 | — | `get_results` | prepared | — |
| 2952 | — | `get_var` | prepared | — |

### core/media-list.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 527 | — | `get_var` | prepared | $wpdb->postmeta — a $wpdb table name; $wpdb->posts — a $wpdb table name |

### core/options-pages.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 1100 | — | `get_var` | prepared | — |
| 1543 | — | `delete` | built and escaped by `$wpdb` | values escaped by $wpdb; the table is a $wpdb property |
| 1550 | — | `get_results` | prepared | $wpdb->term_relationships — a $wpdb table name; $wpdb->posts — a $wpdb table name; implode( ', ', array_fill( 0, count( $term_pairs ), '%d' ) ) — implode of a generated %d placeholder list |
| 1570 | — | `query` | prepared | $wpdb->term_relationships — a $wpdb table name; $wpdb->posts — a $wpdb table name; implode( ', ', array_fill( 0, count( $term_pairs ), '%d' ) ) — implode of a generated %d placeholder list |
| 1612 | — | `query` | prepared | — |
| 1635 | — | `get_col` | prepared | — |
| 1650 | — | `query` | prepared | implode( ', ', array_fill( 0, count( $meta_ids ), '%d' ) ) — implode of a generated %d placeholder list |
| 1699 | — | `query` | only a `$wpdb` table name | $wpdb->options — a $wpdb table name |
| 1752 | — | `query` | only a `$wpdb` table name | $wpdb->options — a $wpdb table name |

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
| 132 | — | `get_results` | prepared | $wpdb->term_relationships — a $wpdb table name; $wpdb->term_taxonomy — a $wpdb table name; $wpdb->posts — a $wpdb table name |
| 176 | — | `get_var` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->term_relationships — a $wpdb table name; $wpdb->term_taxonomy — a $wpdb table name |

### core/rename-file.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 134 | — | `get_col` | prepared | — |
| 188 | — | `get_col` | prepared | — |

### core/rename.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 96 | — | `get_col` | prepared | $wpdb->posts — a $wpdb table name |
| 150 | — | `get_var` | prepared | $wpdb->posts — a $wpdb table name |

### core/rest-tree.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 459 | — | `get_var` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->term_relationships — a $wpdb table name; $wpdb->term_taxonomy — a $wpdb table name |

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
| 1117 | — | `get_results` | prepared | $wpdb->term_taxonomy — a $wpdb table name |
| 1128 | — | `get_results` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->term_relationships — a $wpdb table name; implode( ', ', array_fill( 0, count( $terms ), '%d' ) ) — implode of a generated %d placeholder list |
| 1459 | — | `update` | built and escaped by `$wpdb` | values escaped by $wpdb; the table is a $wpdb property |
| 1566 | — | `get_var` | prepared | $wpdb->term_relationships — a $wpdb table name; $wpdb->posts — a $wpdb table name |
| 1576 | — | `update` | built and escaped by `$wpdb` | values escaped by $wpdb; the table is a $wpdb property |
| 1619 | — | `get_var` | prepared | $wpdb->term_relationships — a $wpdb table name; $wpdb->posts — a $wpdb table name; implode( ', ', array_fill( 0, count( $object_types ), '%s' ) ) — implode of a generated %d placeholder list |
| 1635 | — | `update` | built and escaped by `$wpdb` | values escaped by $wpdb; the table is a $wpdb property |

### core/utilities.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 281 | — | `get_col` | prepared | $wpdb->postmeta — a $wpdb table name |
| 346 | — | `get_col` | prepared | $wpdb->posts — a $wpdb table name |

### uninstall.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 36 | — | `query` | only a `$wpdb` table name | $wpdb->options — a $wpdb table name |
| 66 | — | `get_results` | prepared | $wpdb->term_taxonomy — a $wpdb table name |
| 82 | — | `query` | prepared | $wpdb->term_relationships — a $wpdb table name; implode( ', ', array_fill( 0, count( $tt_ids ), '%d' ) ) — implode of a generated %d placeholder list |
| 83 | — | `query` | prepared | $wpdb->termmeta — a $wpdb table name; implode( ', ', array_fill( 0, count( $term_ids ), '%d' ) ) — implode of a generated %d placeholder list |
| 84 | — | `query` | prepared | $wpdb->term_taxonomy — a $wpdb table name |
| 85 | — | `query` | prepared | $wpdb->terms — a $wpdb table name; implode( ', ', array_fill( 0, count( $term_ids ), '%d' ) ) — implode of a generated %d placeholder list |
| 95 | — | `query` | prepared | $wpdb->term_relationships — a $wpdb table name; $wpdb->posts — a $wpdb table name; implode( ', ', array_fill( 0, count( $tt_ids ), '%d' ) ) — implode of a generated %d placeholder list |
| 110 | — | `query` | prepared | — |
| 120 | — | `query` | only a `$wpdb` table name | $wpdb->options — a $wpdb table name |
| 128 | — | `query` | only a `$wpdb` table name | $wpdb->postmeta — a $wpdb table name |
| 130 | — | `query` | only a `$wpdb` table name | $wpdb->termmeta — a $wpdb table name |
| 171 | — | `query` | only a `$wpdb` table name | $wpdb->sitemeta — a $wpdb table name |
| 178 | — | `query` | prepared | $wpdb->usermeta — a $wpdb table name |
| 200 | — | `query` | only a `$wpdb` table name | $wpdb->options — a $wpdb table name |
| 204 | — | `query` | only a `$wpdb` table name | $wpdb->usermeta — a $wpdb table name |

## The helpers this classification trusts

Named, with the reason. `tests/security/db-calls.mjs` asserts each still casts: a
helper that loses its `absint()` while this list still trusts it is the quietest way
for every row above to become wrong at once.

| helper | why it is trusted |
|---|---|
| `vergeml_ids()` | maps absint over the array and drops anything falsy |
| `absint()` | core |
| `intval()` | core |

