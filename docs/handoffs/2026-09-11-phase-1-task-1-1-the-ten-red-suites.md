# Handover — Phase 1, task 1.1: the ten red suites, characterised (Opus 5)

Run on 2026-09-11 at Nathan's instruction, in the same session as Phase 5.1–5.4
and its follow-ups — against the harness rule, and said so. Every suite was run
on the box or Playground and read; **nothing was fixed**, which is what 1.1 says.

**The headline: nine of the ten reds are not plugin defects.** One is real copy
(two strings). The rest are suites that stopped describing the code after four
deliberate changes on 4–10 September, plus one box quirk and one dead password.
And one of the ten is already green.

Spend: `ai` runs in mock mode and `ai-background` re-describes three files —
a few cents. Nothing else reached a model.

## The ten

| suite | result | class | one line |
|---|---|---|---|
| `ai-background` | **34/34 green** | — | was red on the board; green now with its precondition run |
| `voice` | 1/2 | **real** | two strings say "tree" where the standard says folders |
| `search-try` | fatal | suite bug + fixture | `min()` on an empty array; the test query clears no hit on the new library |
| `librarian` | 65/68 | fixture | the reset spread 28 pictures into every month; the test expects a month of exactly 8 |
| `librarian-ui` | timeout | stale suite | asserts `#vgml-lib-stage`, which no PHP has rendered since the 4 Sept redesign |
| `smart` | 9/11 | timing (+ dead password) | two counts taken after a fixed 2.5 s sleep; the passing one waits properly |
| `ai-folders` | 35/38 | stale probe | reads `$wpdb->last_query` for the UNION; the 10 Sept counts cache writes a transient after it |
| `auto-file` | 16/23 | fixture | plants rows with no object classes; the 5 Sept matcher abstains on evidence-free rows |
| `organize` | 61/66 | environment + suite | `ini_set('memory_limit')` returns `false` under WP-CLI on PHP 8.5.4; the suite never checks |
| `ai` | timeout | stale copy | waits for "Done" or "to go"; the note reads "N of M described · K left · Stop at" since 6 Sept |

## Each, with the evidence and what the fix touches

### voice — real, two strings

    core/admin-menu.php:220   "Say what folders you want, or pick a rule. The tree answers. One button moves the pic…"
    core/admin-shell.php:69   "Build the folder tree"

Both say "tree" where the copy standard says folders. **Fix touches:** those two
strings, `tests/tree/copy.mjs` if the struck forms are added there. Nothing else.

### search-try — a suite bug and a fixture change together

`tests/tree/search-try.php:111`: `min( $t_scores )` with `$t_scores` empty —
PHP 8 throws `ValueError`, and the suite dies before its summary line. It gets
there because `meaning.available` is true and `hits` is empty: the meaning search
answers, but nothing clears `VERGEML_MEANING_FLOOR` for the test's word against
the 1,000 tech-news pictures the reset put in. **Fix touches:** the suite — guard
the empty case (C2 is meaningless with no hits and should say so), and pick a
word the fixture library actually contains.

### librarian — the fixture moved under it

The by-date preflight plants 8 files in March 2026 and 2 in July, then looks for
a depth-1 branch under 2026 with `size === 8` and expects July to fold into its
year. The box now holds, from the reset fixture:

    2026-03  28   2026-04  28   2026-05  28   2026-06  28   2026-07  28   2026-08  27   2026-09  9

So March is 36 and July is 30. This is exactly the "green at 14:30, red at 17:00
with no commit" — the library reset landed between. **Fix touches:** the suite —
find the branch by the ids it planted, not by size; or run the date scheme over a
scoped set.

### librarian-ui — the screen it tests is gone

`tests/librarian/librarian.mjs:53` waits for `#vgml-lib-stage .vgml-lib-rung`.
Grepping `core/`, `js/`, `css/`: no PHP renders `vgml-lib-stage`; the only
reference left is in `js/vergeml-autofile.js`. `admin.php?page=media-librarian`
is now `vergeml_folders_page()` in `core/guide.php` — the Folders screen from the
4 Sept redesign. It is **not** a login failure: a fresh browser lands on the page.
**Fix touches:** retire the suite, or rewrite it against the Folders screen —
which is Phase 3's build, so not before it.

### smart — two fixed sleeps, and the password

With a real login (`tools/box-ui-user.sh`) it is 9/11; with the default password
it dies at the first selector, which is the trap in every handoff. The two reds:

    FAIL  missing-alt filters the grid to its own number  -- 0 tiles for 998
    FAIL  and the grid shows the folder again             -- 0 tiles for 167

The check that passes (`tests/tree/smart.mjs:117`) uses `waitForFunction` on the
expected count. The two that fail (`:126`, `:141`) use `waitForTimeout( 2500 )`
and count — on a box carrying 28 plugins the 998-item re-query has not landed in
2.5 s. **Fix touches:** the suite — the same `waitForFunction` the first check
uses.

### ai-folders — the probe reads the wrong statement

`tests/tree/ai-folders.php:538–545` calls `vergeml_smart_counts( true )` and
reads `$wpdb->last_query` expecting the counts UNION. `fresh = true` does recount
— read `core/smart-folders.php:222–236` — but the 10 Sept counts-at-scale change
then **stores** the answer (`vergeml_smart_counts_store()`), and that transient
write is what `last_query` holds when the suite looks. **Fix touches:** the suite
— capture the statement through the `query` filter or `SAVEQUERIES`, not
`last_query`.

### auto-file — the fixture predates the matcher

`tests/tree/auto-file.php:177`: every planted index row is
`caption: 'seeded', kind: 'photo'` plus a synthetic vector — no object classes.
`core/filing.php` since 5 Sept scores object-class match first
(`:395–430`), uses the vector only as tie-break, and abstains below
`VERGEML_FILING_FLOOR = 0.55`. A row with no classes cannot clear the floor, so
`null` is the matcher being right. All seven reds hang off that first `null`.
**Fix touches:** the fixture — plant `classes` the folders can match; and the
matcher's own suite (`filing-trail`, green) already covers the evidence path.

### organize — WP-CLI refuses the memory limit

`tests/organize/test-organize.php:237` sets `memory_limit` to `128M` to reach the
refusal path. On the box:

    php -r 'ini_set("memory_limit","128M")'   → set, 128M      (raw CLI, php.ini 512M)
    wp eval 'var_dump( ini_set(...) )'         → bool(false), stays -1   (WP-CLI, PHP 8.5.4)

The suite never checks the return, so its precondition fails silently and five
assertions read a `0MB` limit. `vergeml_organize_memory_limit()` is doing the
right thing with `-1`. **Fix touches:** the suite — assert the `ini_set` took, and
when it does not, inject the limit through a filter on the limit function rather
than through ini.

### ai — waits for copy that was struck

`tests/tree/ai.mjs:63`: `/Done|to go/` against `#vgml-ai-note`. Since the 6 Sept
AI-screen copy pass the finished line is
`'%1$s of %2$s described · %3$s left · Stop at %4$s'` (`js/vergeml-ai.js:225`).
The suite also needs the real box login. Mock mode, spends nothing.
**Fix touches:** the suite — match the current line; the `copy` suite already
guards the struck forms.

## What this means for the board

- **1 real finding** (two copy strings), fixable in minutes.
- **7 suites to bring back to the code** — none needs a plugin change.
- **1 suite to retire or rebuild** after Phase 3 (`librarian-ui`).
- **1 already green** (`ai-background`).

Two things every browser suite on the box needs, stated once more because three
of these ten were "red" only for want of them: `UI_USER`/`UI_PASS` from
`tools/box-ui-user.sh` (`VGML_USER`/`VGML_PASS` through `verify.mjs`), and the
admin deleted afterwards — done here after every run.

## Next

**Phase 1, tasks 1.2 onward** — a fresh session. The order that follows from the
above: voice's two strings; then the seven suites, cheapest first (`ai`,
`smart`, `search-try`, `ai-folders`, `organize`, `librarian`, `auto-file`); then
the matrix, size, uninstall and rollback. `librarian-ui` waits for Phase 3.

Opener:

> Read `docs/handoffs/2026-09-11-phase-1-task-1-1-the-ten-red-suites.md`, then
> `plans/four-yesses.md`. State which model you are and follow that profile in
> `~/.claude/harness/model-profiles.md`. This session is Phase 1, task 1.2: bring
> the seven suites named there back to the code, one at a time, running each
> before and after, and fix voice's two strings. Retire nothing. Every browser
> suite needs a box admin from `tools/box-ui-user.sh`, deleted at the end. Gates:
> `node tools/verify.mjs copy surface db-calls escaping globals` and each suite
> itself. End with a handoff.
