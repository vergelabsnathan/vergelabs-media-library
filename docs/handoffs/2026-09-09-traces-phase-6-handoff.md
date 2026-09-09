# Session handover — 2026-09-09, traces Phase 6: the answer where the question is asked (Opus 5)

Phase 6 of `plans/traces.md` is built and deployed. "Why is it here" now reaches
the media grid's modal, which is where most people meet a picture, and the three
strings the plan left open are written.

**The model.** The session opened as **Sonnet 5**, which is not the model the
plan assigns this phase. That was said before any code was written — the Sonnet
profile excludes a shared component, and this phase is a REST route plus a media
view — and Nathan switched the session to **Opus 5**, which the plan names. The
work below was done as Opus: one task at a time, the mirror named per task, no
copy invented, the proof run after each.

Read in this order:

1. `docs/superpowers/specs/2026-09-08-traces.md` — the contract.
2. `docs/handoffs/2026-09-09-traces-phase-5-handoff.md` — Phase 5.
3. `plans/traces.md`, Phase 6.
4. This handoff.

---

## What was built

**The route.** `GET /vergeml/v1/librarian-why/<id>`, registered in
`vergeml_librarian_routes()`, returning `vergeml_librarian_why()`'s own answer
as `{ id, label, lines }`. Read-only, one picture, on demand.

Its permission is **`edit_post` on that picture**, not the librarian's
`manage_categories` — it reads one attachment's record, so it asks the question
the screen it appears on asks. Mirrored from `core/quick-edit.php:161`, the
plugin's other per-attachment route.

**The view.** `js/vergeml-why-view.js`, enqueued on `wp_enqueue_media`. It wraps
`wp.media.view.Attachment.Details.prototype.render` rather than replacing it —
the liability the header of `js/vergeml-media-views.js` warns about — and the
modal's two-column view inherits the wrap, because `TwoColumn` extends `Details`
and overrides `initialize` and `toggleSelectionHandler` but not `render`. Read
off the box's own `wp-includes/js/media-grid.js` before the file was written.

Asked once per picture and cached, so the arrows walking the library re-paint an
answered picture without a request. A late answer is painted only if the view is
still on the picture it was asked about. A picture with no record shows no
section — not an empty list under a heading.

**The listing is untouched.** `vergeml_why_here_field()` still returns early on
`query-attachments` and no markup of ours is in that response.

**The brief names `js/eml-media-views.js` as the mirror; there is no such file.**
It is `js/vergeml-media-views.js` — renamed in the fork cleanup — and that is
what was read.

---

## The copy, exactly as given

| where | line |
|---|---|
| `vergeml_librarian_why()`, margin | `Left where it was · %1$s scored %2$s against %3$s at %4$s, too close to call` |
| `vergeml_librarian_why()`, abstention | `Looked at %1$s · batch %2$s` |
| `js/vergeml-folders.js`, in-flight Move | `%s moved so far` |

Both old margin variants are gone. On a real row the first now reads *"Left where
it was · zzTrailA scored 1.00 against zzTrailC at 0.93, too close to call"* — the
folder that scored best and was refused, then the one it could not beat.

The Move's `Math.max( moved, goal || 0 )` is gone with it: a known goal still
reads *"Moving 12 of 40"*, and an unknown one says how many moved rather than
copying that count into a total nobody worked out.

**One judgment call, and it is the only one.** The new margin line needs four
values, and a row written before `nearest` existed has two of them. Rather than
half-write the sentence, the line is left off there — the same choice the *"Ahead
of"* line above it already makes when the record cannot say it. No such rows
exist on the box (see below), so this is a decision about older sites, not about
anything visible today.

---

## The finding that changed the proof

**The box's `vergeml_librarian_moves` table is empty. Zero rows.**

The browser spec was written to open the modal on a picture that already has a
record, as the plan asks. It could not find one — among the hundred newest
pictures, and then among all of them. The cause is not this phase: Phase 4's own
handoff records it under *"The incident — batch 18's 109 rows are gone, and I
destroyed them"*, `gate7-schema.php` took batch 18, batch 23 and every move row
with them, and nothing has filed since. `tools/box-why-find.php` (new,
read-only) is what established it, and it says the same thing after this session
as before: 0 rows, 0 answerable pictures.

So the proof was split to where the evidence actually is, rather than planting a
record to photograph.

**`tests/tree/filing-trail.php` proves the answer against real rows** — it builds
all four outcomes through the real matcher and removes them again, which is what
it was built to do. Added there: the route hands back the reader's own lines for
every outcome, the attachment's own screen shows the same ones, a margin
abstention names both folders, every abstention says when it was looked at and in
which batch, a picture with no row gets no lines and no field, and the route is
registered `GET` only. **119/119**, up from Phase 4's 81.

```
ok    the route hands the margin picture the reader's own lines  -- Left where it was ·
      zzTrailA scored 1.00 against zzTrailC at 0.93, too close to call / Described by
      zz-model-7 · prompt zzhash01 / Looked at 9 September · batch 999,997
ok    and the attachment's own screen shows the margin picture the same ones  -- all 3 lines
ok    a picture with no row gets no lines from the route and no field on the screen  -- 0 lines
ok    the route is registered, and it only reads  -- GET
```

**`modes.spec.mjs` proves what only a browser can**: the listing asks the route
nothing, the route answers for a real picture under the real label, the modal
asks when a picture is opened and paints the lines that came back, and a picture
with no record gets no section at all. The three lines are fulfilled with
`page.route()` rather than filed — the same way Phase 5 proved a refused request,
and it cannot rot with the library.

```
ok 1 tests\ui\modes.spec.mjs:887:1 › the grid modal says why a picture is here,
     and the listing still does not (2.3m)
```

**Mutation check.** `paint()` was made to return early, deployed, and the spec
went red at exactly the assertion that watches it — *"Error: expect(locator)
.toBeVisible() failed, element(s) not found"* on `.vgml-why .vgml-why-facts li`.
Reverted, deployed, green. The empty case is asserted in the same run, so the
test fails in both directions: a view that paints nothing and a view that paints
something nobody sent.

**Two false passes were found and closed while writing it.** The listing check
read the library before the tiles arrived, so an empty grid satisfied "carries no
why markup" by carrying nothing; it now waits for pictures and counts how many it
checked. And the first version's `.catch( () => null )` around the route would
have read a 404 as "no record" — that is how a broken route passes as missing
data.

---

## The cost, which is the constraint

`tools/box-why-cost.php`, 20 real pictures, before the deploy and after it:

```
  as the details panel does    133 queries
  as the grid's listing does    40 queries
```

Identical either side. The listing sends what it sent this morning.

---

## Gates

| gate | result |
|---|---|
| `npx playwright test … modes.spec shell.spec shots.spec folders.spec` | **38 passed, 3 skipped, 0 failed** (18.7m, 41 tests incl. the new one) |
| `node tools/verify.mjs copy journey guide` | **62/62** — copy, guide, journey all green |
| `node tests/tree/t0-endpoints.js` (Playground) | **21/21** |
| `node tools/filing-baseline-check.mjs` | **3 of 3**, 641 pictures, placements identical, largest score move 4.11e-4 |
| `node tools/verify.mjs filing-trail` (this phase's own) | **119/119** |

The 3 skipped are Phase 5's baseline, unchanged. `modes.spec`'s `ROW_ALARM`
canary: **231px against a ceiling of 300**, the same number Phases 3.5, 4 and 5
measured.

Screenshot: `tests/ui/shots/why-here-modal.png` — the section in the modal, under
the file's own fields and above the taxonomy boxes. **The lines in it are the
spec's fulfilled ones**, because the box has no real record left to show.

---

## The box, as it was left

- Running `aa70387`, deployed and verified, 135 files re-hashed, `php -l: every
  file parses`.
- The throwaway administrator (`vgml-phase6-1788964054`) is deleted.
- `vergeml_librarian_moves`: **0 rows before this session and 0 after** —
  `filing-trail` removed every row and picture it made (*"0 left behind"* twice).
- No fixtures, no probe scripts: `/tmp/vgml-why-*.php` are removed by their own
  runner scripts.
- The local Playground (port 8899) was started for the endpoints gate and
  stopped.
- Deploying to the box was asked for and granted once, at the top of the phase;
  the mutation check's two extra deploys were inside that grant and are named
  here so the count is not a surprise.

## Cost

**Nothing in this phase reached a language model.** No describe pass, no guide
turn, no eval; `GUIDE_WALK` was never set. The route is two queries and the view
is one request.

## Found, not done

- **Nothing on the box demonstrates this surface to a person.** The table is
  empty, so opening any picture there shows no section — correctly. The first
  real Move repopulates it; until then the screenshot above is fulfilled data and
  should not be read as a picture's actual record.
- **The margin line is omitted for rows written before `nearest` existed.** The
  judgment call above. Worth one look; it is a two-line change in
  `vergeml_librarian_why()` if Nathan wants a fallback sentence instead.
- **Where the section sits in the modal is a placement decision, not copy.**
  Before `.attachment-compat` — with the alt text and the file's facts — because
  after it means below several screens of taxonomy checkboxes on this box. The
  screenshot shows it; it is one word in `place()` to move.
- **`tools/box-why-find.php` is new and read-only**, and it is the quickest
  answer to "does this box have anything to show". Worth keeping.
- **The route has no rate or size ceiling**, because it answers at most twenty
  rows for one attachment the caller may already edit. Named so it is a decision
  rather than an oversight.

## Next

Phase 6 was the last phase in `plans/traces.md`. The plan's closing gate — the
same filing pass over the same pictures, placements identical — has been run on
every phase since 4 and is green here: **3 of 3, 641 pictures, no placement
moved.**

What the plan still lists as Nathan's, untouched by this phase: whether the
"share of drafts changed before filing" number is shown, and whether the three
hand-kept RTL sheets should be generated.
