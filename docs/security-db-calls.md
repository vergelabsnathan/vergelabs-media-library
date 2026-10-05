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
| lines mentioning `$wpdb->` | 560 |
| `$wpdb->prepare()` calls | 148 |
| **calls that reach the server** | **221** |
| · prepared | 131 |
| · a literal, nothing interpolated | 0 |
| · only a `$wpdb` table name | 64 |
| · only integers it cast itself | 3 |
| · built and escaped by `$wpdb` (insert/update/delete) | 19 |
| · read by hand, with the reason | 4 |
| · **findings** | **0** |

Read over 68 shipped PHP files.

## Findings

None. Every call that reaches the server is prepared, is a literal, interpolates
only a `$wpdb` table name, interpolates only a value proven integer where it is
written, or is built by `$wpdb` itself.

## Read by hand

The tool proves 217 of 221 calls on its own. These 4 assemble their
SQL from fragments across a nested loop or a function boundary, which is further
than a static reader should be trusted to follow, so each was read and the reason
written down. Each is keyed by a hash of its own SQL, not by its line, so editing
one of these queries expires its review and turns the suite red.

| where | fingerprint | method | why it is safe |
|---|---|---|---|
| core/auto-file.php:270 | `0605b2be08` | `get_row` | $words is vergeml_filing_words_sql( 'i' ) (core/filing.php): a literal SELECT list and LEFT JOINs on {$wpdb->posts} and {$wpdb->postmeta} with literal meta keys, the alias its only argument and a literal 'i' at every call site; the one id is bound through %d |
| core/folder-talk.php:1131 | `ab6eb6af5f` | `get_results` | $words is vergeml_filing_words_sql( 'i' ) (core/filing.php): a literal SELECT list and LEFT JOINs on {$wpdb->posts} and {$wpdb->postmeta} with literal meta keys, the alias its only argument and a literal 'i' at every call site; $product is vergeml_filing_product_sql( 'i' ): literal correlated subqueries on {$wpdb->posts} and {$wpdb->postmeta}, or the literal NULL AS product_id, and an empty join; $only implodes array_map( 'intval', ... ) of the round's ids |
| core/guide.php:1908 | `f602036f5a` | `get_results` | the ids are bound through one %d per id (array_fill), and the meta key through %s; $words is vergeml_filing_words_sql( 'i' ) (core/filing.php): a literal SELECT list and LEFT JOINs on {$wpdb->posts} and {$wpdb->postmeta} with literal meta keys, the alias its only argument and a literal 'i' at every call site; $product is vergeml_filing_product_sql( 'i' ): literal correlated subqueries on {$wpdb->posts} and {$wpdb->postmeta}, or the literal NULL AS product_id, and an empty join |
| core/guide.php:3220 | `1e15f87d19` | `get_results` | the ids are bound through one %d per id (array_fill); $words is vergeml_filing_words_sql( 'i' ) (core/filing.php): a literal SELECT list and LEFT JOINs on {$wpdb->posts} and {$wpdb->postmeta} with literal meta keys, the alias its only argument and a literal 'i' at every call site |

## Every call

Sorted by file. The proof column says why the tool is satisfied; where it names a
variable, every assignment to that variable inside the enclosing function was
checked, and one unproven assignment is enough to make the whole call a finding.


### core/ai-index.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 55 | — | `query` | only a `$wpdb` table name | $wpdb->prefix — a $wpdb table name |
| 187 | — | `get_var` | prepared | — |
| 259 | — | `get_results` | prepared | $wpdb->vergeml_ai_index — a $wpdb table name; $ph — $ph is only ever implode of a generated %d placeholder list |
| 286 | — | `get_row` | prepared | $wpdb->vergeml_ai_index — a $wpdb table name |
| 392 | — | `update` | built and escaped by `$wpdb` | values escaped by $wpdb; table: vergeml_index_table() returns only a $wpdb table name |
| 402 | — | `insert` | built and escaped by `$wpdb` | values escaped by $wpdb; table: vergeml_index_table() returns only a $wpdb table name |
| 415 | — | `delete` | built and escaped by `$wpdb` | values escaped by $wpdb; table: vergeml_index_table() returns only a $wpdb table name |
| 666 | — | `get_row` | only a `$wpdb` table name | $wpdb->vergeml_ai_index — a $wpdb table name |
| 758 | — | `get_col` | prepared | $wpdb->vergeml_ai_index — a $wpdb table name |
| 804 | — | `get_col` | prepared | $wpdb->postmeta — a $wpdb table name; $wpdb->vergeml_ai_index — a $wpdb table name |

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
| 137 | — | `get_row` | prepared | $t — $t is only ever text holding only text holding only text holding only both arms of a ternary: a $wpdb table name / a string literal in our own source |
| 154 | — | `get_results` | only a `$wpdb` table name | $t — $t is only ever text holding only text holding only text holding only both arms of a ternary: a $wpdb table name / a string literal in our own source |
| 155 | — | `get_var` | only a `$wpdb` table name | $t — $t is only ever text holding only text holding only text holding only both arms of a ternary: a $wpdb table name / a string literal in our own source |

### core/ai.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 815 | — | `get_var` | prepared | $wpdb->postmeta — a $wpdb table name; $wpdb->vergeml_ai_index — a $wpdb table name |
| 1158 | — | `get_col` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->postmeta — a $wpdb table name |
| 1174 | — | `get_col` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->vergeml_ai_index — a $wpdb table name |
| 1217 | — | `get_var` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->postmeta — a $wpdb table name |
| 1226 | — | `get_var` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->vergeml_ai_index — a $wpdb table name |
| 1620 | — | `get_col` | prepared | $wpdb->vergeml_ai_index — a $wpdb table name; $wpdb->postmeta — a $wpdb table name |
| 1638 | — | `get_var` | only a `$wpdb` table name | $wpdb->vergeml_ai_index — a $wpdb table name; $wpdb->postmeta — a $wpdb table name |
| 1884 | — | `get_var` | prepared | $wpdb->posts — a $wpdb table name |
| 1885 | — | `get_var` | only a `$wpdb` table name | $wpdb->vergeml_ai_index — a $wpdb table name |

### core/auto-file.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 100 | — | `get_col` | prepared | $wpdb->term_relationships — a $wpdb table name; $wpdb->term_taxonomy — a $wpdb table name |
| 270 | — | `get_row` | read by hand — see the reason | $wpdb->vergeml_ai_index — a $wpdb table name |
| 486 | — | `get_var` | prepared | $wpdb->vergeml_librarian_batches — a $wpdb table name |
| 513 | — | `insert` | built and escaped by `$wpdb` | values escaped by $wpdb; table: vergeml_librarian_batches_table() returns only a $wpdb table name |
| 564 | — | `get_col` | prepared | $wpdb->posts — a $wpdb table name; $table — $table is only ever vergeml_index_table() returns only a $wpdb table name; $wpdb->term_relationships — a $wpdb table name; $ph — $ph is only ever implode of a generated %d placeholder list |
| 775 | — | `get_var` | prepared | $wpdb->posts — a $wpdb table name; $described — $described is only ever both arms of a ternary: text holding only $table is only ever vergeml_index_table() returns only a $wpdb table name / a string literal in our own source; $wpdb->term_relationships — a $wpdb table name; $ph — $ph is only ever implode of a generated %d placeholder list |

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
| 1147 | — | `get_results` | prepared | $wpdb->term_relationships — a $wpdb table name; $wpdb->term_taxonomy — a $wpdb table name; $wpdb->vergeml_ai_index — a $wpdb table name; $wpdb->termmeta — a $wpdb table name; $in — $in is only ever implode of a generated %d placeholder list |
| 1178 | — | `get_results` | prepared | $wpdb->term_relationships — a $wpdb table name; $wpdb->term_taxonomy — a $wpdb table name; $wpdb->vergeml_ai_index — a $wpdb table name; $in — $in is only ever implode of a generated %d placeholder list |

### core/folder-talk.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 183 | — | `get_var` | only a `$wpdb` table name | $wpdb->vergeml_ai_index — a $wpdb table name |
| 193 | — | `get_results` | prepared | $wpdb->vergeml_ai_index — a $wpdb table name |
| 238 | — | `get_var` | only a `$wpdb` table name | $wpdb->vergeml_ai_index — a $wpdb table name |
| 239 | — | `get_var` | only a `$wpdb` table name | $wpdb->vergeml_ai_index — a $wpdb table name |
| 274 | — | `get_var` | only a `$wpdb` table name | $wpdb->vergeml_ai_index — a $wpdb table name |
| 283 | — | `get_results` | prepared | $wpdb->vergeml_ai_index — a $wpdb table name |
| 954 | — | `get_var` | only a `$wpdb` table name | $wpdb->vergeml_ai_index — a $wpdb table name |
| 1131 | — | `get_results` | read by hand — see the reason | $wpdb->term_relationships — a $wpdb table name; $wpdb->term_taxonomy — a $wpdb table name; $wpdb->termmeta — a $wpdb table name; $wpdb->vergeml_ai_index — a $wpdb table name; $wpdb->postmeta — a $wpdb table name; $only — $only is only ever both arms of a ternary: text holding only implode of a generated %d placeholder list / a string literal in our own source |
| 2257 | — | `get_results` | prepared | $wpdb->vergeml_ai_index — a $wpdb table name; $ph — $ph is only ever implode of a generated %d placeholder list |
| 2535 | — | `get_var` | prepared | $wpdb->vergeml_ai_index — a $wpdb table name; $wpdb->term_relationships — a $wpdb table name; $wpdb->term_taxonomy — a $wpdb table name |

### core/guide.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 270 | — | `get_col` | only a `$wpdb` table name | $wpdb->postmeta — a $wpdb table name; $wpdb->posts — a $wpdb table name |
| 366 | — | `get_var` | only a `$wpdb` table name | $wpdb->posts — a $wpdb table name |
| 405 | — | `get_row` | prepared | $wpdb->term_relationships — a $wpdb table name; $wpdb->term_taxonomy — a $wpdb table name; $t — $t is only ever a $wpdb table name |
| 416 | — | `get_row` | only a `$wpdb` table name | $t — $t is only ever a $wpdb table name |
| 522 | — | `get_var` | only a `$wpdb` table name | $wpdb->vergeml_ai_index — a $wpdb table name |
| 736 | — | `get_var` | only a `$wpdb` table name | $t — $t is only ever a $wpdb table name |
| 737 | — | `get_var` | only a `$wpdb` table name | $t — $t is only ever a $wpdb table name |
| 738 | — | `get_results` | only a `$wpdb` table name | $t — $t is only ever a $wpdb table name |
| 740 | — | `get_var` | only a `$wpdb` table name | $t — $t is only ever a $wpdb table name |
| 741 | — | `get_var` | only a `$wpdb` table name | $t — $t is only ever a $wpdb table name |
| 742 | — | `get_var` | only a `$wpdb` table name | $t — $t is only ever a $wpdb table name |
| 743 | — | `get_results` | prepared | $t — $t is only ever a $wpdb table name |
| 785 | — | `get_var` | prepared | $t — $t is only ever a $wpdb table name; $wpdb->term_relationships — a $wpdb table name; $wpdb->term_taxonomy — a $wpdb table name |
| 1908 | — | `get_results` | read by hand — see the reason | $wpdb->vergeml_ai_index — a $wpdb table name; $wpdb->postmeta — a $wpdb table name; $ph — $ph is only ever implode of a generated %d placeholder list |
| 2785 | — | `get_results` | only integers it cast itself | $select — $select is only ever a string literal in our own source / appended a string literal in our own source; $t — $t is only ever a $wpdb table name; $join — $join is only ever a string literal in our own source / appended text holding only a $wpdb table name / appended a $wpdb->prepare() fragment whose format string holds only a $wpdb table name; $where — $where is only ever a string literal in our own source / appended a $wpdb->prepare() fragment whose format string holds only a $wpdb table name / appended a $wpdb->prepare() fragment whose format string holds only a literal, nothing interpolated; $group — $group is only ever a string literal in our own source; $limit_sql — $limit_sql is only ever a string literal in our own source / a $wpdb->prepare() fragment whose format string holds only a literal, nothing interpolated |
| 3220 | — | `get_results` | read by hand — see the reason | $wpdb->vergeml_ai_index — a $wpdb table name; $ph — $ph is only ever implode of a generated %d placeholder list |

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
| 882 | — | `get_results` | prepared | $wpdb->posts — a $wpdb table name; $placeholders — $placeholders is only ever implode of a generated %d placeholder list |
| 903 | — | `get_results` | prepared | $wpdb->postmeta — a $wpdb table name; $placeholders — $placeholders is only ever implode of a generated %d placeholder list |

### core/import-csv.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 488 | — | `get_col` | prepared | $wpdb->posts — a $wpdb table name; $marks — $marks is only ever implode of a generated %d placeholder list |

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
| 884 | — | `get_col` | prepared | $wpdb->term_relationships — a $wpdb table name; $wpdb->term_taxonomy — a $wpdb table name; $placeholders — $placeholders is only ever implode of a generated %d placeholder list |
| 1260 | — | `insert` | built and escaped by `$wpdb` | values escaped by $wpdb; table: vergeml_librarian_batches_table() returns only a $wpdb table name |
| 1498 | — | `get_results` | prepared | $wpdb->vergeml_ai_index — a $wpdb table name; $in — $in is only ever implode of a generated %d placeholder list |
| 1812 | — | `query` | prepared | $wpdb->vergeml_librarian_moves — a $wpdb table name; $placeholders — $placeholders is only ever implode of $rows is only ever an empty array initialiser / text holding only both arms of a ternary: a string literal in our own source / a string literal in our own source |
| 2091 | — | `get_results` | prepared | $wpdb->vergeml_librarian_moves — a $wpdb table name |
| 2131 | — | `get_results` | prepared | $wpdb->term_taxonomy — a $wpdb table name; $wpdb->term_relationships — a $wpdb table name; $placeholders — $placeholders is only ever implode of a generated %d placeholder list |
| 2164 | — | `query` | prepared | $wpdb->vergeml_librarian_moves — a $wpdb table name; $placeholders — $placeholders is only ever implode of a generated %d placeholder list |
| 2210 | — | `query` | prepared | $wpdb->vergeml_librarian_moves — a $wpdb table name; $batches — $batches is only ever implode of a generated %d placeholder list; $files — $files is only ever implode of a generated %d placeholder list |
| 2355 | — | `get_results` | prepared | $wpdb->vergeml_librarian_batches — a $wpdb table name |
| 2406 | — | `get_var` | prepared | $wpdb->vergeml_librarian_batches — a $wpdb table name |
| 2415 | — | `query` | prepared | $wpdb->vergeml_librarian_moves — a $wpdb table name |
| 2420 | — | `query` | prepared | $wpdb->vergeml_librarian_batches — a $wpdb table name |
| 2805 | — | `get_var` | only a `$wpdb` table name | $wpdb->vergeml_ai_index — a $wpdb table name |
| 2902 | — | `get_results` | prepared | $wpdb->vergeml_librarian_moves — a $wpdb table name |
| 2980 | — | `get_var` | prepared | $wpdb->vergeml_librarian_batches — a $wpdb table name |

### core/media-list.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 527 | — | `get_var` | prepared | $wpdb->postmeta — a $wpdb table name; $wpdb->posts — a $wpdb table name |

### core/options-pages.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 1100 | — | `get_var` | prepared | — |
| 1543 | — | `delete` | built and escaped by `$wpdb` | values escaped by $wpdb; the table is a $wpdb property |
| 1551 | — | `get_results` | prepared | $wpdb->term_relationships — a $wpdb table name; $wpdb->posts — a $wpdb table name; $rows2remove_format — $rows2remove_format is only ever implode of a generated %d placeholder list |
| 1572 | — | `query` | prepared | $wpdb->term_relationships — a $wpdb table name; $wpdb->posts — a $wpdb table name; $rows2remove_format — $rows2remove_format is only ever implode of a generated %d placeholder list |
| 1615 | — | `query` | only integers it cast itself | $wpdb->prefix — a $wpdb table name; $table — $table is only ever each item of a literal array in our own source |
| 1638 | — | `get_col` | prepared | $id_column — $id_column is only ever a string literal in our own source; $table — $table is only ever a core table name from _get_meta_table() |
| 1653 | — | `query` | prepared | $table — $table is only ever a core table name from _get_meta_table(); $id_column — $id_column is only ever a string literal in our own source; $placeholders — $placeholders is only ever implode of a generated %d placeholder list |
| 1700 | — | `query` | only a `$wpdb` table name | $wpdb->options — a $wpdb table name |
| 1753 | — | `query` | only a `$wpdb` table name | $wpdb->options — a $wpdb table name |

### core/organize.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 356 | — | `get_row` | prepared | $wpdb->vergeml_organize_runs — a $wpdb table name |
| 377 | — | `get_row` | only a `$wpdb` table name | $wpdb->vergeml_organize_runs — a $wpdb table name |
| 463 | — | `insert` | built and escaped by `$wpdb` | values escaped by $wpdb; table: vergeml_organize_table() returns only a $wpdb table name |
| 516 | — | `query` | prepared | $wpdb->vergeml_organize_runs — a $wpdb table name |
| 546 | — | `get_var` | only a `$wpdb` table name | $wpdb->vergeml_ai_index — a $wpdb table name |
| 681 | — | `get_results` | prepared | $wpdb->vergeml_ai_index — a $wpdb table name; $placeholders — $placeholders is only ever implode of a generated %d placeholder list |
| 694 | — | `get_results` | prepared | $wpdb->vergeml_ai_index — a $wpdb table name |
| 2758 | — | `get_var` | prepared | $wpdb->vergeml_organize_runs — a $wpdb table name |
| 2767 | — | `query` | prepared | $wpdb->vergeml_organize_runs — a $wpdb table name |
| 2806 | — | `get_var` | prepared | $wpdb->posts — a $wpdb table name |
| 2811 | — | `get_var` | only a `$wpdb` table name | $wpdb->vergeml_ai_index — a $wpdb table name |
| 2840 | — | `get_var` | prepared | $wpdb->vergeml_ai_index — a $wpdb table name; $placeholders — $placeholders is only ever implode of a generated %d placeholder list |
| 3061 | — | `get_var` | prepared | $wpdb->vergeml_organize_runs — a $wpdb table name |
| 3076 | — | `query` | prepared | $wpdb->vergeml_organize_runs — a $wpdb table name |

### core/plan-tree.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 109 | — | `get_row` | only a `$wpdb` table name | $t — $t is only ever a $wpdb table name |
| 134 | — | `get_results` | prepared | $t — $t is only ever a $wpdb table name |
| 383 | — | `get_var` | only a `$wpdb` table name | $t — $t is only ever a $wpdb table name |
| 388 | — | `get_results` | prepared | $t — $t is only ever a $wpdb table name |
| 563 | — | `get_col` | prepared | $wpdb->postmeta — a $wpdb table name |
| 566 | — | `get_var` | only a `$wpdb` table name | $wpdb->vergeml_ai_index — a $wpdb table name |
| 1444 | — | `get_col` | prepared | $wpdb->postmeta — a $wpdb table name |
| 1447 | — | `get_var` | only a `$wpdb` table name | $wpdb->vergeml_ai_index — a $wpdb table name |
| 1746 | — | `get_col` | prepared | $wpdb->postmeta — a $wpdb table name |

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
| 149 | — | `get_var` | only a `$wpdb` table name | $wpdb->vergeml_ai_index — a $wpdb table name; $wpdb->posts — a $wpdb table name |

### core/rest-tree.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 459 | — | `get_var` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->term_relationships — a $wpdb table name; $wpdb->term_taxonomy — a $wpdb table name |

### core/search-meaning.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 338 | — | `get_results` | prepared | $wpdb->vergeml_ai_index — a $wpdb table name |
| 365 | — | `get_var` | only a `$wpdb` table name | $wpdb->vergeml_ai_index — a $wpdb table name |
| 421 | — | `get_results` | prepared | $wpdb->vergeml_ai_index — a $wpdb table name; $ph — $ph is only ever implode of a generated %d placeholder list |
| 492 | — | `get_results` | prepared | $wpdb->vergeml_ai_index — a $wpdb table name |
| 512 | — | `update` | built and escaped by `$wpdb` | values escaped by $wpdb; the table is a $wpdb property |
| 569 | — | `get_var` | only a `$wpdb` table name | $wpdb->vergeml_ai_index — a $wpdb table name |

### core/search-try.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 58 | — | `get_row` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->vergeml_ai_index — a $wpdb table name |
| 76 | — | `get_results` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->vergeml_ai_index — a $wpdb table name |
| 146 | — | `get_results` | prepared | $wpdb->vergeml_ai_index — a $wpdb table name; $ph — $ph is only ever implode of a generated %d placeholder list |

### core/seo-context.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 81 | — | `get_row` | prepared | $wpdb->prefix — a $wpdb table name |
| 279 | — | `get_col` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->postmeta — a $wpdb table name; $wpdb->prefix — a $wpdb table name |
| 301 | — | `get_col` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->postmeta — a $wpdb table name |

### core/smart-folders.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 323 | — | `get_results` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->postmeta — a $wpdb table name; $wpdb->vergeml_ai_index — a $wpdb table name |
| 398 | — | `get_results` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->postmeta — a $wpdb table name |
| 618 | — | `get_var` | prepared | $wpdb->posts — a $wpdb table name |
| 727 | — | `get_var` | prepared | $wpdb->postmeta — a $wpdb table name |
| 810 | — | `get_results` | prepared | $wpdb->posts — a $wpdb table name |
| 845 | — | `get_results` | prepared | $wpdb->postmeta — a $wpdb table name; $placeholders — $placeholders is only ever implode of a generated %d placeholder list |
| 884 | — | `get_var` | only a `$wpdb` table name | $wpdb->posts — a $wpdb table name |
| 910 | — | `get_col` | prepared | $wpdb->options — a $wpdb table name |
| 951 | — | `get_col` | prepared | $wpdb->posts — a $wpdb table name |
| 988 | — | `get_var` | only a `$wpdb` table name | $wpdb->posts — a $wpdb table name |

### core/taxonomies.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 1117 | — | `get_results` | prepared | $wpdb->term_taxonomy — a $wpdb table name |
| 1130 | — | `get_results` | prepared | $wpdb->posts — a $wpdb table name; $wpdb->term_relationships — a $wpdb table name; $terms_format — $terms_format is only ever implode of a generated %d placeholder list |
| 1461 | — | `update` | built and escaped by `$wpdb` | values escaped by $wpdb; the table is a $wpdb property |
| 1567 | — | `get_var` | prepared | $wpdb->term_relationships — a $wpdb table name; $wpdb->posts — a $wpdb table name |
| 1577 | — | `update` | built and escaped by `$wpdb` | values escaped by $wpdb; the table is a $wpdb property |
| 1623 | — | `get_var` | prepared | $wpdb->term_relationships — a $wpdb table name; $wpdb->posts — a $wpdb table name; $placeholders — $placeholders is only ever implode of a generated %d placeholder list |
| 1640 | — | `update` | built and escaped by `$wpdb` | values escaped by $wpdb; the table is a $wpdb property |

### core/utilities.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 281 | — | `get_col` | prepared | $wpdb->postmeta — a $wpdb table name |
| 346 | — | `get_col` | prepared | $table — $table is only ever vergeml_index_table() returns only a $wpdb table name; $wpdb->posts — a $wpdb table name |

### uninstall.php

| line | in | method | class | proof |
|---|---|---|---|---|
| 36 | — | `query` | only a `$wpdb` table name | $wpdb->options — a $wpdb table name |
| 66 | — | `get_results` | prepared | $wpdb->term_taxonomy — a $wpdb table name |
| 86 | — | `query` | prepared | $wpdb->term_relationships — a $wpdb table name; $tt_in — $tt_in is only ever implode of a generated %d placeholder list |
| 87 | — | `query` | prepared | $wpdb->termmeta — a $wpdb table name; $term_in — $term_in is only ever implode of a generated %d placeholder list |
| 88 | — | `query` | prepared | $wpdb->term_taxonomy — a $wpdb table name |
| 89 | — | `query` | prepared | $wpdb->terms — a $wpdb table name; $term_in — $term_in is only ever implode of a generated %d placeholder list |
| 99 | — | `query` | prepared | $wpdb->term_relationships — a $wpdb table name; $wpdb->posts — a $wpdb table name; $tt_in — $tt_in is only ever implode of a generated %d placeholder list |
| 114 | — | `query` | only integers it cast itself | $wpdb->prefix — a $wpdb table name; $table — $table is only ever each item of a literal array in our own source |
| 124 | — | `query` | only a `$wpdb` table name | $wpdb->options — a $wpdb table name |
| 132 | — | `query` | only a `$wpdb` table name | $wpdb->postmeta — a $wpdb table name |
| 134 | — | `query` | only a `$wpdb` table name | $wpdb->termmeta — a $wpdb table name |
| 175 | — | `query` | only a `$wpdb` table name | $wpdb->sitemeta — a $wpdb table name |
| 182 | — | `query` | prepared | $wpdb->usermeta — a $wpdb table name |
| 204 | — | `query` | only a `$wpdb` table name | $wpdb->options — a $wpdb table name |
| 208 | — | `query` | only a `$wpdb` table name | $wpdb->usermeta — a $wpdb table name |

## The helpers this classification trusts

Named, with the reason. `tests/security/db-calls.mjs` asserts each still casts: a
helper that loses its `absint()` while this list still trusts it is the quietest way
for every row above to become wrong at once.

| helper | why it is trusted |
|---|---|
| `vergeml_ids()` | maps absint over the array and drops anything falsy |
| `absint()` | core |
| `intval()` | core |

