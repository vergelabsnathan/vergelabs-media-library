# Plan — what every picture rests on

Spec: `docs/superpowers/specs/2026-09-08-traces.md`
Diagnosis: `docs/superpowers/specs/2026-09-08-traces-diagnosis.md`
Ticket: `tickets/2026-09-08-traces.md`
Second ticket: `tickets/2026-09-09-traces-loose-ends.md` — what the build found

**Phases 1, 2, 3 and 3.5 are done.** Their handoffs are
`docs/handoffs/2026-09-08-traces-phase-1-handoff.md`,
`…-phase-2-handoff.md`, `docs/handoffs/2026-09-09-traces-phase-3-handoff.md`
and `docs/handoffs/2026-09-09-traces-phase-3-5-handoff.md`. **Phase 4 is
next**, and the 3.5 handoff's "found, not done" list is about the tables it
alters — read it before starting.

Seven phases now, not five. Phase 3 surfaced things that belong in this plan
rather than in a list nobody runs, so 3.5 and 6 were added on 2026-09-09 and
Phase 4 grew. One phase per fresh session, the gates after each, a handoff at
the end of each, no compaction. The model per phase is named because a phase
sized for one and run on the other fails predictably —
`~/.claude/harness/model-profiles.md`.

**The order matters more than it did.** Phase 3.5 comes before Phase 4: two of
the three things it investigates live in the table Phase 4 alters, and its
third is the gate Phase 5 ends on. Running 4 first buries the evidence under
six columns of new history.

**The binding constraint, on every phase:** nothing here changes what the
plugin decides or does. The describe pipeline, the scheduler, auto-file, the
watch, the tree and the Move button behave exactly as they do today. A phase
that changes a behaviour has gone wrong, and the gates below are written to
catch that.

## Stop points — Nathan's, before the phase that needs them

- ~~**The mock on Phase 3**~~ — lifted 2026-09-08, and Phase 3 shipped against
  the Folders screen's own components. The grammar is settled for Phase 6 too:
  the `vgml-facts` list with the brand-mark bullet, and no new visual grammar
  invented.
- **The prompt rule's wording** is settled in the spec; a change to it is
  Nathan's.
- **Whether the "share of drafts changed before filing" number is shown to the
  owner** or only recorded. Recording it is Phase 4; showing it is a surface
  and needs the mock.
- **Three strings are open**, each named in the phase that needs it:
  - **Phase 6, not Phase 4** — the "too close to call" line now that `nearest`
    is stored and it can name both folders. The approved shape was
    *"Architecture 0.58 and Landscape 0.54, too close to call"*. Phase 4 stored
    the column and left `core/librarian.php:2908` and `:2910` alone because the
    string was not settled; it moves here so all three open strings are written
    in one pass, against both surfaces at once, since Phase 6 is what puts this
    line in the grid modal.
  - Phase 6 — the date and batch on an **abstention**, which was looked at
    rather than filed.
  - Phase 6 — the **in-flight Move** when no count was worked out. It reads
    *"Moving 12 of 12"* today.
- **Whether the three hand-kept RTL sheets should be generated** instead of
  named. Generating rewrites rules nobody has reviewed.

## Phase 1 · The reason is kept — Opus

Schema and the write path. Nothing visible changes.

**Files.** `core/librarian.php` (the two tables and
`vergeml_librarian_moves_insert()`), `core/filing.php` (return only — no
decision changes), and whichever callers hand moves to the insert:
`core/guide.php`, `core/folder-talk.php`, `core/auto-file.php`.

**Behaviour.**
- `vergeml_librarian_moves` gains `why`, `score`, `runner_up`, `runner_score`,
  `prompt_hash`, `model_version`. dbDelta, additive, nullable, no backfill.
- Every caller that has a `vergeml_filing_pick()` result passes it through to
  the insert. A caller that has no pick — a hand drag, a bulk move — writes
  `why = 'by hand'` and leaves the scores null. That distinction is the point:
  a row says whether a person or the matcher decided.
- An abstention is written as a row with `term_id = 0` and its reason.

**Mirror.** `core/librarian.php`'s existing `vergeml_librarian_install()` for
the dbDelta shape and the reserved-word trap recorded in its header
(`step_cursor`, not `cursor`).

**Proof.** `tests/tree/` gets `filing-trail.php`: a picture through each of the
four outcomes writes the row it should, with the values `vergeml_filing_pick()`
returned for it; a hand move writes `by hand`; an existing site with the old
columns upgrades without loss. Plus the negative query returns a non-empty set
on the box.

**Do not.** Do not touch the floor, the margin, the gates or the order of the
matcher. Do not backfill history — an empty `why` means "before this shipped"
and must stay readable as that.

## Phase 2 · The model stops counting — Opus

The dry run replaces the model's arithmetic. Server and plugin.

**Files.** `lib/guide.ts` and `lib/guide-stream.ts` in the service, the guide's
REST path in `core/guide.php`, `js/vergeml-folders.js`.

**Behaviour.**
- The plugin runs `vergeml_filing_pick()` over the draft before the turn is
  returned and hands the counts back with it.
- The draft on screen carries the matcher's number per folder, and one line for
  what it will not place.
- `guideRules` gains the rule from the spec, verbatim.

**Proof.** `lib/guide.test.ts` asserts the rule by name. An eval case for *"did
you put them in folder yes or no?"* passes only when the answer names the draft
and not the library. On the box: a walk of the guide where every number on the
screen is traced to a `vergeml_filing_pick()` call, and no number appears in
the model's message.

**Cost.** One guide turn per walk. Say the number before running it; the walk
is a few cents, not a describe pass.

**Do not.** Do not change what the model may propose. Do not add a model call.

## Phase 3 · What a person sees — Opus

The two surfaces. Not blocked: Nathan lifted the mock on 2026-09-08. Built from
the Folders screen's existing components, no new visual grammar invented.

**Files.** `js/vergeml-folders.js`, `core/guide.php`, and the attachment's own
screen for "why is it here".

**Proof.** `tests/ui/folders.spec.mjs` extended: the draft's counts equal the
dry run's, and the abstention line equals the number of abstentions. A
screenshot into the conversation before the phase is called done.

## Phase 3.5 · Why the measurements move — Fable 5.1

**No feature. Three unexplained numbers, and it runs before Phase 4.** Added
2026-09-09 from `tickets/2026-09-09-traces-loose-ends.md`.

**Fable, not Opus.** Written as Opus on 2026-09-09 and corrected the same day:
this is an investigation, and there are no acceptance criteria to give it per
task because nobody knows the answers yet. Opus's profile wants a narrow task
with a proof beside it and fails by patching a symptom; that is exactly the
wrong shape here. Fable takes a whole problem with the context loaded once and
room to follow it. Its failure mode is scope widening, and the "do not" below
plus the found-not-done list in the handoff are the fence. The output is an
answer per number and a suite or a note that keeps it answered, not a fix for
its own sake — two of the three may turn out to be somebody else's plugin, and
that is a result.

**Files.** Investigation first; whatever it names second. Expect
`tests/tree/filing-baseline.txt` (read, not rewritten), `core/folder-talk.php`
(`vergeml_talk_undo()`), `tests/ui/modes.spec.mjs`.

**Behaviour.**
- **The baseline drift.** 108 of 641 rows differ, 107 by ~0.0001 in the
  runner-up's score alone, and picture 2817 moved `ok` → `margin` with its
  score down exactly 0.030000 — which is the deepest-folder tie-break flipping.
  Phase 2 ruled out its own changes, the service, the profiles and the
  descriptions, each by measurement; start from what it did **not** rule out:
  term order, `parent_id`, and anything that changes which of two equal folders
  is "deepest".
- **Batch 18's 109 blank rows**, `undone = 0`, dated 8 September 15:56. Find
  which build wrote them. `vergeml_librarian_moves_insert()` accepts a
  four-element row and writes nothing where the reason goes — that is the
  shape to look for.
- **`modes.spec:506`.** 337px with `folders.spec` ahead of it, green without.
  Find what the earlier spec leaves behind — a screen option, a column set, a
  user meta — and either isolate the spec or delete an assertion nobody can
  trust.

**Proof.** Each number gets a written answer in the handoff with the
measurement behind it. Where the answer is a defect, a suite that fails before
the fix and passes after. Where the answer is "another plugin", the evidence
that says so, and the assertion is changed to say what it actually measures.

**Do not.** **Do not re-take `tests/tree/filing-baseline.txt`.** It is
re-taken when the drift is explained, and re-baselining first destroys the only
evidence there is. Do not backfill the 109 rows.

**Done 2026-09-09**, by Opus rather than Fable —
`docs/handoffs/2026-09-09-traces-phase-3-5-handoff.md`. In one line each:

- **The drift is the embedding service, and it is not a defect.** The same
  phrase does not come back as the same vector; the drift is fourth-decimal and
  changes no placement. `tools/filing-baseline-check.mjs` is the gate now, and
  the baseline was not re-taken.
- **The 109 rows had no reason passed to them, not a reason dropped** —
  measured by writing one row of each shape and reading it back. So an empty
  `why` means what the schema says it means. The last step is open and stated
  as open: no build that has been on the box can have written them, and the
  batch id was never reused.
- **`modes.spec:506` was measuring another suite's leftover pictures**, which
  sit at the top of page one because page one is the newest attachments. Green
  in both orders now, and it prints the row's picture on a pass.

## Phase 4 · Who approved it, and the record made true — Opus

Was Sonnet and two nullable columns. It is now a schema change with a
behaviour change beside it, which `~/.claude/harness/model-profiles.md` puts
outside what Sonnet is given.

**Files.** `core/librarian.php`, `core/folder-talk.php`
(`vergeml_talk_undo()`), the two places a batch is inserted
(`core/auto-file.php:479` and `core/librarian.php:1286`), and the three tree
suites in the first bullet below.

**Before any edit, ten minutes.** `vergeml_librarian_moves` holds 109 rows and
all of them are batch 18. **Batch 23 — `refile`, created 2026-09-09 06:35:37 —
has zero rows**, and `vergeml_talk_trail_write()` returns before creating a
batch when the trail is empty, so a batch with no rows means the batch was made
and the insert wrote nothing. Find out whether that insert is failing;
`tools/box-batch-when.php` reads the table and is read-only. If it is failing,
that is the whole phase and the rest waits: three more columns on a table whose
writes fail silently is worse than none.

**Behaviour**, in this order — the first one comes before the schema because
every task after it runs those suites against the box repeatedly.
- **The tree suites stop deleting other people's batches.** Found in Phase 3.5:
  `tests/tree/auto-file.php:385` deletes every `auto`/`suggested` batch created
  today, `tests/tree/nl-commands.php:296` every `spoken` batch ever, and
  `tests/tree/filing-trail.php:768` every batch above its own start high-water
  mark — while each deletes move rows only `WHERE attachment_id` is one of its
  own fixtures. So a suite reaches batches it never created and orphans their
  moves, and `filing-trail.php` runs `env: 'box'`. A suite deletes the batches
  it caused and their move rows with them, and nothing else.
- `vergeml_librarian_batches` gains `user_id` and `approved_at`; the Move and
  the undo record their actor. A batch made by cron or by the watch records 0,
  which reads as "nobody pressed anything" and has to stay readable as that.
- **`VERGEML_LIBRARIAN_VERSION` goes 2 → 3.** Without it `maybe_install()`
  never runs dbDelta and the columns never reach an existing site. And the
  upgrade runs on a call path rather than on deploy: Phase 3.5 measured the
  Phase 1 code live on the box from 8 September 09:00:08 with the table not
  altered until 06:46 the next morning. Confirm the columns with `SHOW COLUMNS`
  on the box, not with a deploy that exited zero.
- `vergeml_librarian_moves` gains `nearest` — the folder
  `vergeml_filing_pick()` scored best and refused. dbDelta, additive,
  nullable, no backfill, exactly as Phase 1's six were.
- **`vergeml_talk_undo()` marks the rows it reverses `undone = 1`**, as
  `core/librarian.php:2177` already does for the librarian's own undo. This is
  the one behaviour change in the whole plan, and it is a correction: the rows
  it leaves today assert moves that no longer hold.
- With `nearest` stored, `vergeml_librarian_why()`'s margin line names both
  folders. **The copy for that is Nathan's** — the approved line was
  *"Architecture 0.58 and Landscape 0.54, too close to call"*, and the two
  current ones, at `core/librarian.php:2908` and `:2910`, name the runner-up
  only. **If Nathan has not settled the string, leave both lines exactly as
  they are**, store the column, and say so in the handoff. The session does not
  write this string.

**Proof.** `tests/tree/filing-trail.php` extended: a filed batch names the
person and the moment; an undo names its own; a refused picture's row carries
the folder it nearly went to and `vergeml_librarian_why()` reads both names
out; and **a guide undo leaves no row claiming a move that was reversed** —
the assertion that fails today. For the suites, the batch count and the move
count on the box are the same before and after running each of the three twice.

**And `node tools/filing-baseline-check.mjs`**, which is this phase's own
proof: a schema change must change no placement. It asserts the placements are
identical and no score moved further than 0.001, and it must not be
re-baselined — see the gate section for why a byte diff of that file can no
longer pass.

**Mirror.** Phase 1's dbDelta and the reserved-word trap in
`core/librarian.php`'s header; `vergeml_librarian_undo_step()` at
`core/librarian.php:2177` for how rows are marked.

**Do not.** Do not show the "share of drafts changed before filing" number —
that is a surface and needs the mock. Do not touch the matcher. **Do not
backfill, delete or tidy batch 18's 109 rows** — Phase 3.5 could not identify
the build that wrote them and left that open deliberately; they are the
evidence. Do not re-take `tests/tree/filing-baseline.txt`.

**Left out on purpose.** `vergeml_librarian_moves_insert()` still accepts a
four-element row, and it now has no caller. Requiring the fifth element would
also remove the branch its header defends — a plan already in flight when a
deploy lands finishes on the rows it started with rather than fataling. That is
a decision, not a task, and it is Nathan's.

## Phase 5 · The failure states — Sonnet

Already approved by Nathan on 2026-09-07 and carried here so it is not lost.
**Re-read against the code on 2026-09-09**, at the end of Phase 4: the line
numbers had moved, one of the two files is a different problem, and the failure
fabricates a zero in three places rather than one. What follows is the corrected
brief; do not work from the paragraph it replaced.

**Files.** `js/vergeml-folders.js` — three sites, all in the same failure:

| site | what it reads when `guide/rules` fails |
|---|---|
| `:651` | the fabricated `{ rules: [], unfiled: 0, pictures: 0 }` in `loadRules()`'s catch |
| `:668` | *"Move only the **0** unfiled pictures. Today's 19 folders stay."* beside *"Move every picture. Today's 19 folders are removed."* |
| `:744` | every rule card's pill reads *"**0** folders"* — `counts[ r.id ] || 0` in `renderRules()` |

The third is the one nobody had noticed and it is the same lie as the other two.
The four rule cards themselves come from the local `RULES` constant, so they
still render and stay pickable on a failure — that is correct and stays. What
must not survive is a number nobody computed.

`js/vergeml-gallery-block.js:65` is a **different** shape and is not a
fabricated zero: `loadFolders()` catches and returns an empty folder *list*, so
the block's folder picker silently offers nothing rather than saying it could
not load. Fix it in the same pass, in the same spirit; it has no count to
suppress.

**Behaviour.**
- A failed `guide/rules` says so through `talk.note()`, which this file already
  uses for a failed Move.
- No fabricated zeros: the scope radios and the rule pills show no number at all
  rather than 0, exactly as Phase 3's draft counts do.
- The scope choice is disabled while the number is unknown. The rule cards stay
  pickable.
- The gallery block says its folders could not load rather than showing none.

**Copy.** Nathan's, as always. Proposed on 2026-09-09 and used unless he changes
it before the session starts — fact, consequence, action, in the voice of the
sibling string in the same file (*"That did not go through. Nothing moved."*):

    The numbers did not load. No rule can say what it would do — reload to try again.

**Proof.** `tests/ui/folders.spec.mjs`: with the endpoint refused, the screen
shows the failure, the scope radios are unavailable, and `0` appears
nowhere on the rules panel — not in a scope radio, not on a rule pill. That last
assertion is the one that fails today, in three places. Mirror it on Phase 3's
own test, *"when the dry run gives up the draft says so, and offers no number at
all"*, which already refuses the screen a fabricated zero and asserts it the
same way.

**Mirror.** Phase 3's own give-up state: `vergeml_guide_fit_unknown()` in
`core/guide.php` and `fitUnknown()` / `syncCounted()` at
`js/vergeml-folders.js:792` are the same problem already solved once — a number
nobody computed, not shown.

**Do not.** Do not make the rule cards unpickable: picking one starts a dry run,
which is a separate request and may well succeed. Do not invent a retry
mechanism; the note names reloading and that is enough.

## Phase 6 · The answer where the question is asked — Opus

Added 2026-09-09. "Why is it here" reaches the attachment's own screen and not
the media grid's modal, which is where most people meet a picture. Phase 3
guarded the field off `query-attachments` because that listing runs
`attachment_fields_to_edit` for every item on the page — 5.7 queries a picture,
measured, some 450 on an eighty-item page — and the modal renders from that
same listing response.

**Files.** A read-only REST route (the spec allows one), `core/librarian.php`,
and a media view in `js/` beside the plugin's existing ones.

**Behaviour.**
- One route, one picture, on demand: `vergeml_librarian_why()`'s own answer.
- The details panel asks for it when a picture is opened, and not before. The
  listing stays as cheap as it is today — that is the constraint, not a
  preference.
- A picture with no record shows no section, exactly as the field does now.

**Proof.** `tools/box-why-cost.php` again: the listing's query count is
unchanged from today's. A browser spec opens the grid modal on a picture with
a row and reads the same lines the attachment screen shows.

**Mirror.** `js/eml-media-views.js` for how this plugin already extends
WordPress's media views; `vergeml_why_here_field()` for the markup, which does
not change.

**Copy.** Nathan's, and two strings are open before this phase:
- the date and batch on an **abstention**, which was looked at rather than
  filed, so "Filed 9 September · batch 23" is untrue there;
- the **in-flight Move** with no count worked out, which reads *"Moving 12 of
  12"* today because the goal is null.

**Do not.** Do not put the field back on `query-attachments`. Do not rewrite
the markup — it is the approved one.

## The gates, after every phase

```
npx playwright test --config tests/ui/playwright.config.mjs modes.spec shell.spec shots.spec folders.spec
node tools/verify.mjs copy journey guide
node tests/tree/t0-endpoints.js
node tools/filing-baseline-check.mjs
```

And per phase, the suite named in it.

The last line was added in Phase 3.5 and belongs on every phase from Phase 4
on, not only on Phase 5: it is cheap, it spends nothing, and it is the only
thing standing between "we changed no decision" and a claim nobody checked.

**The mutation check, once, in Phase 1.** Make `vergeml_filing_pick()` return a
`why` that does not match what it decided, and `tests/tree/filing-trail.php`
goes red. A trail nobody can prove wrong is not a trail.

**The regression gate that matters most.** Before Phase 1 and after Phase 5,
run the same filing pass on the box over the same pictures and compare the
placements. **They must be identical.** This work records reasoning; it does
not change a single decision, and that is the assertion that proves it.

**And that gate is run with `node tools/filing-baseline-check.mjs`, not with a
byte diff.** Phase 3.5 found why the baseline drifts and it is not a defect:
the matcher scores a folder partly on how alike two class phrases are, each
class phrase's vector comes from the service, and the service — OpenAI's
`text-embedding-3-small` — does not answer the same phrase with the same 512
floats every time. Asked six times, two phrases in nine gave a second answer,
~1.5e-4 apart. So a score is reproducible to about a thousandth and a
**placement** is reproducible exactly, the floor and the margin and the depth
tie-break being two to three orders of magnitude above the noise.

The checker asserts the three things that hold: the library is the one the
baseline was taken over, every picture would be filed exactly where it was, and
no score moved further than 0.001. It never re-takes the baseline. Measured on
2026-09-09: 43 of 641 rows moved in the fourth decimal, no `term_id` and no
`why` differed, and the check is red on a changed placement, a score outside
the band, or a missing picture.

The one exception the plan carries to "nothing changes a behaviour" is Phase
4's `vergeml_talk_undo()` marking its rows undone — a correction to a record,
not to a decision, and the placements it produces are identical either way.

## Cost

Nothing in Phases 1, 3, 3.5, 4, 5 or 6 reaches a model. Phase 2 spends one
guide turn per walk. No describe pass is needed at any point; the index on the
box is already described. If a phase finds it needs one, it says the number
before running it.

## Openers, one per phase

Paste one of these into a **fresh** session. One phase per session; do not run
two. Every one of them ends with a handoff and no compaction.

### Phase 1

```
Read plans/traces.md, then docs/superpowers/specs/2026-09-08-traces.md and its
diagnosis. State which model you are and follow that profile in
~/.claude/harness/model-profiles.md.

This session is Phase 1 only: the reason a picture moved is recorded.

Before anything, capture the baseline the last gate needs: run the filing pass
read-only over the box's library and save the placements, so the same pass can
be compared after Phase 5. It must be identical.

Then: six columns on vergeml_librarian_moves, vergeml_librarian_moves_insert()
carrying them, every caller passing the vergeml_filing_pick() result it already
has, a hand move writing 'by hand', and an abstention written as a row with
term_id 0. In core/guide.php around line 1718 only $pick['term_id'] survives
today -- that is the change. The pairs in $work are [attachment, branch] and
are the carrier; extending them is additive.

Do not touch the floor, the margin, the gates or the order of the matcher.
Do not backfill history.

Gates: tests/tree/filing-trail.php (new, with its mutation check -- make
filing_pick return a why that does not match what it decided and it goes red),
then tests/ui/modes.spec.mjs, shell.spec, shots.spec, folders.spec, and
node tools/verify.mjs copy journey guide, and node tests/tree/t0-endpoints.js.

Nothing in this phase spends credits. Make a throwaway admin with
tools/box-ui-user.sh (ssh needs -i ~/.ssh/hetzner_vgml) and delete it at the
end. Never deploy to the box while a suite is running against it.
End with a handoff in docs/handoffs/.
```

### Phases 2 to 6

Same shape, one phase named, the phase's own files, behaviour, proof and "do
not" copied from above. Do not write a new opener from memory: the phase in
this plan is the brief.

### The traps every opener should carry

Paid for between 6 and 9 September, and cheaper to read than to rediscover.

- **The dry run is 10 seconds warm and 210 cold.** A spec that times out at
  120s has a cold phrase cache, not broken code: warm it with
  `add_filter('vergeml_guide_fit_budget', fn() => 900)` and one run.
- **`tests/perf/mu-fit-cold.php` must be on the box**, or `folders.spec`'s
  give-up test fails with *"is tests/perf/mu-fit-cold.php installed?"*. It is
  inert unless a request carries `vgml_fit_cold`.
- **`modes.spec:506` measures page one of the media list**, which is the twenty
  newest attachments — so fixtures another suite left behind land at the top of
  what it measures and it reports a screen nobody ships. That was the 337px:
  eight `zz` pictures dated 8 September among real ones whose newest is dated
  1 September. Answered in Phase 3.5, green in both orders since. It now prints
  the tallest row's **picture** on a pass as well as a failure; if it goes red,
  read that name first.
- **`shots.spec` can fail once** with `net::ERR_ABORTED` on `page.goto`.
  Re-run the spec alone before believing it.
- **The `GUIDE_WALK=1` walk moves real pictures and does not undo when it
  fails.** If it goes red: `wp term list media_category --format=count`, then
  `vergeml_talk_undo()`. It is not in the gate list; do not run it without a
  reason.
- **Never deploy while a suite is running against the box.** PHP-FPM restarts
  and a spec fails with a 502 that reads exactly like a regression.
- **Run only the suites the phase names.** `tests/librarian/gate7-schema.php`
  proves a site that loses its librarian tables gets them back — by losing
  them, four times, and then firing a real `datetype` apply. Run in Phase 4 for
  reassurance after a schema bump, it destroyed batch 18's 109 rows and batch 23
  and left eleven folders on the tree. `log_bin` is OFF on that MariaDB and
  there is no dump: none of it came back. **Fixed the same day** — it now
  renames both tables aside and back and takes off the folders it made, proved
  with `tools/box-gate7-canary.php` — but the lesson stands: a suite's blast
  radius is a property of the suite, not of the change being tested.
- **The browser specs need an administrator.** `UI_USER` and `UI_PASS`, made
  with `tools/box-ui-user.sh` and deleted at the end. Without them all 39 tests
  fail identically at ~22s each, which reads like a broken build and is a
  missing login.
- **A suite that stops early leaves its fixtures in the library.** Four
  `zz trail` pictures sat among Nathan's real ones from 8 to 9 September.
  `tools/box-why-clean.php` sweeps both prefixes; run it if a tree suite exits
  non-zero.
- **`node tests/tree/t0-endpoints.js` needs Playground**: `node
  tools/deploy.mjs --zip`, then `node tools/play.mjs --port 8899`, then `node
  tools/verify.mjs tree`. Wait for the admin to stop answering 502, not for
  the log line.
- **The four browser specs take about 17 minutes.** Do not pipe them through
  `tail` or the failure detail is lost. Do not edit a spec file while its own
  run is in flight — the result belongs to neither version.
- **Heredocs eat backslashes**; anything with a regex or an escape goes
  through the Write tool, and a Python edit on Windows flips a file to CRLF
  unless it writes with `newline=''`.
- **Three RTL sheets are kept by hand** and `tools/rtl.mjs --check` cannot see
  them; it now names them on every run. Edit both files.
- **The box's session holds Nathan's own 4 September draft** with the model's
  fabricated counts in it (Illustrations 23, Screenshots 6, Diagrams 5). The
  specs restore it. Do not correct it; it is the demonstration.
