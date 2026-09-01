# The watch — updates found on the day, proven on a stage, triaged green / yellow / red

## Problem

The plugin integrates with WordPress core, a floor of PHP 7.4 and a head of 8.5,
and twenty-odd third-party plugins and themes, each releasing on its own clock.
What exists today for finding out that one of them moved:

- `tools/watch-competitors.mjs` polls wordpress.org for six rivals and four
  builders, hashes the new zip's files and reports which changed. Good, and
  half the job. It knows nothing of WordPress core, PHP, the four SEO plugins,
  WooCommerce, ACF, Polylang, WP Rocket or the paid builders, and it says
  "these files changed", not "the hook we register on is gone".
- `.github/workflows/watch-builders.yml` was meant to run it nightly and open
  an issue when `tests/builders/compat.mjs` failed. Its first two lines were
  bare `>` text, so the file was invalid YAML: every push made a 0-second
  failed run and the nightly has never executed once. Fixed in this work
  (comment markers), but the job as written would still fail on Linux —
  `watch-competitors.mjs` unzips with PowerShell.
- Nothing runs the real suites against a *new* WordPress or a *new* plugin
  release. The gate proves the plugin against what is installed today.
- Nothing connects a finding to a person. A red run is a red run in the
  Actions tab.

So the first news of Elementor 3.30 breaking the folder tree would be a
support ticket.

## User story

As the person shipping this plugin to agencies, I want to know the morning
after WordPress, PHP or an integrated plugin releases whether anything of ours
broke — with the paperwork done for me when nothing did, and a plan and a
drafted fix waiting when something did — so that customers never find out
before I do.

## Decisions taken

- **Extend `tools/watch-competitors.mjs`, do not replace it.** Its state file,
  manifest diff and report shape stay; it gains sources (core, PHP, changelog
  pages), more plugins, a contract check and a machine-readable verdict.
- **The contract file is the load-bearing part.** `tools/watch/contract.json`
  lists, per dependency, the exact hooks, meta keys, classes, constants, tables
  and JS globals our code relies on, each with the file that relies on it. A
  new release is downloaded and grepped for every symbol. Missing symbol = red,
  before any suite runs. Deterministic; no model involved.
- **Three verdicts, three levels of autonomy:**
  - green — contract intact, stage suites pass → bump `Tested up to` (core)
    or the verified-against note (plugin), commit to main, no human.
  - yellow — passes, but the changelog or a deprecation notice names our
    surface → plan as a GitHub issue. No code.
  - red — contract broken or a stage suite failed → issue with the plan, plus
    a fix branch and PR from a non-interactive `claude -p` run. A human merges.
- **Nothing red or yellow ever commits to main. No verdict ever deploys to
  wordpress.org.**
- **The stage is a third site on the box**, `/var/www/upd`
  (`http://upd.46.225.66.194.nip.io`), a clone of the main site with its own
  database `wpupd`, `WP_ENVIRONMENT_TYPE=staging`, and the plugin directory a
  symlink to the deployed copy so `deploy --box` updates it too. The watch may
  upgrade anything on it. It never touches `/var/www/wp` or `/var/www/ms`.
- **Signals**: the stage site first — WordPress's own updater reports a new
  version for anything installed there, paid plugins included, licence or not
  (Nathan's point, 2026-09-01), read with `wp plugin list --update=available`;
  then wordpress.org version-check (stable and beta/RC), php.net releases JSON
  and wordpress.org plugin info; public changelog pages only as a fallback for
  a paid plugin that is not installed. A paid plugin's zip is still not
  downloadable without a licence, so its contract check runs against the copy
  the stage upgrades to, not against a download.
- **PHP has its own leg**: `PHPCompatibilityWP` sniffs at `testVersion 7.4-`
  through the newest release, run in the Action (Composer, no box needed),
  plus the Playground gate booted with `--php=<new>` when Playground supports
  it. The box's PHP is upgraded by hand, not by the watch.
- **Core betas are tested.** A new RC triggers the stage upgrade with
  `wp core update --version=X-RCn`; the verdict is filed against the coming
  release so the fix ships before release day.
- **Plans are written by a model through OpenRouter**, given the contract diff,
  the changelog excerpt, the failing suite output and the files named in the
  contract. The plan goes in the issue body in the `plans/` format. Model:
  Claude via OpenRouter, never a direct Anthropic key.
- **The autonomous fix is `claude -p`** in the Action on a branch
  `watch/<slug>-<version>`, prompt = the plan, permission mode restricted to
  the repo, followed by the PHP lint and the contract check; it opens a PR with
  the gate output. If the run cannot make the gate green, the PR is opened as
  a draft that says so. It never force-pushes, never touches main.
- **Notification is GitHub issues**, labelled `watch`, `watch:red|yellow|green`,
  plus `gh`'s own email. A digest email from the service is PRP-2/3 work.
- **The known-issues feed is written from here**: every yellow and red verdict
  also appends to `tools/watch/known-issues.json` (dependency, version, what
  breaks, workaround, fix status, issue URL), which the plugin's Get help
  screen and the support agent read later. Committed with the verdict.
- OPEN: whether Nathan wants a Slack/Telegram ping in addition to GitHub
  issues (one action step; needs a webhook URL from him).
- OPEN: whether green may also bump the plugin version and tag a release, or
  only edit readme metadata. The plan assumes metadata only.

## Out of scope

- Updating the box's PHP, nginx or MariaDB. Hand work.
- Watching plugins we do not integrate with (that is the rivals list, which
  stays as it is).
- Auto-merging anything. Auto-releasing anything.
- The support agent and the in-plugin Get help screen (PRP-2/3). This work
  only writes the file they will read.
- Multisite-specific upgrade runs. The stage is single-site; the network suite
  runs on `/var/www/ms` by hand as today.

## Context

**Files to read first**
- `tools/watch-competitors.mjs` — the watcher being extended; keep its state
  and report shapes.
- `.github/workflows/watch-builders.yml` — the workflow being extended; note
  the issue-creation step and the baseline-commit step.
- `tests/builders/compat.mjs` — the Playground compat matrix; the stage leg
  is its box-side equivalent.
- `tools/verify.mjs` — the gate runner: suite list, `before` hooks, how the
  box vs Playground split works, `--allow-skips`.
- `tests/integrations/live.php`, `divi.php`, `polylang.php` — the live checks
  the stage leg runs after an upgrade.
- `tools/deploy.mjs` — SKIP list (add nothing that must ship), the box hash
  verify.
- `core/page-builders.php`, `core/gallery-widgets.php`, `core/neighbours.php`,
  `core/seo-context.php`, `core/multilingual.php`, `core/health-delete.php`,
  `core/compatibility.php` — where the contract symbols live. The contract
  file is derived from these, with `file:line` for each symbol.
- `.claude/skills/validate/SKILL.md` — gates 1–3 are what the Action can run
  without a box; the contract check becomes gate 8.

**Files that change**
- `tools/watch-competitors.mjs` — rename to `tools/watch/watch.mjs` with a
  one-line shim left at the old path; add sources, contract check, `--verdict`.
- `tools/competitors.json` → `tools/watch/state.json` (moved, same shape,
  plus `core`, `php` keys).
- `.github/workflows/watch-builders.yml` → `.github/workflows/watch.yml`.
- `package.json` — `watch`, `watch:seed`, `watch:contract` scripts.
- `readme.txt` — nothing by hand; green edits `Tested up to`.

**Files created**
- `tools/watch/contract.json` — the dependency contract.
- `tools/watch/contract-check.mjs` — greps a downloaded zip (or the box's
  installed copy) for every symbol; JSON verdict.
- `tools/watch/sources.mjs` — core, PHP, wp.org plugin, changelog-page probes.
- `tools/watch/stage.sh` — runs on the box over SSH: upgrade one thing on
  `/var/www/upd`, run Plugin Check, the integration checks and the box suites,
  print JSON, restore the previous version from the kept zip.
- `tools/watch/triage.mjs` — turns watch + contract + stage results into a
  verdict, writes the issue body (calls OpenRouter for yellow/red plans),
  appends `known-issues.json`, performs the green edit.
- `tools/watch/fix.sh` — the `claude -p` branch/PR step for red.
- `tools/watch/known-issues.json` — the feed.
- `tests/watch/contract.test.mjs` — the contract check against a fixture zip
  with one symbol deliberately removed; the watcher's "cannot fail" guard.

**Prior art in this repo**
- The manifest diff and the Divi changelog probe in `watch-competitors.mjs`.
- `tools/plugin-check.mjs` drives Plugin Check in a browser; the stage leg
  reuses it against the stage URL.
- `tools/multisite.mjs` scp's a PHP suite to the box and runs it with
  `wp eval-file` — the shape of `stage.sh`'s invocation.

**External docs**
- https://api.wordpress.org/core/version-check/1.7/?channel=beta
- https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request[slug]=elementor
- https://www.php.net/releases/index.php?json&version=8
- https://core.trac.wordpress.org/query?milestone=6.9&component=Media&format=csv
- https://github.com/PHPCompatibility/PHPCompatibilityWP
- https://docs.anthropic.com/en/docs/claude-code/cli-reference (`claude -p`,
  `--allowedTools`, `--permission-mode`)
- https://openrouter.ai/docs/api-reference/chat-completion
- https://cli.github.com/manual/gh_secret_set

## Tasks

1. **Move and shim.** `git mv tools/watch-competitors.mjs tools/watch/watch.mjs`,
   `git mv tools/competitors.json tools/watch/state.json`; leave
   `tools/watch-competitors.mjs` as `import './watch/watch.mjs'`. Replace the
   PowerShell unzip with `unzip -o` when `process.platform !== 'win32'`.
2. **Contract file.** Write `tools/watch/contract.json` from the grep of
   `core/*.php` and `js/*.js`: for each of elementor, beaver-builder,
   brizy, zionbuilder, js_composer, divi, bricks, oxygen, breakdance, avada,
   thrive, cornerstone, yootheme, betheme, themify, tailor, oshine,
   learnpress, dokan, wordpress-seo, seo-by-rank-math, wp-seopress,
   all-in-one-seo-pack, woocommerce, advanced-custom-fields, polylang, wpml,
   wp-rocket, litespeed-cache, filebird, real-media-library, and `core`:
   `{ source, slug|url, symbols: [ { kind, value, usedIn } ] }`. Kinds:
   `hook`, `class`, `function`, `constant`, `meta`, `table`, `js`. Core's
   symbols are the `wp.media` view names and REST fields the JS extends.
3. **Contract check.** `tools/watch/contract-check.mjs <dir|zip> <slug>`:
   greps for each symbol with kind-appropriate patterns (`do_action(
   'name'` / `apply_filters( 'name'` for hooks; `class Name` / `namespace`;
   `define( 'NAME'`; meta keys as string literals; table names as
   `prefix . 'name'`). Output `{ slug, missing: [...], found: n }`. A hook
   may also be declared in a docblock `@hook`; count that as found with a
   note. Exit 2 when anything is missing.
4. **Sources.** `tools/watch/sources.mjs` exporting `core()`, `php()`,
   `plugin(slug)`, `changelogPage(url, regex)`. Core returns stable + newest
   beta/RC. PHP returns the newest patch per supported minor. Changelog-page
   probes for js_composer (CodeCanyon item page), Divi (existing probe),
   WP Rocket (wp-rocket.me/changelog), Polylang Pro (polylang.pro/changelog),
   Rank Math Pro, SEOPress Pro, Yoast Premium — each with an explicit regex
   and an error when the regex stops matching.
5. **Watcher verdict.** `watch.mjs --json` gains `kind: 'dependency'` entries
   and, for each moved dependency with a downloadable zip, runs the contract
   check and attaches `contract: { missing }`. `--verdict` prints one of
   `green|yellow|red` per moved item with the reason.
6. **Stage runner.** `tools/watch/stage.sh <kind> <slug> <version>` on the
   box: `cd /var/www/upd`; for core `wp core update --version=`; for a plugin
   `wp plugin install <slug> --version= --force` (keep the previous zip under
   `/var/www/.stage-keep/`); then `wp plugin activate` the one under test
   (deactivate after); run `php -l` over our plugin, `wp eval-file` the
   matching `tests/integrations/*.php`, and the box browser suites from
   `tools/verify.mjs` that name that dependency (`--only` list), against
   `http://upd.46.225.66.194.nip.io`; print JSON `{ passed, failed, output }`;
   restore the previous version. Never runs on `/var/www/wp` — the script
   refuses any path but `/var/www/upd`.
7. **Triage.** `tools/watch/triage.mjs report.json stage.json`: verdict per
   item; green → edit `readme.txt` `Tested up to` (core) or the
   "verified against" changelog note (plugin) and stage the change; yellow /
   red → build the plan prompt (contract diff, changelog excerpt ≤ 4k chars,
   stage output ≤ 8k chars, the `usedIn` files' relevant functions), call
   OpenRouter `anthropic/claude-sonnet-4.5` (cheap enough nightly; the fix
   step uses the stronger model), write the issue body in the `plans/`
   format, `gh issue create --label watch,watch:<verdict>`; append
   `known-issues.json`.
8. **Fix step.** `tools/watch/fix.sh <issue>`: `git switch -c
   watch/<slug>-<version>`, `claude -p "$(plan)" --permission-mode
   acceptEdits --allowedTools Edit,Write,Read,Grep,Glob,Bash(php -l *)
   --max-turns 40`, then `node tools/watch/contract-check.mjs` against the
   new release and gate 1; `gh pr create --draft` when either fails, a normal
   PR when both pass; body carries the gate output and links the issue.
9. **Workflow.** `.github/workflows/watch.yml`: nightly cron + dispatch;
   steps: checkout, node 22, `pnpm watch --json`, contract check, PHP
   compatibility (`composer global require phpcompatibility/phpcompatibility-wp
   dealerdirect/phpcodesniffer-composer-installer`, `phpcs -p --standard=
   PHPCompatibilityWP --runtime-set testVersion 7.4- --extensions=php
   --ignore=*/tests/*,*/tools/*,*/node_modules/* .`), stage over SSH when
   `secrets.BOX_SSH_KEY` is set (`ssh -i` with the key written to a temp
   file, `stage.sh` per moved item), triage, commit state + green edits +
   known-issues with `git push`, fix step when red and
   `secrets.OPENROUTER_API_KEY` and the Claude Code OAuth token are set.
   Concurrency group so two runs cannot both push.
10. **Secrets.** `gh secret set BOX_SSH_KEY < ~/.ssh/hetzner_vgml`,
    `gh secret set OPENROUTER_API_KEY`, `gh secret set
    CLAUDE_CODE_OAUTH_TOKEN` (from `claude setup-token`, Nathan's account).
    The last one **cannot be produced by an agent** — it is an interactive
    login. Until it exists the red path opens the issue without a PR and
    says why.
11. **Seed and first run.** `pnpm watch:seed` to record today; `gh workflow
    run watch.yml`; confirm the run is green and `state.json` was committed
    by the Action.
12. **Test.** `tests/watch/contract.test.mjs`: builds a tiny fixture plugin
    zip carrying three symbols, checks that all are found, removes one, and
    checks the run exits 2 naming it. Add it to `tools/verify.mjs` as a
    Playground-free suite.

Tasks 6 and 10 change state outside the repo (a site on the box; repository
secrets). Both are reversible: `rm -rf /var/www/upd`, `DROP DATABASE wpupd`,
`gh secret delete`.

## Validation strategy

- Gate 1 (PHP lint) on anything PHP the fix step produces — it runs inside
  `fix.sh`.
- Gate 3 (version consistency) after any green edit — the triage step refuses
  to stage a `Tested up to` edit if the three version lines disagree.
- New: `tests/watch/contract.test.mjs` (task 12) — the check must fail on a
  removed symbol. Without this, a regex that matches nothing reports every
  symbol present, which is the same failure shape as the suites that could
  not fail.
- New: a dry run of the whole workflow with a forced "moved" (`--pretend
  elementor=3.99.0`) that goes through contract check → stage → triage and
  opens an issue labelled `watch:dry-run`, then closes it. This proves the
  wiring end to end before a real release does.
- The stage leg reuses the existing suites; no new browser test.
- Query-count budget: not applicable, no new endpoint.

## Risks

- **The stage site drifts from production reality.** It is a clone of one
  configuration on the day it was made. Mitigation: `stage.sh` reports the
  active plugin list and versions with every run; a monthly re-clone is a
  one-liner in the box notes.
- **Changelog-page regexes rot silently.** Each probe throws when its regex
  stops matching, and the report shows `!`; the workflow fails the run on any
  probe error so it is seen. This is the Divi probe's existing behaviour.
- **A model-written plan that is wrong.** It is a plan in an issue, read by a
  person; red's PR is a draft when the gate is not green. Neither reaches
  main on its own.
- **`claude -p` in CI needs an OAuth token that only Nathan can mint**, and
  it consumes his subscription. Task 10 states this; the red path degrades
  to issue-only without it.
- **Concurrent pushes**: the Action commits state; a human pushing at the
  same minute conflicts. `concurrency:` on the workflow plus `git pull
  --rebase` before push.
- **Secrets in logs.** `set +x`, and the SSH key goes to a temp file with
  `chmod 600`, deleted in an `always()` step.
- **Plugin Check on the stage** hits the real site; it is slow (minutes) but
  the nightly has hours.
- **The contract grep has false negatives** when a plugin renames a file but
  keeps the hook (found) or defines a hook dynamically (`do_action( "prefix_
  $name" )`) — those are marked `dynamic: true` in the contract and count
  as "cannot verify" rather than missing.
