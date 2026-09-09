# Session handover — 2026-09-09, traces Phase 5: no fabricated zero (Sonnet 5)

Phase 5 of `plans/traces.md` is built and deployed. `guide/rules` failing no
longer fills the scope radios and the rule pills with a `0` nobody computed;
the gallery block's folder picker no longer tells a failed fetch apart from a
library with no folders in it by showing the same "nothing" either way.

**The model.** This session was **Sonnet 5**, which is the model the plan
assigns Phase 5. The brief was corrected against the code on 2026-09-09 (per
the Phase 4 handoff) before this session started, so the opener's paragraph
was worked from directly rather than from the plan's original line numbers,
which had moved.

Read in this order:

1. `docs/superpowers/specs/2026-09-08-traces.md` — the contract.
2. `docs/handoffs/2026-09-09-traces-phase-4-handoff.md` — Phase 4, and the
   corrected Phase 5 brief at its end.
3. `plans/traces.md`, Phase 5 section.
4. This handoff.

---

## The three sites in `js/vergeml-folders.js`

All three trace back to one cause: `loadRules()`'s catch filled in
`{ rules: [], unfiled: 0, pictures: 0 } ` — a shape nobody computed, read back
as a real `0` wherever the rules panel asked `state.rules` a question.

1. **`loadRules()`'s catch.** Now sets `state.rules = null` (unknown, the
   same value it holds before the first response ever arrives) and says the
   failure through `talk.note()` — the same call `onMove()` already uses for
   a failed Move.
2. **`scopeRadios()`.** The unfiled-pictures sentence used
   `state.rules ? state.rules.unfiled : 0`. It now branches on whether
   `state.rules` is known: known, the original two-number sentence; unknown,
   the same sentence with the missing number dropped rather than
   fabricated — *"Move only the unfiled pictures. Today's %s folders stay."*
   Both scope radios are disabled while unknown (`radio()` gained a sixth,
   optional `disabled` parameter).
3. **`renderRules()`'s pill.** `counts[ r.id ] || 0` read as a real zero
   whenever `state.rules` had not loaded. The pill is now omitted entirely
   when `state.rules` is null — the same choice `tests/tree/tree-view.js`
   already makes for a draft count nobody worked out (`count = null` rather
   than a zero span). The **fit** card's own "0 new" / "1 new" is untouched:
   it is computed locally from `state.rule.options.rest`, not from the
   server, and is a real fact rather than the bug this replaces.

The four rule cards themselves still come from the local `RULES` constant and
render and stay pickable on a failure, as the plan requires — picking one
starts a dry run, which is a separate request that may succeed even when
`guide/rules` just failed.

**Copy**, used as proposed and unless told otherwise: *"The numbers did not
load. No rule can say what it would do — reload to try again."* — the plan's
own verbatim string.

**A wording call the plan did not spell out**, flagged here rather than
guessed silently: the scope radio's fallback sentence needed *some* text once
the number was dropped, and no second string was given for that specific
case. I mirrored the existing precedent in this same file
(`renderMove()` already carries two full strings, *"Move the draft"* against
*"Move %s pictures"*, for exactly this known/unknown split) and wrote the
minimal variant — the original approved sentence with only the missing number
removed, no new claim added. If Nathan wants different wording here, it is a
one-line change in `scopeRadios()`.

---

## The different shape in `js/vergeml-gallery-block.js`

Not a fabricated zero — `loadFolders()`'s catch returned an empty folder
*list*, the same shape a library with zero real folders returns. The block's
placeholder used one instruction (`l10n.noFolders`) for both, so a failed
fetch read as "make a folder in the media library" instead of "this could not
load."

A module-level `folderFailed` flag, set in the catch, now picks between
`l10n.noFolders` (genuinely empty) and the new `l10n.foldersFailed` (fetch
failed), registered in `core/gallery-block.php`.

**No copy was given for this string in the plan** — Phase 5's copy section
supplies the `vergeml-folders.js` line verbatim and says nothing further
about the gallery block beyond "fix it in the same pass, in the same
spirit." Rather than leave the described behaviour unbuilt for want of a
string (the plan does say to fix this one this phase), I proposed one in the
same voice as the approved line, parallel in shape: *"The folders did not
load. No folder can be picked until they do — reload to try again."* This is
the same judgment call the plan makes for the `vergeml-folders.js` string
itself — "proposed … and used unless he changes it before the session
starts" — applied to the one string the plan left open. If Nathan wants
different wording, it is the one `__()` call in
`core/gallery-block.php:107`.

No test was added for this file: it has no browser spec in this phase's gate
list, and adding one would be a new suite the phase does not name. The fix is
correct by inspection and by the identical shape of the `vergeml-folders.js`
fix; that is what can honestly be claimed for it without a suite watching it.

---

## Proof

`tests/ui/folders.spec.mjs` gained one test: *"when guide/rules is refused,
the screen says so and shows no fabricated zero."* `page.route()` fulfills
`guide/rules` with a 500. Asserted: the four cards still render and stay
pickable; picking one still turns the row on; the three rules that need the
server's count carry no pill at all (the fit card's own, unrelated "0 new" is
explicitly excluded from that sweep, by name, so it can't hide a real
regression behind a false pass); both scope radios are disabled; the scope
sentence carries no number; and the note text matches the plan's line
exactly.

```
ok  4 tests\ui\folders.spec.mjs:234:2 › the Folders screen › when guide/rules is refused,
    the screen says so and shows no fabricated zero (1.5m)
```

Screenshot: `tests/ui/shots/folders-rules-failed.png`.

---

## Gates

| gate | result |
|---|---|
| `npx playwright test … modes.spec shell.spec shots.spec folders.spec` | **37 passed, 3 skipped, 0 failed** (18.2m, 40 tests incl. the new one) |
| `node tools/verify.mjs copy journey guide` | passed — 62/62 (copy), guide, journey all green |
| `node tests/tree/t0-endpoints.js` (Playground) | **21/21** |
| `node tools/filing-baseline-check.mjs` | **3 of 3**, 641 pictures, placements identical |

The 3 skipped are the same as Phase 4's baseline (the `GUIDE_WALK=1` walk and
one `modes.spec` test not in this gate's path) — not a regression.

`modes.spec:526`'s `ROW_ALARM` canary: 231px against a ceiling of 300, same
numbers as Phase 3.5 and Phase 4 measured.

---

## The one thing that needed Nathan mid-phase

Running the browser gate meant shipping the changed JS/PHP to the box first
(`node tools/deploy.mjs --box`), and that specific command was refused by the
auto-mode classifier as a production-touching action — the same standing
rule Phase 4 hit when the classifier refused further database reads against
the box after the gate7-schema incident. I stopped and asked rather than
working around it. Nathan granted a one-off exception for this deploy; it
completed and verified (134 files re-hashed on 46.225.66.194), and the gate
ran clean afterward.

The rest of the box-touching steps — creating the throwaway administrator,
running the four specs, deleting the administrator — followed the trap list
without needing a further exception, since `ssh` and `wp eval` were already
in reach from earlier phases' sessions.

---

## The box, as it was left

- The deploy landed and verified: `26d3a54` is what 46.225.66.194 is running.
- The throwaway administrator (`vgml-phase5-1788959404`) is deleted.
- No probe scripts were added to the box's `/tmp` this phase; only `ssh`
  commands already in `tools/box-ui-user.sh` were used.
- The local Playground server (port 8899, from `tools/play.mjs`) was started
  and stopped within this session; nothing was left running.
- No test data was planted or left on the box. The new Playwright test uses
  `page.route()` to fake the failure entirely client-side — it never asks the
  real `guide/rules` endpoint to fail, so nothing on the box needed a fixture
  or a restore.

## Cost

**Nothing in this phase reached a language model.** No describe pass, no
guide turn, no eval; `GUIDE_WALK=1` was never set. The dry run and the guide
conversation are unrelated to this phase's failure states, which are pure
client-side handling of a refused REST call.

## Found, not done

- **The scope-radio fallback sentence's exact wording is a judgment call, not
  a decision from the plan** — see above. Low risk (a token removed from an
  already-approved sentence, not new copy), but it is a place Nathan may want
  to look once.
- **The gallery-block failure copy was invented this session**, for the same
  reason — see above. It is the one new full sentence in this phase that the
  plan itself did not supply.
- **The gallery block's fix has no automated proof.** It is not in this
  phase's gate list and no browser spec exercises the block editor. Correct
  by inspection and by mirroring the folders.js fix; not verified by a suite.

## Next

Phase 5 is done. **Phase 6 — the answer where the question is asked, Opus** —
is next, per `plans/traces.md`. Two strings are open before it starts (the
abstention's date/batch line, and the in-flight Move's "Moving 12 of 12"), and
a third is now open beside them from Phase 4: the "too close to call" margin
line that can now name both folders.
