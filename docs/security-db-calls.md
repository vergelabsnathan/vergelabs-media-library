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
| lines mentioning `$wpdb->` | 569 |
| `$wpdb->prepare()` calls | 195 |
| **calls that reach the server** | **214** |
| · prepared | 184 |
| · a literal, nothing interpolated | 0 |
| · only a `$wpdb` table name | 20 |
| · only integers it cast itself | 0 |
| · built and escaped by `$wpdb` (insert/update/delete) | 10 |
| · read by hand, with the reason | 0 |
| · **findings** | **0** |

Read over 68 shipped PHP files.

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
| 441 | — | `get_col` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->postmeta — a $wpdb table name |
| 493 | — | `get_var` | only a `$wpdb` table name | $wpdb->posts — a $wpdb table name |
| 597 | — | `get_results` | prepared | $wpdb->postmeta — a $wpdb table name |
| 819 | — | `get_results` | prepared | $wpdb->postmeta — a $wpdb table name |
| 866 | — | `get_results` | prepared | $wpdb->posts — a $wpdb table name; implode( ', ', array_fill( 0, count( $ids ), '%d' ) ) — implode of a generated %d placeholder list |
| 887 | — | `get_results` | prepared | $wpdb->postmeta — a $wpdb table name; implode( ', ', array_fill( 0, count( $ids ), '%d' ) ) — implode of a generated %d placeholder list |

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
| 93 | — | `get_var` | prepared | $wpdb->posts — a $wpdb table name |
| 120 | — | `get_var` | prepared | — |
| 127 | — | `get_row` | prepared | — |
| 141 | — | `get_var` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->postmeta — a $wpdb table name |
| 210 | — | `get_results` | prepared | — |

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
| 2953 | — | `get_var` | prepared | — |

### core/media-list.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 527 | — | `get_var` | prepared | $wpdb->postmeta — a $wpdb table name; $wpdb->posts — a $wpdb table name |

### core/options-pages.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 1095 | — | `get_var` | prepared | — |
| 1545 | — | `delete` | built and escaped by `$wpdb` | values escaped by $wpdb; the table is a $wpdb property |
| 1572 | — | `query` | prepared | — |
| 1595 | — | `get_col` | prepared | — |
| 1610 | — | `query` | prepared | implode( ', ', array_fill( 0, count( $meta_ids ), '%d' ) ) — implode of a generated %d placeholder list |
| 1659 | — | `query` | only a `$wpdb` table name | $wpdb->options — a $wpdb table name |
| 1712 | — | `query` | only a `$wpdb` table name | $wpdb->options — a $wpdb table name |

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
| 1558 | — | `get_var` | prepared | $wpdb->term_relationships — a $wpdb table name; $wpdb->posts — a $wpdb table name |
| 1568 | — | `update` | built and escaped by `$wpdb` | values escaped by $wpdb; the table is a $wpdb property |
| 1611 | — | `get_var` | prepared | $wpdb->term_relationships — a $wpdb table name; $wpdb->posts — a $wpdb table name; implode( ', ', array_fill( 0, count( $object_types ), '%s' ) ) — implode of a generated %d placeholder list |
| 1627 | — | `update` | built and escaped by `$wpdb` | values escaped by $wpdb; the table is a $wpdb property |

### core/utilities.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 281 | — | `get_col` | prepared | $wpdb->postmeta — a $wpdb table name |
| 346 | — | `get_col` | prepared | $wpdb->posts — a $wpdb table name |

### uninstall.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 36 | — | `query` | only a `$wpdb` table name | $wpdb->options — a $wpdb table name |
| 73 | — | `get_results` | prepared | $wpdb->term_taxonomy — a $wpdb table name |
| 89 | — | `query` | prepared | $wpdb->term_relationships — a $wpdb table name; implode( ', ', array_fill( 0, count( $tt_ids ), '%d' ) ) — implode of a generated %d placeholder list |
| 90 | — | `query` | prepared | $wpdb->termmeta — a $wpdb table name; implode( ', ', array_fill( 0, count( $term_ids ), '%d' ) ) — implode of a generated %d placeholder list |
| 91 | — | `query` | prepared | $wpdb->term_taxonomy — a $wpdb table name |
| 92 | — | `query` | prepared | $wpdb->terms — a $wpdb table name; implode( ', ', array_fill( 0, count( $term_ids ), '%d' ) ) — implode of a generated %d placeholder list |
| 104 | — | `query` | prepared | — |
| 114 | — | `query` | only a `$wpdb` table name | $wpdb->options — a $wpdb table name |
| 122 | — | `query` | only a `$wpdb` table name | $wpdb->postmeta — a $wpdb table name |
| 124 | — | `query` | only a `$wpdb` table name | $wpdb->termmeta — a $wpdb table name |
| 165 | — | `query` | only a `$wpdb` table name | $wpdb->sitemeta — a $wpdb table name |
| 172 | — | `query` | prepared | $wpdb->usermeta — a $wpdb table name |
| 194 | — | `query` | only a `$wpdb` table name | $wpdb->options — a $wpdb table name |
| 198 | — | `query` | only a `$wpdb` table name | $wpdb->usermeta — a $wpdb table name |

## The helpers this classification trusts

Named, with the reason. `tests/security/db-calls.mjs` asserts each still casts: a
helper that loses its `absint()` while this list still trusts it is the quietest way
for every row above to become wrong at once.

| helper | why it is trusted |
|---|---|
| `vergeml_ids()` | maps absint over the array and drops anything falsy |
| `absint()` | core |
| `intval()` | core |

