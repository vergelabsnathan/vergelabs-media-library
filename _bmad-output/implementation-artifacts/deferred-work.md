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
  summary: a percent code that takes a yearly plan under Stripe's minimum charge (PRIVATE0, 99 % of EUR 39 = EUR 0.39) yields a free year -- Stripe marks the sub-minimum invoice paid with amount_paid 0, activates the subscription, and the webhook issues the licence on invoice.paid. Guard in the intent route (refuse a subscription whose first invoice would be under the floor, or floor the coupon), and decide what to do with PRIVATE0 (Nathan's: switch off or restrict to credits).
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
