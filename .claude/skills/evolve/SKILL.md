---
name: evolve
description: Turn a miss into a change to the AI layer itself — a rule, a skill step, or a validation gate. Use after anything went wrong that a better rule would have prevented. Proposes, never applies.
---

# Evolve

Every failure is data about the layer, not just about the code.

## Steps

1. **Name the miss.** What actually went wrong, in one sentence.
2. **Find where it should have been caught.** Exactly one of:
   - `CLAUDE.md` — a constraint that was not written down
   - a skill in `.claude/skills/` — a step that was missing or wrong
   - `validate` — a gate that should have failed and did not
3. **Write the smallest diff that would have prevented it.** Show it. Do not apply it.

## Rules

- **One artifact per evolve.** If it seems to need three, the miss is not understood yet.
- **Propose only.** The user approves every layer edit. Agents over-document: they write two
  hundred lines where twenty belong, and a bloated rules file is one that gets followed less,
  not more.
- **Every rule must trace to something that actually happened.** No speculative guidance.
- Prefer deleting or tightening an existing line over adding a new one.
- If the honest answer is "no rule would have prevented this", say that. Not every failure is
  a systems failure, and padding the layer to look responsive makes it worse.

## Then

Commit the approved change on its own, so the layer's history is readable in git:
`chore(ai-layer): <what changed and why>`
