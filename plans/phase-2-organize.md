# Phase 2 — the /organize backend: a proposed tree, stored as data

## Problem

Phase 1 gave every image a caption, four enum attributes and an embedding, and
**nothing reads any of it**. `vergeml_index_get()` has exactly one caller
outside the tests — the AI screen, to show a count. The library now knows what
its pictures are and cannot answer a single question about its own shape.

The spike (`tools/organize-spike.php`) established three things, and each one
sets a task below:

1. **Deterministic clustering works.** k-means++ with the random weighting
   replaced by "take the farthest point" gives byte-identical trees across
   runs. 58 files, 5 branches, 4 iterations, 3.8ms. Without this, "runs
   produce diffs" is meaningless because every diff would be noise.
2. **k chosen as `sqrt(n/2)` is not usable.** On the test library it put
   **35 of 58 files** into one branch and named it "Wordmark Primary" after an
   arbitrary member — a 60%-of-your-library grab bag with a confident, wrong
   name. At `k=12` the same library splits sensibly but grows three
   single-file folders. Neither is a tree anyone would accept.
3. **It does not scale, and the numbers say exactly where it stops.** Measured
   on the test box with synthetic vectors, k = `sqrt(n/2)`, ten iterations:

   | n | dims | k | seeding | one pass | total | memory |
   |---|---|---|---|---|---|---|
   | 500 | 64 | 16 | 88ms | 12ms | 0.2s | 0.6MB |
   | 2,000 | 64 | 32 | 1.5s | 97ms | 2.4s | 2.6MB |
   | 10,000 | 64 | 71 | **36.6s** | 1.0s | **46.7s** | 13MB |
   | 2,000 | **768** | 32 | 16.9s | 1.1s | **28.0s** | **39MB** |

   Three things follow, and they are the reason this phase is not just the
   spike tidied up:

   - **Seeding dominates** — 36.6 of the 46.7 seconds at 10,000 files. It is
     O(n·k²) and it is the first thing that has to go.
   - **Dimensions cost linearly, and real ones are 12× the mock's.** The mock
     returns 64; production embeddings are 768–1536. Extrapolating the two
     measured rows, a real 10,000-file library at 768 dims is **nine minutes
     of arithmetic**, not forty-seven seconds.
   - **Memory is the harder wall.** 2,000 vectors at 768 dims is 39MB as PHP
     arrays — a float is 8 bytes plus array overhead, so roughly 10× the raw
     float32. Ten thousand at 768 dims is around 196MB, over the 128MB a lot
     of shared hosting allows, and the plugin's whole promise is that it runs
     there.

Nothing is persisted, so no run can be compared with another. And a file
already in the index is never revisited: when the pipeline gained embeddings,
58 rows had to be cleared by hand before anything would re-describe.

## User story

As a site owner with a few thousand unfiled images, I want to see the folder
structure the plugin would propose — with a reason for every file and nothing
in my library touched — so that I can judge it before letting it near
anything.

## Decisions taken

- **Phase 2 proposes. It never writes a taxonomy term and never moves a
  file.** Applying is Phase 3, behind the undo log. Every endpoint here is
  read-only with respect to the media library; the only thing it writes is its
  own run rows.
- **The tree is data, not folders.** One row per run in a custom table, the
  tree as JSON in it. Folders exist only if a human later says so.
- **Runs are kept and comparable.** A diff between two run ids is computed
  from stored rows. Keep the last 10 runs per site; older ones are pruned.
- **No vector database.** See the note at the end of this section — this is
  the one decision most likely to be revisited later, so the reasoning is
  written down rather than assumed.
- **Clustering stays deterministic.** No `rand()`, no `shuffle()`, no
  iteration over a hash-ordered array. Points are walked in `attachment_id`
  order and ties break to the lower id. A test asserts two runs are identical.
- **Seeding is bounded.** Centroids are seeded from a deterministic sample of
  at most `VERGEML_ORGANIZE_SEED_SAMPLE` (2000) points — every point still
  gets *assigned*, but the O(k²) search only ever runs over the sample. At the
  measured rate that turns 36.6s of seeding at 10,000 files into ~1.5s,
  independent of library size.
- **Clustering runs on a reduced vector, not the stored one.** The full
  embedding stays in the column for similarity and search; clustering uses a
  deterministic projection down to `VERGEML_ORGANIZE_DIMS` (64). This is
  forced by measurement, not preference: at 768 dims a 10,000-file library is
  nine minutes of arithmetic and ~196MB of PHP arrays, and the plugin's
  premise is shared hosting. The projection must be fixed and stored with the
  run, so two runs are comparable, and it must be the same projection for
  every site — a per-site random one would make recorded fixtures useless.
  Averaging fixed contiguous bands of the source vector is the cheapest thing
  that preserves neighbourhood structure and has no seed to get wrong.
- **Async is the shape this plugin already uses**: a `POST` step endpoint the
  caller loops, carrying a cursor, exactly like `vergeml_health_scan_step()`
  and `vergeml_ai_index_step()`. No cron, no background process, nothing that
  needs a worker on shared hosting.
- **Cancel is a flag on the run row**, checked at the top of each step. A
  cancelled run keeps whatever it had built and is marked `cancelled`, because
  a partial tree is still worth showing.
- **Per-branch regeneration re-clusters one branch's members only**, writing a
  new run whose parent is the old one. It never re-clusters the whole library
  to change one folder.
- **Refine-as-input**: a run may name a previous run plus per-branch
  instructions (`split`, `merge`, `keep`). Kept deliberately small in this
  phase — the vocabulary is three verbs, not natural language.
- **One line of reason per assignment, stored**, not generated on read. A tree
  nobody can interrogate is a tree nobody will accept.
- **Labels come from stored tags**, scored by how common they are inside the
  branch against how common across the library, with no model call. A folder
  name is a summary of data already held; paying per folder to be told
  "photos" is a poor trade.
- **The embeddings are mock and the pipeline must not care.** Everything here
  is written against the vector in the column, whatever produced it.
- `OPEN:` **How is k chosen?** The spike proved `sqrt(n/2)` produces a
  catch-all. Candidates, none yet chosen: (a) target average branch size —
  pick k so branches average ~30 files; (b) silhouette score over a few
  candidate k values, which costs several clustering passes; (c) split any
  branch above a size threshold and re-cluster it, recursively, which gives a
  real tree rather than a flat list; (d) let the caller pass k and default to
  (a). **This is the single decision that most affects whether the output is
  any good, and it should not be guessed.** (c) is the most interesting
  because Phase 3 renders *branches*, not a flat assignment list — but it is
  also the most work.
- **Nothing is called "big". The library is measured, on the host it is
  actually on.** A file count is a stand-in for the things that matter and a
  poor one: 3,000 files on a slow shared host is a worse experience than
  15,000 on decent hardware. So no threshold is hard-coded anywhere. Instead:
  - **Memory** is arithmetic, done before starting: files × dims × 8 bytes,
    plus overhead, against `ini_get( 'memory_limit' )`. If it will not fit,
    project further or shrink the chunk — do not begin and die halfway.
  - **Time** is measured, not predicted. Run the first chunk, time it, and
    multiply by what is left. Ten seconds of real work on *their* server beats
    any constant I could pick here.
  - **Cost** is a count, known exactly once the duplicate scan has run.
  Warnings fire on the thing the user cares about — "this will take more than
  ten minutes", "this will cost more than N credits" — never on the file
  count. Someone with 800 images on a slow host deserves the warning; someone
  with 12,000 on a fast one may not.
- **The duplicate scan runs before the first describe run, and its result is
  the quote.** Phase 0 built it and it costs nothing — no service call, no
  credit, just hashing files on disk. Running it first means the number shown
  to the user is counted rather than estimated: this many files, this many are
  copies, this many are skipped, this many will be described, this is what
  that costs. It also makes the free tier do the strongest thing a free tier
  can: tell somebody something true and useful about their own library before
  asking for money.

### Why no vector database

Asked directly, and the answer is no — not now, and probably not ever in the
free plugin.

- **Clustering needs every vector anyway.** k-means is a full pass over the
  set; an approximate-nearest-neighbour index buys nothing for the one
  operation this phase performs.
- **The numbers do not justify a dependency.** A 768-dimension float32 vector
  is 3KB. Ten thousand files is 30MB — large for a PHP array, unremarkable for
  a `longblob` column read in chunks.
- **It would break the product thesis.** "No build step, runs on cheap shared
  hosting" is the whole positioning. Requiring Pinecone or Qdrant adds a
  service, a key, a bill and a second place customer data lives.
- **It would contradict the contract just written.** `docs/ai-service.md`
  promises the service stores no pixels; a hosted vector index would hold a
  representation of customer media, which is the same promise broken in a
  different tense.
- **MySQL's own vector types are not reachable.** MySQL 9 and MariaDB 11.7
  have them; the floor here is whatever shared hosts run, which is often
  MariaDB 10.x.

Revisit if — and only if — the telemetry shows real libraries above ~50,000
files, or interactive semantic search (Phase 5) proves too slow chunked. The
cheap intermediate step before any external service is a quantised
prefilter: store a small int8 projection beside the full vector, shortlist on
that, rank on the real one. That is a Phase-5 problem and is written here only
so nobody reaches for a database first.

## Out of scope

- Any UI. The roadmap says "before any UI" and means it; the recorded runs
  from this phase become the fixture the Phase-3 screen is built against.
- Applying a tree, creating folders, moving files, undo. All Phase 3.
- Deleting, quarantining or merging anything. Phase 5.
- Real embeddings, and any judgement about whether the clusters are *good* —
  see Risks.
- Natural-language refinement. Three verbs, no parser.
- Document text extraction, still unbuilt from Phase 1.

## Context

**Files to read first**

- `tools/organize-spike.php` — the algorithm this phase productionises, and
  the three findings above. Read it before writing `core/organize.php`; most
  of the maths transfers unchanged, the seeding does not.
- `core/ai-index.php` — where the vectors live, `vergeml_index_vector_out()`,
  and the `$wpdb->vergeml_ai_index` registration that keeps Plugin Check
  quiet. Also `vergeml_index_migrate_step()` as the resumable-walk idiom.
- `core/health.php` — the closest existing pair of a chunked step endpoint and
  a report endpoint, including how the cursor is carried and how the option
  records progress. `vergeml_health_scan_step()` is the shape to copy.
- `core/ai.php` — REST registration style, permission callbacks, and
  `vergeml_ai_index_step()` for "process a capped batch, report what is left".
- `vergelabs-media-library.php` around line 1200 — the safe-mode guard every
  feature file loads inside, and the `vergeml_activate` action that new tables
  hook for their schema.
- `docs/ai-roadmap.md` — Phase 2 and Phase 3. Phase 3 constrains this: it
  renders branches with counts, sample thumbs, a reason and an agreement
  distribution, so the data this phase stores has to include all of that.
- `.claude/skills/validate/SKILL.md` — the seven gates and the corrected
  Plugin Check command.
- `tools/verify.mjs` — how suites are run now, and where a new one is
  registered.

**Files that change**

- `vergelabs-media-library.php` — one `include_once` for `core/organize.php`,
  inside the safe-mode guard, after `core/ai-index.php`.
- `core/ai-index.php` — the re-describe path (task 1), which is Phase-1 debt
  this phase cannot proceed without.
- `tools/verify.mjs` — register the new suite.

**Files created**

- `core/organize.php` — schema, clustering, labelling, the two REST endpoints,
  run storage and diffing.
- `tests/organize/test-organize.php` — the PHP suite.
- `tests/organize/fixtures/` — recorded runs, which are the deliverable the
  Phase-3 UI is built against.

**Prior art in this repo**

- Chunked resumable walks: `vergeml_health_scan_step()`,
  `vergeml_index_migrate_step()`, `vergeml_import_run()`.
- A custom table done correctly: `core/ai-index.php` — `dbDelta`, the
  `vergeml_activate` hook, and registration on `$wpdb` so the SQL sniffs can
  read the queries. Copy that pattern exactly; it is the one that passed
  Plugin Check.
- Batch REST + a browser loop: `/vergeml/v1/ai-index` and `js/vergeml-ai.js`.

**External docs** — none. k-means is not a library dependency here.

## Tasks

1. **`core/ai-index.php`: re-describing.** Add
   `vergeml_index_stale( $model, $version, $dims )` returning ids whose stamp
   or embedding dimensions differ from the current ones, plus a `rescope`
   argument on `vergeml_ai_pending()` so `ai-index` can be pointed at them.
   Phase-1 debt: without it, a model change strands every existing row, and
   this phase cannot even re-run its own fixtures without SQL by hand.
2. **`core/organize.php`: the table.** `{prefix}vergeml_organize_runs` —
   `run_id`, `parent_run_id`, `status` (`running|done|cancelled|failed`),
   `k`, `n`, `cursor`, `tree` (longtext JSON), `params` (JSON), `created_at`,
   `updated_at`. Registered on `$wpdb` and hooked to `vergeml_activate`,
   exactly as `ai-index` does. `dbDelta`, no `DEFAULT` on the text columns.
3. **`core/organize.php`: vector loading in chunks.**
   `vergeml_organize_vectors( $after, $limit )` — id-ordered, resumable, so a
   library that does not fit in one request still loads. Returns id, vector,
   tags, kind, document_type.
3b. **`core/organize.php`: the projection.**
   `vergeml_organize_project( $vector, $dims = 64 )` — average fixed
   contiguous bands down to 64 components and re-normalise to unit length.
   Fixed, seedless and identical on every site, so recorded runs stay
   comparable. The suite asserts that projecting twice gives the same result
   and that two similar full vectors stay similar after projection.

4. **`core/organize.php`: bounded deterministic seeding.**
   `vergeml_organize_seed( $sample, $k )` — the spike's farthest-point rule,
   but over a deterministic sample capped at 2000 points (every `ceil(n/2000)`-th
   id). Fixes the O(n·k²) blow-up. Assert in the suite that the sample is the
   same for the same library.
5. **`core/organize.php`: assignment and refinement.** Lloyd iterations with a
   cap, an early exit when nothing moves, and empty clusters keeping their
   centroid rather than collapsing to the origin. Ported from the spike, which
   is already correct here.
6. **`core/organize.php`: k selection.** Blocked on the `OPEN:` above. Whatever
   is chosen, it lives in one function with the reasoning in a comment, and
   the caller may always override it.
7. **`core/organize.php`: labelling.** Port `vgml_spike_label()`: tags common
   inside the branch and rare outside it, falling back to the `kind` mix when
   a branch shares nothing. Never a model call.
8. **`core/organize.php`: reasons.** One stored line per assignment — the
   branch it went to, its distance from that centroid, and the tags that put
   it there. Phase 3 renders these; they are not decoration.
8b. **`core/organize.php`: the pre-flight quote.**
   `GET /vergeml/v1/organize-quote` — counted, not estimated. Returns total
   files, how many are duplicates (from the Phase-0 hashes, which must have
   been scanned), how many the skip rules exclude, how many therefore need
   describing, the credits that implies, and the memory arithmetic for this
   host. Refuses with a clear reason if the duplicate scan has not run, rather
   than quoting a number it cannot stand behind.

8c. **`core/organize.php`: measured time estimates.**
   `vergeml_organize_estimate( $done, $elapsed_ms, $remaining )` — extrapolate
   from work actually performed on this host. Every step returns an updated
   estimate, so the number shown gets better as it goes rather than being a
   guess fixed at the start.

9. **`core/organize.php`: `POST /vergeml/v1/organize-step`.** Takes
   `{run_id?, k?, parent_run_id?, refine?}`, returns
   `{run_id, status, done, remaining, cursor, partial_tree}`. Creates the run
   on first call. Permission `manage_categories`. Checks the cancel flag
   first. Writes the partial tree each step, so a caller that stops still has
   something.
10. **`core/organize.php`: `POST /vergeml/v1/organize-cancel`.** Sets the flag.
    Separate endpoint because a cancel that has to wait for the step it is
    cancelling is not a cancel.
11. **`core/organize.php`: `GET /vergeml/v1/organize-run`.** One run by id, or
    the latest. The Phase-3 screen's read path.
12. **`core/organize.php`: diffing.** `vergeml_organize_diff( $a, $b )` —
    branches added, removed, renamed, and files that moved between branches.
    Computed from stored trees, never by re-running.
13. **`core/organize.php`: per-branch regeneration.** A step whose `refine`
    names one branch re-clusters only its members into a child run.
14. **`core/organize.php`: pruning.** Keep the last 10 runs, delete older ones.
    Marked as the one destructive act in this phase — it deletes only rows this
    phase wrote, never media.
15. **`tests/organize/test-organize.php`.** Seeds vectors directly into the
    index (no model needed), then asserts: two runs identical; the sample is
    stable; every file lands in exactly one branch; branch sizes sum to n; a
    cancelled run keeps its partial tree; a diff of a run against itself is
    empty; a diff after a per-branch split shows only that branch's files
    moving; labels are non-empty; reasons exist for every assignment.
16. **`tests/organize/fixtures/`.** Record two runs at different k as JSON.
    These are the mock the Phase-3 UI is built against, which is the phase's
    actual deliverable.
17. **`tools/verify.mjs`.** Register the suite so it runs with the others.

None of tasks 1–13 or 15–17 is irreversible. **Task 14 deletes rows** — its own
table only, and it must never be able to touch `posts` or `postmeta`.

## Validation strategy

- **Gates 1–5 always.** Gate 6 because there are new PHP files and a new
  table, and the last one needed `$wpdb` registration to pass. Gate 7 because
  there is a new table and a new option: prove `vergeml_set_options()` leaves
  `vergeml_organize` alone, the same regression guard as `vergeml_health` and
  `vergeml_stats`.
- **Query budgets.**
  - `organize-step`: **≤ 4 per step**, flat. One to read the run row, one to
    load the batch of vectors, one to write the row back, plus one of slack.
    It must not grow with `k` or with the number of steps already taken — an
    N+1 here is a query per file, and the file count is the whole point.
  - `organize-run`: **2**. The run row, and the post rows for whatever ids it
    returns — via one `IN (...)`, with `update_post_cache()`, because that is
    exactly the N+1 that made `health-report` cost 70 queries when it looked
    like 5.
  - `organize-cancel`: **2**.
- **Measure over REST, never from `wp eval` after other work in the same
  request.** That mistake reported 4 queries for an endpoint running 70. Add
  the endpoints to `tests/perf/bench.mjs` alongside `health-report`.
- **A wall-clock budget per step, not per library**: one step stays under ~5
  seconds whatever the library size, because shared hosts time out at 30 and
  the browser drives the loop. The step size is the safety valve, and it is
  adjusted from the measured rate rather than fixed. Unmodified, the spike's
  algorithm takes 46.7s in one request at 10,000 files, so this is the
  difference between the phase working and not.
- **Test that the estimate is honest.** Time the first chunk, extrapolate,
  then run the whole thing and compare. The suite asserts the projection lands
  within ±30% of actual — not because 30% is precise, but because an estimate
  that is wrong by more than that is worse than no estimate, and a user who is
  told two hours and waits six will not use the feature again.
- **Assert peak memory in the suite** at a synthetic 10,000 vectors, against a
  ceiling of 64MB — half of the 128MB a modest shared host gives, because
  WordPress and everything else also has to fit. Also assert the pre-flight
  memory arithmetic refuses to start a run it has calculated will not fit.
- **New suite** `tests/organize/test-organize.php`, run through
  `node tools/verify.mjs organize`.

## Risks

- **The clusters cannot be judged yet.** The embeddings are mock and derived
  from filenames, and the fixtures are named `vgml-fx-<subject>`, so
  clustering them is close to clustering their names. The machinery is
  testable; the *quality* is not, and no test in this phase should pretend
  otherwise. Any claim that the output is good must wait for real embeddings.
- **Memory is the binding constraint, and it is measured, not guessed.** 2,000
  vectors at 768 dims took 39MB as PHP arrays; 10,000 would be around 196MB,
  against the 128MB a lot of shared hosting allows. The projection in task 3b
  is what makes this fit (10,000 × 64 dims ≈ 13MB measured), and task 3 loads
  in chunks so the *source* vectors are never all resident at once. What does
  stay resident is the projected set, the centroids and the assignment array —
  budget those explicitly and assert the peak in the suite rather than hoping.
- **Safe-mode load order.** `core/organize.php` must load inside the guard and
  after `core/ai-index.php`, or a crash in clustering cannot be switched off
  and `$wpdb->vergeml_ai_index` will not exist when it is read.
- **Existing installs.** New table, new option — gate 7, and the schema hook
  must survive a site that upgrades without visiting an admin screen.
- **PHP 7.4.** No arrow functions in the hot loops where they would read
  nicely, no `str_contains`.
- **Run-table growth.** A tree for 10,000 files is a large JSON blob. Ten of
  them is tens of megabytes in one table. Task 14 exists for this; check the
  stored size at scale rather than assuming.
- **The `OPEN:` on k is not a detail.** Building tasks 2–5 and 7–13 against a
  k rule that turns out wrong means the persistence and diffing are fine and
  every tree they hold is a 60% catch-all. It is worth answering first.

---

**Review this, then start a new session to run `/execute`.** Continuing in this
one would execute from a context already full of exploration — the spike, the
scale probe, a harness pass and two earlier phases — which is exactly what the
plan/execute split exists to prevent.

The two `OPEN:` lines are real blockers, not hedging. `/execute` is written to
stop when it finds one.
