# Change inventory since 3.16.1 — raw material for the changelog

Distilled 2026-09-19 from the 353 commits between `f79dac9` (where 3.16.1
landed) and `52eb23a`. Roughly 255 were internal-only — test suites, fixtures,
`tools/`, `docs/`, handoffs, specs, mocks, deploy and CI — and are not listed.
About 98 survive.

**This is an inventory, not copy.** The changelog's words are Nathan's. The
numbers below come from the commit bodies and are the part worth keeping.

**The version has not moved.** The plugin header and `readme.txt` still say
3.16.1.

---

## Folders screen — a tree you build, confirm and fill

The Folders screen became a step-by-step workflow (Tree · Fill · Describe ·
Alt text · Rename) instead of a conversation that had to happen first.

- A step rail, no step a gate. The page opens on the session's step and fires
  no model route on load — *Propose folders* is a button with its credit cost
  on it. Every step is reachable by clicking; alt text is optional.
- **Paste a tree instead of talking.** One folder per line, full path with `>`
  between levels. Leading whitespace is never depth; an existing folder at that
  place is reused case-insensitively. Refusals named by line: no name, deeper
  than five levels, more than five hundred. The Rules tab and its screen were
  removed.
- **Build the tree by clicking.** `+` on a row opens an empty child in edit
  mode; `+ New folder` makes a top-level one; the name may itself be a path.
  `×` removes a row.
- **Confirm locks the tree.** The draft's words are stored on the folders and
  the planner is asked only about folders the draft has nothing for. Unconfirm
  offers *Restore the earlier classes* when a confirm replaced a planned
  profile within the day.
- **Words on folders, editable.** Classes show as quiet pills after the name;
  `×` takes one out and the count re-runs with no model call; `#word` adds one.
  `×` on the last word means "no words" explicitly — the confirm writes a
  name-only profile instead of silently restoring the stored word (on the shop
  a restored draft had read as 270 profiles lost).
- **Tree reading at catalogue scale.** Parents closed by default, counts on
  closed parents at any depth, a find box from ten folders, open/close all from
  ten, and hovering a closed parent previews its children as chips without
  opening it.

## Filing pictures by evidence rather than by name

One filing path with three honest outcomes — fits / siblings / nothing —
replacing the old cosine-to-folder-phrase guess, then about twenty rule changes
each measured against a marked answer key.

- **One matcher, three outcomes.** `sure` from 0.70, `likely` below; never a
  locked folder, never a picture placed by hand. Two children too close to call
  put the picture in the *parent* rather than nowhere. The preview and the run
  count with the same function — on the box the two tallies were identical
  (488 + 25 + 487). The planner is no longer called during a fill.
- **Telling folders apart.** A class held by k folders is worth 1/k (counted by
  lines, so a folder and its own ancestor holding one word are one holder); the
  describer's second phrase is worth 0.85; a folder's own name scores 1.0 at
  any rank; a cross-parent tie becomes an either/or question.
- **Accuracy on hand-marked samples of 60 placements:** sure 47 % → 73 % →
  **87 %**, likely 40 % → 23 % → 57 %. The engine baseline moved sure 264 → 340
  when the planner started speaking the describer's vocabulary.
- **Accuracy on seed-labelled truth sets** (626-picture shop, 200-picture tech
  library): rules-only ends at shop **347 of 581 (60 %)** with 72 % of placed
  pictures right, tech **153 of 200 (77 %)** with 76 % right.
- **The fill learns from its own placements.** A folder holding three or more
  described pictures is read over them; a member word belongs to the folder
  holding most of the pictures that say it; a locked folder owns none. The fill
  then runs a **second round** over what round 1 left.
- **The picture's own words as evidence** — filename, title, alt — worth a
  second-phrase hit, but only where the describer's phrases already hit that
  folder (a filename word alone had put an office desk in Phones, sure).
- **A "view" of the tree owns nothing.** A folder more than half of whose
  children repeat names held higher up (a *sale* branch) keeps no class and is
  never filed into — it had taken **201 of 626** pictures on round 1.
- **Trees in another language.** Leaves no picture's words match are sent to the
  planner for a class in the describer's words. On a Dutch shop: 290 leaves,
  five batches, 27 credits, 64 named; round 1 sure 159 → 195.
- **Hand placements are sticky**, and a picture in a locked folder is never
  filed out of it.

## WooCommerce shops

- **"Use my N product categories."** While the tree is empty on a site that
  sells, one press turns the product categories into folders (root to leaf,
  parent before child, capped at five hundred, Woo's empty default left out).
  On the real shop: one press draws nine folders where the same outcome
  previously took four typed conversation turns.
- **Pictures filed by the product they belong to.** Featured image, gallery and
  `post_parent` resolve to a product, and the product's deepest category that
  names a folder places the picture — sure, before any matching, never
  overruled. On the real shop: **32 placed by product · 0 by evidence · 1
  question · 1 to sort**.
- **The Tree step's estimate counts product placements.** It read *23 would be
  placed · 10 would stay unfiled* where the fill placed **32** — every shop
  owner was shown a worse number than what happened. One extra query.
- **The Fill step reads differently on a shop:** the rail turns round to Tree ·
  Fill · Describe, *on products* appears beside the title, and the pills read
  *by product · by evidence · to sort*.
- **The planner starts from the shop's own names** instead of guessing
  *Headwear / Hoodies / Other*.
- A site with WooCommerce and no product pictures pays no extra query.

## A text model as a second opinion on filing

New behaviour, live for licensed sites.

- Asked **once per picture per tree**, in batches of forty, cached a week.
  Pictures placed by hand, by answer, by product, or in a locked folder are
  never sent. No licence, or a failed call, and the fill runs rules-only.
- **Three tiers.** Both agree → sure. They disagree → an either/or question
  carrying both folders. Model silent → *in doubt*, left where it was. Rules
  silent, model names a folder → likely.
- **What it buys:** shop 354 of 581 placed right (61 %) with **89 %** of placed
  pictures right, against 60 % / 72 % rules-only; tech 139 of 200 (70 %) with
  **84 %** right, against 77 % / 76 %. Fewer pictures placed, far more right.
- **Copy carries no "AI"** — *both matches agree*, *matched X, but in doubt*.
- **The Tree step's dry count never asks the model**; it is rules-only and
  labelled an estimate.
- On a warm one-slice fill, **3.22 s of 3.29 s is the one service call**.
  Metered, not debited.

## Questions and the residue

- The residue is grouped and labelled honestly: kind first, the class named by
  majority with its share, *mixed, mostly X* under 70 %, eight cards then one
  card for the small groups. *Put in X* only on a 60 % majority.
- **An answer is a decision.** Split and keep-in-the-parent now mark the
  pictures, so the next fill keeps them instead of asking again — every fill on
  the shop had been re-asking.
- Either/ors about a single picture fold into one card; two folders sharing a
  leaf name are named by their full paths.
- **Show me is offered only when there is something it has not already shown.**
- Keyboard: 1–4 answer the focused card, Enter opens its strip; the response
  carries one answer instead of thirty questions and 240 thumbnails.

## Why is this picture here — the placement trail

- **Every move records its reason** — the folder, the matcher's word, the
  score, the runner-up, the prompt and model behind it. Abstentions are
  recorded too: the only record anywhere of a picture the evidence would not
  place.
- **"Why is it here" on the attachment's edit screen and in the grid's modal**,
  read back from one placement, nothing re-run. Deliberately kept out of the
  bulk listing, which would cost ~450 queries on an 80-item grid page.
- **Who approved a batch** is recorded, and an undo marks the rows it reverses.
- **No fabricated numbers.** When the dry run cannot answer within its budget
  the screen says so rather than drawing zeros.

## Alt text and describing

- The Alt text step writes the catalogue's alt and **never overwrites** an
  existing one.
- The word on a picture (sure / likely / by you) shows in the modal and on the
  list row.
- **A prompt change no longer re-describes the library by itself.** The stale
  count waits on the dashboard's button, which shows the credit count before
  the press.
- **The AI screen's summary line repaints after a run** — it had read *997
  described · last run 17 September* until somebody reloaded.
- A describer-written alt does not count as the picture's own evidence; only an
  alt a person wrote. The describer's alt had doubled the ties on the tech
  library (margin 62 → 162).

## The media library list and the folders panel

- **The folders panel is on the list view too**, not only the grid — reported
  three times. Median row height with the panel present: **81 px**, against the
  1,960 px that caused the original decision.
- **The list toolbar is one row**: the showing folder as a chip with its count,
  core's search, a Filter chip whose card holds core's selects, the view switch
  and the pages. Core's own two rows are emptied — the controls are moved,
  never rebuilt. First row at 1280×800: **429 px → 201 px**.
- **The table is a table again.** A `display:block` had turned off core's fixed
  layout: the File column sat at 759 px at any window and rows stood 211 px.
- A folder click swaps in fresh nav elements, so bulk actions and page numbers
  follow the click (the pages had read 627 against a five-picture folder).
- **New filter: Placed by hand.**

## Speed and scale

- **The media library opens at a quarter of a million pictures.** The five
  smart-folder counts cost **10,038 ms** of query time at 251,000 attachments
  and ran on every admin page load. Now **8.6 ms and four queries cold, nothing
  warm**. Above 50,000 attachments the four expensive counts are not computed
  at all and say *not looked*.
- **Phrase vectors held for the request and cached a week:** a dry run went
  **44.6 s → 9.4 s** cold, 7.0 s warm. The pick then memoised each phrase:
  626 pictures against 319 folders, **27 s → 15 s**.
- **Move answers immediately.** The apply used to file for 20–40 s before
  answering, during which the browser gave up (nginx 499) and the screen said
  nothing had moved while everything moved. Now **3.9 s**.
- **The confirm asks about far fewer folders:** shop 322 → **37** asked, one
  batch, **0 credits** (was 308 asked, 37 credits); tech 21 → 3.
- A fill can no longer stall behind a cron lock.

## Progress and honest status

- **One progress row for every long step**, with a moving number, and a yellow
  pill once nothing has moved for 30 s.
- **A heartbeat every two seconds inside a pass** — the shop's whole fill had
  been one silent 27-second pass.
- Pictures in no folder are *in no folder*, never the To sort folder's name.
- **The round-2 line shows round 2's own number** — *round 1: 32 placed ·
  round 2: 32* had read as 32 more where round 2 placed none.

## Bugs a user would have hit

- **Sixteen JavaScript call sites named four helpers that did not exist**, so
  Complete Cleanup, Restore default MIME types, Apply settings to the network
  and six taxonomy confirmations **did nothing at all, silently**.
- The list screen's reset-filters button **threw on every fresh site**, taking
  the script down.
- **A deployed change did not reach a browser that already had the old
  script** — the cache-busting string was built from file mtimes that arrive as
  1980-01-01 from a zip, so it was identical after every update.
- **A folder name is read as the person wrote it** — WordPress stores `&` as
  `&amp;` and every tree, draft match and profile text took the stored form
  (fourteen folders of one shop catalogue).
- **Enhanced Media Library, run alongside:** both plugins ship the same forked
  media JavaScript and the older copy answered, so the grid showed every file
  regardless of folder.

## Packaging and compliance

- **Plugin Check went from 1,045 errors to 0 errors and one non-blocking
  warning** on a clean archive.
- **The readme discloses an external service** — the Help screen fetches
  `known-issues.json` from the plugin's own public repository, sending nothing.
- **The release zip stopped shipping** `tools/`, `tests/`, `docs/`, `plans/`,
  `node_modules/` and the lock file: 1,988 files → 136.
- **Security reporting channels added**: Patchstack VDP, GitHub private
  advisories, `security.txt`.

---

## What an existing user has to adapt to

These belong in the changelog, and some belong in an **Upgrade Notice**.

- **The Rules tab is gone** from the Folders screen.
- **The folders panel now appears in list view**, where it deliberately did not
  before. The table scrolls sideways beside it.
- **The media list's toolbar is rebuilt as one row**; the controls are moved.
- **The tree no longer opens every branch by default.**
- **A prompt change no longer re-describes the library** — sites that relied on
  the automatic sweep must press the dashboard button.
- **Above 50,000 attachments the smart-folder counts are no longer shown**;
  they report *not looked*.
- **A confirmed tree refuses edits** until unconfirmed.
- **The same questions are no longer re-asked** after a split or keep answer.
- **Filing decisions have moved substantially.** A site that runs a fill after
  updating will get a different filling than before.
- **With a licence, the fill now makes outbound calls per slice** that it did
  not make before — metered, not debited, but new network traffic and the
  dominant cost of a fill.
- **With Enhanced Media Library active, this plugin now dequeues EML's media
  scripts, styles and grid template.**
- Developer-facing: the `vergeml_connect_base` filter and `vergeml_talk_vector()`
  were removed; the librarian schema went 1 → 4, additive, nothing backfilled.

## Gated, or not reachable by a user

- **`/rename-files` is off**, behind `VERGEML_FILE_RENAME`. This period
  *tightened* it: two `admin_post` handlers and the offer in the journey had
  been registered with the constant off. 74 of 76 endpoints are live.
- **The text-model filing tier requires a sealed licence key** — on an
  unlicensed site none of the agree/doubt behaviour is reachable.
- `VERGEML_AI_ALLOW_NONPROD`, `VERGEML_NO_WATCHDOG`, `VERGEML_SAFE_MODE`
  unchanged.

## Two things to settle before the changelog is written

1. **`readme.txt` does not disclose what a fill sends. This blocks the
   submission.** Verified 2026-09-19 against `core/filing.php:1761`. On a
   licensed site, `vergeml_filing_ask_model()` POSTs to
   `https://ai.vergelabs.nl/v1/file`, forty pictures a call, during a **fill**
   — and the body carries:
   - `license_key` and `site` (`home_url()`)
   - **`folders`** — every folder path in the tree, as names
   - **`pictures`** — per picture: its id, the describer's object phrases
     (`says`), and its `caption`

   The External services section describes only the **describe** run ("a
   downsized copy of the image, its file name, its MIME type, your site's
   address and your licence key"). It never says that folder names and stored
   descriptions leave the site during a fill. The section on sharing library
   counts even says "Never a file name, a title, a folder name or a picture" —
   true of *that* feature, but a reader takes it as the plugin's posture.

   wordpress.org requires every external service and the data sent to it to be
   named in the readme. This has to be written before the archive is cut. The
   facts are above; the words are Nathan's.
2. **CSV export formula injection is a known, unfixed finding.** A folder named
   `=HYPERLINK(...)` is written unquoted and evaluates in Excel, Numbers and
   Sheets. It was deliberately recorded rather than fixed — the fix changes the
   bytes of files customers already hold, and the export feeds the import. It
   predates this range, but it is open, and it is the kind of thing a reviewer
   or a researcher finds.
