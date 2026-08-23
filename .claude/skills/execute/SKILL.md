---
name: execute
description: Implement stage of R-PIV. Take a plan file and build it in a clean session, then run the full validation gate and iterate until green. Use only in a fresh session, never in the one that wrote the plan.
---

# Execute

Argument: a path to `plans/<slug>.md`.

## Before writing anything

1. Read the plan in full.
2. Read every file it lists under **Context — files to read first**.
3. **Validate the plan against the code.** It was written from an earlier state; the repo may
   have moved. If a task no longer makes sense, say so and stop — do not improvise around it.
4. If the plan contains an `OPEN:` line, stop and ask. That marker exists precisely so it is
   not guessed at.

## Building

Work the task list in order. After each task, state what you did in one line.

Follow the repo's own idiom rather than your defaults — match the surrounding comment density,
naming and structure. New feature files load inside the safe-mode guard.

Do not expand scope. If you find something else broken, note it for `/evolve` and keep going.

## Finishing

Run `/validate` — the **whole** gate, not the parts you think are relevant. Read the failures,
fix them, run it again. Repeat until green.

> Optimise for correctness, not time.

A gate that fails twice the same way means the plan or the approach is wrong, not that the fix
needs another attempt. Stop and say so.

## Report

- What was built, against the plan's task list
- The validation table, with real output
- Anything the plan got wrong (this is `/evolve` input)
- What still needs a human: the manual pass, and anything you could not verify
