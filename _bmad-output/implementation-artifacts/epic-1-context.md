# Epic 1 Context: A customer gets 4.0.0 and keeps what they had

<!-- Compiled from planning artifacts. Edit freely. Regenerate with compile-epic-context if planning docs change. -->

## Goal

Plugin 4.0.0 is cut, tagged, archived and checked, but the suite around it still sells 3.16.x: the service's catalogue says free 3.16.1, the site's install page sends new customers to a GitHub release that is 3.16.0, and nobody has proven that a 3.16.1 site upgrades cleanly or that Pro 1.0.2 survives the update. This epic makes every new install 4.0.0, makes the service's own checks agree, proves the in-place upgrade on a real database, and proves Pro against the archive customers actually have. Existing free sites can only be reached through wordpress.org (no updater in the free plugin, by rule), which is outside this epic.

## Stories

- Story 1.1: The channel serves 4.0.0
- Story 1.2: A 3.16.1 site upgrades in place and keeps everything
- Story 1.3: Pro 1.0.2 works on 4.0.0

## Requirements & Constraints

- The served zip's `Version:` header, the catalogue's version, `/api/health`, the daily release-check and GitHub Releases/latest must all say 4.0.0 and refer to byte-identical bytes.
- An upgraded site keeps every folder, term assignment and librarian row; the librarian schema moves 3 → 4; no upgrade lock is left behind; the Folders screen opens on the old session.
- Pro keeps its licence page, provenance column and describe path on free 4.0.0, tested against the Pro archive customers have (the `0825e17` build), not pro HEAD, which carries unreleased changes.
- Test first; one mutation per story; every proof is the suite's or probe's own output line, pasted.
- Every cost in money or model calls is said before it is spent. Nothing here should spend anything beyond, at most, a few cents of describe calls in 1.2 if pictures are described rather than moved by hand.
- Nothing runs against ms2, the real shop or production data that the story does not name; snapshots are literal SQL, never read through the code under test.
- User-facing strings — the catalogue changelog line, the GitHub release text — are Nathan's and go out only as approved.
- Secrets (`PLUGIN_RELEASES`, `DOWNLOAD_SECRET`, `CRON_SECRET`) are read through `vercel env` and never printed.
- A deploy is verified by a marker unique to the build (the header inside the zip, the sha256), never by a status code.

## Technical Decisions

- Release artefacts are immutable, versioned static files: `service/public/releases/<slug>-<version>-<sha256 first 12>.zip`. Never overwrite one; retire the unversioned free zip in its own commit after the catalogue stops naming it.
- The catalogue (`PLUGIN_RELEASES`, one Vercel env string carrying both slugs) is the only source of truth and moves last: deploy the zip → read its header back over HTTPS → change the env → redeploy → health → release-check by hand. Rollback is the env step alone. A typo in one slug's entry takes the whole catalogue down, so parse the new JSON with the service's own parser before setting it.
- The free plugin never gets an updater; only Pro calls `/api/plugin/update`. The catalogue's free entry serves health and the cron.
- Every tagged version gets a GitHub release with the archived zip attached, byte-identical to the served file.
- Version-to-version upgrade proof runs on real MySQL in a fresh WordPress beside `/var/www/ms2` on the Hetzner box, through `wp plugin install <zip> --force` (the `WP_Upgrader` path a customer takes). Playground is the smoke only.
- Pro's contract with free is four touch points: the plugin basename, `vergeml_index_get()` rows with `alt`/`model`/`locked`/`error`/`described_at`, option `vergeml_ai['site_profile']`, and the media list honouring column id `vgmlpro_source`. Proof is free archive then Pro archive activated in Playground; Pro's box suites run against pro HEAD and are supporting evidence only.
- Catalogue `source` URLs are `https://vergelabsmedia.com/releases/<file>`; `ai.vergelabs.nl` (what Pro calls) is the same deployment and is where the channel is probed.
- Any new top-level directory in the plugin repo is `export-ignore`, or it lands in the release zip.

## Cross-Story Dependencies

- 1.1, 1.2 and 1.3 are independent: 1.2 and 1.3 use the zips in `../dist`, not the served ones.
- Epic 2's buyer walk uses 1.1's output (the 4.0.0 Pro download path) and does not start until Epic 1 is done and its cost is approved.
- On the record, not in this epic: Pro has no minimum-free-version gate; pro HEAD's licence sealing is unreleased (a 1.0.3 is waiting).
