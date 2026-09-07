# The list view: why it is unusable, measured

Written 2026-09-06, **corrected 2026-09-07** after Nathan sent a screenshot of
the media library in list mode — 1920 × 28,800 — and said the surface is not
right in any way, and that opening the left pane clips the list.

Nothing was changed on the screen. The tool is `tools/look-list*.mjs`,
read-only apart from pressing the panel's own collapse control and injecting
stylesheets that vanish on the next load. Numbers and shots in `test-results/`.

Measured at 1600 × 900 on the box, `upload.php?mode=list`, 645 files.

> **Correction.** The first version of this document said the cause was the
> column count, on the strength of one measurement: hiding nine of the thirteen
> columns took a row from 1,960px to 36px. Five further measurements disprove
> it. Taking *our* three extra columns away changes the row by nothing. Taking
> all four of ours away changes it by nothing. Taking every other plugin's away
> leaves 1,455px. A width floor of 1,840px still leaves 1,365px. The count was
> a symptom of the same starvation, not the cause. What follows is what the
> measurements actually support.

## What is on the screen

| | table | columns | File column | median row | page |
|---|---|---|---|---|---|
| as it arrives (panel folded) | 1019px | 13 | 42px | **1,545px** | 32,973px |
| panel open | 763px | 13 | 29px | **1,960px** | 41,587px |

A media row should be about 70px.

## What actually makes a row tall

A row is as tall as the tallest thing in it that is still in the flow. In a
column 42px wide, anything holding a sentence wraps at a character or two a
line. Measured inside one row, as it stands:

| in the row | height | what it is |
|---|---|---|
| `.row-actions` | **1,153px** | core's Edit · Delete · View · Copy URL · Download |
| `span` in our Alt text cell | 407px | **ours** — the whole alt sentence |
| `span.vgmlpro_describe` | 237px | **ours** |
| `.has-media-icon` | 214px | core's thumbnail cell |
| the filename link | 154px | core |
| `.vgml-used-quiet` | 134px | **ours** — Used on |

`.row-actions` is the biggest single item by a factor of three. Core hides it
until hover with `position: relative; left: -9999em` — **relative**, so it
stays in the flow and still takes vertical space. At a normal title width it is
one line and nobody notices. At 42px it wraps to 1,153px and sets the height of
every row on the screen.

## What each candidate rule is worth, measured

| | File column | median row | page |
|---|---|---|---|
| as it is | 42px | 1,545px | 32,973px |
| `.row-actions { white-space: nowrap }` | 42px | **601px** | 13,595px |
| + our four cells cannot wrap | 42px | 562px | 13,263px |
| + our three extra columns off | 42px | 562px | 13,263px |
| + **all four** of our columns gone | 42px | 562px | 13,263px |
| + FileBird's pane gone as well | 58px | **348px** | 8,707px |

Read the last two rows. With every column of ours removed and FileBird's pane
gone, a row is still **348px**. Nothing we can do to our own columns fixes this
screen, because the table is starved of width before our columns are counted.

Giving the File column 34% makes it worse, not better — 562px back up to
1,459px — because the width has to come from the other twelve, and then they
wrap instead.

## Where the width goes

```
1600  the window
-160  WordPress's admin menu
-319  FileBird Pro's own folder pane   #filebird-root, sticky, outside #wpbody-content
-316  our gutter                       .wrap { padding-left: 316px }, .vgml-tree absolute at 300px
= 763  for a thirteen-column table
```

At 1280 — an ordinary laptop — the same arithmetic leaves **457px**.

Our gutter is unconditional. `.vgml-tree` is `position: absolute` inside
`.wrap` and the wrap is padded to make room. That padding does not know
FileBird already took 319px, and it does not yield when what is left cannot
hold a table. There is no floor and no scroll: `overflow-x` computes to
`hidden`, so the columns simply crush.

**This is the finding.** A folder tree that permanently takes 316px from a
table it does not own is not viable in list mode, on any site that has other
plugins adding columns. Everything else is downstream of it.

## Three more, found while measuring

**D1 — the collapsed rail is a clipped fragment, not a collapsed panel.** At
44px it still paints tree content: a folder pill and text sliced mid-word,
visible in `look-list-1-arrives.png` at x≈505. It sits 38px from FileBird's own
collapse chevron, so the screen offers two unlabelled ‹ › controls side by side
and neither says which pane it folds. This is the clipping Nathan pressed.

**D2 — the fold is remembered inverted.** The screen arrives folded; two
presses leave it folded; a reload brings it back open:

```
as it arrives      is-collapsed   table 1019
one press          (open)         table  763
second press       is-collapsed   table 1019
after a reload     (open)         table  763   ← should have been folded
```

**D3 — 883px of notices sit above the table**, eight of them, one ours. The
table starts 1,291px down the page. Only one is in our gift, but a list that
begins below the fold on a 900px viewport is what a customer sees.

## What follows

Not a patch to the table's CSS — five of them were measured and the best got a
row to 348px, which is still five times too tall. The tree has to stop being a
permanent gutter in list mode, and we have to stop adding columns to a table we
do not own. That is a design change, so it goes to a spec and a mock:
`docs/superpowers/specs/2026-09-07-list-view.md`.

The one rule worth keeping from the measurements regardless of the design is
`.row-actions { white-space: nowrap }` — one line, scoped to this screen, and
worth 950px a row on its own.

`tests/ui/modes.spec.mjs` walks this screen and passed throughout, because it
asserts what is on the screen and never how tall a row is.
