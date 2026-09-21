# S30 — Wave 5: the decision sitting, all seven answered

**Date:** 2026-09-21, afternoon. **Model:** Opus 5 (the S29 session,
continued). **From:** `docs/handoffs/2026-09-21-s29b-wave-4-the-walk-on-4-0-2.md`.
**Plan:** `plans/finish-the-suite.md` — "Wave 5 — held". **The document:**
`docs/decisions-2026-09.md`. Plugin `95aa586` → `2dbd823` (+ this handoff's
commit); service `origin/main` `fc940b9` → `299adc1` (serving). **Spent:
nothing.** Nothing on ms2 or the real shop.

## What Nathan said

"go ahead then" → the sitting document written from a sub-agent's read of
the repos; "okay well normal language" → the seven rows restated plainly;
then one message: "2 support@ 3 yes i agree 4 yeah fine 5 santa cruz 6 uk
vat or us according to the rules which is i am a spanish entity for now 7
okay". Row 1 came after: "yes and let users know about it".

## Answers and where they landed

| Row | Answer | Landed |
|---|---|---|
| 1 · 3.1 outage | yes, and tell the users (answered after the sitting) | readme.txt FAQ "What happens when the AI service is down?"; docs credits page, service 299adc1, live; 3.1 → done |
| 2 · 3.3 response time | one business day stays; `support@vergelabsmedia.com` | `readme.txt` Support (the ticket mail's sentence, verbatim); site support heading, service `0317178`, live (0 old mentions); 3.3 → `done` |
| 3 · 4.3 fill | client-grade, pause at 500 / first 100 | `epics.md` story 4.3; a mock next |
| 4 · PRIVATE0 | off | `deferred-work.md` row decided; the clicks (Switch off; `…AB26` cancel) are Nathan's, not yet confirmed |
| 5 · addresses | Santa Cruz | `vat.md` item 5 (`1f92502`); Stripe's business profile is Nathan's field |
| 6 · UK VAT | per the rules for a Spanish entity; the accountant states them; no hold | `vat.md` item 6; the recommended checkout hold not taken |
| 7 · retros | recorded, headless | wave 6; 2.1's code review first |

Also: the Playground CLI pinned in one place, `tools/lib/playground.mjs`
(`@wp-playground/cli@3.1.54`, `VGML_PLAYGROUND_CLI` overrides); six call
sites read it; `csv-local` **69/69** on the pin (`3f590ea`).

## On record

- `readme.txt` changed after the 4.0.2 cut (one Support sentence) — 4.0.3
  material; the served zip is untouched.
- Nathan's unpushed pricing commit carries a "more than 25 sites" link
  still on `support@in.vergelabs.nl` — his branch, `efc9143`.
- `security@in.vergelabs.nl` on the site is left as is (a different box).
- Service work again went through a worktree at `origin/main` pushed as
  `main`; the checkout was rebased after (`--autostash`), pricing commits
  now `dc0a692`, `eee31ea`, still unpushed.

## Open, and Nathan's

- The clicks: PRIVATE0 off, `…AB26` cancelled, Stripe business profile to
  Calle Fernando Fuentes 2.
- The accountant's answer on UK (and US) treatment → `vat.md` item 6.
- The wordpress.org form with `dist/vergelabs-media-library-4.0.2.zip`.

## Next — S31, wave 6

```
Read docs/handoffs/2026-09-21-s30-the-decision-sitting.md, then
docs/decisions-2026-09.md and plans/finish-the-suite.md ("Waves", wave 6).
State which model you are and follow that profile in
~/.claude/harness/model-profiles.md. This session: bmad-code-review on story 2.1, then
bmad-retrospective -H for epics 3 and 4; sprint-status rows to done. Stop
points: nothing on ms2 or the real shop; every user-facing string is mine;
say what goes out before any push. End with a handoff in docs/handoffs/.
```
