---
name: prime
description: Research stage of R-PIV. Load a ticket, explore the code that it touches, then interview the user to strip out assumptions before any plan is written. Use at the start of any non-trivial piece of work.
---

# Prime

Argument: a ticket file (`tickets/*.md`), an issue number, or a plain description.

**This skill does not write code and does not write a plan.** It ends in a conversation.

## 1. Read the work

Load the ticket. If it references a doc in `docs/`, read that too. If it is a plain description,
restate it in one paragraph and ask the user to confirm you have the right thing before spending
tokens exploring.

## 2. Explore the code it touches

Find and read the actual files — not a guess at which files. For this repo that usually means:

- `vergelabs-media-library.php` for registration, options, and the safe-mode load order
- `core/` for the feature itself
- `js/vergeml-media-views.js` and `js/eml-media-grid.js` for anything touching the media library UI
- `tests/` for what is already proven and what harness exists

Report back: how you would approach it, which files change, which existing behaviour is at risk,
and what the repo already does that you should follow rather than reinvent.

## 3. The interview — the part that matters

> Your number one job is to reduce the number of assumptions you are making.

Generate **every** question whose answer would change what you build. Do not pre-filter to the
ones that seem important; a question that feels minor is exactly where a silent assumption hides.
Expect 15–30 for a real feature.

Then ask them with `AskUserQuestion`, in batches, as multiple choice with a recommendation first
and a one-line reason for the recommendation. The user should be able to answer most by picking
the recommendation. Order the batches so that answers which invalidate other questions come first.

Cover at minimum:

- **Behaviour at the edges** — empty states, one item, thousands of items, deleted parents
- **What happens to existing installs** — saved options, existing terms, sites mid-upgrade
- **UI semantics** — what a click/drag means, what is reversible, what needs confirming
- **Scope boundaries** — what is explicitly *not* in this piece of work
- **How we will know it worked** — the validation strategy, chosen now rather than after

Stop when the remaining questions no longer change the build. Say so explicitly.

## 4. Hand off

End with: "Ready to plan. Run `/plan-feature`." Do not roll straight into planning — the user
decides when the interview is done.
