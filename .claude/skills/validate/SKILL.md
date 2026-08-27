---
name: validate
description: Run the full validation gate for VergeLabs Media Library â€” lint, version consistency, Playground functional tests, query-count budgets, and Plugin Check. Use after any code change, and as the terminating loop for /execute.
---

# Validate

Run **every** gate below. Do not stop at the first failure â€” collect them all, then fix and
rerun the whole thing. The loop terminates on green, not on first output.

Report a table: gate, pass/fail, and the actual output for anything that failed. Never report a
gate as passing without having run it, and never infer a result from a previous run.

## Gate 1 â€” PHP lint (fast, always first)

Every PHP file must parse on the 7.4 floor. Locally there is no PHP binary, so this runs on the
test VPS:

```bash
tar -C /c/dev/media-plugin --exclude=node_modules --exclude=.git --exclude=.verify.lock -czf /tmp/vgml.tgz plugin
scp -i ~/.ssh/kamatera_vgml -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null \
  /tmp/vgml.tgz root@185.229.224.239:/tmp/
ssh -i ~/.ssh/kamatera_vgml -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null \
  root@185.229.224.239 'cd /tmp && rm -rf lint && mkdir lint && tar xzf vgml.tgz -C lint &&
  echo "linting $(find lint -name "*.php" | wc -l) files" &&
  find lint -name "*.php" -exec php -l {} \; | grep -v "No syntax errors"; echo "lint done"'
```

Empty output between the file count and `lint done` means clean. **A count of zero is a
failure**, not a pass: the tar found nothing, and a gate that lints no files prints exactly
what a clean gate prints. This has already happened — the paths in this file went stale after
the repo moved, so the tar packed an empty directory and the gate stayed green while checking
nothing.

## Gate 2 â€” PHP 7.4 syntax floor

`php -l` on the box runs PHP 8.3, so it will happily accept syntax that breaks for users on 7.4.
Grep for the constructs that do not exist in 7.4:

```bash
cd /c/dev/media-plugin/plugin
grep -rnE 'match\s*\(|\?\->|readonly |enum [A-Z]|function [a-zA-Z_]+\([^)]*(public|private|protected) ' \
  --include=*.php . | grep -v '/tests/'
```

Any hit is a failure unless it is provably not the PHP 8 construct (e.g. the word "match" in a
comment). Explain any hit you dismiss.

## Gate 3 â€” version consistency

All three must agree, or wordpress.org ships a different version than the plugin reports:

```bash
cd /c/dev/media-plugin/plugin
grep -n "^Version:" vergelabs-media-library.php
grep -n "VERGEML_VERSION'," vergelabs-media-library.php
grep -n "^Stable tag:" readme.txt
```

Three commands, three lines, three matching numbers. **Fewer than three lines is a failure**:
the pattern here used to be `^ \* Version:`, which matches nothing in this file's header, so the
gate printed two lines and read as clean while checking two of the three places.

## Gate 4 â€” functional tests in Playground

```bash
cd /c/dev/media-plugin/plugin
MSYS_NO_PATHCONV=1 npx --yes @wp-playground/cli@latest server --port 8899 \
  --mount-dir "C:\dev\media-plugin\plugin" /wordpress/wp-content/plugins/vergelabs-media-library \
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
cd /c/dev/media-plugin/plugin
node tests/perf/bench.mjs http://127.0.0.1:8903 admin:benchbenchbenchbench   # Playground
node tests/perf/bench.mjs http://185.229.224.239 admin:<app-password>        # real MariaDB
```

Budgets, both environments (they must agree â€” a difference is a bug):

| Endpoint | Queries | Note |
|---|---|---|
| `vergeml/v1/tree` | **6** | must not grow with folder count; verified flat from 200 â†’ 2000 folders. 4 + 1 (five smart-folder counts as one UNION) + 1 (per-user tree state); raised deliberately 26-08-2026 |
| `vergeml/v1/health-report` | **5** | 3 with nothing to show, plus the 2 that fetch what is shown. Flat: neither moves with the number of duplicate groups |
| `wp/v2/media?per_page=40` | â€” | core's own endpoint, printed for scale only. **Not a budget**: measured 7 in Playground and 86â€“109 on the box, because it costs whatever the site's other plugins make it cost |

A rise in **our** endpoints' query count is a regression even if wall-clock improved. If the
tree's count moves with the number of folders or files, an N+1 has been introduced â€” that is a
hard fail.

Measure over REST, never from `wp eval` after other work in the same request: a scan that ran
first leaves the caches warm, and the endpoint does not get them. That reported 4 queries for a
`health-report` that actually ran 70.

## Gate 6 â€” Plugin Check

The wordpress.org submission gate. Must be clean before any release.

Check a **clean archive**, not the working tree. `git archive` honours the export-ignore rules
in `.gitattributes`, so it contains what users would install; checking the deployed folder
instead reports the dev files â€” `.claude`, `CLAUDE.md`, `.github`, `tests/` â€” as warnings that
are not real, and burying the real findings under them is how a real one gets missed.

```bash
cd /c/dev/media-plugin/plugin
git archive HEAD --prefix=vergelabs-media-library/ -o /tmp/vgml-clean.tar
scp -i ~/.ssh/kamatera_vgml -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null \
  /tmp/vgml-clean.tar root@185.229.224.239:/tmp/
ssh -i ~/.ssh/kamatera_vgml -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null \
  root@185.229.224.239 'cd /var/www/wp/wp-content/plugins &&
  rm -rf vgml-clean-check && mkdir vgml-clean-check &&
  tar xf /tmp/vgml-clean.tar -C vgml-clean-check && cd /var/www/wp &&
  wp plugin install plugin-check --activate --allow-root 2>/dev/null;
  wp plugin check wp-content/plugins/vgml-clean-check/vergelabs-media-library --allow-root;
  rm -rf wp-content/plugins/vgml-clean-check'
```

`Success: Checks complete. No errors found.` is the only passing output. A custom table's name
built by a helper function reads to the sniffs as unprepared SQL â€” register it on `$wpdb` rather
than suppressing the warning.

## Gate 7 â€” the upgrade path

Existing installs carry saved options that defaults never touch. If this change added or altered
a default, prove the migration runs:

```bash
ssh ... root@185.229.224.239 'cd /var/www/wp &&
  wp option update vergeml_version 2.10.1 --allow-root &&
  wp eval "vergeml_set_options();" --allow-root &&
  wp eval "print_r( get_option( \"vergeml_taxonomies\" ) );" --allow-root'
```

Check the migration changed what it should and left the site's own taxonomies (`eml_media` = 0)
alone.

## Skipping a gate

Only with a stated reason, written into the report. "Not relevant to this change" is acceptable
for gates 6 and 7 on changes that touch neither packaging nor options. Gates 1â€“5 always run.
