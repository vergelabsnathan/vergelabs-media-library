# Plan — what every picture rests on

Spec: `docs/superpowers/specs/2026-09-08-traces.md`
Diagnosis: `docs/superpowers/specs/2026-09-08-traces-diagnosis.md`
Ticket: `tickets/2026-09-08-traces.md`

Five phases. One phase per fresh session, the gates after each, a handoff at
the end of each, no compaction. The model per phase is named because a phase
sized for one and run on the other fails predictably —
`~/.claude/harness/model-profiles.md`.

**The binding constraint, on every phase:** nothing here changes what the
plugin decides or does. The describe pipeline, the scheduler, auto-file, the
watch, the tree and the Move button behave exactly as they do today. A phase
that changes a behaviour has gone wrong, and the gates below are written to
catch that.

## Stop points — Nathan's, before the phase that needs them

- **The mock on Phase 3 was lifted by Nathan on 2026-09-08.** Build the two
  surfaces from the Folders screen's existing components -- its cards, its
  counts, its type -- and invent no new visual grammar. Screenshot them into
  the conversation before the phase is called done.
- **The prompt rule's wording** is settled in the spec; a change to it is
  Nathan's.
- **Whether the "share of drafts changed before filing" number is shown to the
  owner** or only recorded. Recording it is Phase 4; showing it is a surface
  and needs the mock.

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

## Phase 3 · What a person sees — Opus, and only after the mock

The two surfaces. Blocked on the stop point above.

**Files.** `js/vergeml-folders.js`, `core/guide.php`, and whichever screen the
mock puts "why is it here" on.

**Proof.** `tests/ui/folders.spec.mjs` extended: the draft's counts equal the
dry run's, and the abstention line equals the number of abstentions. A
screenshot into the conversation before the phase is called done.

## Phase 4 · Who approved it — Sonnet

Small, mechanical, one table.

**Files.** `core/librarian.php`, and the two places a batch is created.

**Behaviour.** `vergeml_librarian_batches` gains `user_id` and `approved_at`;
the Move and the undo record their actor.

**Proof.** `tests/tree/filing-trail.php` extended: a filed batch names the
person and the moment; an undo names its own.

**Do not.** Do not show the number yet — that is a surface and needs the mock.

## Phase 5 · The failure states — Sonnet

Already approved by Nathan on 2026-09-07 and carried here so it is not lost.

**Files.** `js/vergeml-folders.js:599`, `js/vergeml-gallery-block.js:66`.

**Behaviour.** A failed load says so through `talk.note()`; the scope choice is
disabled while the number is unknown; no fabricated zeros.

**Proof.** `tests/ui/folders.spec.mjs`: with the endpoint refused, the screen
shows the failure and the scope radios are unavailable — and the word "0
unfiled" is nowhere on the screen.

## The gates, after every phase

```
npx playwright test --config tests/ui/playwright.config.mjs modes.spec shell.spec shots.spec folders.spec
node tools/verify.mjs copy journey guide
node tests/tree/t0-endpoints.js
```

And per phase, the suite named in it.

**The mutation check, once, in Phase 1.** Make `vergeml_filing_pick()` return a
`why` that does not match what it decided, and `tests/tree/filing-trail.php`
goes red. A trail nobody can prove wrong is not a trail.

**The regression gate that matters most.** Before Phase 1 and after Phase 5,
run the same filing pass on the box over the same pictures and compare the
placements. **They must be identical.** This work records reasoning; it does
not change a single decision, and that is the assertion that proves it.

## Cost

Nothing in Phases 1, 3, 4 or 5 reaches a model. Phase 2 spends one guide turn
per walk. No describe pass is needed at any point; the index on the box is
already described.

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

### Phases 2 to 5

Same shape, one phase named, the phase's own files, behaviour, proof and "do
not" copied from above. Do not write a new opener from memory: the phase in
this plan is the brief.
