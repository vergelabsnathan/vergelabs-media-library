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
- **Pasted text may be written any of three ways**, detected rather than
  declared: `Parent > Child`, `Parent/Child`, and indentation. A live tree
  preview shows what was understood, and the preview is what makes it safe.
- **Every route in produces a draft, never folders.** The same draft the
  conversation builds, over the same tree, behind the same Move button with the
  same undo. One grammar for every way in.

## The contract

### One draft, four ways to fill it

The method switch gains two entries. Each one carries **one or two sentences
saying what it is and what it will do** — the screen's own words, not a
tooltip:

| method | what the screen says |
|---|---|
| **Conversation** | Describe how you want the library organised and the assistant proposes a structure. Best when you are not sure yet. |
| **Rules** | Build folders from what the pictures already are — their kind, their subject, who they are for. No conversation, no model. |
| **Paste a list** | Paste the folders you already have, one per line. Indentation, `Parent > Child` and `Parent/Child` all work. |
| **Upload a file** | Bring a structure from a spreadsheet or a document: CSV, TXT, Markdown, JSON or XLSX. |

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
*"14 folders, 3 levels deep. 2 already exist and will be reused."*

**The detection, in order.** A line's indentation (tab or two spaces) sets its
depth if any line is indented; otherwise a `>` or `/` in the line splits it into
a path; otherwise the file is flat. Mixed spellings in one paste are read by
whichever is most common, and the preview shows the result either way.

**What is refused, and said out loud**: an empty name, a line deeper than five
levels, more than 500 folders in one paste, and a name a folder already has at
that exact place in the tree (reused, not duplicated).

### Upload, same destination

Drop a file or choose one. CSV with one path per line, or a column per level, or
a `name,parent` pair — sniffed, not configured. XLSX reads the first sheet. JSON
takes an array of paths or a nested object. Markdown and TXT are read as an
indented list.

The file's name and row count are shown, then the same preview as paste, then
the same draft. **A file never becomes folders on its own.**

## Out of scope

- PDF and Word.
- Any change to what the matcher decides or how pictures are filed.
- The other eight screens' layout. The app shell is this screen only in this
  work; doing all nine is its own phase.
- Importing pictures, or moving anything. This builds a structure; the Move
  button still moves.

## How it is proven

- The conversation thread scrolls inside its own region with the composer and
  the Move button on screen at the 25-turn cap — asserted at 1600×1000 and at
  1280×800.
- A paste in each of the three spellings produces the same tree.
- A paste of 501 folders is refused, and says why.
- A file of each accepted type produces a draft, and produces no folders.
- The existing filing baseline is unchanged: this work decides nothing about
  where a picture goes.
