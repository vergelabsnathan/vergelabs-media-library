# Session handover — 2026-09-09, traces Phase 3.5: why three measurements move (Opus 5)

Phase 3.5 of `plans/traces.md`. No feature, and none was built. Three numbers
that moved for reasons nobody had found; one is fully answered and now has a
gate that keeps it answered, one is answered as far as the evidence goes and
its last step is stated as open rather than guessed, and one turns out to have
been measuring debris rather than the screen.

`core/filing.php` is untouched, as it has been across all four phases. The
floor is 0.55, the margin 0.08, the gates and the order of the matcher are as
they were. `tests/tree/filing-baseline.txt` was **not** re-taken.

Read in this order:

1. `docs/superpowers/specs/2026-09-08-traces.md` — the contract.
2. `docs/handoffs/2026-09-09-traces-phase-3-handoff.md` — what Phase 3 left.
3. `tickets/2026-09-09-traces-loose-ends.md` — the list this phase took three
   items from.
4. `plans/traces.md` — Phase 4 is next.
5. This handoff.

**The model.** This session was **Opus 5**; the plan assigns Phase 3.5 to
Fable 5.1. That is worth knowing before reading the shape of the work: the
phase was run as a sequence of narrow measurements with a probe and an output
line for each, which is the Opus profile, rather than one long uninterrupted
investigation. The Fable fences were kept — everything found outside the three
numbers is in **Found, not done** and none of it went into the build.

---

## The first number — the filing baseline drift

### The answer

**The service does not answer the same phrase with the same vector.** The
matcher scores a folder as `0.75 × class + 0.25 × embed`. The `embed` half
comes from vectors already stored on the box and does not move. The `class`
half is `vergeml_filing_class_match()`, which for any pair that is not a word
match asks `vergeml_meaning_vector()` for each phrase — and that fetches from
the service and caches the answer in a transient.

The service is OpenAI's `text-embedding-3-small` at `dimensions: 512`
(`service/lib/embed.ts:22`). Asked the same phrase six times on the box:

```
phrase       answers  max |diff|     worst cosine   checksums
signage      1        0.000e+0       1.000000000    d7c410×6
building     1        0.000e+0       1.000000000    cdf41e×6
street       1        0.000e+0       1.000000000    d0ae7c×6
crosswalk    1        0.000e+0       1.000000000    4d0983×6
skyline      2        1.831e-4       0.999999425    24c83d×5 ceea4a×1
portrait     1        0.000e+0       1.000000000    4902cf×6
headshot     2        1.221e-4       0.999999655    eb0675×5 ff3c1d×1
fruit        1        0.000e+0       1.000000000    856824×6
logo         1        0.000e+0       1.000000000    fae21e×6

phrases that gave more than one answer: 2 of 9
```

`tools/box-embed-repeat.php`. Two phrases in nine gave a second answer in six
calls, differing by ~1.5e-4 in a single dimension. `skyline` is a class of
folders 1790 and 1792; `headshot` is a class of 1798. **All three are among
the folders the drift lands on**, which is the loop closed from both ends.

And the cached vector is not always what the service says now.
`tools/box-embed-cached.php` compares what the box holds against a fresh
answer, without deleting or writing a single transient:

```
what         cached   fresh    max |diff|   cosine       phrase
class        cdf41e   cdf41e   0.000e+0     1.000000000  building
class        3cc7da   d7c410   1.526e-4     0.999999556  signage
class        4d0983   4d0983   0.000e+0     1.000000000  crosswalk
```

The box holds a `signage` that the service no longer gives. Folder 1783's
classes are `building, signage, street, crosswalk`, and **1783 is the runner-up
in 23 of the 32 rows whose runner-up score drifted** — the single largest
contributor.

### Why Phase 2 ruled the service out, and what it actually measured

`tools/box-embed-stable.php` asked **one phrase three times** and got one
checksum, and the comment in `core/search-meaning.php:141` records that as *"the
service answers the same phrase with the same 512 floats every time"*. Three
calls is a small sample of a one-in-six event, and — the part that matters — it
compares **fresh against fresh**. It never compares fresh against **what the
cache holds**, which is the comparison the baseline actually depends on. That
comparison is what found it.

### Why it stopped moving, and why 108 became 43

Every phrase vector on the box was written in one five-minute window:

```
reading every deadline as write + 7 days:
  2026-09-08   699 phrases   first 16:04:25   last 16:10:01
  2026-09-09     1 phrases   first 07:41:02
phrase vectors held: 700
already past their deadline: 0
```

`tools/box-drift-when.php`. Not one predates 8 September 16:04. That is the
deploy at 16:04:10 landing `326ab67` — *"phrase vectors kept for a week"* — on
a cache that had been living an hour at a time, and one cold run refilling all
700. Before that, every phrase was re-fetched hourly, and **every re-fetch was
a fresh roll of the dice**. So each baseline comparison was against a different
generation of phrase vectors, which is why the drift was 108 rows on
8 September and is **43 rows today** — and why picture 2817, which Phase 2 saw
flip `ok` → `margin`, is byte-identical to the baseline again:

```
2817	1781	ok	0.646552	1797	0.458591   (baseline)
2817	1781	ok	0.646552	1797	0.458591   (today)
```

Two runs twenty minutes apart today are byte-identical, because the cache has
not expired since. The 0.030000 that Phase 2 measured on 2817 was exactly the
depth tie-break at `core/filing.php:452` — `$scores[$cand] >= $scores[$best] - 0.03`
— being crossed by a fourth-decimal nudge.

### The size of it, against the size of a decision

| | |
|---|---|
| largest move in a winning score, over 641 pictures | 5.500e-5 |
| largest move in a runner-up's score | 4.110e-4 |
| the depth tie-break | 0.03 |
| the margin | 0.08 |
| the floor | 0.55 |

**No `term_id` and no `why` differs today, in any of the 641 rows.** The
placements are identical; only the arithmetic behind them wobbles, two to
three orders of magnitude below anything that decides.

### What keeps it answered

`tools/filing-baseline-check.mjs` — new. It runs the read-only pass on the box,
compares it against `tests/tree/filing-baseline.txt`, and asserts the three
things that can actually be asserted:

```
  filing baseline · 641 pictures · 46.225.66.194

  rows whose numbers moved at all            43
  largest move in a winning score            5.500e-5
  largest move in a runner-up's score        4.110e-4
  the band a score may move in               0.001

  ok    every picture in the baseline is still in the library
  ok    every picture would be filed exactly where it was
  ok    every score is within 0.001 of the baseline

  3 of 3 checks passed — the placements are identical
```

The band is 0.001: over twice the largest drift measured across 641 pictures,
and thirty times below the tie-break it must never reach. **It never re-takes
the baseline** — a gate that rewrites its own expectation is not a gate.

**Mutation check.** Three doctored runs, each red, exit 1:

```
mut-placement  FAIL  1 picture would be filed somewhere else now
                     2817  ok in 1781 (0.646552)  ->  margin in 1797 (0.646552)
mut-score      FAIL  1 score moved further than the service's own noise explains
                     299  score 0.000e+0, runner-up 4.080e-3
mut-missing    FAIL  the library is not the one the baseline was taken over
                     1 picture gone, 0 new
```

Unmutated: exit 0.

**This is what Phase 5's last gate should run**, in place of a byte diff. A byte
diff over these six columns can never pass again except by luck, because two of
them are floats produced by a provider that does not repeat itself.

---

## The second number — 109 rows with no reason

### What is proven

**Nothing dropped a reason. Whatever wrote those rows passed none at all.**
Measured, rather than argued: `tools/box-moves-shape.php` writes one row of each
shape the write path can produce into a batch id no batch has, reads them back
whole, and deletes them.

```
five elements, a matcher reason      why 'ok'    score 0.812345  prompt_hash 'abc123def456'
five elements, no packed scores      why 'plan'  score NULL      prompt_hash 'abc123def456'
four elements, no reason at all      why ''      score NULL      prompt_hash ''

batch 18's rows                      why ''      score NULL      prompt_hash ''

scratch rows left behind: 0
rows in the table now:    109
```

Only the four-element shape reproduces it, and it reproduces it exactly —
because those six columns are then never named in the INSERT and MariaDB fills
each with its column default (`why varchar(16) NOT NULL DEFAULT ''`, `score
float NULL`, `runner_up ... DEFAULT 0`, `prompt_hash`/`model_version` empty).
That is `core/librarian.php:216`.

The rows themselves, read back:

```
batch 18 · 2026-09-08 15:56:23 · refile · {"source":"auto-file"} · 109 rows
folders claimed:
    2073  (no such folder)  87 rows   <- deleted
    2074  (no such folder)  10 rows   <- deleted
    2075  (no such folder)   6 rows   <- deleted
    2076  (no such folder)   6 rows   <- deleted

pictures still in the folder the row claims: 0
pictures no longer in it:                    109
```

So the record is verifiably stale in the way the ticket says: 109 rows assert
placements that no longer hold, and `undone = 0` on every one of them.

### The undone half, which is separate and is Phase 4's

The two anomalies are **not one thing**. `vergeml_talk_undo()` restores terms
from the `VERGEML_TALK_UNDO` option and never touches the moves table; only
`core/librarian.php:2177` sets `undone = 1`, and that is the librarian's own
undo. So `undone = 0` means "no librarian undo ran", not "these moves still
hold". That is exactly what Phase 4 already plans to fix, and the row shape
above shows the blank `why` has an entirely different cause.

### What is not proven, and I am not going to invent it

The four-element call does not exist in any build that has been on the box.

- Every insert caller in the build live at 15:56 passes five elements —
  `auto-file.php:417`, `folder-talk.php:1280`, `librarian.php:1629`,
  `nl-commands.php:522` — read **off the box**, from
  `/root/vgml-backup-20260908-160410`, which `tools/deploy.mjs:527` writes as a
  `cp -a` of the live plugin immediately before each unzip. That backup is the
  build deployed at 15:49:36, which is the one live at 15:56:23.
- Every deploy backup from `20260908-090145` onward carries the six-column
  INSERT and the five-element trail write. Only `20260908-090008` — the build
  live before 09:00:08 — is schema version 1 with the five-column INSERT.
- The only four-element caller in the plugin's history is the pre-Phase-1
  `vergeml_autofile_file()` (`auto-file.php:402` at `1cc5c7f^`), and it is only
  ever called with `'auto'` or `'accepted'`. Pre-Phase-1 `core/folder-talk.php`
  wrote **no move row at all** — `1cc5c7f`'s own message says so: *"the one that
  wrote no move row at all"*.
- Batch 18's id was never reused. MariaDB has been up since 2026-09-02 06:10
  (`Uptime 615046`), so InnoDB's auto-increment counter never reset; it stands
  at 25.
- The other two WordPress installs on the box (`/var/www/upd`, `/var/www/ms`)
  have their own databases (`wpupd`, `wpms`) and run the same version.
- No tool in `tools/` writes to the moves table. The Pro add-on does not
  reference it.

So a batch created at 15:56:23 on 8 September carries 109 rows that no build
on the box at that moment could have written, and the id cannot have been
handed out twice. **I could not close that step, and I am recording it as open
rather than picking whichever story reads best.**

What it does **not** change: the rows are unambiguously from a writer that
passed no reason, an empty `why` therefore means precisely what
`core/librarian.php`'s schema comment says it means — *"the move happened
before this shipped"* — and nothing writes an empty one today. Backfilling them
would be inventing history, which the ticket already rules out.

### The thing found on the way, and it is a live defect

**Three tree suites delete other people's batch rows and orphan their move
rows.**

| suite | what it deletes | what it leaves |
|---|---|---|
| `tests/tree/auto-file.php:385` | **every** `auto`/`suggested` batch created today | their move rows, except its own fixtures' |
| `tests/tree/nl-commands.php:296` | **every** `spoken` batch, ever | the same |
| `tests/tree/filing-trail.php:768` | every batch above its start high-water mark | the same |

Each deletes moves only `WHERE attachment_id` is one of its own fixtures, then
deletes batch rows by scheme or by id range — which reaches batches the suite
never created. `filing-trail.php` runs `env: 'box'`. This is the "tests never
touch live state" rule again, and it is left for Phase 4, which is the phase
that touches these tables.

---

## The third number — a gate that flickers

### It does not flicker on folders.spec. It never did.

Run both ways deliberately, same build, same box, back to back:

```
RUN A · modes.spec alone
  ok 2 modes.spec.mjs:506 › the rows on the media list, arriving in grid (1.5m)
  ok 3 modes.spec.mjs:506 › the rows on the media list, arriving in list (1.2m)
  2 skipped, 5 passed (4.3m)

RUN B · folders.spec then modes.spec
  ok  9 modes.spec.mjs:506 › the rows on the media list, arriving in grid (1.2m)
  ok 10 modes.spec.mjs:506 › the rows on the media list, arriving in list (1.2m)
  3 skipped, 11 passed (9.8m)
```

Green in both orders, in both modes. So the earlier spec leaves behind nothing
that this measures, and the order was a correlation.

### What it actually measures

`ROW_ALARM` measures the tallest row on **page one of the media list**, and
page one is the twenty newest attachments by date. Measured today in the
alarm's own state — ours hidden, every other plugin's column on:

```
every column on the screen:
  ours   taxonomy-media_category, taxonomy-colour, vergeml_used, vgmlpro_source
  theirs fb_folder, fb_filesize, qode-optimizer, seopress_alt_text, aioseo-details

20 rows on the page, tallest first:
   231px  post-1779    Gothic Cathedral Interior
   231px  post-1762    Skateboard Components Kit Flat Lay
   211px  post-1773    San Francisco Skyline Aerial View
   199px  post-1775    Towering Storm Cloud at Dusk

the alarm is 300px; the tallest row is 231px
```

69px of headroom, 23%. A 337px row is 106px above anything the real library
produces — that is a different row on the page, not the same rows growing.

And there was one. The newest real attachment on the box is dated
**2026-09-01**:

```
104743  2026-09-01 13:23:07  Placeholder Image Icon
  1779  2026-08-30 08:27:29  Gothic Cathedral Interior
```

From 8 to 9 September, eight `zz trail` / `zzShot` fixture pictures dated
**2026-09-08 09:00** were sitting in the library — a week newer than anything
real, so they occupied the top of page one, which is exactly what this alarm
measures. The Phase 3 handoff records them and records removing them at the end
of that session with `tools/box-why-clean.php`. The two Phase 3 runs straddled
a `filing-trail.php` run; the trap the plan already carries — *"a suite that
stops early leaves its fixtures in the library"* — is the same one, one level
up.

**So the alarm was never about other plugins' columns, and it was never
order-dependent.** It was reporting a library that had another suite's debris
at the top of it. Two phases wrote it off as *"not ours"*, which was true of
the columns and wrong about the cause.

### What changed, so a green can be read

`tests/ui/modes.spec.mjs` — the assertion now says what it measures, on a pass
as well as a failure:

- `rowHeights()` carries each row's **title** as well as its height.
- Every one of the three sets prints its measurement: the tallest height, the
  ceiling, **which picture that row is**, and the columns that were on. A green
  that prints nothing is why two runs an hour apart could not be compared.
- The failure message names the row and says page one is the twenty newest
  attachments, so the next person reads "is this a real picture or debris?"
  rather than "another plugin's column again".

The ceiling stays at 300 and the sets are unchanged. Nothing about the
pass/fail contract moved; only what it tells you.

There is no point asking which *cell* is tall, and the code now says so: they
are table cells, so every cell in a row is exactly the row's height. The row's
identity is the only thing that discriminates.

---

## Gates

| gate | result |
|---|---|
| `node tools/verify.mjs filing-trail copy journey guide` | **passed** — `passed   copy, guide, filing-trail, journey` |
| `node tools/verify.mjs tree` (Playground) | **21/21 passed** |
| `npx playwright test … modes.spec shell.spec shots.spec folders.spec` | **36 passed, 3 skipped** (14.8m), exit 0 |
| `node tools/filing-baseline-check.mjs` (new) | **3 of 3**, and red on all three mutants |

The two order runs, before any change, are in the third answer above: 5 passed
alone, 11 passed with `folders.spec` ahead. The gate run afterwards is the
36-pass line, on the changed spec.

### What the changed assertion says now, on a pass

All six measurements, printed in both modes for the first time:

```
      list · ours off, and the other plugins' columns off
        tallest 81px of 20 rows, ceiling 100
        that row is post-104743 "Placeholder Image Icon"
        3 columns on: title, author, date
      list · all four of ours on, the other plugins' columns off
        tallest 98px of 20 rows, ceiling 100
        that row is post-1762 "Skateboard Components Kit Flat Lay"
        7 columns on: title, author, taxonomy-media_category, taxonomy-colour, vergeml_used, date, vgmlpro_source
      list · as the screen ships, with every other plugin's column on
        tallest 231px of 20 rows, ceiling 300
        that row is post-1779 "Gothic Cathedral Interior"
        8 columns on: title, author, date, fb_folder, fb_filesize, qode-optimizer, seopress_alt_text, aioseo-details
```

Grid is identical, row for row.

**And it earned its keep on the first run.** The middle set — our own four
columns, nobody else's — is **98px against a ceiling of 100**. Two pixels. That
is the set that is genuinely ours, `ROW_CEILING` is the number this plugin is
actually held to, and it has presumably been that tight for a while: a green
printed nothing, so nobody could see it. It is in **Found, not done** below
rather than acted on here, because the ceiling and what goes in those cells are
not this phase's.

## Cost

**Nothing in this phase reached a language model.** No describe pass, no guide
turn, no eval; `GUIDE_WALK=1` was never set.

It did reach the **embedding** service, which the brief did not anticipate:
`tools/box-embed-cached.php` made 6 `/embed` calls and
`tools/box-embed-repeat.php` made 54. Those are text embeddings, fractions of a
cent in total, and they are the measurement the whole first answer rests on —
but the brief said nothing reaches a model, so it is said here.

## The box, as it was left

31 folders, unchanged. `tools/box-why-clean.php` reports `removed 0 pictures and
0 folders · 0 left behind`. Three batches (1, 18, 23) and 109 move rows —
**batch 18 untouched**, as instructed. The scratch rows
`tools/box-moves-shape.php` wrote were deleted by the same script, which asserts
it rather than claiming it. No transient was written or deleted by any probe.
Nathan's own 4 September draft with the model's fabricated counts is where it
was. A throwaway administrator was made with `tools/box-ui-user.sh` and deleted
at the end.

## Found, not done

- **Three tree suites orphan other batches' move rows** — `auto-file.php:385`,
  `nl-commands.php:296`, `filing-trail.php:768`, above. Phase 4's tables.
- **Batch 23 is a `refile` batch with zero rows**, created 2026-09-09 06:35:37.
  `vergeml_talk_trail_write()` returns before creating a batch when the trail is
  empty, so a batch with no rows means the batch was made and the insert wrote
  nothing. Not chased; noted because Phase 4 is about to add columns to that
  table.
- **`vergeml_librarian_moves_insert()` still accepts a four-element row.** Its
  header defends this as a kindness to a plan in flight across a deploy. It is
  also the only shape that can write a row with no reason, and it now has no
  caller. Phase 4 could require the fifth element and delete the branch.
- **`tools/box-embed-stable.php` records a conclusion that is wrong.** Its
  header, and the comment it seeded at `core/search-meaning.php:141`, both say
  the service is repeatable. Measured above, it is not. The week-long cache is
  still right — for a better reason than the one written down: it is not
  saving a fetch, it is **holding the answer still**. Worth correcting in
  place, and not done here because it is a comment in `core/`, not this phase's
  file.
- **The dry run's numbers move with the cache.** A site whose phrase cache
  expires gets fourth-decimal different scores on the next run. It changes no
  placement at the measured magnitude, but a picture sitting within 1e-4 of the
  floor, the margin or the tie-break can change its mind — 2817 did, twice, in
  both directions. Nobody has decided whether that is worth pinning (a stored
  vector per class phrase rather than a cached one), and it is a matcher
  question, so out of scope everywhere in this plan.
- **`filing-baseline-check.mjs` is not in `tools/verify.mjs`.** It needs the box
  and the repo's baseline file at once, which is neither of verify's two
  environments. Named in `plans/traces.md` for Phase 5 instead.
- **Our own four columns leave 2px under their ceiling.** `ROW_CEILING` is 100
  and the tallest row with only our columns on is 98px, in grid and in list,
  measured on the box today. That is the number this plugin is actually
  answerable for — the 300px alarm is about other people's columns — and it is
  two pixels from red. Nobody knew because a passing assertion printed nothing;
  it prints now. Whether 100 is the right ceiling, or whether a cell of ours is
  taller than it needs to be, is a question for whoever owns the media list.
