---
name: validate
description: Run the full validation gate for VergeLabs Media Library — lint, version consistency, Playground functional tests, query-count budgets, and Plugin Check. Use after any code change, and as the terminating loop for /execute.
---

# Validate

Run **every** gate below. Do not stop at the first failure — collect them all, then fix and
rerun the whole thing. The loop terminates on green, not on first output.

Report a table: gate, pass/fail, and the actual output for anything that failed. Never report a
gate as passing without having run it, and never infer a result from a previous run.

## Gate 1 — PHP lint (fast, always first)

Every PHP file must parse on the 7.4 floor. Locally there is no PHP binary, so this runs on the
test VPS:

```bash
cd /c/dev && tar --exclude=node_modules --exclude=.git -czf /tmp/vgml.tgz vergelabs-media-library
scp -i ~/.ssh/kamatera_vgml -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null \
  /tmp/vgml.tgz root@185.229.224.239:/tmp/
ssh -i ~/.ssh/kamatera_vgml -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null \
  root@185.229.224.239 'cd /tmp && rm -rf lint && mkdir lint && tar xzf vgml.tgz -C lint &&
  find lint -name "*.php" -exec php -l {} \; | grep -v "No syntax errors"; echo "lint done"'
```

Empty output between the command and `lint done` means clean.

## Gate 2 — PHP 7.4 syntax floor

`php -l` on the box runs PHP 8.3, so it will happily accept syntax that breaks for users on 7.4.
Grep for the constructs that do not exist in 7.4:

```bash
cd /c/dev/vergelabs-media-library
grep -rnE 'match\s*\(|\?\->|readonly |enum [A-Z]|function [a-zA-Z_]+\([^)]*(public|private|protected) ' \
  --include=*.php . | grep -v '/tests/'
```

Any hit is a failure unless it is provably not the PHP 8 construct (e.g. the word "match" in a
comment). Explain any hit you dismiss.

## Gate 3 — version consistency

All three must agree, or wordpress.org ships a different version than the plugin reports:

```bash
cd /c/dev/vergelabs-media-library
grep -n "^ \* Version:" vergelabs-media-library.php
grep -n "VERGEML_VERSION'," vergelabs-media-library.php
grep -n "^Stable tag:" readme.txt
```

## Gate 4 — functional tests in Playground

```bash
cd /c/dev/vergelabs-media-library
MSYS_NO_PATHCONV=1 npx --yes @wp-playground/cli@latest server --port 8899 \
  --mount-dir "C:\dev\vergelabs-media-library" /wordpress/wp-content/plugins/vergelabs-media-library \
  --blueprint=tests/tree/blueprint.json &
# wait for "Ready!", then:
node tests/tree/t0-endpoints.js
node tests/watchdog/recovery.js
```

Browse `127.0.0.1`, never `localhost`. Kill the server afterwards (`pkill -f wp-playground`).

## Gate 5 — query-count budget

The performance gate, and the only performance number that means anything in Playground.
Boot time is noise; **query count is the measurement**.

```bash
cd /c/dev/vergelabs-media-library
node tests/perf/bench.mjs http://127.0.0.1:8903 admin:benchbenchbenchbench   # Playground
node tests/perf/bench.mjs http://185.229.224.239 admin:<app-password>        # real MariaDB
```

Budgets, both environments (they must agree — a difference is a bug):

| Endpoint | Queries | Note |
|---|---|---|
| `vergeml/v1/tree` | **6** | must not grow with folder count; verified flat from 200 → 2000 folders. 4 + 1 (five smart-folder counts as one UNION) + 1 (per-user tree state); raised deliberately 26-08-2026 |
| `wp/v2/media?per_page=40` | 6 | core's own baseline, for comparison |

A rise in query count is a regression even if wall-clock improved. If the tree's count moves with
the number of folders or files, an N+1 has been introduced — that is a hard fail.

## Gate 6 — Plugin Check

The wordpress.org submission gate. Must be clean before any release:

```bash
ssh -i ~/.ssh/kamatera_vgml -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null \
  root@185.229.224.239 'cd /var/www/wp &&
  wp plugin install plugin-check --activate --allow-root 2>/dev/null;
  wp plugin check vergelabs-media-library --allow-root'
```

## Gate 7 — the upgrade path

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
for gates 6 and 7 on changes that touch neither packaging nor options. Gates 1–5 always run.
