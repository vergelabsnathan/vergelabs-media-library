# Wave 1 B — Story 4.1: the CSV export cannot execute in a spreadsheet

**Date:** 2026-09-20. **Model:** Opus 5. **From:**
`plans/finish-the-suite.md` (mini-spec B, FR12). **Spec:**
`_bmad-output/implementation-artifacts/spec-4-1-the-csv-export-cannot-execute-in-a-spreadsheet.md`
(status `review`; `sprint-status.yaml` untouched — 1c's). Worktree
`wt/plugin-B`, branch `story/4.1-csv-cells` from `43003b4`. **Spent:
nothing.** Nothing on the box, ms2 or the real shop; no network beyond
Playground's own package fetch. Not pushed.

## What landed

Plugin `43003b4` → **`f510bf2`** `fix(csv): story 4.1 -- the export cannot
execute in a spreadsheet (FR12)`, then this handoff's commit.

- `core/import-csv.php` — `vergeml_csv_cell_out()` / `vergeml_csv_cell_in()`
  beside `vergeml_csv_line()`. A cell whose first character is `=`, `+`,
  `-`, `@`, tab or CR is written `"'…"` (apostrophe in front, always quoted).
  The parser strips exactly that apostrophe on both cells it reads, before
  its existing `trim()`. The pair inverts for a name already beginning with
  an apostrophe before a trigger: `'=x` goes out as `''=x`, comes back `'=x`;
  `'Quoted` matches neither side. BOM, delimiter, CRLF, header row and the
  `WP_Error` codes unchanged.
- `tests/import/csv-cells.php` — new, fixture-only: stubs the two hooks,
  `get_terms` and friends, `WP_Error`, the i18n pair and a `$wpdb` with one
  attachment, then runs the real `vergeml_csv_export_rows()`,
  `vergeml_csv_path()`, `vergeml_csv_line()` and `vergeml_csv_parse()` over
  twelve folders whose names are the triggers, `-5`, `'=x`, `'Quoted`, `Acme`
  (with one file) and a nested `Root/=child`. 69 rows in four sections: what
  a spreadsheet sees (`str_getcsv` of the line begins with `'`), the pair
  inverts, the whole file parsed back, what must not change.
- `tools/verify.mjs` — `csv-local` registered (`env: 'local'`,
  `php: 'wasm'`) beside the box `csv` entry, which is as it was.

## Proof lines, verbatim

`node tools/verify.mjs csv-local` from the worktree:

```
69/69 passed
════════ summary
  passed   csv-local
```

Before the change (test first), the same run:

```
  FAIL  a cell starting = reaches the spreadsheet as text: =HYPERLINK("http://x","x")  -- =HYPERLINK("http://x","x")
  …
  FAILED — the suite printed no "N/M passed" line, so there is nothing to trust here
```
(fatal at section B: `vergeml_csv_cell_in()` undefined; the fourteen
section-A prefix rows FAIL above it.)

**Mutation** — `vergeml_csv_cell_out()` returning its input:

```
48/69 passed
  FAILED — 48/69, exit 1
```
The 21 red rows: every "reaches the spreadsheet as text" and "written
inside quotes" pair (14), the `'=x` second apostrophe, both `'=x` pair rows,
"the file carries the HYPERLINK cell prefixed", "no line begins with a
trigger", "`'=x` came back with its own apostrophe", "no prefix survived the
import". Restored: `69/69 passed` again.

`php -l core/import-csv.php`: no PHP binary on this machine (`which php`
empty). Playground's wasm run loads the file on every `csv-local` run; a
syntax error would be a fatal in `result.txt` and a red suite.

## Decisions (the spec's "Decisions taken", in one line each)

- Prefix inside the quoted cell, always quoted (the mini-spec's shape).
- Export matches `^'*[=+\-@\t\r]`, import strips one `'` on `^'+[=+\-@\t\r]` —
  the only invertible pair for apostrophe-first names.
- Trigger set is OWASP's six, not `|` `%` `;` `\`.
- The box suite `tests/import/csv.php` is not edited: it needs real terms and
  `vergeml_import_run`, which this session cannot run.

## Found, not done

- **Tab- and CR-first names collapse at the importer's `trim()`** — the
  cell-level pair is exact (`'\tname` → `\tname`), then `trim()` and
  `vergeml_csv_folder()`'s per-part trim drop the whitespace. Pre-existing;
  `wp_insert_term` would strip it from a term name anyway. Pinned by a row
  ("came back trimmed"), not changed.
- **The path walker's `/`→`-`** — `=HYPERLINK("http://x","x")` comes back as
  `=HYPERLINK("http:--x","x")`. Pre-existing (`vergeml_csv_path()`), not this
  story's; the suite asserts the returned form.
- **A nested formula name is safe by construction** — `Root/=child` begins
  with `R`. Asserted, so a future change to the path shape would show.

## Open questions

- Should 1c add a `=HYPERLINK` folder to the box suite's fixture for the
  real-term round trip? If so: one more path, the 5→6 counts, and the tidy
  list carries the returned name with `http:--x`.
- Excel and LibreOffice hide a leading `'` in a text cell; Sheets may show it
  on a CSV import. Cosmetic either way — the formula does not run.

## For 1c

Merge `story/4.1-csv-cells`; `node tools/verify.mjs csv-local` here, `csv`
on the box after the deploy. No copy for Nathan from this story. Changelog
line is F's (`release-notes-4.0.1-proposal.md`).
