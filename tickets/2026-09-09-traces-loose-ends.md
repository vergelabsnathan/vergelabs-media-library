# INITIAL — What Phase 3 found and did not fix

Asked by Nathan on 2026-09-09, after Phase 3 of `plans/traces.md` shipped:
address everything the three phases turned up, leave nothing on the table, and
run it through the harness rather than around it — ticket, plan, execute,
validate, one phase per session.

This is the companion to `tickets/2026-09-08-traces.md`. That one is the
argument; this one is the list of things the build itself uncovered. The
handoff with the evidence for each is
`docs/handoffs/2026-09-09-traces-phase-3-handoff.md`.

## FEATURE

**A trail that is true about undo.** `vergeml_talk_undo()` puts the terms back
and leaves `undone = 0` on every row it reversed — only the librarian's own
undo (`core/librarian.php:2177`) marks them. So the moves table asserts moves
that no longer hold. Phase 3's reader works around it by only trusting a row
whose folder the picture is still in, which is a workaround in the reader for a
lie in the table. Phase 4 is about to write *who approved* onto those rows, and
an approver on a row that misreports whether it was undone is a record that is
more confidently wrong than the one we started with.

**The folder the matcher nearly chose.** `vergeml_filing_pick()` returns
`nearest` — the folder that scored best and was refused — and the trail has no
column for it. So the line an owner reads for a refusal, *"scored 1.00 against
0.93 for zzShotC, too close to call"*, can name only the runner-up. The
sentence is about two folders and the record holds one of them.

**"Why is it here", where people actually look.** The field renders on the
attachment's own screen. It does not render in the media grid's modal, because
`attachment_fields_to_edit` is also what `wp_prepare_attachment_for_js()` runs
for every item of a `query-attachments` listing — measured at 5.7 queries a
picture, some 450 on an eighty-item page — so Phase 3 guards it off that path.
A grid-mode site therefore never sees it. The answer is the read-only route the
spec already allows plus a media view that asks for one picture on demand.

**Three numbers that move and nobody can say why.**

- `tests/tree/filing-baseline.txt` no longer matches the box: 108 of 641 rows
  differ, 107 by ~0.0001 in the runner-up's score alone, and picture 2817 moved
  from `ok` to `margin` with its score down exactly 0.030000. Phase 2 ruled out
  its own changes, the service, the folder profiles and the descriptions, each
  by measurement.
- `vergeml_librarian_moves` batch 18: **109 rows, `undone = 0`, every one with
  an empty `why`**, dated 8 September 15:56 — after the columns shipped, and
  from a Move the Phase 2 handoff records as undone by hand. All four insert
  callers pass a reason today, so nothing now writes an empty one.
- `modes.spec:506` measured 337px against a 300px alarm in the run that had
  `folders.spec` ahead of it and passed in the run that did not — same build,
  same box, an hour apart. Phases 1 and 2 both recorded it as a standing
  failure over other plugins' columns and left it there.

**Two sheets pairs kept by hand that nothing checked.** `node tools/rtl.mjs
--check` reported "up to date" while `css/eml-admin-media-rtl.css` was missing
a block just added to its source, because that pair is not in the tool's list.
`css/eml-admin.css` and `css/vergeml-tree.css` are in the same position. The
tool now names all three on every run; whether they should be generated instead
is open.

**Two states that need a string.** An abstention carries no date or batch,
because it was not filed and "Filed" would be untrue. And with no count worked
out, the in-flight Move reads *"Moving 12 of 12"*, a total nobody has.

## WHY

The first three are the same failure the parent ticket is about, found inside
our own fix: a record that is written but not quite true, a value computed and
dropped, and an answer that exists but not where the question is asked. We are
holding the product to a white paper about exactly this.

The three moving numbers matter more than they look. Phase 5's last gate is
*"run the same filing pass over the same pictures and the placements are
identical"* — the assertion that proves this whole plan changed no decision.
That gate rests on a baseline that has drifted for reasons nobody has found. A
gate whose input is unexplained does not prove anything, and a suite that is
red or green depending on what ran before it teaches everyone to stop reading
it. Two phases have already written `ROW_ALARM` off as "not ours"; that was
true and it was the wrong reason to stop looking.

## OUT OF SCOPE

- Any change to what the matcher decides. Unchanged from the parent ticket:
  the floor stays 0.55, the margin 0.08, the gates as they are.
- **Re-taking `tests/tree/filing-baseline.txt`.** It has drifted for a reason
  nobody has found, and re-baselining destroys the only evidence of it. It is
  re-taken when the drift is explained, not before.
- Backfilling `why` on the 109 rows. Explaining them is in scope; inventing
  reasons for them is not.
- Converting the three hand-kept RTL sheets. Naming them is done; generating
  them would rewrite rules nobody has reviewed and is its own decision.

## OTHER CONSIDERATIONS

- **Phase 4 is no longer a Sonnet phase.** `plans/traces.md` assigned it Sonnet
  when it was two nullable columns. With the undo fix and the `nearest` column
  it is a schema change with a behaviour change beside it, and
  `~/.claude/harness/model-profiles.md` puts schemas outside what Sonnet is
  given. It is Opus.
- **The investigation comes before Phase 4**, not after. Two of the three
  unexplained things live in the table Phase 4 is about to alter, and finding
  out why 109 rows are blank is cheaper before six more columns of history sit
  on top of them.
- **Nothing here blocks the listing.** Same as the parent ticket: the free
  plugin can be submitted without any of it.
- Three phases of this work are committed but unpushed, and the nightly watch
  commits to `main` around 05:17 UTC — `git pull --rebase` before pushing.
