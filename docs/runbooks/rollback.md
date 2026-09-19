# Pulling a release back

A release goes out through the catalogue: `PLUGIN_RELEASES` on the Vercel
project `vergelabsmedia`, production environment. It is a JSON array with one
row per plugin — slug, version, `source` (where the zip is), `paid`, the
WordPress and PHP minimums, a changelog line. `service/lib/updates.ts` reads it
from the environment on every request; nothing about a release lives in the
database. Pulling a release back is therefore an environment change plus a
production deployment, and then the wait for sites to ask again.

## When

- A release fatals on load, breaks a screen, or writes something wrong, and
  the fix is not minutes away. Withdrawing the row stops every site that has
  not yet taken it. It does nothing for the sites that have — see "A site
  that already took it".
- Only the Pro plugin asks this channel. `pro/includes/updates.php` asks
  `/api/plugin/update` for its own slug and caches the answer for six hours
  in the transient `vgmlpro_update_check`. The free plugin has no updater of
  its own: its row is checked by `/api/health` and the daily `release-check`,
  but no site reads it. Once the free plugin is on wordpress.org (Phase 1.8) a
  free pull-back happens there — set `Stable tag` in `trunk/readme.txt` back
  to the previous version in the SVN. Until then new free installs come from
  GitHub Releases/latest — the site's install page (`public/index.html`) and
  the licence email (`service/lib/email.ts`, `FREE_PLUGIN_RELEASE`) both
  point there — so a free pull-back is `gh release edit v<previous> --latest`
  (GitHub's "latest" is whichever release was marked last; unmarking the bad
  one alone is not enough), plus the catalogue row back to the previous file
  so health and the release-check stay honest. The two "previous" versions
  differ today: the catalogue's is 3.16.1, GitHub's is v3.16.0 (no v3.16.1
  release was ever published).

## How releases are named (binding for both plugins from 4.0.0, 2026-09-19)

Every zip in `service/public/releases/` is `<slug>-<version>-<sha256
prefix>.zip` — twelve hex characters from 4.0.0 on
(`vergelabs-media-library-4.0.0-bf0d63b70056.zip`); Pro's existing
`vergelabs-media-library-pro-1.0.2-2a6a794642.zip` carries ten and predates
the rule. A published file is never overwritten. The current and the previous
version of each slug stay on disk, so step 2 below is an env flip; anything
older is retired in its own commit, and never within a day of the catalogue
moving off it (Pro sites cache the package URL for six hours). As of
2026-09-19 the free plugin has both (`…-3.16.1-7f2a4fe9bee9.zip` beside
4.0.0); Pro has only 1.0.2 — 1.0.1's file was removed in `15f4d47`, so a Pro
rollback today is `git show 15f4d47^:public/releases/vergelabs-media-library-pro-1.0.1-d0fe7f2ee9.zip > public/releases/<same name>`,
commit, deploy, then the env step. The unversioned
`vergelabs-media-library.zip` is gone; a restored file always takes the
versioned name, never that one.

The order of a release is: commit and deploy the zip; fetch it and check that
the `Version:` header inside is the version and the sha256's first twelve
characters are the ones in the file name; write the new catalogue and parse
it with the service's own reader — from `service/`,
`PLUGIN_RELEASES="$(cat catalogue.json)" npx tsx -e "import('./lib/updates.ts').then(m=>console.log(m.releases().map(r=>r.slug+'@'+r.version+' '+r.source)))"`
must print every row with the new `source` (`releases()` never throws: a
typo prints nothing, a bad row is dropped silently, so count the rows); then
`vercel env rm` / `vercel env add` and a redeploy; then
`gh release create v<version> <zip> --title … --notes-file … --latest` in the
plugin repo. The GitHub asset is uploaded as `vergelabs-media-library.zip`
on purpose — every release's asset has that name, and WordPress users expect
it — and its digest must equal the served file's. 4.0.0 went out this way on
2026-09-19 (service `5e1f5e9`, `dd07dd0`, `3eb0b9b`).

## What to change

1. Keep what is there: from `service/`,
   `vercel env pull --environment=production --yes prod.env`, and copy the
   `PLUGIN_RELEASES` line somewhere safe.
2. Write the catalogue you want: the same array, with the bad row's `version`
   and `source` set back to the previous release. The previous zip has to
   exist at that `source` (see "How releases are named"); if it was removed,
   restore it from git and deploy it first.
3. Replace the variable: `vercel env rm PLUGIN_RELEASES production -y`, then
   `vercel env add PLUGIN_RELEASES production < catalogue.json`. The two
   together took 11 seconds in the rehearsal. The live deployment does not
   change yet — it keeps the environment it was built with, so there is no
   gap in service between this step and the next.
4. Deploy: `vercel redeploy <current production URL> --target production`
   (`vercel ls` prints the URL), or push to `main`. Production builds took
   24–45 seconds on 2026-09-11 and 09-13. This is the step that changes what
   sites see. It ran from the agent's terminal twice on 2026-09-13 (the
   monitor rehearsal, `../../../service/docs/runbooks/incident.md`,
   "Rehearsed") and was refused by the shell classifier on 2026-09-11; if it
   is refused, it is Nathan's, from his terminal or the dashboard.
5. Say so in the handoff or the commit: which version was withdrawn, at what
   time, and what the catalogue now says.

## How you know it worked

- `https://vergelabsmedia.com/api/plugin/update?slug=vergelabs-media-library-pro&version=<bad>`
  answers `"update": false` — the channel no longer knows anything above the
  bad version. With `version=<previous>` it answers `"update": false` too.
- `/api/health` is 200 and its `releases` line names the versions you meant.
- `/api/cron/release-check`, with `Authorization: Bearer $CRON_SECRET` (the
  secret is in the same Vercel environment), answers `"ok": true`: the zip at
  each `source` carries the version its row claims.
- The file at the restored `source` hashes to the twelve characters in its
  own name (`sha256sum` on the fetched bytes) — the cron reads the header,
  not the bytes.
- On a site: `wp transient delete vgmlpro_update_check`, then
  `wp plugin list --fields=name,version,update,update_version`. Without the
  delete, a site that checked in the last six hours still shows the withdrawn
  version — that is its cache, not the channel. The Updates screen's "Check
  again" does not clear our transient either.
- A site that still shows the withdrawn version cannot install it. The
  download route (`app/api/plugin/download/route.ts`) compares the token's
  version with the catalogue and answers 409 `release_unavailable` for a
  withdrawn one, so "Update now" fails instead of installing.

## A site that already took it

- WordPress does not downgrade. A site on 1.0.3 sees a catalogue at 1.0.2 as
  up to date. The fleet-wide fix is a newer version: the previous code with
  the version bumped, published the usual way. A pull-back only protects the
  sites that have not updated yet.
- A white screen: core's recovery mode emails the site's admin address a link
  that logs in with the plugin paused (`docs/recovery.md`, section 2). Our own
  watchdog (`core/watchdog.php`; `includes/watchdog.php` in Pro) only catches
  a fatal after it has registered. A fatal above the `require_once …
  watchdog.php` line in the main file — where the rehearsal builds fatal on
  purpose — is never caught.
- By FTP or the host's file manager: rename the folder
  `wp-content/plugins/vergelabs-media-library-pro` to
  `vergelabs-media-library-pro.off` (free plugin:
  `wp-content/plugins/vergelabs-media-library`). WordPress skips an active
  plugin whose main file is gone, so the site is back on the next request,
  and the Plugins screen drops it from the active list when it next loads.
  Then upload the previous version through Plugins → Add New → Upload, or
  unzip it in place under the original folder name, and delete the `.off`
  folder. Folders, files and the plugin's tables are untouched throughout;
  nothing needs re-filing.
- With WP-CLI: `wp plugin deactivate vergelabs-media-library-pro`, then
  `wp plugin install <previous zip> --force`.

## Rehearsed

Rehearsed on 2026-09-11 (Phase 1, task 1.7), in two halves, because a broken
row must not reach any site but the stage.

- **The channel and a site, on the box.** The stage site `/var/www/upd` was
  pointed at the box's own copy of the service with
  `define( 'VGMLPRO_API_BASE', 'http://127.0.0.1:3100' );` in its
  `wp-config.php` — the Pro updater accepts that override — and that copy's
  catalogue was changed with `tools/box-stage-channel.sh`. Times, UTC:
  the catalogue took a broken Pro 1.0.3 at 19:16:13 and the service restarted;
  the stage still said 1.0.2 from its cache, and said 1.0.3 at 19:16:19 once
  the transient was deleted. Pulled back at 19:17:44; the stage still said
  1.0.3 from the cache; 1.0.2 at 19:17:51 after the delete. The box was
  restored as found by 19:19: the catalogue line, the `wp-config.php` line,
  and the two zips.
- **Production, on Vercel.** `vercel env rm` + `add` with the free row at a
  broken 3.16.2 took 11 seconds (19:14:37 → 19:14:48). The redeploy was
  refused to the agent, so the variable was put back (19:16:36 → 19:16:47)
  and `vercel env pull` showed the pre-rehearsal catalogue again, byte for
  byte. The live deployment never changed. Health and release-check answered
  200 and `"ok": true` at 19:19:44. Step 4 is still to be rehearsed by
  Nathan; the build time above is from `vercel ls`.
- **The broken builds.** `python tools/broken-release.py <release.zip> <slug>
  <old> <new> <out.zip>`: every file of the real release, the two version
  lines bumped, and one call to a function that does not exist right after
  the version define. Never commit one; never point a row at one on
  production.

The stage's `wp plugin list` lines before, during and after are in
`docs/handoffs/2026-09-11-phase-1-s3-matrix-scale-rollback.md`.
