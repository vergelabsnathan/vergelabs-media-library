---
title: 'The CSV export cannot execute in a spreadsheet'
type: 'feature'
created: '2026-09-20'
status: 'review'
route: 'dispatch'
review_loop_iteration: 0
baseline_commit: '43003b4'
context:
  - '{project-root}/plans/finish-the-suite.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** `vergeml_csv_line()` writes a folder name verbatim. A folder named `=HYPERLINK("http://x","x")` — or `+cmd|…`, `-2+3`, `@SUM(…)` — becomes a formula the moment somebody opens the export in Excel, LibreOffice or Sheets, which is the one thing the file exists to be opened in. Anyone who can name a folder (an author, on a site where folders are shared) can plant a cell that runs when the administrator exports. FR12 in `plans/suite-readiness.md`.

**Approach:** Neutralise on the way out, undo on the way in, prove the pair by the round trip. A cell whose first character is one of OWASP's six — `=`, `+`, `-`, `@`, tab, CR — is written as `"'…"`: a single quote in front, inside a quoted cell. A spreadsheet reads a leading apostrophe as "this is text" and shows the name without it; the file stays readable by a person. The import strips exactly that prefix, so a folder that went out comes back with its name — including a folder called `-5`, which is a name and not a number. Every other cell is byte-for-byte what it was. The proof runs here, on Playground's PHP with the checkout mounted (the `csv-local` entry, `php: 'wasm'`), because wave 1 has no box; the box `csv` suite keeps its shape and 1c re-runs it after the deploy.

## Boundaries & Constraints

**Always:** the BOM, the `,` delimiter, the `\r\n` line end, the header row `folder,attachment_id,filename` and the import's `WP_Error` codes and `problems` shape stay what they are. The prefix goes on inside a quoted cell (`"'=…"`), never as a bare `'=…`. The pair is invertible: what `vergeml_csv_line()` writes, `vergeml_csv_parse()` reads back to the same name.

**Never:** the box, ms2 or the real shop; a network call beyond Playground's own boot; a change to `tests/import/csv.php`'s box fixture that this session cannot run; a file outside `core/import-csv.php`, the local suite, the `csv-local` registration in `tools/verify.mjs`, this spec and the handoff.

## I/O & Edge-Case Matrix

| Cell in (export) | Written as | Read back (import) | Why |
|---|---|---|---|
| `Acme` | `Acme` | `Acme` | plain name, unchanged |
| `=HYPERLINK("http://x","x")` | `"'=HYPERLINK(""http:--x"",""x"")"` | `=HYPERLINK("http:--x","x")` | the formula case; `/`→`-` is the path walker's existing rule, not this story's |
| `+1` `-1` `@x` | `"'+1"` `"'-1"` `"'@x"` | `+1` `-1` `@x` | OWASP's other three printable triggers |
| `-5` | `"'-5"` | `-5` | a name that is a number keeps being a name |
| `\tname` / `\rname` | `"'\tname"` / `"'\rname"` | `\tname` / `\rname` at the cell; the importer's existing `trim()` then drops the whitespace | tab and CR are triggers too; the trim is the importer's, pre-existing, and matches what `wp_insert_term` would do to the name anyway |
| `'=x` (a name that already starts with an apostrophe before a trigger) | `"''=x"` | `'=x` | the only way the pair stays invertible: export prefixes when the cell is `'*` + trigger, import strips one `'` when the cell is `'+` + trigger |
| `'Quoted` | `'Quoted` | `'Quoted` | an apostrophe before an ordinary character is nobody's trigger; untouched both ways |
| `12` in the id column | `12` | `12` | digits never start with a trigger; the id column is unaffected |
| `A, B` / `x"y` | `"A, B"` / `"x""y"` | as before | RFC 4180 quoting unchanged |

</frozen-after-approval>

## Code Map

- `core/import-csv.php:138-153` `vergeml_csv_line()` — the writer; the quote-or-not decision is a regex on `[",\r\n]`. The prefix lands here, and a prefixed cell is always quoted.
- `core/import-csv.php:272-273` `vergeml_csv_parse()` — the two cells the import reads (`$cells[0]` the path, `$cells[1]` the id), each through `trim()`. The strip goes before the trim.
- `core/import-csv.php:123-135` `vergeml_csv_path()` — `/` in a name becomes `-`; why the HYPERLINK name comes back with `http:--x`.
- `core/import-csv.php:380-427` `vergeml_csv_folder()` — splits the path on `/` and trims each part; the strip must have happened before this sees the cell.
- `tests/import/csv.php` — the box round trip (real terms, real MySQL); its section B asserts the RFC 4180 quoting with `vergeml_csv_line()` directly. Mirror for the local suite's shape.
- `tests/filing/pick.php:22-66` — how a fixture-only suite stands in for WordPress under the wasm runner: `define( 'ABSPATH' )`, the handful of helpers the file calls at load, then `require`.
- `tools/verify.mjs:730-799` `runPhpLocal()` — Playground's `run-blueprint` with a `runPHP` step, checkout at `/plugin`, stdout to `/out/result.txt`; passes only on the suite's own `N/N passed` line with N = N and exit 0.
- OWASP, CSV Injection — the character set: `=`, `+`, `-`, `@`, Tab (0x09), CR (0x0D).

## Tasks & Acceptance

**Execution:**
- [x] T1 The local suite `tests/import/csv-cells.php`: stubs for what `core/import-csv.php` reaches at load and inside the writer, the path walker and the parser (`add_action`, `add_filter`, `__`, `_n`, `number_format_i18n`, `WP_Error`, `is_wp_error`, `get_terms`, `get_objects_in_term`); the real `vergeml_csv_export_rows()` over a stubbed term list; the matrix above as rows: what the spreadsheet sees (`str_getcsv` of the written line begins with `'`), the cell-level pair, the parse-level round trip through `vergeml_csv_parse()`, the plain and apostrophe-first names untouched, the id column untouched, BOM/header/delimiter/error codes unchanged. Registered as `csv-local` (`env: 'local'`, `php: 'wasm'`) beside the box `csv` entry. Run first: red on the prefix rows.
- [x] T2 `core/import-csv.php`: `vergeml_csv_cell_out()` / `vergeml_csv_cell_in()` beside `vergeml_csv_line()`; the writer prefixes and quotes; the parser strips on both cells before its trim.
- [x] T3 `node tools/verify.mjs csv-local` green; the mutation (prefix removed) red on the named rows; restored, green again. `php -l` if a PHP binary exists (it does not on this machine — the wasm run is the syntax check).
- [x] T4 Commit on `story/4.1-csv-cells`; handoff `docs/handoffs/2026-09-20-wave1-B-csv-cells.md`.

**Acceptance Criteria:**
- Given a folder named `=HYPERLINK("http://x","x")`, when it is exported, then the cell is written `"'=HYPERLINK(…)"` and a CSV reader hands a spreadsheet a cell that begins with `'`.
- Given that export, when it is parsed by the importer, then the folder's name is the exported name without the prefix — and the same for names starting `+`, `-`, `@`, a tab, a CR, and for `-5`.
- Given a folder named `Acme` or `'Quoted`, when exported and parsed, then both cells are byte-for-byte unchanged.
- Given the import, when it is handed an empty file, a header-only file or an over-deep path, then the error codes are what they were.

## Decisions taken

- **Prefix inside the quoted cell, always quoted.** The mini-spec's `"'=HYPERLINK(...)"` shape. A bare `'=x` would also be safe in Excel, but quoting makes the choice visible in the file and costs nothing.
- **The pair is made invertible for apostrophe-first names.** Export prefixes on `^'*[=+\-@\t\r]`; import strips one `'` on `^'+[=+\-@\t\r]`. Without the `'*`, a folder already called `'=x` would go out as `'=x` and come back as `=x`. With it, `'=x` goes out as `''=x` and returns. A name like `'Quoted` never matches either side.
- **The strip is applied to both cells the parser reads** (path and id), for symmetry with the writer, which prefixes every field. An id cell never starts with a trigger in a file this plugin wrote, so nothing changes for real files.
- **The strip happens before `trim()`.** Otherwise `'\tname` would keep its `'` (trim does not reach past it) and the strip would then expose whitespace the importer trims anyway — same result, but the order is the honest one.
- **The box suite is not edited.** The mini-spec allows "or a sibling file if the box suite depends on box fixtures"; it does (real terms, `wp_insert_term`, `vergeml_import_run`). An unrun edit to a suite 1c will run on the box is a risk without a proof here. The local suite runs the real `vergeml_csv_export_rows()`, `vergeml_csv_path()`, `vergeml_csv_line()` and `vergeml_csv_parse()` — everything but the database.
- **Trigger set is OWASP's six**, no more. `|`, `%`, `;` and `\` appear on some lists for DDE or locale reasons; they are not what the mini-spec names and they would prefix ordinary names like `100% done`.

## Open questions

- **Tab- and CR-first names collapse at the importer's `trim()`** (pre-existing; `sanitize_text_field` in `wp_insert_term` would strip them from a term name too). The cell-level pair is exact; the parse-level result for `\tname` is `name`. Safe default taken: the trim stays. If a leading-whitespace name is ever meant to survive, that is the importer's trim to change, not this story's.
- **Should the box suite grow a `=HYPERLINK` folder through the real term round trip?** Not done here (see Decisions). 1c could add it when it runs `csv` on the box: one more path in the fixture, the count assertions 5→6, and the tidy list carries the returned name (`=HYPERLINK("http:--x","x")` — the path walker's `/`→`-`).
- **Excel shows the apostrophe or not?** Excel and LibreOffice hide a leading `'` in a text cell; Sheets shows it as typed for a CSV import in some versions. Either way the formula does not run, which is the requirement; a name shown as `'=x` in one program is cosmetic.

## Implementation Notes

- The change is 20 lines in `core/import-csv.php`: two helpers and two call sites. The local suite is the larger file.
- `-5` matters because the trigger is `-`, and a folder called `-5` is a real possibility on a site with numbered folders; the round trip proves it is not turned into `5` or refused.

## Spec Change Log

- 2026-09-20 written from the mini-spec B in `plans/finish-the-suite.md`; the apostrophe-first invertibility row and the trim note added after reading the parser (`trim()` on both cells, `vergeml_csv_folder()` trims each part again).

## Review Triage Log

## Verification

**Commands:**
- `node tools/verify.mjs csv-local` — expected: `N/N passed`, one suite passed.
- Mutation: `vergeml_csv_cell_out()` returns its input unchanged → expected: the "what the spreadsheet sees" rows and the `'=x` round trip go red; restored → green.
- `php -l core/import-csv.php` — no PHP on PATH here; the wasm run loads the file and is the syntax check.
- Cost: nothing. No box, no network beyond Playground's own package fetch.
