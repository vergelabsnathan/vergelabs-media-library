---
name: validate
description: Run the full validation gate for VergeLabs Media Library â€” lint, version consistency, Playground functional tests, query-count budgets, and Plugin Check. Use after any code change, and as the terminating loop for /execute.
---

# Validate

Run **every** gate below. Do not stop at the first failure â€” collect them all, then fix and
rerun the whole thing. The loop terminates on green, not on first output.

Report a table: gate, pass/fail, and the actual output for anything that failed. Never report a
gate as passing without having run it, and never infer a result from a previous run.

## Gate 1 — PHP lint (fast, always first)

Every PHP file must parse on the 7.4 floor, and this now runs here rather than on a box:

```bash
cd "/c/Users/viete/Desktop/🟢 Claude Projects/Media Plugin/plugin"
find . -name "*.php" -not -path "./node_modules/*" | wc -l
find . -name "*.php" -not -path "./node_modules/*" \n  -exec "/c/Users/viete/Desktop/🟢 Claude Projects/Media Plugin/.tools/php74/php.exe" -l {} \; 2>&1 | grep -v "No syntax errors"
```

Empty output after the file count means clean. **A count of zero is a failure**, not a pass:
a gate that lints no files prints exactly what a clean gate prints, and that has happened here
before — the paths went stale after the repo moved, so the tar packed an empty directory and
the gate stayed green while checking nothing.

The binary is PHP **7.4.33**, which is the floor itself, so this catches what the test box could
not: the box runs 8.3 and happily accepts syntax that breaks for the users this plugin is for.
Install it once with:

```powershell
$dst = "C:\Users\viete\Desktop\🟢 Claude Projects\Media Plugin\.tools\php74"
New-Item -ItemType Directory -Force $dst
Invoke-WebRequest "https://windows.php.net/downloads/releases/archives/php-7.4.33-nts-Win32-vc15-x64.zip" -OutFile "$env:TEMP\php74.zip"
Expand-Archive "$env:TEMP\php74.zip" -DestinationPath $dst -Force
```

Gate 2 still matters even so: 7.4 will reject PHP 8 syntax outright, but the grep there also
catches constructs in files this lint might skip.

## Gate 2 â€” PHP 7.4 syntax floor

`php -l` on the box runs PHP 8.3, so it will happily accept syntax that breaks for users on 7.4.
Grep for the constructs that do not exist in 7.4:

```bash
cd "/c/Users/viete/Desktop/🟢 Claude Projects/Media Plugin/plugin"
grep -rnE 'match\s*\(|\?\->|readonly |enum [A-Z]|function [a-zA-Z_]+\([^)]*(public|private|protected) ' \
  --include=*.php . | grep -v '/tests/'
```

Any hit is a failure unless it is provably not the PHP 8 construct (e.g. the word "match" in a
comment). Explain any hit you dismiss.

## Gate 3 â€” version consistency

All three must agree, or wordpress.org ships a different version than the plugin reports:

```bash
cd "/c/Users/viete/Desktop/🟢 Claude Projects/Media Plugin/plugin"
grep -n "^Version:" vergelabs-media-library.php
grep -n "VERGEML_VERSION'," vergelabs-media-library.php
grep -n "^Stable tag:" readme.txt
```

Three commands, three lines, three matching numbers. **Fewer than three lines is a failure**:
the pattern here used to be `^ \* Version:`, which matches nothing in this file's header, so the
gate printed two lines and read as clean while checking two of the three places.

## Gate 4 â€” functional tests in Playground

```bash
cd "/c/Users/viete/Desktop/🟢 Claude Projects/Media Plugin/plugin"
MSYS_NO_PATHCONV=1 npx --yes @wp-playground/cli@latest server --port 8899 \
  --mount-dir "C:\Users\viete\Desktop\🟢 Claude Projects\Media Plugin\plugin" /wordpress/wp-content/plugins/vergelabs-media-library \
  --blueprint=tests/tree/blueprint.json &
# wait for "Ready!", then:
node tests/tree/t0-endpoints.js
node tests/watchdog/recovery.js
```

Browse `127.0.0.1`, never `localhost`. Kill the server afterwards (`pkill -f wp-playground`).

## Gate 5 â€” query-count budget

The performance gate, and the only performance number that means anything in Playground.
Boot time is noise; **query count is the measurement**.

```bash
cd "/c/Users/viete/Desktop/🟢 Claude Projects/Media Plugin/plugin"
node tests/perf/bench.mjs http://127.0.0.1:8903 admin:benchbenchbenchbench   # Playground
node tests/perf/bench.mjs http://46.225.66.194 admin:<app-password>          # real MariaDB
```

Budgets, both environments (they must agree â€” a difference is a bug):

| Endpoint | Queries | Note |
|---|---|---|
| `vergeml/v1/tree` | **7** | must not grow with folder count; verified flat from 200 â†’ 2000 folders. 4 (two `get_terms` statements, the unassigned count, the termmeta prime) + 1 (every smart-folder count as one UNION, the AI group's thirteen included) + 2 (`vergeml_smart_scan` and `vergeml_index`, both non-autoloaded options the handler reads once). Raised 5 â†’ 6 on 26-08-2026 and 6 â†’ 7 on 28-08-2026 |
| `vergeml/v1/health-report` | **5** | 3 with nothing to show, plus the 2 that fetch what is shown. Flat: neither moves with the number of duplicate groups |
| `wp/v2/media?per_page=40` | â€” | core's own endpoint, printed for scale only. **Not a budget**: measured 7 in Playground and 86â€“109 on the box, because it costs whatever the site's other plugins make it cost |

A rise in **our** endpoints' query count is a regression even if wall-clock improved. If the
tree's count moves with the number of folders or files, an N+1 has been introduced â€” that is a
hard fail.

The 6 â†’ 7 raise is worth reading before repeating it. Phase 4a added the AI group and said in
its own commit message that the tree's budget was untouched, because the thirteen new counts
ride the UNION that was already there â€” which is true. What it could not check, because the box
was unreachable that day, was that `vergeml_ai_folders_count_branches()` asks
`vergeml_index_state()` whether the schema is laid down, and that option is not autoloaded. One
constant query, measured 7 in Playground on four folders and 7 on the box on twenty thousand
attachments. **Flatness is the invariant this budget exists to protect, and it held**; the
number was simply never measured. A budget may only be raised with a derivation like this one
and a measurement in both environments â€” never to make a red gate green.

Measure over REST, never from `wp eval` after other work in the same request: a scan that ran
first leaves the caches warm, and the endpoint does not get them. That reported 4 queries for a
`health-report` that actually ran 70.

## Gate 6 — Plugin Check

The wordpress.org submission gate. Must be clean before any release.

Check a **clean archive**, not the working tree. `git archive` honours the export-ignore rules
in `.gitattributes`, so it contains what users would install; checking the working folder
instead reports the dev files — `.claude`, `CLAUDE.md`, `tests/`, `tools/` — as findings that
are not real, and burying the real ones under them is how a real one gets missed.

```bash
cd "/c/Users/viete/Desktop/🟢 Claude Projects/Media Plugin/plugin"
git archive HEAD --prefix=vergelabs-media-library/ -o /tmp/clean.tar
mkdir -p /tmp/pc && tar xf /tmp/clean.tar -C /tmp/pc

MSYS_NO_PATHCONV=1 npx --yes @wp-playground/cli@latest server --port 8907 --php=8.3 --wp=latest   --mount-dir "<extracted>ergelabs-media-library" /wordpress/wp-content/plugins/vergelabs-media-library   --blueprint=tools/plugin-check-blueprint.json &

node tools/plugin-check.mjs http://127.0.0.1:8907
```

`Checks complete. No errors found.` is the only passing output. The driver ticks **every**
category — the form defaults to "Plugin Repo" alone, which skips Security, Performance and
Accessibility — and waits for the site itself rather than for a port to stop refusing.

**Not through WP-CLI.** `wp plugin check` crashes php-wasm part way through the run
(`RuntimeError: unreachable`), so the blueprint's `wp-cli` step is not an option in Playground.

A custom table's name built by a helper function reads to the sniffs as unprepared SQL —
register it on `$wpdb` rather than suppressing the warning. A new file in `core/` needs the
`if ( ! defined( 'ABSPATH' ) ) exit;` guard, which is the one this gate has actually caught.

## Gate 7 — the upgrade path

Existing installs carry saved options and schemas that defaults never touch. If this change
added or altered either, prove the migration runs — and prove it from the state an existing
install is actually in, not from a fresh one.

Two halves, and both have drawn blood:

**Options.** A changed default does nothing for a site that already has the old value written to
the database. The migration belongs in `vergeml_set_options()` behind a
`version_compare( get_option( 'vergeml_version', '' ), 'X.Y.Z', '<' )` guard, and it must leave
alone anything the user owns.

**Schema.** A lazy install that tests only for "never installed" will not notice a version bump,
and a site that already has the table silently never receives the new column or index. Both
`vergeml_librarian_maybe_install()` and `vergeml_index_maybe_install()` compare against their
version constant *and* ask the database whether the table is still there — they were each fixed
after failing exactly this.

Run it with a blueprint rather than a box:

```bash
cd "/c/Users/viete/Desktop/🟢 Claude Projects/Media Plugin/plugin"
MSYS_NO_PATHCONV=1 npx --yes @wp-playground/cli@latest run-blueprint --php=8.3 --wp=latest   --mount-dir "C:\Users\viete\Desktop\🟢 Claude Projects\Media Plugin\plugin" /wordpress/wp-content/plugins/vergelabs-media-library   --blueprint=tests/librarian/gate7-blueprint.json
cat tests/librarian/gate7-last-run.txt
```

`tests/librarian/gate7-schema.php` is the shape to copy for any new table: baseline, dropped
table with the option intact, dropped table with the option gone, and the same loss reached
through the real endpoint.

## Skipping a gate

Only with a stated reason, written into the report. "Not relevant to this change" is acceptable
for gates 6 and 7 on changes that touch neither packaging nor options. Gates 1â€“5 always run.
