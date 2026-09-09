# Session handover — 2026-09-09, traces Phase 3: what a person sees (Opus 5)

Phase 3 of `plans/traces.md` is built. The draft says when it does not know, a
picture says why it is where it is, and every number beside a draft is now
asserted as painted rather than as answered.

Read in this order:

1. `docs/superpowers/specs/2026-09-08-traces.md` — the contract.
2. `docs/superpowers/specs/2026-09-08-traces-diagnosis.md` — the argument.
3. `docs/handoffs/2026-09-08-traces-phase-2-handoff.md` — the numbers underneath.
4. `plans/traces.md` — the five phases. Phase 4 is next.
5. This handoff.

`core/filing.php` is untouched. `tests/tree/filing-baseline.txt` was not
re-taken; the drift recorded in the Phase 2 handoff is still unexplained and
still needs an answer before Phase 5.

## Task 1 — the draft says when it does not know

`vergeml_guide_draft_fit()` answers null when it runs past its twenty-second
budget or cannot score at all. Until now the screen drew **nothing**: no
counts, no lines. Silence beside a draft reads as "these folders are
unchanged", which is a claim nobody computed — and a folder the draft invents
fell back through `js/vergeml-tree-view.js:499` to a **0**.

| file | what |
|---|---|
| `core/guide.php` | `vergeml_guide_fit_unknown()`; the turn route uses it in place of null |
| `js/vergeml-folders.js` | `fitUnknown()`, `syncCounted()`; `movingCount()` answers null; the button drops its number |
| `js/vergeml-tree-view.js` | `setCounted()`; no count pill and no "after Move" on a draft whose counts nobody worked out |
| `tests/ui/folders.spec.mjs` | the state, walked |
| `tests/perf/mu-fit-cold.php` | the mu-plugin that forces it |

The fit keeps its shape and says `counted: false`, `move: null`, `counts: {}`.
Null would have been indistinguishable from "no draft to answer about", which
is the value a session write already writes.

**Copy, from Nathan on 2026-09-09:**

    The counts are not worked out yet
    The next turn should have them

In the `vgml-facts` list the four counting lines use, with the brand-mark
bullet. **The Move button reads "Move the draft"** — advised rather than
chosen from the list, because the dot form (`Move · no changes yet`) is this
screen's disabled-state idiom and the button here still works. It stays
enabled on purpose: a cold library must not be locked out of filing because a
count is missing, and the plan's binding constraint is that Move behaves
exactly as it does today.

**Proof.** `folders.spec.mjs` → *when the dry run gives up the draft says so,
and offers no number at all*. The budget is filtered to 0 for one request
through `tests/perf/mu-fit-cold.php`, so the state is reached on a warm box in
one turn rather than waited for. It asserts the two lines, that no folder
wears a count, that the button offers none and is still enabled, and that
`\b0\b` appears nowhere on the draft. Screenshot:
`tests/ui/shots/folders-no-counts.png`.

**Mutation check.** The count-pill suppression was disabled, deployed, and the
spec went red on `.vgml-list .vgml-count` — *Expected 0, Received 1*, the
fabricated zero back on "Draft probe". Reverted, green.

**The budget was not raised anywhere.** The state is the point.

## Task 2 — why is this picture here

`vergeml_librarian_why()` in `core/librarian.php` reads one placement back out
of `vergeml_librarian_moves` and the batch that carried it, and
`vergeml_why_here_field()` puts it on the attachment's own screen under "Used
in", which it mirrors. Nothing is reconstructed and the matcher is never
re-run: a score worked out today would answer a question about today.

**Which row it reads, and why it is not simply the last one.** The guide's own
undo puts terms back **without marking the rows undone** (`vergeml_talk_undo()`
does not touch `undone`; only the librarian's undo at `core/librarian.php:2177`
does). So the newest row can describe a move that no longer holds. The reader
takes the newest non-undone row whose folder the picture is **still in**;
failing that an abstention, which cannot go stale because nothing moved;
failing both, a row with no reason on it, which says only that the move
predates the record. A picture the record has never heard of gets no field at
all rather than an empty one.

**Copy, from Nathan on 2026-09-09** — facts, one per line; field label "Why is
it here":

    In zzShotA · scored 1.00
    Ahead of Landscape at 0.44 · by 0.37
    Described by anthropic/claude-haiku-4.5 · prompt 6bb36302
    Filed 9 September · batch 23

    Left where it was · best score 0.25, below the floor of 0.55
    Left where it was · scored 1.00 against 0.93 for zzShotC, too close to call
    Left where it was · the wrong kind for the folder that fit

    Put here by hand · nothing scored it
    Chosen in a draft you approved · nothing scored it
    Moved before the reason was recorded

Two places the approved wording met data that does not exist, both flagged
rather than invented around:

- **"too close to call" names one folder, not two.** The approved line was
  *"Architecture 0.58 and Landscape 0.54"*. `vergeml_filing_pick()` returns the
  near-miss winner as `nearest` and **Phase 1 has no column for it** — the row
  keeps the winner's score and the runner-up's name. The line names the folder
  the record can name. A seventh column would fix it and that is schema, which
  is Phase 1's.
- **An abstention gets no "Filed" line.** It was not filed; it was looked at.
  The date and batch are dropped there rather than captioned with a verb that
  is untrue. If that date is wanted, it needs a string.

**Proof.** `tests/tree/filing-trail.php`, 47 checks → **81**. The same four
fixtures Phase 1 built, run through the real pass, then read back: each
outcome's `why`, `score`, `runner_up`, `runner_score`, `prompt_hash` and
`model_version` equal what `vergeml_filing_pick()` returned for that picture,
the placed one names its folder and its batch, the three refusals claim
neither, a planted empty row reads as *"Moved before the reason was
recorded"*, and a picture with no row answers with nothing.

Screenshots: `tests/ui/shots/why-here-placed.png` and
`tests/ui/shots/why-here-left.png` — a real picture, scored by the real
matcher on the box, read back by the real reader.

### The hot path, and what it cost

`attachment_fields_to_edit` is not only the details panel: WordPress runs it
through `get_compat_media_markup()` inside `wp_prepare_attachment_for_js()`,
and the media grid's `query-attachments` request prepares a **whole page** of
attachments in one go. Measured on the box with `tools/box-why-cost.php`, over
20 real pictures with the post caches primed:

    as the details panel does   153 queries
    as the grid's listing does   40 queries
    the listing is spared 113 queries, 5.7 a picture

So the field is skipped on `query-attachments`. On an eighty-item grid page
that is some 450 queries not run.

**The consequence, and it is Nathan's to settle.** The grid's modal renders
`compat` from the listing's own response — it does not refetch per item — so
with the guard in place **the field does not appear in the grid modal**. It
appears on the attachment's own screen (`post.php`, which is where the media
list's Edit link goes) and that is the surface the brief names. Making the
modal carry it needs the read-only route the brief allows plus a media-view
extension in JavaScript, which is a shared and fragile surface and was not
started this late in a session. Measured, verified both ways, not guessed.

## Task 3 — the counts beside the draft, finished

No production change was needed and none was made. Phase 2 put the matcher's
count on every draft folder and Task 1 removed the fallback that could paint
something else; what was missing was the assertion that the number **on
screen** is the run's, rather than the number in the answer the route gave.

`folders.spec.mjs`'s dry-run test now reads every rendered count pill out of
the DOM by `data-key`, and checks each against the run: a leaf or open row
against `fit.counts[key]`, a collapsed row against the sum of its branch's
counts, which is what the row means. The folder the draft makes is asserted by
name, because it is the one with no live count to fall back on. Then the
abstention lines: the rendered lines equal `fit.preview` word for word, and
each line's digits equal `fit.unfiled.floor`, `.margin`, `.gated` — with the
negative case asserted too, that no line claims a reason that did not happen.

No fifth string was written.

## Gates

| gate | result |
|---|---|
| `node tools/verify.mjs filing-trail` | **81/81** (47 before) |
| `node tools/verify.mjs copy journey guide` | 52/52, 62/62, 30/30 |
| `node tools/verify.mjs tree` (Playground) | 21/21 |
| `npx playwright test … folders.spec` | **6 passed, 1 skipped** (the walk) |
| `npx playwright test … modes.spec shell.spec shots.spec` | **30 passed, 2 skipped, 0 failed** |

The browser gate ran in two halves on the same build — nothing changed between
them — because the session ended mid-run and killed the first one at its
seventh test.

**`modes.spec:506` passed this time, in grid and in list.** That is the
`ROW_ALARM` canary that has been red since Phase 1 at 337px against 300. It
was red earlier in this same session, in the run that had `folders.spec` ahead
of it, and green in the run that did not — so the row height it measures
depends on what ran before it, and the alarm is order-dependent rather than
fixed. Nothing in this phase touches the media list's columns. Worth knowing
before anyone reads a future green as a repair.

## The box, as it was left

31 folders, which is what it had at the start. Nathan's own session — 14 turns
and the 4 September draft with the model's fabricated counts in it — is back;
the specs restore it and it was not corrected.

**Eight fixture pictures were removed, four of them not this session's.**
`zz trail ok/floor/gated/margin`, dated **2026-09-08 09:00**, were sitting in
the library among Nathan's real pictures — debris from an earlier run of
`tests/tree/filing-trail.php` that stopped before its teardown.
`tools/box-why-clean.php` now sweeps both prefixes and was run; the box reports
`0 left behind` and 31 folders.

A throwaway administrator was made with `tools/box-ui-user.sh` and deleted at
the end.

`wp-content/mu-plugins/mu-fit-cold.php` is installed on the box and is inert
unless a request carries `vgml_fit_cold`. `folders.spec.mjs` needs it; without
it that one test fails with *"is tests/perf/mu-fit-cold.php installed?"*.

## Found, not done

- **The grid modal has no "why is it here"** — above, and Nathan's call.
- **The near-miss folder has no column.** `vergeml_filing_pick()` computes
  `nearest` and the trail drops it, so "too close to call" can name only one
  of the two folders. Schema, so Phase 1's, and worth one column.
- **A batch a person approved has no date on an abstention.** Needs a string.
- **`css/eml-admin-media.css` and its RTL twin are not generated, and nothing
  checks them.** `tools/rtl.mjs` carries eight sheets and this pair is not
  among them, so `--check` says "up to date" while the RTL sheet is missing
  whatever was added to the source. The facts list was mirrored by hand here.
  Any rule added to that sheet in future has the same trap waiting.
- **`vergeml_talk_undo()` does not mark rows undone.** The reader works around
  it by checking the picture's current folders. On the box this shows as batch
  18: **109 rows, `undone = 0`, every one with an empty `why`**, from a Move
  the Phase 2 handoff records as undone by hand. All four insert callers pass a
  reason today, so nothing now writes an empty one — but those rows are dated
  8 September 15:56 and the columns shipped before that. Unexplained.
- **The in-flight Move label.** With no count, `state.movingGoal` is null and
  "Moving %1$s of %2$s" reads *"Moving 12 of 12"* while the run is going. Not
  the draft state Task 1 names, and it needs a string.
- **`tests/tree/filing-baseline.txt` is still drifted.** Untouched, as
  instructed. Phase 5's last gate depends on it.
- **`ROW_ALARM` is order-dependent, which nobody knew.** `modes.spec:506` was
  337px against 300 in the run with `folders.spec` ahead of it and **green** in
  the run without — same build, same box, an hour apart. Phases 1 and 2 both
  recorded it as a standing failure over other plugins' columns (`fb_folder`,
  `fb_filesize`, `qode-optimizer`, `seopress_alt_text`, `aioseo-details`) and
  left it alone; it now looks like a spec that measures a screen an earlier
  spec left in a different state. Still not ours, but "not ours" was the wrong
  reason to stop looking.

## Cost

**Nothing in this phase reached a model.** No describe pass, no guide turn, no
eval. The dry run spends no credits; `GUIDE_WALK=1` was never set.

## Pushed

All three phases are on `origin/main` as of 2026-09-09 — six commits for this
phase, rebased onto the nightly watch's two and pushed clean. `main` is 0
behind, 0 ahead. Phases 1 and 2 went up with them; they had existed only on
this machine since 8 September.
