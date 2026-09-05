# Testing

Two environments, one runner, and a set of traps that have each cost a session
at least once. The gates themselves live in the internal validation notes and are not repeated.

## The two environments, and what each one can answer

| | Playground | The box |
|---|---|---|
| What it is | `@wp-playground/cli`, WordPress on PHP-wasm over SQLite | Hetzner CX33, Nuremberg — `46.225.66.194` |
| Boots in | seconds | it is always up |
| PHP / DB | 8.3 / SQLite shim | **8.5 / MariaDB 11.8** |
| Good for | anything functional, anything destructive, the upgrade path | numbers, query counts, real SQL, forward-compatibility |
| Cannot answer | performance, `$wpdb->num_queries`, anything needing real MySQL | nothing, but a suite that breaks the site should not run here |

**Playground cannot measure performance.** PHP-wasm spends ~2.4s booting
WordPress on every request, so core's endpoints time the same as ours and
wall-clock says nothing at all. What transfers is the **query count**: it is a
property of the algorithm, so both environments must agree on it, and a
disagreement is a bug rather than a hardware difference.

**The box runs ahead of the users on purpose.** PHP 8.5 and MariaDB 11.8 are
newer than almost anything this plugin will be installed on, which is exactly
why forward-compatibility bugs surface there first. It found the
`imagedestroy()` deprecation within a minute of existing — 545 lines in one
duplicate scan — and the `fgetcsv()` one on the CSV importer's first run. The
7.4 floor is checked separately, by Gate 1, against a real 7.4 binary.

### Reaching the box

    ssh -i ~/.ssh/hetzner_vgml root@46.225.66.194

Root over SSH, password login off, WordPress in `/var/www/wp`. Both plugins are
installed: `vergelabs-media-library` and `vergelabs-media-library-pro`.

**Kamatera is retired.** Anything still naming `185.229.224.239` is stale.

## Three copies, and the only thing that keeps them honest

This plugin exists in three places at once, and they do not agree unless made to.

| Copy | Where | Updated by |
|---|---|---|
| the source | `<your local checkout>` | editing it |
| the Playground zip | `playground/vergelabs-media-library.zip`, served to the world over the repo's raw URL | `pnpm deploy` |
| the box | `46.225.66.194:/var/www/wp/wp-content/plugins/…` | `pnpm deploy` |

There used to be a tarball recipe here. It had no verification step and nothing
that cleared opcache, and on 31-08-2026 all three copies disagreed with nothing
to say so. The source had the change; the zip was ten days old and did not
contain `core/admin-shell.php` at all; the box was running the previous day's
files. Every screenshot came off one of the two stale copies, so a nav item
that was already correct got rebuilt twice on the strength of it — and the
marketing domain turned out to be pinned to an eleven-hour-old Vercel
deployment the same afternoon, which is the same failure in a second place.

    pnpm deploy          # rebuild the zip, ship to the box, verify both
    pnpm deploy:check    # say what is stale; change nothing
    pnpm deploy:zip      # zip only
    pnpm deploy:box      # box only

**Run `pnpm deploy:check` before believing a screenshot.** It takes seconds and
it is the difference between debugging the code and debugging a copy of it from
last week.

### Every screen, rendered once

    pnpm smoke           # deploy to the box, then render all eight admin screens there

`tools/smoke-screens.php` draws each screen under an administrator with the
admin includes loaded — what runs is what runs when somebody opens the page —
and reports the size and a few shape checks (one `vgml-pg-head`, no stray
`<h1>`). A screen can lint clean and still fatal the moment it is drawn: an
undefined helper, a variable removed from a heading that something further down
still read. This catches that before a person does.

Two harness traps it already accounts for, so nobody rediscovers them:
`set_current_screen()` fatals without `wp-admin/includes/screen.php` and its
class loaded first, and the three settings screens live in a file the plugin
only includes when `is_admin()` is true — which under WP-CLI it is not, so the
smoke loads `core/admin-menu.php` and `core/options-pages.php` itself, in the
plugin's own order. Do not write the loop as a shell one-liner over `wp eval`;
the quoting fails in ways that look exactly like eight broken screens.

### What "verified" means here

`tools/deploy.mjs` never reports success from an exit code. Every shipped file
is hashed locally, the digests travel with the payload as `.deploy-manifest`,
and the far end re-hashes all of them and is asked whether they match:

- the **zip** is checked entry by entry against the CRCs in its own central
  directory, so a rebuild that silently dropped a file fails rather than passes;
- the **box** runs `sha256sum -c` over the manifest and reports how many files
  it actually verified.

It clears opcache and reloads PHP-FPM afterwards. PHP holds compiled files in
memory, so new bytes on disk are not necessarily the code being run, and a
deploy that skips this looks exactly like a deploy that did not happen.

Two things it does deliberately: it keeps a dated copy of the plugin directory
under `/root/vgml-backup-*` before writing, and it refuses any host not listed
in its `BOXES` map — a typo here overwrites a plugin directory on a live
WordPress.

### The zip is a published artifact

`playground/blueprint.json` installs it from
`raw.githubusercontent.com/…/main/playground/vergelabs-media-library.zip`, so
whatever is committed is what every person opening the Playground link runs.
Rebuilding it is not enough: **it has to be committed**, or the link stays on
the previous one.

It is written by a small ZIP writer inside `deploy.mjs` rather than by
PowerShell. `Compress-Archive` on Windows PowerShell 5.1 writes entry names
with backslashes (`vergelabs-media-library\core\ai.php`); Windows opens that
happily, Linux `unzip` and PHP's `ZipArchive` do not — they produce a single
file with a backslash in its name instead of a directory tree, and the plugin
unpacks into an unusable blob. The writer also fixes the timestamps, so
rebuilding over unchanged files gives a byte-identical archive and an empty
diff rather than noise.

### Playground against the working tree

    pnpm play            # mounts this checkout, then open 127.0.0.1:8899

`tools/play.mjs` reads the same `blueprint.json` and swaps its `installPlugin`
step for `activatePlugin` against the mount, so the demo data stays identical to
the shared link while the code under test is the file just edited. Two reasons
it cannot simply reuse the blueprint as-is: `installPlugin` and `--mount-dir`
collide with "Device or resource busy", and installing the zip locally means
testing the zip rather than the edit.

## The runner

`tools/verify.mjs` runs the standing battery, one suite at a time.

    node tools/verify.mjs                     # every suite it can reach
    node tools/verify.mjs csv ai-background   # only those
    node tools/verify.mjs --base http://46.225.66.194 --playground http://127.0.0.1:8899

Three things about it are load-bearing:

- **One at a time, by a lock file.** The suites mutate the site they test — t0
  files and unfiles, the watchdog breaks a plugin file and repairs it, the
  health suites scan and rescan. Overlapping runs produce failures
  indistinguishable from real regressions. That cost most of a session once.
- **A box has to be in the `BOXES` map to be reachable.** PHP suites are
  shipped over SSH, and the destination used to be hardcoded while `--base`
  moved only the HTTP suites — so pointing the runner at a second box ran the
  browser half against the new one and the PHP half against the old one, and
  reported both under the new one's name. A box it does not know is now refused
  loudly.
- **A suite reporting `0/0` is a failure, not a pass.** See the `wp eval-file`
  trap below.

`before:` on a suite is its precondition, run for it rather than written in a
document and forgotten — `smart.mjs` needs `wp option delete vergeml_smart_scan`,
and `ai.mjs` needs three files put back to undescribed.

## The traps

### `wp eval-file` evaluates the file inside a function

So every variable at the top of a PHP suite is a **local of that function**, and
`global $pass` binds to a different, empty variable. The counters stay at zero
whatever happens, the summary reads `0/0 passed`, and the `exit(1)` at the
bottom can never fire. **Four suites reported success for a week this way.**

Use `$GLOBALS['x']`, never `global $x`. The runner now also fails any PHP suite
whose last `N/M passed` line reads zero.

Nested `function` declarations have the same shape of problem: they exist only
once execution reaches them, so a callback must be declared **above** the
`add_filter()` that registers it.

### Playground: `127.0.0.1`, never `localhost`

WordPress builds URLs from `siteurl`. Browse the other name and every nonce
fails with "the link you followed has expired".

### Playground on Windows: `MSYS_NO_PATHCONV=1`

Without it Git Bash rewrites `/wordpress/...` into
`C:/Program Files/Git/wordpress/...`. The mount is two arguments:

    MSYS_NO_PATHCONV=1 npx --yes @wp-playground/cli@latest server --port 8899 \
      --mount-dir "<your local checkout>" /wordpress/wp-content/plugins/vergelabs-media-library \
      --blueprint=tests/tree/blueprint.json

A blueprint's `installPlugin` collides with `--mount-dir` ("Device or resource
busy") — use `activatePlugin` against the mount instead.

**Check the port is free before blaming the blueprint.** A Playground left
running from an earlier session holds it, and the new one exits with
`EADDRINUSE` after printing what looks like a successful startup banner.

### Plugin Check crashes under WP-CLI

`wp plugin check` takes php-wasm down part way through (`RuntimeError:
unreachable`), so the blueprint's `wp-cli` step is not an option. The browser
route is, and `tools/plugin-check.mjs` drives it — ticking **every** category,
because the form defaults to "Plugin Repo" alone and skips Security,
Performance and Accessibility.

Always check a **clean archive**, never the working tree: `git archive` honours
`.gitattributes`, so it contains what users install. Checking the working
folder reports the internal dev folders, `tests/` and `tools/` as findings, and
burying a real one under them is how a real one gets missed.

### An autoloaded option is only free once it exists

"Autoloaded, so it costs nothing" is true of an option that has been written.
An option that has **never** been written is not in `alloptions`, so
`get_option()` runs a real query for it and caches the miss — once per request,
for ever, on every site where the feature has not been used yet. Which is
nearly every site.

`vergeml_private_folders` shipped that way for about an hour. Gate 5 caught it:
Playground read 8 on the tree against the box's 7, and **core's own
`wp/v2/media` had gained one as well**, which is the detail that said it was
not a folder problem. Deleting the option on the box reproduced it there
immediately.

Any new autoloaded option needs a line in `vergeml_set_options()` creating it
empty, and that line is an upgrade-path change — Gate 7, not just Gate 5.

### Query counts must be measured over REST

Never from `wp eval` after other work in the same request: a scan that ran first
leaves the caches warm and the endpoint does not get them. That once reported 4
queries for a `health-report` that actually ran 70.

### Test debris drifts the counts

Sweep `zz*` attachments and terms, and probe stamps, between full batteries, or
folder counts drift and drag targets mis-aim. Suites written since are expected
to clean up after themselves, and to assert that they did.

## Writing a suite that can actually fail

The house rule, learned the expensive way: **a check that cannot fail is worse
than no check, because it reads as cover.**

- Assert on **deltas against a baseline** taken before seeding, not on absolute
  numbers — the box's library is not empty and other suites have run.
- **Register your own fixtures** rather than relying on the site's state.
  `tests/tree/auto-file.php` registers its own taxonomy with the plugin's
  `update_count_callback`, because `hide_empty => true` plus core's
  `publish`-only counter hides every attachment, which are `inherit`.
- **Order the assertion so the thing under test is what decides it.** The
  background suite's lock check first ran *after* a describe pass, and the mock
  is fast enough to finish the whole backlog in one pass — so the second pass
  had nothing to do and the check passed without the lock being involved. It now
  takes the lock before any work happens.
- **Mutate the product and watch the suite go red.** Every suite here that
  guards a specific bug has been run against that bug reintroduced:
  `pro/tests/api-base.php` against the filter put back, `lib/routing.test.ts`
  against the routing spread removed. If you cannot make it fail, you have not
  tested anything.
- Print `N/M passed` as the last line and `exit(1)` on any failure. The runner
  reads both.

## The suites

| Suite | File | Where | What it is for |
|---|---|---|---|
| `tree` | `tests/tree/t0-endpoints.js` | Playground | the tree endpoint, assigning, undo, move |
| `tree-view` | `tests/tree/tree-view.mjs` | local, from disk | the shared tree component: the draft by term id, the two states, the fold rule |
| `folders-version` | `tests/tree/folders-version.php` | box | the folders version stamp, its route, and that the poll is one option read |
| `guide` | `tests/tree/guide.php` | box | the Folders screen's server side: the page paints within its budget, a draft made safe, the four rules, the plan the re-filing takes, the done line, the cap |
| `ai-folders` | `tests/tree/ai-folders.php` | box | the AI group's counts and its join |
| `ai-folders-ui` | `tests/tree/ai-folders.mjs` | Playground | whether the group can be used |
| `auto-file` | `tests/tree/auto-file.php` | Playground | filing by itself, judged through the seam |
| `say` | `tests/tree/nl-commands.php` | Playground | spoken commands, mostly refusals |
| `quarantine` | `tests/tree/quarantine.php` | Playground | setting aside — that the file survives it |
| `utilities` | `tests/tree/utilities.php` | Playground | the utilities surfaces |
| `health` | `tests/tree/health.mjs` | box | the health report |
| `ai` | `tests/tree/ai.mjs` | box | describing, over the screen |
| `smart` | `tests/tree/smart.mjs` | box | smart folders, first-run |
| `ai-background` | `tests/ai/background.php` | box | the cron run, with no browser attached |
| `csv` | `tests/import/csv.php` | box | folders as a file, and the round trip |
| `organize` | `tests/organize/test-organize.php` | box | the proposed tree |
| `librarian` | `tests/librarian/test-librarian.php` | box | applying and undoing, in the database |
| `librarian-schema` | `tests/librarian/gate7-schema.php` | box | dropped tables, and the lazy reinstall |
| `librarian-ui` | `tests/librarian/librarian.mjs` | Playground | whether the screen can be used |
| `watchdog` | `tests/watchdog/recovery.js` | Playground | breaks the plugin, and proves it recovers |

Plus, outside the runner:

- `pro/tests/api-base.php` — four modes, one per process, because a constant can
  only be defined once. See its header.
- `service/lib/*.test.ts` — `npx vitest run` in the service repo.

## The benchmark

    node tests/perf/bench.mjs http://127.0.0.1:8903 admin:benchbenchbenchbench
    node tests/perf/bench.mjs http://46.225.66.194 admin:<app-password>

An application password for the box is made with:

    wp user application-password create admin bench --porcelain --allow-root

Playground gets one from `tests/perf/blueprint.json`, which also seeds 200
folders and 1000 files and copies `tests/perf/mu-perf.php` in as an mu-plugin —
the same probe the box runs.

Budgets and their derivations live in the validate skill. The short version:
`vergeml/v1/tree` is **7** and must not move with folder count, and
`health-report` is **5** with something to show and 3 with nothing.
