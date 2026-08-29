# The journey — one path through every feature

## Problem

The features are built. The product is not.

Read from the running screens on 29-08-2026, logged in, on a library of 85
files:

- **The AI screen is a junk drawer.** Six `<h2>` sections of equal weight and
  no stated order: Licence, Describe the library, Describe in the background,
  File what is still loose, Say what you want, Set aside. Two of them do the
  same job — "Describe new images / Fix missing alt text" and, immediately
  below, "Start in the background / Alt text in the background". Nothing tells
  a person why there are two or which they want. That duplication was
  introduced by this repo in 3.9.0 and is ours to undo.
- **There is no beginning.** Eight screens, and none of them says where to
  start. The Overview is a menu of destinations.
- **The way in is hidden.** The first question anybody has is "can I try this
  without paying". The answer is demo mode — a checkbox in a `form-table` row
  labelled **Testing**, below the licence key field.
- **Prerequisites are invisible.** Alt text needs descriptions. The AI folder
  group needs descriptions. The Librarian's subject scheme needs descriptions
  *and* the duplicate scan. No screen says so; you discover it by pressing a
  button and getting a result that makes no sense.
- **Nothing states what a run will cost before it runs.** "Describe new
  images" on a twenty-thousand-image library spends twenty thousand credits.
  The most expensive button in the product is unguarded.
- **Two screens open on the word "Loading…" and nothing else** — Librarian and
  Library health.
- **State is one line of grey text.** "22 folders, 85 files". Not how many are
  described, how many have no alt text, whether a run is half finished.

Every one of these flows works correctly *once you know the order*. Nothing
teaches the order.

## The pattern already exists in this repo

`vergeml_librarian_stage()` (core/librarian.php:2459) returns
`unscanned | unindexed | unproposed | ready` by asking the health state and the
organize count, and `vergeml_librarian_card_text()` turns each into a sentence
a person can act on.

**That is the whole idea, implemented once, for one feature.** This plan
generalises it. Nothing here is a new concept; it is the existing concept
applied to all of them and given a screen.

## User story

As somebody who has just installed this on a library nobody ever filed, I want
one screen that tells me what to do next and what it will cost, so that I never
press a button whose prerequisite has not run and never spend credits by
accident.

## Decisions taken

- **A stateful list, not a wizard.** It does not hijack the person or lock the
  other screens. Every stage stays reachable directly; the journey says which
  one is worth opening now.
- **No numbered circles with a connecting line.** Explicitly on Nathan's banned
  list as recognisable slop. Stages are rows separated by a rule, the same
  language as the shell — a title, one sentence keyed to state, a state word, a
  single action.
- **Structure and geometry come from the shell already built from WP Rocket's
  own CSS**, plus this repo's existing elements. Nothing is drawn from
  imagination.
- **Each feature owns its own stage**, registered through a filter, exactly as
  `vergeml_ai_page_cards` works today. A feature switched off by safe mode
  contributes nothing and its row simply is not there.
- **Cost is stated before it is spent**, every time, in credits and in files.
- **Demo mode is promoted to the way in**, not a testing checkbox.
- **The background/foreground duplication is folded into one section** with a
  choice, undoing what 3.9.0 introduced.
- **Copy answers "what does this do for you", never "what does this do to the
  software"** — the rule already written into core/help.php.
- OPEN: whether the journey screen replaces the Overview or sits above it as a
  new first item. Task 3 builds it as a new first item, `media-start`; merging
  the two is a later decision once it can be seen.

## Out of scope

- No change to the folder tree panel, the media modal, or any front-end output.
- No change to what any endpoint computes. The journey reads existing state
  helpers; it does not invent numbers.
- No new AI capability. Nothing here describes an image differently.
- No removal of any existing screen. Every URL that works today still works.
- Not the visual pass on the six inner screens — this is the spine, and the
  inner screens follow once the spine is right.

## Context

**Files to read first**

- `core/librarian.php:2459-2497` — `vergeml_librarian_stage()` and
  `vergeml_librarian_card_text()`. The pattern being generalised.
- `core/admin-shell.php` — the shell every screen already renders inside, and
  the nav built from `$submenu`.
- `css/vergeml-shell.css` — the tokens and the section rhythm. Reuse, do not
  restate.
- `core/ai.php:707-780` — `vergeml_ai_rest_status()`, which already computes
  images / indexed / unindexed / missing_alt / ready / credits.
- `core/help.php` and `js/vergeml-help.js` — written, not yet wired.

**State helpers that already exist and must be reused rather than duplicated**

| Feature | Helper | File |
|---|---|---|
| Librarian | `vergeml_librarian_stage()` | core/librarian.php:2459 |
| Health / duplicates | `vergeml_health_state()` | core/health.php:66 |
| Smart-folder scan | `vergeml_smart_scan_state()` | core/smart-folders.php:355 |
| Proposed tree | `vergeml_organize_count()` | core/organize.php:468 |
| AI counts | `vergeml_ai_pending()`, `vergeml_ai_settings()` | core/ai.php:401, 34 |
| Background run | `vergeml_ai_run_payload()` | core/ai-background.php |
| Import sources | `vergeml_import_found()` | core/import-ui.php:111 |
| Set aside | `vergeml_quarantine_count_branch()` | core/quarantine.php:332 |

**Files created**

- `core/journey.php` — the stage registry, the ordered model, the screen.
- `css/vergeml-journey.css` — the stage list only. Tokens come from the shell.
- `js/vergeml-journey.js` — refresh after an action, no page reload.
- `tests/tree/journey.php` — the suite.

**Files that change**

- `vergelabs-media-library.php` — load `core/journey.php` and `core/help.php`
  inside the safe-mode guard, after the features whose stages they read.
- `core/ai.php` — fold the two describe sections into one; move demo mode out
  of the Testing row; add the cost confirmation; wire `vergeml_help()`.
- `core/ai-background.php` — remove its own card; contribute a choice to the
  describe section instead.
- `core/options-pages.php` — wire `vergeml_help()` into all 21 option rows.
- `core/librarian.php`, `core/health.php` — register their stages; replace the
  bare "Loading…" first paint with what is known before the fetch returns.

## Tasks

Ordered. Each verifiable alone.

1. **The stage contract.** `core/journey.php`: define what a stage is —
   `id, title, state, sentence, action_label, action_url, meta`, where `state`
   is `done | now | later | blocked`. Add the filter
   `vergeml_journey_stages`. No feature registers yet. Verify: the filter runs
   and returns an empty array on a site with everything off.

2. **The model.** `vergeml_journey()` — collects stages, orders them, and
   decides which single stage is `now` (the first that is neither `done` nor
   `blocked`). Everything after the first non-done stage is `later`, so exactly
   one row ever says "do this next". Verify by unit assertions in the suite.

3. **The screen.** `media-start`, registered first in the submenu so it is the
   first nav item. Renders the stage list inside the existing shell. Rows are
   separated by `1px solid var(--vgml-rule)` — the shell's own rule — with the
   state as a word, not a badge, and one button per row. **No numbered
   circles, no connecting line.**

4. **Stage: your library.** Always `done`. Files, folders, unfiled, described,
   missing alt — real numbers from the helpers above. This is the row that
   replaces "22 folders, 85 files".

5. **Stage: try it, or connect a licence.** `done` when a key is stored or demo
   mode is on. Otherwise `now`, offering both, with demo mode stated first and
   in plain words: nothing is sent anywhere, no credits, no licence.

6. **Stage: find the copies.** Wraps `vergeml_health_state()`. Free, no AI, and
   a prerequisite of the Librarian — which is why it comes before describing.

7. **Stage: describe your images.** `blocked` with a reason when stage 5 is not
   done. States the count and the credit cost **in the row**, before the click.

8. **Stage: fill in missing alt text.** `blocked` while nothing is described,
   naming what has to happen first. This is the prerequisite that is invisible
   today.

9. **Stage: file what is loose.** Reuses `vergeml_librarian_stage()` directly —
   it already returns the right four answers. Says which of the two schemes is
   available, and that date-and-type costs nothing.

10. **Stage: bring folders in.** Only when `vergeml_import_found()` is
    non-empty or a CSV is staged. A row that would always say "nothing to
    import" is a broken-looking feature, which is the reasoning already applied
    to the CSV source in core/import-csv.php.

11. **Undo the duplication.** `core/ai.php` gets one "Describe your images"
    section with a choice — on this screen, or in the background — and
    `core/ai-background.php` stops rendering a card of its own. **Not
    reversible in a later task without re-deciding, so it is called out.**

12. **The cost confirmation.** Before any describe run starts: how many images,
    how many credits, how many remain afterwards. Refuses with a plain sentence
    when the balance is short, rather than starting and failing at file 400.

13. **Demo mode out of "Testing".** It becomes the first thing on the AI
    screen's licence section, worded as the way to try the product.

14. **Wire the help.** `vergeml_help()` into all 21 rows in
    `core/options-pages.php` and the four on the AI screen. Copy is already
    written in `core/help.php`; this task only places the calls.

15. **Kill the bare "Loading…".** Librarian and Health render what is known
    server-side before the fetch returns — the stage sentence and the button —
    so the first paint is never one word.

## Validation strategy

**Gates.** 1, 2, 3 always. Gate 4 (functional) for the new screen. Gate 5 —
the journey screen reads many helpers and **must not become the slowest screen
in the plugin**: budget **12 queries**, to be measured on the box and in
Playground and written into the validate skill with a derivation, or lowered.
Gates 6 and 7 apply: a new screen and a new option touch packaging and the
upgrade path.

**New suite:** `tests/tree/journey.php`, on the box.

- exactly one stage is `now`, on every state combination tested
- a stage whose prerequisite is unmet is `blocked` and its reason names the
  stage that has to happen first
- with demo mode off and no key, describing is `blocked`, not merely disabled
- the cost stated for N images is N credits, and a short balance refuses
- a feature removed by safe mode contributes no stage and the list still
  renders
- **mutation check:** break the ordering so two stages say `now`, and confirm
  the suite goes red. A suite that cannot fail is worse than none.

## Risks

- **Safe-mode load order.** `core/journey.php` reads helpers from librarian,
  health, organize, ai and import. It must load after all of them and inside
  the guard, and every call must be `function_exists()`-guarded — in safe mode
  those files are not loaded at all.
- **Query budget.** Collecting eight stages naively means eight helpers each
  doing their own counting. `vergeml_ai_pending()` returns *ids*, so calling it
  for a count on a large library is a full column read — the journey must count
  without materialising ids, or cache per request.
- **PHP 7.4.** No arrow functions, no `match`, no named arguments. Gate 2's
  grep also trips on a parameter named `$private`, so avoid parameter names
  that read as PHP 8 keywords.
- **An existing install.** The new screen adds a submenu item; the nav is built
  from `$submenu`, so it appears without migration. Any new option must be
  written in `vergeml_set_options()` — an autoloaded option that has never been
  written costs a query on every request, which Gate 5 caught once already.
- **Registration order.** `media-start` must be registered before the other
  submenu pages to be the first nav item; menu registration order is priority
  order on `admin_menu`, and ai/health/librarian use priorities 11–13.
- **Wording.** Every stage sentence is customer-facing copy. It says what the
  person gets, never what the code does.

---

Per the skill: review this, then run `/execute` in a **new session**. Executing
here would run from a context already full of exploration, which is what the
split exists to prevent — though that call is Nathan's, and he has taken it the
other way before.
