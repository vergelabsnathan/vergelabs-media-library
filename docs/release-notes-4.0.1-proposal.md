# 4.0.1 copy proposal — for Nathan's approval

Everything in this file is a proposal. Nothing here has been written into
`readme.txt`. Story F changes no code; it names evidence for each line and
leaves the wording for Nathan to accept, edit or reject.

## (a) Three changelog lines for 4.0.1

Proposed voice matches the existing `= Fixed =` bullets in `readme.txt`
(short factual clause in bold, one sentence after).

* **The support ticket no longer carries your licence key.** It now sends
  the last four characters of the key and your site's address; the service
  matches those to a licence itself, and a free install still sends nothing.
* **A CSV cell that starts with `=`, `+`, `-`, `@`, tab or a carriage
  return no longer opens as a formula.** The export quotes it safely;
  importing it back strips the safety mark, so the cell round-trips
  unchanged.
* **A PHP 8.4+ deprecation notice on every AI request is gone.**
  `vergeml_ai_rest_status()` took an implicit-nullable parameter; it is
  explicit now.

**Evidence:**

- Line 1 (FR10, story A, landed the same day as `fcad5ea`: `core/get-help.php` sends `licence` = the key's last four characters, upper-cased, beside the site, never `key`): `plans/finish-the-suite.md` lines 43–51 (mini-spec A,
  "Behaviour": *"the ticket body carries the key's prefix (the `…93RJ`
  shape the account page shows) and the site, never the key... a free
  install still sends no key"*); ticket identifier FR10 defined in
  `_bmad-output/planning-artifacts/epics.md:29` ("The support ticket no
  longer carries the plaintext licence key"). Current code, read in this
  worktree, still sends the full key: `core/get-help.php:217-225`
  (`$body['key'] = $key;` when a key is set) — this is the line story A
  is replacing, not yet changed here.
- Line 2 (FR12, story B): `plans/finish-the-suite.md` lines 52–59
  (mini-spec B, "Behaviour": *"a cell whose first character is `=`, `+`,
  `-`, `@`, tab or CR is prefixed with a single quote inside the quoted
  cell... the import strips that prefix back, round-trip equal"*); FR12
  defined in `_bmad-output/planning-artifacts/epics.md:31` ("The CSV
  export quotes formula-leading cells so a folder named
  `=HYPERLINK(...)` does not execute"). Current code, read in this
  worktree, has no such guard: `core/import-csv.php:138-153`
  (`vergeml_csv_line()`) quotes only on `["",\r\n]` (RFC 4180), not on a
  leading formula character — this is the gap story B closes, not yet
  changed here.
- Line 3 (PHP 8.4+ fix, already in the tree): commit `6dc3223` (`git show
  6dc3223 --stat`), `core/ai.php` diff:
  `-function vergeml_ai_rest_status( WP_REST_Request $request = null )`
  →`+function vergeml_ai_rest_status( ?WP_REST_Request $request = null )`
  at the line the commit message names, `core/ai.php:1995`. Commit
  message: *"on PHP 8.4+ vergeml_ai_rest_status() logged an
  implicit-nullable deprecation on every request... Fixed here with an
  explicit ?WP_REST_Request -- PHP 8.5's linter goes from 2 deprecation
  lines to 0 -- and it ships with 4.0.1"*. Confirmed as a 4.0.1 item in
  `_bmad-output/implementation-artifacts/deferred-work.md` (row for
  `spec-1-2-a-3-16-1-site-upgrades-in-place.md`: *"the 4.0.1 changelog
  names the PHP 8.4+ deprecation fix (core/ai.php
  vergeml_ai_rest_status), beside FR10 and FR12"*).

## (b) The "External services" sentence — what story A landed

Story A wrote the minimal factual sentence into `readme.txt:183` in the same commit as the code (`fcad5ea`), as the plan's Files line required; the plan's Copy line said "propose", so the words are still Nathan's to change at the train. The sentence as it stands:

> Pressing Send on the Get help screen posts what you typed, the email address you gave, the last four characters of your licence key if you have one (never the key itself), and a full system report: […]

Nothing else in that paragraph changed. The consolidation review's note: "the short form" in the changelog line above was replaced by "the last four characters" so the two texts agree.

## (c) Should `Tested up to` and `Requires PHP` move?

**Recommendation: no, neither moves for 4.0.1.**

Current header, `readme.txt:4-7`: `Requires at least: 6.5` /
`Tested up to: 7.1` / `Requires PHP: 7.4`.

- **`Tested up to`.** No evidence anywhere in the repo of a WordPress
  minor version newer than 7.1 being tested. The most recent WordPress
  compatibility matrix, `docs/wordpress-org-submission.md:37`, tested
  "WordPress 6.5, 7.0, 7.1" (dated 2026-09-11). Today's launch smoke,
  `docs/handoffs/2026-09-20-s25-buyer-walk-on-4-0-0.md:99-101`, ran the
  release zip on *"a fresh WP 7.1.1 / PHP 8.3 Playground"* — 7.1.1 is a
  patch of the already-declared 7.1 line, not a new minor version, so it
  is not evidence for moving the field.
- **`Requires PHP`.** This field states the floor, and nothing read
  found evidence the floor is changing — 7.4 is still the oldest PHP the
  suites run. The PHP 8.4+ fix (commit `6dc3223`) removes a deprecation
  notice at the *upper* end; it does not touch the floor.

**Found, outside what was asked, worth a look:** the Description's prose
line, `readme.txt:17` — *"Works on WordPress 6.5 through 7.1 and PHP 7.4
through 8.3."* — states an upper bound of PHP 8.3. The box the suites run
on is PHP 8.5.4 (`docs/benchmarks.md:7`: *"PHP 8.5.4-FPM, WordPress
7.1"*; also `docs/handoffs/2026-09-06-phase-6-handoff.md:111` and others).
That prose sentence is not one of the two header fields this story was
asked about, and story F does not touch `readme.txt`, so this is flagged
rather than proposed as a line here.

## (d) Two-line "What changed" paragraph, changelog voice

Modelled on the existing changelog's italic strapline under each version
heading (e.g. `readme.txt:296`, `*A screen that builds your folders and
fills them...*`).

> *A support ticket that keeps your key to itself, a CSV export a
> spreadsheet can't turn into a command, and a PHP 8.4 warning that is
> gone.*

**Evidence:** the three changelog lines in (a) above and their citations;
voice matched against `readme.txt`'s existing straplines for 4.0.0
(`readme.txt:296`), 3.16.1 (`readme.txt:347`) and 3.16.0 (`readme.txt:360`).

## Unsourced

- No release date was invented.
- (Resolved at consolidation, 2026-09-20: the sent value is the key's **last four characters**, upper-cased — `core/get-help.php` after `fcad5ea`; the earlier prefix-vs-suffix question is closed.)
