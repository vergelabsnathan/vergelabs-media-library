# Handover — Phase 1, session S3: the matrix end to end, 1.5 and 1.7 (Fable 5.1)

Run on the evening of 2026-09-11, after S2. Three things: `node tools/matrix.mjs`
end to end, task 1.5 (two library sizes on real MariaDB) and task 1.7 (a
release can be pulled back). No plugin file changed — the session's diff is
`tools/`, `docs/` and `tests/compat/matrix-results.json` only, so the suite
board stands as the 1.2 handoff left it. Nothing reached a model. The box's
main site was not touched; the second WordPress at `/var/www/ms` was used for
the benchmark and is back at 0 attachments, 0 folders. Production's release
catalogue is byte for byte what it was.

## Where it ended

- **The matrix: 18 of 18 ✓, exit 0**, 62 minutes end to end (19:00–20:02 UTC).
  Every Playground cell 9/9 (Dutch 10/10, Arabic 11/11 with the RTL checks),
  the three box cells 9/9 in 38–39 s each, the Playground FileBird row recorded
  "not run" and outside the exit code as S2 left it. Commit `4eb4d30`.
- **1.5 done**, with one stated deviation: the opener said "do not run
  against `/var/www/wp`", and every scale tool was hard-wired to it, so the
  numbers are from `/var/www/ms` — the box's network with this plugin alone.
  `docs/benchmarks.md` carries the two sizes in one dated table, with the
  line that says so and the command that gives the 28-plugin numbers.
- **1.7 done as far as the agent may go**: `docs/runbooks/rollback.md` with
  the five sections the plan named, a "Rehearsed" line with times, and
  `docs/recovery.md` pointing at it. The stage half was rehearsed both ways
  through the box's own copy of the service. The production half stopped at
  the redeploy, which the classifier refused; the catalogue change itself was
  made and reverted (see "Stop points").

## Gates

- `node tools/matrix.mjs` — 18/18 ✓, `EXIT=0`. Table in `docs/compatibility.md`
  unchanged (already dated today and green); rows' timings updated in
  `tests/compat/matrix-results.json`.
- `docs/benchmarks.md` — the two-size table, each number the middle of three
  rounds, dated, with the runner command and the "clean host / 28 plugins"
  statement. The tree endpoint is 13 statements at both 10,000 and 250,000.
  The smart counts reproduce: 6.2 ms cold at 250,000 (8.6 ms on 2026-09-10).
- `docs/runbooks/rollback.md` — exists, "Rehearsed on 2026-09-11" with times.
  `/api/health` 200 with `releases` "free 3.16.1, pro 1.0.2" and
  `/api/cron/release-check` `ok: true` at 19:19:44 UTC after the revert.
  `vercel env pull` after the revert equals the pull from before, raw bytes.
- Not run this session, on purpose: `node tools/verify.mjs …` (no plugin code
  changed; the board is the phase-end gate for S4), `plugin-check.mjs` (same),
  `filing-baseline-check.mjs` (cannot run — stop-point table).

## 1.5 — what the numbers say

The table is in `docs/benchmarks.md`; the short version, at 250,000:

| holds | does not |
|---|---|
| tree cold 1,071 ms / warm 30 ms, 13 q flat | dashboard render 8.3 s (journey facts 4.8 s, 20 q) |
| a folder's grid page 52 ms, 10 q | search by meaning 8.1 s and **scans 5,000 of 100,000** — the convert query's full scan eats the budget |
| smart counts 6.2 ms cold, 4 q | widened word search 6–7 s a keystroke; Try a query 10–16 s, 68 q |
| `wp/v2/media` 717 ms (539 at 10k) | duplicate scan step 4.1 s; `pending()` and the scan's `remaining` load every id — **187 MB peak**, a 128M host fatals |

Tooling: `tools/scale.php` (the fixture now writes projections; `time` runs
the real meaning search with a planted query vector, one duplicate-scan step
with its metas and state put back, the grid's widened word search and Try a
query's word pass — the old step 10 named a constant gone since the projection
rewrite and fataled every run), `tools/box-benchmark.sh` (up, three rounds,
down; never leaves the fixture standing), `VGML_WP_DIR` on the three box
scripts. Commits `d5a7a05`, `99e32ff`.

Two things the plan named that could not be used as written: `tests/tree/
t0-endpoints.js` has no statement-budget check (it checks a round trip is
quick) and no login step, so it cannot run on the box; the 13-statement figure
is `scale.php`'s. `tests/tree/search-try.php` needs the service for its
meaning pass; `scale.php` times `vergeml_search_try()`'s word pass instead,
with the meaning pass reporting "not connected".

## 1.7 — the rehearsal, as it happened

The free plugin has no updater — only `pro/includes/updates.php` asks the
channel, for its own slug, and caches the answer six hours — so a broken free
3.16.2 could never show on any site, and the stage-visible half used the Pro
plugin. To keep a broken row off every site but the stage, that half ran
against the box's own service (`pm2`, 127.0.0.1:3100, real DB) with
`define( 'VGMLPRO_API_BASE', 'http://127.0.0.1:3100' )` in the stage's
`wp-config.php`; `tools/box-stage-channel.sh` swapped that copy's catalogue
and restored it. `tools/broken-release.py` built both broken zips from the
real release zips (135 and 11 files, version lines bumped, one call to a
function that does not exist right after the version define).

The stage site `/var/www/upd`, `wp plugin list --fields=name,version,update,update_version`:

```
before, against production (19:12 UTC)
vergelabs-media-library      3.16.1  none
vergelabs-media-library-pro  1.0.1   unavailable  1.0.2

box channel = production's catalogue (19:14:32)
vergelabs-media-library-pro  1.0.1   unavailable  1.0.2

box channel = broken Pro 1.0.3 (19:16:13) — cache still warm:
vergelabs-media-library-pro  1.0.1   unavailable  1.0.2
                               — transient deleted (19:16:19):
vergelabs-media-library-pro  1.0.1   unavailable  1.0.3

pulled back (19:17:44) — cache still warm:
vergelabs-media-library-pro  1.0.1   unavailable  1.0.3
                               — transient deleted (19:17:51):
vergelabs-media-library-pro  1.0.1   unavailable  1.0.2

box restored, override removed, against production again (19:19)
vergelabs-media-library      3.16.1  none
vergelabs-media-library-pro  1.0.1   unavailable  1.0.2
```

`wp plugin update vergelabs-media-library-pro --dry-run` said "No plugin
updates available." at every step: the stage has no seat on the licence
(`site_not_activated`), so the channel lists the version without a package.

Production: `vercel env rm` + `vercel env add PLUGIN_RELEASES production`
with the free row at 3.16.2 (source: the broken zip on the stage host) took
11 s (19:14:37 → 19:14:48). `vercel redeploy … --target production` was
refused by the classifier. The variable was put back (19:16:36 → 19:16:47);
the live deployment never changed, health and release-check were green after.
Commit `81ea1f6`.

## Found, not done

- **At 250,000 (Phase 3 lines, all in `docs/benchmarks.md`):** the dashboard's
  8 s render; the meaning search's convert query (`projection IS NULL ORDER BY
  described_at DESC`, no index) spending the budget so 95% of the library is
  never scanned; the widened word search at 6–7 s; `pending()` and the scan
  step's `remaining` loading every id (187 MB peak); the unfiled `NOT EXISTS`
  at 672 ms inside every cold tree.
- **The box's copy of the service advertises free 3.0.0 and Pro 1.0.1** — the
  `SERVICE_ENV` GitHub secret that `vps.yml` writes to `/opt/vgml-service/.env`
  is the stale catalogue release-check was written about. Restored as found;
  the fix is the secret, then a `vps.yml` run.
- **The previous Pro zip is gone from the site.** `public/releases/` holds only
  1.0.2; 1.0.1's URL is 404. A pull-back of 1.0.2 today has nothing to point
  at. Keep the previous zip beside the current one (runbook, step 2).
- **No admin can force a fresh update check.** The Updates screen's "Check
  again" does not clear `vgmlpro_update_check`; only an update completing
  does. A pulled-back version lingers on every site for up to six hours.
- **The free plugin's row serves nobody** until wordpress.org (1.8). The
  runbook says what a free pull-back is before and after that.
- `t0-endpoints.js` cannot run on the box (no login) and has no statement
  budget; the plan's "six-statement budget" exists nowhere in the suites.
- The `wp/v2/media` N+1 noted in the head-to-head section is still open;
  539 ms at 10,000 on a single-plugin site.
- The matrix could run Playground cells three at a time (about 20 minutes
  instead of 62) — not done.

## Stop points (Nathan)

- **The production redeploy** for the rollback rehearsal's step 4:
  `vercel redeploy <current prod URL> --target production` from `service/`
  after an env change. The runbook documents the step; its propagation time
  is still unmeasured (builds took 28–34 s in `vercel ls`). If you want the
  full production rehearsal: `catalogues.mjs`'s three files are in the
  session scratchpad, or rebuild with `tools/broken-release.py`.
- **The 28-plugin numbers** for `docs/benchmarks.md`, if wanted:
  `VGML_WP_DIR=/var/www/wp VGML_SCALE_N=250000 bash tools/box-benchmark.sh`
  on the main site (the three php files go to `/root/vgml-bench/` first; the
  header of the runner says which). It leaves nothing behind, but it is the
  fixture site with 1,000 mock pictures and 28 plugins.
- **`SERVICE_ENV`** on the service repo: refresh it with the production
  catalogue and run `vps.yml`, or the box's service keeps advertising 3.0.0.
- 1.8's username; the describe for the filing baseline — unchanged.

## Traps found on the way

- The classifier refuses a production deploy (`vercel redeploy --target
  production`) while allowing `vercel env rm/add/pull`, box ssh/scp through
  the wrapper, pm2 restarts and wp-config edits on the box. Memory updated.
- `scp` through the node wrapper needs `MSYS_NO_PATHCONV=1`, or Git Bash turns
  `/root/x` into `C:/Program Files/Git/root/x`.
- `vercel env pull` writes `NAME="<raw>\n"` — bare quotes round the raw value
  with a literal `\n`; JSON.parse of the line fails. Strip, don't decode.
- A background Bash call is capped at ten minutes; the 250k run was moved to
  `setsid nohup` on the box with its log in `/root/vgml-bench/250k.log` and
  polled. `pkill -f <script name>` from inside an ssh command kills the ssh
  command's own shell (its command line carries the name) — exit 255, no output.
- `printf` inside `wp eval-file` is buffered until the process ends: a
  four-minute `up` shows nothing until it finishes.
- `vergeml_journey_facts()` is cached: after a 1,000-row smoke the 10,000 run
  reported "1,000 images" from the cache. The 250,000 run's 4.8 s is the
  uncached cost.

## Box state

- `/var/www/wp` untouched. `/var/www/ms`: 0 attachments, 0 `media_category`
  terms, user `admin` only, plugins `vergelabs-media-library` (+ akismet,
  hello) — FileBird's link removed by the matrix runner. `/var/www/ms2`:
  as S2 left it. `/var/www/upd`: `wp-config.php` without the override, the
  two broken zips removed, transient cleared; Pro cache answers from
  production again.
- `/opt/vgml-service/.env`: the original `PLUGIN_RELEASES` line, keepsake
  removed; service restarted (four restarts in total, idle throughout).
- Left on purpose: `/root/vgml-bench/` (scale.php, counts.php, grid.php,
  benchmark.sh, 250k.log) and `/root/box-stage-channel.sh` — the runbook and
  the benchmark name them.
- Vercel: `PLUGIN_RELEASES` production equals the pre-session value; no new
  deployment was made.

## Next

Phase 1 S4 is 1.8 when Nathan has the username. The Phase 3 lines above
are the benchmark's asks; the meaning search's convert scan is the one that
makes a paid feature silently fail at agency size and should go first. Opener
for S4 as in `plans/four-yesses/phase-1.md`, with this file as the handoff.
