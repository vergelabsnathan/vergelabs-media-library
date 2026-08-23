---
name: plan-feature
description: Plan stage of R-PIV. Freeze the /prime conversation into a reviewable plan file that a fresh session can execute with no other context. Use once the interview has stopped changing the build.
---

# Plan feature

Writes `plans/<slug>.md`. Writes nothing else. **No code.**

## The plan is a handoff document

Assume the session that executes it has never seen this conversation, has not read the ticket,
and cannot ask you anything. Everything it needs is in the file or in a path the file names.
Anything you leave implicit will be assumed wrongly.

Assume equally that it is read by a human who will approve or reject it — reviewing a plan is
far cheaper than reviewing the code that came from one, and that is the point of the gate.

## Structure

```markdown
# <Title>

## Problem
What is wrong or missing today. Concrete, from the code, not restated from the ticket.

## User story
As a <who>, I want <what>, so that <why>.

## Decisions taken
Every answer from the interview that constrains the build, one line each.
This is the most valuable section — it is the assumption list, made explicit.

## Out of scope
What this deliberately does not do. Prevents the executing session from helpfully expanding.

## Context
- Files to read first: <paths, with why>
- Files that change: <paths>
- Files created: <paths>
- Prior art in this repo: <what already does something similar>
- External docs: <urls>

## Tasks
Ordered. Each one small enough to verify on its own. Each names the file it touches.
Mark any task that cannot be undone.

## Validation strategy
Chosen now, not after. Which gates in .claude/skills/validate apply, plus any new test this
work needs and where it goes. State the query-count budget for any new endpoint.

## Risks
What could break that is not obviously connected. For this repo: safe-mode load order,
saved options on existing installs, PHP 7.4 syntax, core media views.
```

## Rules

- Long is fine; vague is not. A 600-line plan that names every file beats a 100-line summary.
- Never write a task you have not confirmed is possible in this codebase.
- If the interview left a question open, the plan says so under **Decisions taken** as
  "OPEN: <question>" — never paper over it with a guess.
- Finish by telling the user to review it and then **start a new session** to run `/execute`.
  Say plainly that continuing in this session would execute from a context already full of
  exploration, which is what the split exists to prevent.
