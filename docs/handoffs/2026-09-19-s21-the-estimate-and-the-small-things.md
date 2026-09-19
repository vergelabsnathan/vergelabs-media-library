# S21 — the estimate tells the truth, three of the four small things, and a check that was lying

**Date:** 2026-09-19. **Model:** Opus 5. **Card:** S21 of every-picture-a-home
(`.harness/active.json` as S20 left it). **From:**
`docs/handoffs/2026-09-19-s20-the-shops-way-in.md`. **Plan:** none — S20's
`plans/shop-way-in.md` was done; every task here was a task of its own, test
first, one mutation each. Plugin `542bcf8` → `SHIP`, six commits.

## The score lines

Rules-only, after every task, unchanged all session (no rule moved):

- **shop: SCORE right-and-placed 347 of 581 (60 %) · right of placed 72 %**
- **tech: SCORE right-and-placed 153 of 200 (77 %) · right of placed 76 %**

## Nathan's calls

- **Teach the preview the product placements** — yes, the plain fix, no new
  copy and no line on the screen naming them.
- **All four of S19's small things**, knowing two of them needed files the
  card had not listed. Three are built; the fourth is below.
- **The rounds line** reads *round 2: N more*.
- **Show me**: do not offer it when the card has already shown everything.
- **A shot sheet of every Folders screen** for his own pass.

## After the handoff was written — two more, on Nathan's "move on"

7. **The fill's seconds, timed (`tools/box-fill-phases.php`, new).** On the
   shop, warm, 32 of 33 already claimed by a product: profiles 0.02 s, the
   slice read 0.01 s, the products' folders 0.01 s, **the text model 3.22 s**,
   the picks 0.03 s — **3.29 s in all, 98 % of it the one service call**. At
   the press nothing is claimed yet, so all 33 go in the chunk and that is the
   fifteen seconds. The beat is set only after a chunk returns, so nothing can
   move during it. Moves nothing; the model call is metered, not debited.
8. **`folders.spec:1546` was the box, not the code.** `u1.prev` is the site's
   whole count of folders carrying an earlier profile
   (`vergeml_guide_prev_count`), and the test asserted an absolute **0**. The
   box answered **15** — the exact fifteen the fill-walk dropped before S20's
   put-back (`bb8f6c1`): S20 fixed the tool and never cleaned what earlier runs
   had left. They also meant the box's own Folders screen had been offering
   *Restore the earlier classes* for a restore point that only existed because
   of the bug. `tools/box-prev-profiles.php` (new) lists them and took them off
   (15 removed, 0 left); the test passed alone in **2.4 m**. The assertion is
   now the **delta** — the second confirm keeps exactly one more than the site
   already had — which also checks the count *after* the confirm, which the
   original never did. Green again alone in **2.6 m**, and the box carries none.

## Then the release itself (after the card below was first written)

9. **The change inventory (`990a8ff`).** 98 user-visible changes distilled from
   the 353 commits since 3.16.1, grouped, with the numbers from the commit
   bodies kept: `docs/release-inventory-2026-09-19.md`.
10. **Every outbound request audited (`741b511`).** Twenty call sites in
    shipped code — eighteen external, two loopback, and one of the eighteen
    made from the admin's browser. `docs/outbound-audit-2026-09-19.md`. The
    readme's External services section described **two** of the eighteen and
    contradicted the code in five places. Three findings verified by hand: the
    support ticket carries the plaintext licence key, an email address and
    **every active plugin with versions**, and needs **no licence**; the
    browser-side `window.fetch` exposes the admin's IP; the known-issues feed
    is cached twelve hours and one hour after a failure, against a claimed
    "at most once a day".
11. **External services rewritten and moved (`94641a8`, `badb481`).** It now
    names all three destinations and everything that leaves. Moved from a
    `###` subsection of the Description to its own `##` section, because the
    Description is truncated by the readme parser and a disclosure nobody can
    read is not a disclosure.
12. **4.0.0 (`a558059`, `8ef4e7a`, `badb481`).** The version in all three
    places, a changelog of 34 bullets across seven sections including "Changed,
    and worth knowing before you update", and an Upgrade Notice the readme
    never had (263 of 300 characters). Major rather than minor: filing
    decisions move, the Rules tab is gone, the list toolbar is rebuilt, EML's
    media scripts are set aside.
13. **Plugin Check on the 4.0.0 archive (`0931858`): no errors**, two warnings
    — the known `mismatched_plugin_name`, and the Description's length
    (11,468 characters against the parser's 2,500; it was 13,856 before this
    release, so moving External services out shortened it rather than caused
    it). Neither blocks.

**Two corrections made to this session's own copy before it shipped:** "the one
thing that works without a licence key" (Connect and the feed do too), and
"32 of 33 pictures, sure, with no AI asked" — the text model is not asked, but
`vergeml_filing_profile_build` returns null without a vector from the service
(`core/filing.php:384`), so nothing is filed at all without a licence. The
product rule removes the guessing, not the licence.

## Built, story by story

1. **The Tree step's estimate counts what the fill files by product
   (`89ba1f1`).** The dry count ran the matcher the fill runs, over the same
   pictures against the same profiles, and skipped the one step the fill takes
   first: `vergeml_filing_product_folders()`, the folder a product's categories
   name (S10.8). Measured on the real shop before a line was changed
   (`tools/box-fit-products.php`, read-only): at the press the estimate said
   **23 would be placed · 10 would stay unfiled** where the fill placed **32 by
   product**. The owner was shown a worse number than what happens, on every
   shop. The draft's profiles are keyed by the run's own numbers and carry
   their paths, so `vergeml_filing_product_folder()` resolves a category to a
   draft folder that does not exist yet exactly as it resolves one to a real
   folder. A site that sells nothing pays nothing: the product column is a
   literal `NULL` and the step returns the rows untouched. On the shop the
   whole dry count went **60 → 61 queries**. `tests/tree/guide.php` section J,
   red first at **54/57** (J2, J3, J4), green at **57/57**.
2. **The rounds line says round 2's own number (`90bfc50`).** `rounds[2]` is
   the total placed by the end of round 2, and the line printed it beside
   round 1's — so the shop's *round 1: 32 placed · round 2: 32* read as 32
   more where round 2 had placed none. Now *round 1: 553 placed · round 2: 87
   more · 113 to sort*, and *0 more* when it placed nothing. `folders.spec`
   red first: Expected `round 2: 87 more`, Received `round 2: 640`.
3. **The AI screen's header catches up after a run (`90bfc50`).** The line
   under the title was painted with the page and nothing repainted it: a run
   described three pictures and it still read *997 described · last run 17
   September* until somebody reloaded. `/ai-status` renders it again when
   asked (`line=1`); the screen asks when a run ends and never on the poll,
   because the counts behind it are the page's own dozen queries and a run
   polls every couple of seconds. An absent line leaves the screen alone
   rather than blanking it. `tests/tree/ai.mjs` **10/11 → 11/11**: *997
   described · last run 17 September* → *1,000 described · 1,000 with alt text
   · last run 19 September 07:52*, no reload. The check waits for the line,
   because `finish()` writes the note before it asks for the line. Neither
   suite's three pictures (106215, 106409, 106603) is in the tech truth set —
   checked before the run — so the mock descriptions cannot move a SCORE.
4. **Show me, only when there is something it has not shown (`2be49fa`).** The
   card shows eight of a group's pictures and the answer hands back the
   group's, so on a group of eight or fewer it showed exactly what was on the
   screen. One pass at the end of `vergeml_filing_questions()`; a card that
   does not offer it also refuses it. `tests/filing/residue.php` red first at
   **31/35**, and the boundary mutated from `<=` to `<` takes 4f alone
   (**36/37**). Green at **105/105 + 37/37** — three checks added: 4f states
   the boundary, 16b and 23b keep the two regressions the old checks carried
   (a sibling look hands back pictures not children, an either/or look hands
   back pictures not folders) on cards of nine.
5. **Plugin Check, run again (`2c4fbcb`).** All five categories in Playground
   against a clean `git archive` of `89ba1f1`: **0 errors, 1 warning**
   (`mismatched_plugin_name` on readme.txt line 0) — the same as the 3.16.1
   release archive on 2026-09-12.
6. **`deploy.mjs --check` was lying (`d57ee3b`).** `verifyBox()` re-hashed the
   box's files against the `.deploy-manifest` the *last deploy* shipped with
   them. That proves the box has not drifted on its own and nothing more: with
   three changed files in the working tree the box had never seen, `--check`
   answered **"box up to date (137 files verified)"** — the one reassurance
   that file exists to refuse to give. The box's manifest is now compared to
   the one the working tree makes, before the per-file check, and a mismatch
   names both digests. Proven both ways: `STALE -- the box holds another build
   (its manifest is b5c0682664a5, this tree's is 14ee81a4f485)` where the same
   command had said "up to date" a minute earlier, and "up to date" again
   after a real deploy. **Found by going to confirm the box still had the old
   JS for a red test run.**

## The walk (the real shop, from zero, again)

`tools/box-shop-untree.php` (new: freeze, clear, restore, all by literal SQL)
froze the shop's tree to `/var/www/ms2/wp-content/vgml-realshop-tree-s21.json`,
and the restore was **proven before it was relied on** — cleared, restored, 9
folders and 32 pictures back — then cleared again for the walk. A session admin
`vgmls21` of this session's own, deleted after on both networks.

Folders opens on Tree · 33 pictures · 32 on products · 0 folders → one press on
*Use my 9 product categories* → **9 folders · 32 would be placed · 1 would stay
unfiled** (S20's shot read 23 / 10) → *This is my tree* → Fill → **32 by
product · 0 by evidence · 1 question · 1 to sort**. Nine shots,
`docs/superpowers/mocks/shots/2026-09-19-shop-way-in-*.png`. The shop is back
in the state it started in. Spent: nothing — the 33 are described and cached.

## The shot sheet, for Nathan's own pass

`tools/box-folders-sheet.mjs` (new) → **`docs/superpowers/mocks/2026-09-19-folders-sheet.html`**,
ten shots at 1440×900: every rail step on the tech library (1,000 pictures,
rail *describe · tree · fill · alt · rename*, lands on fill) and on the real
shop (33 pictures, rail turned round — *tree · fill · describe · alt · rename*,
lands on fill). It presses rail steps and nothing else: no confirm, no fill, no
answer. `vgmls21` deleted on both networks after.

## Found, not done

- **The fourth small thing: *Filling 0 of 33 · 15 s* stands still.** The
  heartbeat S19 wanted already exists — `core/folder-talk.php:1164` beats every
  two seconds **inside the row loop** (S10.0, HEMA's 626 in 27 s). It cannot
  fire before the loop starts, and on a 33-picture library the loop is the fast
  part: the fifteen seconds are the setup ahead of it — seeding the nine folder
  profiles, resolving the product folders, and the one service call for the
  single picture no product claimed (`vergeml_filing_ask_model` beats only
  *after* each chunk returns). So `seen` is honestly 0; what is wrong is that
  the row shows a count where it should say what it is doing. **Needs two
  things S21 did not have: the three phases timed on the shop (a real fill),
  and Nathan's words for what the row says while the count is still zero.**
  `core/folder-talk.php` was outside the card's scope.
- **The fourth small thing is not what S19 called it.** *Filling 0 of 33 · 15 s*
  does **not** stand still: `renderProgress` puts the bar in its indeterminate
  state while `done` is 0 (`js/vergeml-folders.js:319`) and pushes the moving
  seconds (`:351-353`, whose comment names Nathan's own earlier "0 of 1 batches
  for fifty seconds"). The bar animates and the seconds climb. Only the **count**
  sits at 0, and the count is true — nothing is filed until the model answers.
  So this is a wording choice, not a defect: whether the row should name what it
  is doing while the count is honestly zero. Nathan's, and it needs no further
  measurement.
- **A new shop, and a growing one: three cases, one of them unbuilt.** Asked
  after the sheet (Nathan, 2026-09-19). The product rule runs in exactly two
  places — the fill (`core/folder-talk.php:1127`) and the dry count
  (`core/guide.php:1922`, added today). It is **not** in `core/auto-file.php`.
  1. **A shop with categories and products but no product pictures.** Correct
     as it stands: the rail only turns round when a picture is actually on a
     product, and `guide` H6 already holds the line (a Woo site with no product
     picture pays no query). Nothing to do.
  2. **A shop still filing everything under Woo's default category.** One press
     would make a single folder called *Uncategorized* — truthful and useless.
     Woo's default is skipped while empty and kept once products are in it
     (`vergeml_folders_product_paths`, `guide` H3). A one-line guard could
     withhold the button when the default is the *only* category with anything
     in it. Small, and Nathan's shape call.
  3. **A shop that keeps growing — the real gap.** A picture uploaded to a new
     product tomorrow is not placed by its product on upload. It waits for the
     next Fill; if auto-file reaches it first it is placed by *evidence*, the
     weaker answer the product rule exists to avoid. Not a regression — the
     Fill button is the front door and it does place them — but the fact is
     sitting in the database unused until somebody presses it. Building it
     means a new automatic write path on a shop's real library, against
     auto-file's own doctrine that filing is earned per folder (a product
     placement is a fact, not a suggestion, so the doctrine arguably does not
     apply to it). **Deliberately deferred past the submission**: it wants a
     walk and its own session, and nothing about it is broken today.
- **The archive is not ready to send as it stands.** Plugin Check passes, but
  `Version:` and `Stable tag:` are still 3.16.1 and the changelog's newest
  entry is 3.16.1, while S12–S21 added the Folders screen, filing by evidence,
  the shop's way in and the fill's two rounds. Sending it would ship all of
  that under a version that names none of it. The bump and the changelog entry
  are copy, and Nathan's. Noted in `docs/wordpress-org-submission.md`.

## Gates

- `filing` **105/105 + 37/37** · `guide` **57/57** · `ai` **11/11** · `sticky`
  61/61 · `surface` 27/27 · `roles` · `escaping` 9/10 (the known ratio row,
  243 text against 65 HTML; `docs/security-escaping.md` regenerated) · `copy`
  58/58 · `tree-view` 65/65 · `talk-chips` 6/6 · `folders-shop` 11/11 ·
  `seed-shop` 8/8 · `ai-background` 37/37 · `filing-trail` 125/125.
- `folders.spec` + `modes.spec` on the tech site as `vgmls21`: **27 passed, 1
  failed, 3 skipped (29.9 m)** — the one failure is `folders.spec:1546`, the
  pre-existing one below. S20's own run of the same two was 27 passed, 1
  failed, 3 skipped (30.4 m): the same line, the same count. The red run before
  the rounds fix was 19 passed, 2 failed, 1 skipped (24.9 m, folders.spec
  alone), the second failure being the rounds line itself.
- Both SCORE lines above, after every task. Bands not re-taken (no rule moved).
- Plugin Check on a clean archive of `89ba1f1`: 0 errors, 1 known warning.

## Open, and Nathan's

- **The three readme decisions**, on the proof page generated from `readme.txt`
  itself: does External services sound like him; is 4.0.0 the number (three
  strings if not); and the Description at 11,468 characters against the
  parser's 2,500 — trim it or accept the cut.
- **The plaintext licence key in the support ticket.** Raised 2026-09-19 and
  not decided. The key is sealed at rest against the site's auth salt so a
  database leak cannot hand out working licences, and then `vergeml_help_send()`
  posts it in the clear. Attaching a system report is ordinary; attaching the
  key is an inconsistency in the plugin's own threat model. The site token
  already sent, or the key's last four characters, would identify the customer
  as well.
- **The growing-shop case, the Uncategorized guard, and the shot-sheet pass** —
  all three deliberately after the submission.

## Next — S22

The card in `plugin/.harness/active.json` has been updated to the state below;
the JSON block that used to sit here described work that is now done and has
been removed rather than left to mislead. In short: nothing mechanical is left
before the form. What follows it is the licence key in the support ticket, the
growing shop, the Uncategorized guard, and the sheet pass.

Opener, cwd `plugin`:

```
Read docs/handoffs/2026-09-19-s21-the-estimate-and-the-small-things.md. State
which model you are and follow that profile in
~/.claude/harness/model-profiles.md. This session is S22 of
every-picture-a-home; the card is already in .harness/active.json — read it
before anything else. Both rules-only SCORE lines first (shop 347 of 581,
tech 153 of 200). 4.0.0 is cut and passes Plugin Check; if I have edited
readme.txt, re-cut the archive and re-check it before anything else. Then the
licence key in the support ticket, then the growing shop (a product's picture
placed on upload — plan it before building it, it is a new automatic write
path). Test first, one mutation per story, every cost said before it is spent.
Talk plainly. End with a handoff carrying the S23 card.
```

The old S22 card, for the record, is in this file's history at `1c0c167`.

```json
{
  "phase": "Every picture a home — S22: the last small thing, and the release. The estimate, the rounds line, the AI header and Show me are done and the archive passes Plugin Check unchanged. What is left: Filling 0 of 33 stands still on a one-slice run — the three setup phases timed on the shop first, then Nathan's words for what the row says while the count is zero; the user's pass over the Folders screens from the shot sheet; folders.spec:1546, red since before S20; and the version bump and changelog for everything since 3.16.1, which is Nathan's copy and the last thing before the wordpress.org form.",
  "model": "opus",
  "plan": "a plan file only if the Filling row turns out to need more than a beat before the loop; the six fields per task",
  "spec": "docs/superpowers/specs/2026-09-16-folders-at-catalogue-scale.md (S10.8, the order of the steps by library)",
  "scope": [
    "core/folder-talk.php (the beat before the row loop), js/vergeml-folders.js renderMove",
    "tests/ui/folders.spec.mjs (1546, and any screen change), tests/tree/**",
    "docs/superpowers/mocks/** (a mock before any visible change)",
    "readme.txt, vergelabs-media-library.php (the version), docs/**, the archive",
    "docs/handoffs/**",
    "plans/**"
  ],
  "readFirst": [
    "docs/handoffs/2026-09-19-s21-the-estimate-and-the-small-things.md (this: the estimate, the three small things, the check that was lying, the fourth measured but not built)",
    "core/folder-talk.php around 1120-1170 (the slice's setup, then the row loop and its two-second beat)",
    "tools/box-shop-untree.php (freeze, clear, restore -- the shop's tree, by literal SQL) and tools/box-folders-sheet.mjs (the shot sheet)",
    "memory: progress-always-visible, ui-less-text-pills, do-the-cli-work, model-spend-discipline, verge-media-library-fork (the archive is stale at 3.16.1)"
  ],
  "handoffDir": "docs/handoffs",
  "stopPoints": [
    "Every user-facing string is Nathan's, verbatim; a mock before any visible change",
    "The price of the model call is Nathan's; until priced it is metered, never debited",
    "The model never overrules a product placement, a hand placement, or files into a view or a locked folder",
    "A band is re-taken only rules-only and with the reason; the model's lines are never a band",
    "Say every cost before it is spent: a describe is a credit a picture; the shop's 33 are described and cached",
    "The version bump and the changelog are Nathan's copy, and the wordpress.org form is his to send",
    "Test first, one mutation per story; a mutation of anything that can start a run holds the cron wire",
    "Never git checkout a file carrying uncommitted work; never chain a checkout into a command",
    "box-eval --site shop is the C.5 main site; --site realshop is the WooCommerce shop"
  ],
  "gates": [
    "node tools/box-eval.mjs tools/box-truth-score.php --site shop and the tech line with --copy tests/tree/truth-tech.json:/tmp/vgml-truth.json --env VGML_TRUTH=/tmp/vgml-truth.json (MSYS_NO_PATHCONV=1): both rules-only lines in every check-in (shop 347 of 581 (60 %) · 72 %, tech 153 of 200 (77 %) · 76 %) — neither falls",
    "node tools/verify.mjs filing sticky guide ai surface roles escaping copy tree-view talk-chips folders-shop seed-shop ai-background filing-trail → green (escaping 9/10 known); service pnpm test green",
    "node tools/deploy.mjs --check before trusting any box result: it names both digests now, and a STALE box means the suite is testing yesterday",
    "folders.spec and modes.spec on the tech site after any screen change",
    "the shop re-walked after any change to the fill: tools/box-shop-untree.php freeze/clear, tools/box-shop-walk.mjs, one press, 32 by product, every screen shot"
  ]
}
```

Opener, cwd `plugin`:

```
Read docs/handoffs/2026-09-19-s21-the-estimate-and-the-small-things.md. State
which model you are and follow that profile in
~/.claude/harness/model-profiles.md. This session is S22 of
every-picture-a-home; the card is already in .harness/active.json — read it
before anything else. Both rules-only SCORE lines first (shop 347 of 581,
tech 153 of 200); then time the fill's three setup phases on the shop and
bring me the numbers before building anything for the Filling row; then
folders.spec:1546, red since before S20; then the version and the changelog,
which are mine to write. Test first, one mutation per story, every cost said
before it is spent. Talk plainly. End with a handoff carrying the S23 card.
```
