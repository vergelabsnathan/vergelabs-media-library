# The list view: why it is unusable, measured

Written 2026-09-06 after Nathan sent a screenshot of the media library in list
mode — 1920 × 28,800 — and said the surface is not right in any way, and that
opening the left pane clips the list.

Nothing was changed. This is the diagnosis the harness asks for before a
redesign: what is wrong, in numbers, from the box. The tool is
`tools/look-list.mjs`, read-only apart from pressing the panel's own collapse
control. Shots in `test-results/look-list-*.png`, numbers in
`test-results/look-list.json`.

Measured at 1600 × 900 on the box, `upload.php?mode=list`, 645 files.

## What is on the screen

| | table width | columns | File column | median row | page |
|---|---|---|---|---|---|
| as it arrives (panel collapsed) | 1019px | 13 | 42px | **1,545px** | 32,973px |
| panel open | 763px | 13 | 29px | **1,960px** | 41,587px |
| same width, four columns | 763px | 4 | 311px | **36px** | 2,199px |

A media row should be about 70px. It is 1,545px before anything is touched.

## The cause is the column count, not the width

The table is `.widefat.fixed`, so `table-layout: fixed` divides the width
evenly among the columns it is given. Thirteen columns into 763px leaves the
File column — which carries the thumbnail, the filename and the row actions —
**29 pixels**. A filename then wraps at roughly one character per line, and the
row grows to two thousand pixels. Every row. Forty thousand pixels of table.

The last row of the table above is the proof: the same 763px, the same rows,
with four columns instead of thirteen gives a **36px row and a 2,199px page**.
Nothing about the width had to change.

The thirteen, with the width each is given when the panel is open:

| column | px | whose |
|---|---|---|
| Select All | 34 | core |
| **File** | **29** | core — the one that matters |
| Author | 76 | core |
| Media Categories | 29 | **ours** |
| Colour | 29 | **ours** |
| Used on | 29 | **ours** |
| Date | 107 | core |
| FileBird Folder | 96 | FileBird Pro |
| Alt text | 29 | **ours** |
| File Size | 76 | FileBird Pro |
| Qode Optimizer | 29 | Qode |
| Alt text | 29 | SEOPress |
| AIOSEO Details | 173 | AIOSEO |

Four are ours. Six belong to other plugins. We add ours on top of whatever is
already there and nothing anywhere asks whether the result still fits.

This is the question the Phase 7 handoff parked as "which of our list columns
earn a place by default". It is not a preference any more; it is the screen
being unusable on a real site.

## Where the width goes

At 1600px, before the table gets anything:

```
1600  the window
-160  WordPress's admin menu
-319  FileBird Pro's own folder pane   #filebird-root, sticky, outside #wpbody-content
-316  our gutter                       .wrap { padding-left: 316px }, holding .vgml-tree absolute at 300px
= 763  for a thirteen-column table
```

Our gutter is unconditional. `.vgml-tree` is `position: absolute` inside
`.wrap`, and the wrap is padded to make room for it. That padding does not know
FileBird already took 319px, and it does not yield when what is left cannot
hold a table. Nothing sets a floor: the table's `overflow-x` computes to
`hidden`, so there is no scroll either — the columns just crush.

Two folder trees side by side is a state we already know about and warn about
on the same screen ("FileBird Pro is also active and also puts folders on the
media library"). The warning is right. The layout still lets both of them take
their full width at the same time.

## Three defects behind the one Nathan pressed

**D1 — the collapsed rail is a clipped fragment, not a collapsed panel.**
At 44px it still paints tree content: a folder pill and text sliced mid-word,
visible in `look-list-1-arrives.png` at x≈505. And it sits 38px from FileBird's
own collapse chevron, so the screen offers two unlabelled ‹ › controls side by
side and neither says which pane it folds.

**D2 — the fold is remembered inverted.** The screen arrives collapsed; two
presses leave it collapsed; a reload brings it back **open**. Measured:

```
as it arrives                is-collapsed   table 1019
after one press              (open)         table  763
after a second press         is-collapsed   table 1019
after a reload               (open)         table  763   ← should have been collapsed
```

So the preference that is saved is not the state that is on screen.

**D3 — 883px of notices sit above the table**, eight of them, one ours. The
table starts 1,291px down the page. Only one is in our gift, but the screen we
ship is the screen after everyone else has had their say, and a list that
begins below the fold on a 900px viewport is what a customer sees.

## What this does not need

It does not need the list rebuilt. Four columns at the width it already has is
a correct, quiet list — that measurement is the whole finding. What it needs is
a rule about how many columns our plugin is willing to put on a screen it does
not own, and a panel that either takes its width or gives it back cleanly.

## The decision that is Nathan's

**Which of our four columns earn a place by default: Media Categories, Colour,
Used on, Alt text.** Every one of them is available in Screen Options either
way; the question is only what a customer sees on the first load, on a site
that already has five columns from other plugins.

Nothing is designed until that is answered, and the answer changes the shape of
the fix rather than a number in it.

## Next, if the diagnosis is accepted

1. A spec and a static mock of the list at 1600, 1280 and 1024, with the box's
   own rows, for approval — not code.
2. Then a ticket and a plan with the gates, including a suite that asserts a
   row is under 100px on a site with other plugins' columns present, and a
   mutation check that removing the rule turns it red.

`tests/ui/modes.spec.mjs` walks this screen today and passed throughout,
because it asserts what is on the screen and never how tall a row is.
