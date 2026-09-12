# Spec — every way in to a folder structure

Asked by Nathan on 2026-09-10, while retesting on a fresh 1000-picture library.
Four things, on the Folders screen:

1. the conversation grows without end and takes the page with it;
2. a proposed structure reads as bullets, not as folders;
3. there is no way to bring a structure you already have;
4. nothing on the screen says what each way of building one *is*.

## What is wrong today, measured

**The conversation has no bottom.** `css/vergeml-talk.css`: `.vgml-conv` is a
flex column with a gap and a margin — no height, no overflow. Every turn makes
the page taller, and the composer and the Move button walk down with it. At the
25-turn cap the button that does the work is several screens below the fold.

**The page scrolls because nobody decided it should not.** Every screen in this
plugin sits in WordPress's own document flow, which is the safe default and the
reason nothing has broken. It is not a constraint: an app shell is
`overflow:hidden` on the wrap, a height off the viewport minus the admin bar,
and panes that scroll on their own.

**A draft is a list of sentences.** The assistant's proposal renders as
`vgml-facts` bullets — the right grammar for *facts about a picture*, the wrong
one for *a structure with shape*. Folders nest; bullets do not.

**There is one way in.** Talking. Somebody who already has a taxonomy — in a
spreadsheet, a content plan, another DAM, a wiki page — has to read it aloud to
a chatbot.

## Decisions taken, 2026-09-10 (Nathan)

- **The Folders screen becomes an app shell.** Fixed to the viewport, three
  regions scrolling on their own, the composer and the Move button always on
  screen. A short window or a phone falls back to normal page scrolling.
- **File import accepts text-shaped files only in v1**: CSV, TXT, Markdown,
  JSON, XLSX. Parsed locally: free, instant, deterministic. **PDF and Word are
  out of v1** — reading those is interpretation, not parsing; it needs a model
  pass, costs credits, and can be confidently wrong. It is a later phase and it
  is a different promise.
- ~~**Pasted text may be written any of three ways**, detected rather than
  declared: `Parent > Child`, `Parent/Child`, and indentation.~~ **Revised
  2026-09-12 (Nathan): one way, and not indentation.** See below.
- **Every route in produces a draft, never folders.** The same draft the
  conversation builds, over the same tree, behind the same Move button with the
  same undo. One grammar for every way in.

## Decision, 2026-09-12 (Nathan) — one way in, and it is paths

Seen the four-way mock: "I want only one way, and not the indentation — it
doesn't work." The manual way in is **one text box, one folder per line, the
full path with `>` between levels**:

```
Hardware
Hardware > Phones
Hardware > Components > Chips
Data centres > Cooling
```

Why paths and nothing else:

- **It survives pasting.** Indentation dies in email, Slack, a spreadsheet
  cell or a phone — tabs become spaces, spaces get trimmed. A `>` on the
  line is still there.
- **Every line is complete on its own.** Order does not matter, lines from
  two places can be pasted together, and a line whose parent is not listed
  creates it. With indentation one wrong line shifts everything under it.
- **`>` already reads as "inside"** — it is the breadcrumb every site uses.
  `/` would also work but collides with real names ("Black/White", "AC/DC")
  and reads as a file path.
- **The preview stays honest:** "18 folders, 3 levels deep" is counted from
  the paths, not guessed from whitespace.

The cost is retyping the parent on each line for a deep tree. That is the
right trade against silent mis-nesting.

Gone with this decision: the **Rules** entry, the **Upload a file** entry,
indentation and `/` as spellings, and the "detected rather than declared"
rule. The conversation stays — it is the product; paste is the one manual
way beside it.

## The contract

### One draft, two ways to fill it

The method switch has two entries. Each carries **one or two sentences saying
what it is and what it will do** — the screen's own words, not a tooltip:

| method | what the screen says |
|---|---|
| **Conversation** | Describe how you want the library organised and the assistant proposes a structure. Best when you are not sure yet. |
| **Paste folders** | One folder per line, the full path with `>` between levels: `Hardware > Phones`. A parent that is not listed is created. The preview shows what was understood before anything is made. |

### A structure looks like a structure

The draft stops being a bullet list. Every folder is a **row in the tree-view
component the screen already uses** (`css/vergeml-tree-view.css`): a chevron
where there are children, the name, and its count. Children collapse and expand.
A folder that is new in this draft carries the same `is-new` mark the tree
already draws; one that is being renamed carries `is-change`.

**No new visual grammar is invented.** The rows are the tree's rows, the counts
are the tree's counts, the pills are `vgml-rule-pick`'s geometry, and the ground,
ink, accent and divider are the shell's tokens.

### Paste, and see it land

A textarea, and beside it a live tree of what was understood — updated as it is
typed, before anything is committed. Under it, one line of plain fact:
*"18 folders, 3 levels deep. None of them exist yet."* — or *"… 2 already
exist and will be reused."*

**The reading, and there is only one.** A line is split on `>`; each segment is
trimmed; empty segments are dropped; the segments are the path from the top.
Leading whitespace is ignored, never read as depth. Every folder on a path is
created if the tree does not have it, so `Hardware > Components > Chips` alone
makes three. The same path twice is one folder. A name is matched to an existing
folder case-insensitively at that exact place in the tree, and reused.

**What is refused, and said out loud**: an empty name, a line deeper than five
levels, more than 500 folders in one paste. A line with no `>` is a top-level
folder, not an error.

## Out of scope

- File upload of any kind, and PDF and Word in particular (2026-09-12).
- Rules as a way in (2026-09-12).
- Any change to what the matcher decides or how pictures are filed.
- The other eight screens' layout. The app shell is this screen only in this
  work; doing all nine is its own phase.
- Importing pictures, or moving anything. This builds a structure; the Move
  button still moves.

## How it is proven

- The conversation thread scrolls inside its own region with the composer and
  the Move button on screen at the 25-turn cap — asserted at 1600×1000 and at
  1280×800.
- A paste of paths in any order, with a repeated path and a missing parent,
  produces the one tree the paths describe; an indented paste is read as
  top-level lines, never as depth.
- A paste of 501 folders is refused, and says why.
- The existing filing baseline is unchanged: this work decides nothing about
  where a picture goes.
