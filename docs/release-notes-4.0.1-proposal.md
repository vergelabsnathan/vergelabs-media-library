# 4.0.1 copy proposal — for Nathan's approval

Everything in this file is a proposal. Nothing here has been written into
`readme.txt`. Story F changes no code; it names evidence for each line and
leaves the wording for Nathan to accept, edit or reject.

## (a) Three changelog lines for 4.0.1

Proposed voice matches the existing `= Fixed =` bullets in `readme.txt`
(short factual clause in bold, one sentence after).

* **The support ticket no longer carries your licence key.** It now sends
  the key's short form and your site's address; the service matches those
  to a licence itself, and a free install still sends nothing.
* **A CSV cell that starts with `=`, `+`, `-`, `@`, tab or a carriage
  return no longer opens as a formula.** The export quotes it safely;
  importing it back strips the safety mark, so the cell round-trips
  unchanged.
* **A PHP 8.4+ deprecation notice on every AI request is gone.**
  `vergeml_ai_rest_status()` took an implicit-nullable parameter; it is
  explicit now.

**Evidence:**

- Line 1 (FR10, story A, **in progress at the time of writing** — this
  describes the behaviour the plan commits to, not code read in this
  worktree): `plans/finish-the-suite.md` lines 43–51 (mini-spec A,
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

## (b) Rewritten "External services" sentence — the support ticket, after story A

Current text, `readme.txt:183` (the "Asking for help" paragraph):

> Pressing Send on the Get help screen posts what you typed, the email
> address you gave, your licence key if you have one, and a full system
> report: your site's address, your WordPress, PHP and MySQL versions,
> your server's limits, your theme, how many files you have, your folder
> and taxonomy counts, this plugin's settings, and **the name and version
> of every plugin you have active**. The screen shows you the report
> before you send it, and it will not send without the tick box.

**Proposed rewrite** (only the licence-key clause changes, same voice,
same paragraph):

> Pressing Send on the Get help screen posts what you typed, the email
> address you gave, the short form of your licence key if you have one
> and your site's address, and a full system report: your site's address,
> your WordPress, PHP and MySQL versions, your server's limits, your
> theme, how many files you have, your folder and taxonomy counts, this
> plugin's settings, and **the name and version of every plugin you have
> active**. The screen shows you the report before you send it, and it
> will not send without the tick box.

(The duplicated "your site's address" in that draft should collapse to
one mention when this lands — left visible here so the diff against the
current sentence is exact.)

**Evidence:** `plans/finish-the-suite.md` lines 43–51, mini-spec A
Behaviour, as above. **Not sourced:** the plan calls the sent value the
key's "prefix" but shows it as a trailing shape, `…93RJ` (a leading
ellipsis reads as the *last* four characters, the way a masked key is
usually shown, not the first four). This proposal uses the plan's own
neutral phrase, "the short form", rather than choosing prefix or suffix.
Confirm which it is against story A's actual code before this sentence
ships.

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

- The prefix-vs-suffix shape of the licence key sent in the support
  ticket (see (b)) — the plan's own wording is ambiguous; nothing in this
  worktree resolves it because story A has not landed here.
- Any actual character count or exact display format for "the short form"
  of the key beyond the plan's own `…93RJ` example.
- Whether the release date for 4.0.1 is set — no date is invented
  anywhere in this file, per the story's "do not invent a date" rule.
