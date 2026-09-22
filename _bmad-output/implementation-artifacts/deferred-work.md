- source_spec: `spec-1-1-the-channel-serves-4-0-0.md`
  summary: service/public/index-v2-backup.html still links /releases/vergelabs-media-library.zip, which is gone (404).
  evidence: nothing links to the backup page; it is Nathan's archived site export, so deleting or editing it is his call.
- source_spec: `spec-1-1-the-channel-serves-4-0-0.md`
  summary: tools/promote.mjs could HEAD every catalogue source under /releases/ and refuse to say promoted on anything but 200, closing the gap between a release commit and the daily release-check; the same script (or a sibling) should produce the <slug>-<version>-<sha256[:12]>.zip name, which today is typed by hand.
  evidence: this story's read-back was by hand; the cron is the only automated check and runs once a day; promote.mjs already parses /api/health.
- source_spec: `spec-1-2-a-3-16-1-site-upgrades-in-place.md`
  summary: a one-command box walk (reset → 3.16.1 → fixture → freeze → 4.0.0 → suite) so the fixture is re-armed for the next schema bump without the story notes.
  evidence: today the fixture and freeze steps are run by hand through the ssh wrapper; only the suite is a verify.mjs entry.
- source_spec: `spec-1-2-a-3-16-1-site-upgrades-in-place.md`
  summary: the 4.0.1 changelog names the PHP 8.4+ deprecation fix (core/ai.php vergeml_ai_rest_status), beside FR10 and FR12.
  evidence: readme copy is Nathan's; the fix is in the tree since 6dc3223.
- source_spec: `spec-1-2-a-3-16-1-site-upgrades-in-place.md`
  summary: 4.0.0's Folders screen answers 500 on a stored draft whose folders are strings (core/guide.php:1255 reads $f['key'] on a string); a malformed session should be discarded, not fatal.
  evidence: seen on the fixture with a hand-shaped draft on 2026-09-19; 3.16.1's own writer never produces that shape, so no customer path reaches it today.
- source_spec: `spec-1-2-a-3-16-1-site-upgrades-in-place.md`
  summary: a second smoke variant that swaps 4.0.0 over a site with no vergeml_guide_session (the matrix's "No session" row).
  evidence: the fixture always plants a session; the row is asserted nowhere.
- source_spec: `spec-1-3-pro-1-0-2-works-on-4-0-0.md`
  summary: Pro's describe writes _wp_attachment_image_alt with update_post_meta outside vergeml_index_writing(), so the free plugin's vergeml_index_watch_alt locks `alt` on that picture and a free re-describe will not paint over it; decide whether that lock is intended for Pro's own writes.
  evidence: pro/includes/describe.php:232 (same in the 1.0.2 archive); plugin/core/ai-index.php:562-576 -- and identically core/ai-index.php:565 in the 3.16.1 archive, so it is pre-existing, not an update-day change. Pro's column reads its own record first (vgmlpro_provenance_state) and still says "We wrote this".
- source_spec: `spec-1-3-pro-1-0-2-works-on-4-0-0.md`
  summary: a `--free-tree` pairing in pro/tools/verify.mjs (free working tree against the Pro 1.0.2 archive) so the next free release can run compat-free against what Pro customers actually hold.
  evidence: the archives leg pins both sides to the 4.0.0 / 1.0.2 zips and --tree mounts both working trees; AD-6 binds every free release after 4.0.0, and that pairing has no mode today.
- source_spec: `spec-1-3-pro-1-0-2-works-on-4-0-0.md`
  summary: `.harness` is not export-ignore in .gitattributes; the 4.0.0 archive ships .harness/active.json (3,986 bytes). A line for the 4.0.1 cut.
  evidence: `unzip -l dist/vergelabs-media-library-4.0.0.zip` lists vergelabs-media-library/.harness/active.json; the archive is cut and tagged, so it stays for 4.0.0.
- source_spec: `spec-1-3-pro-1-0-2-works-on-4-0-0.md`
  summary: Pro's api-base suite on the box fails "the base agrees with the free plugin" because /var/www/wp/wp-config.php:101 defines VERGEML_AI_SERVICE = http://127.0.0.1:3100/v1 (a next-server on the box); decide whether the tech site keeps that define or the check allows it.
  evidence: 2/3 on 2026-09-20 with pro HEAD; every other Pro suite green. The box's configuration, not Pro's code.
- source_spec: `spec-epic-1-retro-chores.md`
  summary: the box walk (upgrade-3161 on /var/www/upg, real MySQL) from the rollback target b787a3bb6a20 -- today's fixture was built from 7f2a4fe9bee9 and stays up until the next schema bump; at that `reset` install the target as the old side (box-upgrade-site.sh plugin <b787 zip>) so the MySQL proof starts from the code customers had.
  evidence: the smoke walks from both 3.16.1s (SMOKE GREEN twice, 2026-09-20); the box proof still starts from git's 3.16.1.
- source_spec: `spec-epic-1-retro-chores.md`
  summary: the snapshot's plugin_digest is recorded, never asserted -- the smoke could compute the same digest from old.zip's and new.zip's entries (tools/lib/zip.mjs readZipEntries) and assert `plugin before` / `plugin after` match, turning A-3's print into a proof.
  evidence: review pass 1 (BH5); A-3 asked for "records"; both smoke runs on 2026-09-20 printed different old digests landing on one new one, by eye.
- source_spec: `spec-epic-1-retro-chores.md`
  summary: a Pro-side archive check (git archive HEAD through readZipIndex, the hidden-segment and furniture rules) -- pro/tools/verify.mjs has no local kind; the export-ignore lines landed in pro cea2e1e.
  evidence: review pass 1 (VG2); git archive in pro/ shipped .harness/active.json, tools/verify.mjs and .gitignore before that commit.
- source_spec: `spec-epic-1-retro-chores.md`
  summary: the default compat-free never compiles Pro's working tree on 8.5 (the archives leg is what customers have; --tree is opt-in); a tree leg belongs with the deferred --free-tree pairing.
  evidence: review pass 1 (VG1-b); a deprecation introduced in Pro's tree boots only under --tree.
- source_spec: `spec-epic-1-retro-chores.md`
  summary: the A-11 seat-safety ordering (deactivate with no key stored, then restore) is pinned by no test; one runbook line naming the invariant, or a fixture that holds a real key on purpose and a ledger read after teardown.
  evidence: review pass 1 (VG3); both tools judge their own last lines, not the licence server's seat count.
- source_spec: `spec-2-1-the-buyer-walk-on-4-0-0.md`
  summary: a percent code that takes a yearly plan under Stripe's minimum charge (PRIVATE0, 99 % of EUR 39 = EUR 0.39) yields a free year -- Stripe marks the sub-minimum invoice paid with amount_paid 0, activates the subscription, and the webhook issues the licence on invoice.paid. Guard in the intent route (refuse a subscription whose first invoice would be under the floor, or floor the coupon), and decide what to do with PRIVATE0 (Nathan's: switch off or restrict to credits). DECIDED 2026-09-21 (docs/decisions-2026-09.md row 4): switch it off; a new credits-only code when one is wanted; the floor (story C) already covers yearly plans. The click at /admin/discounts and the ...AB26 cancel are Nathan's.
  evidence: invoice AWTXBCFC-0012 on the walk customer, status paid, total 0.39, paid 0.00, subscription active, licence ...AB26 with 2,000 credits, no money; lib/discounts.ts discounted() only floors one-off amounts.
- source_spec: `spec-2-1-the-buyer-walk-on-4-0-0.md`
  summary: /account Billing lists a customer's abandoned checkouts as `open` invoices (five EUR 39.00 / 0.78 "open" rows beside the one paid one) -- draft-incomplete subscription invoices should be hidden or shown as "not completed", not as money owed.
  evidence: the walk account's Billing tab on 2026-09-20: "Invoices 7 on file", one paid, five open, from checkouts that never confirmed.
- source_spec: `spec-2-1-the-buyer-walk-on-4-0-0.md`
  summary: the licence email's copy carries the plaintext key and goes to the buyer; fine -- but nothing after the purchase tells the buyer to create the account the email points at, and the order page's "Go to your account" lands on a sign-in form for an address that has no account yet. A first-time buyer should land on registration with the address filled.
  evidence: from the code, not observed -- app/order/page.tsx links plain /account and app/account/page.tsx:219 defaults mode to 'signin'; the walk script opened /account?mode=register itself.
- source_spec: `spec-2-1-the-buyer-walk-on-4-0-0.md`
  summary: the walk licence (...93RJ, EUR 0.50, cancels 2027-09-20) and the free PRIVATE0 licence (...AB26) stay on the walk account nathan+buyer-0920@vergelabs.nl; cancelling ...AB26 from /account is one click (no money), Nathan's.
  evidence: /account overview 2026-09-20 shows both; verify on ...93RJ: 1,999 credits, 0 sites.
- source_spec: `spec-c-subscription-floor.md`
  summary: a code used on a yearly plan never records its redemption -- the webhook's payment_intent.succeeded returns early for subscription plans before recordRedemption(), and invoice.paid writes none -- so max_uses and "first purchase only" do not hold on single/five/agency. Write the redemption on invoice.paid for the first invoice (the subscription's metadata carries code and code_id).
  evidence: WALK0920 (max_uses 1) still answers ok EUR 0.50 on /api/pricing after the 2026-09-20 purchase; app/api/stripe/webhook/route.ts:541 returns before :547.
- source_spec: `spec-3-2-the-licence-key-leaves-the-support-ticket.md`
  summary: a licensed site whose origin changed since activation (http→https, a domain move, staging, a network subsite) now files its ticket with licence_id null -- the full-key path resolved it regardless of site. Decide whether a prefix that matches exactly one licence may resolve without a seat (a 4-character guess would then attach a stranger's licence facts to a ticket), or keep the seat rule and rely on the note's count.
  evidence: consolidation review 2026-09-20 (three lenses); the note now says "(N licences end that way)" so a person can find it; lib/store.ts findLicenceByPrefixOnSite joins live activations only.
- source_spec: `spec-4-1-the-csv-export-cannot-execute-in-a-spreadsheet.md`
  summary: the box suite tests/import/csv.php never exports a folder that starts with a trigger character, so FR12 is proven only by the fixture-only csv-local suite; add one =HYPERLINK folder to the box fixture (counts 5→6) at the next box run; a 4.0.0 export holding a folder literally named '=x loses one apostrophe when imported by 4.0.1 (no in-band marker); the filename column is prefixed too (correct protection, a visible apostrophe on a file named -01.jpg).
  evidence: consolidation review 2026-09-20; csv-local 69/69 is the proof on file; csv on the box unchanged.
- source_spec: `spec-e-minimum-free-gate.md` (pro/docs/specs)
  summary: the licence screen's in-page sentence covers "not active" only; "active, too old" shows via admin_notices alone (settings.php outside the story's Files line); pro readme should name the 4.0.0 floor and could carry `Requires Plugins: vergelabs-media-library`; compat-free --tree with a real key (the archives leg cannot exercise the gate) is the train's, with VGMLPRO_SEATS_KEY.
  evidence: consolidation review 2026-09-20; min-free 15/15 on both archive legs.
- source_spec: `spec-d-billing-and-order.md` (service/docs/specs)
  summary: the order page's registration hand-off relies on the cart having written localStorage vgml-email in the same browser; a buyer who reaches /order in another browser (a 3-D Secure redirect that opened elsewhere) gets an empty email field -- correct, but worth a Playwright row; no e2e spec opens /order at all (the has_account route test is the pin).
  evidence: consolidation review 2026-09-20; app/api/order/route.test.ts 4 rows.
- source_spec: `plans/finish-the-suite.md`
  summary: tools/verify.mjs runPhpPlayground() duplicates runPhpLocal()'s result judging (~30 lines); a shared judge() would keep the two from drifting; verify.mjs in plugin assumed ../plugin and ../dist beside the checkout (pro's runner fixed it for worktrees with sibling()).
  evidence: consolidation review 2026-09-20 (blind hunter); pro 9fa975f sibling().
- source_spec: `plans/finish-the-suite.md` (the release train, S26)
  summary: `.github/workflows/box.yml` deploys to the box on every push to main, so the plugin push that carries a release tag puts the new build on the box before the served zip is read back over HTTPS -- the train's "box deploy only after the read-back" cannot be kept by pushing; either push the plugin main after the read-back next time (the GitHub release needs the tag pushed, not main) or give box.yml a manual trigger for release commits. Also: the shelf now holds two 3.16.1s, 4.0.0, 4.0.1, Pro 1.0.1, 1.0.2, 1.0.3 -- the runbook says older files are retired in their own commit (Pro never within a day of the catalogue moving); 3.16.1 b787a3bb6a20 is the rollback target the upgrade smoke reads, so the retirement is a decision, not a chore.
  evidence: box.yml run 35529498161 at 18:34:29Z on the 4.0.1 push; the HTTPS read-back followed at 18:37Z (S26 handoff).
- source_spec: `spec-4-2-the-compatibility-matrix-on-4-0-0.md`
  summary: beside FileBird 6.5.8 on the box's MariaDB network, a single unselected row dragged onto a folder lights the folder and files nothing -- our draggable's helper() (which sets `dragging`) does not run for that press, so the drop handler (js/vergeml-tree.js:2310) falls back to the checked rows and finds none; a drag of checked rows files them. The 09-11 bytes passed the same cell. Three things moved since: the plugin (c85cde6 → 4.0.1; the arming of the title cell in armOne(), js/vergeml-tree.js:2085 with the reasoning at :2073-2082, is what was meant to win that press -- the two 09-17 list commits aedb0ef S10.6 and 3a13b74 takeNav are the candidates), the box's WordPress (7.1 → 7.1.1), and nothing in FileBird (6.5.8 on both dates, the box's own copy). A story of its own; the cell is the proof (`node tools/matrix.mjs --cell with=filebird,shape=multisite-subdirectory`), and the runner should read the companion's version into the row rather than carry it as a label.
  evidence: matrix 2026-09-20 on 4.0.1, three times: "drag one into the folder: the folder lit up; file 251050 is in []", then "2 of 2 moved; the folder counts 2" (7/10 with the restore step); the same by hand with mock off; the subdirectory cell without FileBird 10/10 a minute earlier.
  resolved: 2026-09-21, story 4.4 (`spec-4-4-a-single-row-drags-into-a-folder-beside-filebird.md`). Not the arming and not WordPress 7.1.1: `e68bbcd` (09-14) made `core/media-list.php` write `.column-title { width: 30% }` once a column beyond core's is on, every column was then sized, and the fixed table gave its 36% of slack to the checkbox column (324px of 900), whose full-cell label is FileBird's drag handle. The share now waits behind `body.vgml-file-share`, which js/vergeml-media-list.js adds only when File measures below it. Cell ✓ 10/10; drag.mjs beside FileBird 21/21 (was 7/19) through tools/box-drag-beside.mjs.
- source_spec: `spec-4-4-a-single-row-drags-into-a-folder-beside-filebird.md`
  summary: tools/matrix.mjs carries the box companion's version as a label ("FileBird 6.5.8 (MariaDB)") and never reads it; tools/box-drag-beside.mjs copies runBox()'s prep and restore (user, link, option snapshot, mock write) rather than sharing it -- matrix.mjs runs its cells on import, so there is nothing to import. A boxPrep()/boxRestore() module both call, reading the companion's version from its header, would keep the two from drifting (the "cd inside the pipeline" defect was found in one copy once).
  evidence: review 2026-09-21 (blind hunter, acceptance auditor); the two prep blocks are line-for-line the same today.
- source_spec: `spec-4-4-a-single-row-drags-into-a-folder-beside-filebird.md` (code review)
  summary: the FileBird cell (the only place a single-row drag beside a companion is exercised) runs by hand through tools/matrix.mjs --cell; CI's pnpm test:ui covers the geometry on the tech site (modes.spec: checkbox < 60px, the press on the title cell, the share class per set) but never a companion's draggable on the same rows. Whether the box workflow should run the two box cells on a push is Nathan's call -- the companion is linked in from the box's own copy per run, which is the matrix's prep.
  evidence: review 2026-09-21 (verification gap); tests/tree/drag.mjs is in no suite either (tools/box-drag-beside.mjs runs it on /var/www/ms by hand).
- source_spec: `spec-4-2-the-compatibility-matrix-on-4-0-0.md`
  summary: the multisite-subdomain shape has no result on 4.0.1 -- its cell runs on /var/www/ms2's sub-site two., and ms2 has held the shop library since 2026-09-16 (the session's stop point). Either free ms2 or provision a third network (tools/box-ms2-provision.sh is the recipe) and point the cell at it. Nathan's call.
  evidence: docs/compatibility.md row "not run on this release"; last ✓ all 9 steps on 2026-09-11.
- source_spec: `spec-4-2-the-compatibility-matrix-on-4-0-0.md`
  summary: with `--parallel 3`, two of the three WordPress 6.5 cells started together never booted -- Playground's own /internal/eval.php threw "The 'wp-config.php' file is not a valid PHP file" on the --define step, 502 for ten minutes; the third 6.5 cell and all three 7.0 cells started together booted fine, and 7.1 was already cached from the mutation run. A race in Playground's shared build cache on a version not yet fetched is the likely cause; a stagger between pool starts, or one warm-up boot per WordPress version before the pool, would settle it. Reruns of the two cells alone: see the handoff.
  evidence: matrix-run log 2026-09-20 lines 83-111; ports 8930 and 8931 (the first two started) failed, 8932 passed.
- source_spec: `spec-4-2-the-compatibility-matrix-on-4-0-0.md` (code review)
  summary: tools/matrix.mjs box cells -- Ctrl-C or an OOM during a box cell skips the finally, leaving mock on, the vgmlmatrix administrator and the FileBird link on /var/www/ms; a SIGINT handler that runs the same cleanup line would close it (not demonstrated, so not patched). Also: a warm-up boot per WordPress version before the pool (the 6.5 boot race); the runner reading the companion's version into the row instead of the "FileBird 6.5.8" label; a `ready`/`demo` field in tests/compat/matrix-probe.php so a Playground row can say the mock constant reached the worker that served it; a flag to run the shape=multisite-subdomain cell once ms2 is free.
  evidence: review 2026-09-20 (edge-case hunter, verification gap, blind hunter); the cleanup line is `runBox()`'s finally.
- source_spec: `plans/finish-the-suite.md` wave 4 (S29b, 2026-09-21)
  summary: tools/buyer-walk.mjs waits for the account page in its own browser window, but the confirmation mail opens in the buyer's default browser, so the registration and the download happen out of the script's sight and `buy` never reaches the zip capture; `final` then fails with "the walk session is gone". Poll the service instead (the account's licence via the API, or the download route's log) or print the link for the buyer to paste. Also: a second `buy` on the same day overwrites the first run's 01/02 shots (the stamp is the date).
  evidence: S29b handoff -- the first run's log stops at WAITING while the account page was open in Nathan's own browser; `final` 12:24:35 FAIL.
- source_spec: `plans/finish-the-suite.md` wave 4 (S29b, 2026-09-21)
  summary: tools/plugin-check.mjs, matrix.mjs, play.mjs, uninstall-walk.mjs and verify.mjs call `npx @wp-playground/cli` unpinned; 3.1.55 resolves but cannot install (`@php-wasm/node-8-1@3.1.55` unpublished, 2026-09-21). Pin `@3.1.54` in one place (a constant in tools/lib) until the package is fixed.
  evidence: S29 handoff, found 1; Plugin Check for 4.0.2 ran on the cached 3.1.54.
  resolved: 2026-09-21 (S30), `tools/lib/playground.mjs` pins 3.1.54 for the six call sites; `VGML_PLAYGROUND_CLI` overrides.
- source_spec: `plans/finish-the-suite.md` wave 4 (S29b, 2026-09-21)
  summary: box-issued licence rows are never removed -- nine `box@vergelabs.nl` rows (ids 5, 12-17, 20, 22) sit in the production licences table; health counts them ("14 licences"). Either a `box` flag the counts and the accountant's views exclude, or a retire script. Nathan's call.
  evidence: read-only query 2026-09-21 12:23 UTC; S26 wrote "deleted after" of the env file, not the row.
- source_spec: `spec-2-1-the-buyer-walk-on-4-0-0.md` (code review, 2026-09-21)
  summary: tools/buyer-walk.mjs `final` saves our invoice PDF and prints its status, type and size, never its number and total (T6 says "read for its number and total"; AC3 "the same number"). The record's "our PDF Total EUR 0.50" was read by eye. Reading it needs a PDF text extractor the plugin repo does not carry (the streams are Flate-compressed); the service's own invoice test covers the total. Add pdf-parse to the plugin's dev dependencies or render the PDF in the walk window and shoot it -- Nathan's call.
  evidence: review 2026-09-21 (acceptance auditor); buyer-walk.mjs final, the `/api/invoice` block.
- source_spec: `spec-4-0-3-retro-fixes.md` (build, 2026-09-21)
  summary: a held picture (a transient 5xx/429 answer) is reported twice in vergeml_ai_index_step()'s `errors` -- once by the general report before the transient branch (core/ai.php:1541), once inside it (:1584) -- so the AI screen's "N failed" and the background run's error count over-read while the service answers 5xx. One `$errors[]` line to drop; E3-1 touches the same branch.
  evidence: tests/ai/outage.php step 2 prints "reported 2 time(s)" (asserted "at least once, never fatal" until E3-1).
  resolved: 2026-09-22 (E3-1, 4.0.4) -- the transient branch no longer reports again.
- source_spec: `spec-4-0-3-retro-fixes.md` (code review, 2026-09-21)
  summary: the outage suite pins the sequential describe path only (vergeml_ai_parallel forced to 1, pre_http_request answers). A customer site runs the parallel path (Requests::request_multiple, core/ai.php:843-896), which the filter cannot reach; an unreachable service is `vergeml_ai_transport` there (:882, :891), classified by the same prefix test at :1570 by reading, not by a suite. E3-1 changes exactly these two branches: it needs a seam (a filter around request_multiple's answers) and a parallel step in outage.php, or the readme's "cannot be reached" sentence stays backed for the path no customer runs.
  evidence: verification-gap review 2026-09-21; repo search for `vergeml_ai_transport` finds no test.
  resolved: 2026-09-22 (E3-1) -- the `vergeml_ai_describe_answers` seam; outage.php step 2 drives `vergeml_ai_transport`.
- source_spec: `spec-4-0-3-retro-fixes.md` (code review, 2026-09-21)
  summary: a stub written during a `stale` (Re-describe) run is merged onto the picture's existing described row (vergeml_index_set updates when a row exists, core/ai-index.php:390-397), and every reader filters `WHERE error = ''` (search by meaning, filing, the counts): a Re-describe pressed during an outage removes described pictures from search until they are re-described -- and a marked picture that already has alt text is reached by no button (`unindexed` needs no row, `missing-alt` needs empty alt, `stale` needs error = ''). The FAQ's "everything already described keeps working" is false in that case. E3-1 material: a stub must not overwrite a good description (write the error beside it, or hold instead), and a marked picture needs a way back that does not depend on empty alt.
  evidence: blind-hunter + edge-case review 2026-09-21, confirmed at core/ai-index.php:390 and the `error = ''` predicates in ai.php:936, :1727, :756 (stale).
  resolved: 2026-09-22 (E3-1, 4.0.4) -- a refusal for a described picture keeps the description and stamps the row current; a marked picture with alt text arises only from a refusal about the file.
- source_spec: `spec-4-0-3-retro-fixes.md` (code review, 2026-09-21)
  summary: three clauses of the outage paragraph are carried over from S30 without a suite line: "Searching by meaning falls back to the ordinary word search" (core/ai.php vergeml_ai_search_on on a WP_Error), "your credit balance shows the last number it read" (vergeml_ai_credits_state), "no credits are taken for a picture that was not described" (service lib/batch.ts refundFor / the ledger). Each needs its line or a probe, or the paragraph says which are promises (E3-9).
  evidence: blind-hunter review 2026-09-21; the spec's clause list covers the describe claims only.
- source_spec: `spec-4-0-3-retro-fixes.md` (code review, 2026-09-21)
  summary: "tries it again ten minutes later" is the background run's behaviour (core/ai-background.php:400 recounts with vergeml_ai_pending_count, which ignores the hold, so the run stays active and rebooks); a foreground "Watch it here" run ends when only held pictures are left (vergeml_ai_index_step returns remaining 0 at core/ai.php:1434-1442, js/vergeml-ai.js:245 finishes) and nothing retries until the next press. No suite runs a tick with a held picture. Pin it in outage.php (run_start, tick, assert active and rebooked) once the words settle (decision D2).
  evidence: verification-gap + edge-case review 2026-09-21, confirmed at the cited lines.
  resolved: 2026-09-22 (E3-1) -- outage.php steps 11 and 12 pin the waiting tick and the ordinary tail; the paragraph says which run comes back and which waits for a press.
