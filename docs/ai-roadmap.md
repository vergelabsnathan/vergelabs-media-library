# The AI layer — build roadmap

Distilled from the verified Storm Research plan (26-08-2026, six lenses, 33
claims checked) plus the owner's decisions. This file is the durable copy;
the full research lives in the owner's records. Read together with
`docs/ai-service.md` (the service contract) and `plans/phase-0-foundations.md`
(the active plan).

## The product thesis

The centerpiece is the **AI Librarian**: propose a folder structure for a
whole library, show it for review, apply it chunked, undo it from a
move-log. The moat is not the AI (interchangeable, being commoditised by
core) — it is the **safety machinery**: quarantine instead of delete,
review by branches, batch undo that survives deactivation, honest
vocabulary. Undo is the feature, not the apology.

Business model: **credits, never bring-your-own-key**. The plugin talks only
to the VergeLabs service (holds provider keys, owns model + prompt, meters
credits before proxying). Librarian preview free on the user's own library;
apply is an event-priced credit pack (€49-class); a small monthly allowance
(€8–15) carries the recurring drip (auto-filing, alt-on-upload, smart
folders). Free tier: primitives + dedup report + capped alt (~50/month) +
one sample-Librarian grant.

## Build order (each phase = one R-PIV cycle minimum)

- **Phase 0 — free foundations** *(plan written: `plans/phase-0-foundations.md`)*
  Dedup report (md5 exact + thumbnail dHash near, two lists always,
  read-only), honest "Used in" wording, opt-in numbers-only telemetry,
  five-user interview script. Saves credits before the first describe-run.
- **Phase 1 — the real index**
  Custom table (not postmeta), migrating `_vergeml_ai` meta in. Per file:
  English caption + enum attributes (kind, has_people, has_text,
  document_type, orientation) + embedding + model/version/prompt-hash stamp
  + user-edit protection flags. Documents via embedded-text extraction
  first, page-1 vision only under ~200 chars. Release test: same file,
  three runs, same enums.
- **Phase 2 — the /organize backend spike, before any UI**
  Real pipeline on the test box at three library sizes: frozen embeddings →
  seeded deterministic clustering → per-cluster labeling → tree persisted
  as data, runs produce diffs. Async contract: progress, partial trees,
  refine-as-input, per-branch regeneration, cancel, one-line reason per
  assignment. Recorded runs become the mock the UI is built against.
- **Phase 3 — the Librarian end to end**
  Review screen renders BRANCHES (counts, sample thumbs, reason, agreement
  distribution, a "needs a look" branch) — never a flat assignment list.
  Axis choice (2–3 schemes) before assignment; existing folders untouched
  by default. Apply chunked; undo from a custom-table move-log (Redirection
  pattern) that survives deactivation; files touched since apply excluded
  and reported. Universal pre-flight panel (files, credits, time, skips;
  out-of-credits = pause + resume) ships here, reused by everything after.
- **Phase 4 — the drip** (order matters: daily-use first)
  AI smart folders (filter stored enums + embedding views, no model call at
  view time) → auto-filing (gate = 3-sample self-consistency agreement +
  embedding distance to folder centroid + per-folder earned autonomy;
  suggestion chip default, badge + digest when auto; NEVER verbalized
  confidence — it is uncalibrated) → natural-language commands (allowlisted
  verbs only: move, tag, retitle, create_folder; no delete, no overwriting
  hand-written fields; untrusted text stays data; ambiguity shows the
  selection before running).
- **Phase 5 — utilities, in trust order**
  Cleanup advisor (plugin-owned quarantine + 30–90 day delay + export
  manifest; never MEDIA_TRASH, never bare delete — core hard-deletes by
  default) → similarity/merge on Phase-0 hashes + embeddings → bulk
  titles/alt (metadata-only; physical renames a guarded opt-in for
  zero-reference files with the full rewrite pipeline and a redirect
  self-test: fetch the old URL, a naked 404 means rewrite references
  instead) → semantic search v2 free on the embeddings.

## Standing rules (every phase)

- Service side stores no pixels; logs hold token counts. EU zero-retention
  routing. Art. 28 DPA + published sub-processor list before the first
  hosted call. Off by default; readme disclosure (wp.org guideline 7).
- Every stored description stamped model+version+prompt-hash. No "latest"
  model aliases. User-edited fields flagged and never regenerated over.
- Vocabulary law: never "unused" or "safe to delete" — only "no references
  found in scanned locations" plus what was scanned.
- "Runs on cheap shared hosting" is a release gate: small batches,
  resumable everything, no request that hashes or describes while rendering
  a page.
- Connector stays tucked away: service URL non-filterable (constant-only
  override, https enforced), licence sealed at rest (AES-256-GCM off the
  auth salt), REST exposes only booleans.

## What already exists (as of 27-08-2026)

Described Library v1 (postmeta `_vergeml_ai` — migrates to the Phase-1
table), Fix Missing Alt Text, caption-enriched media search, the credits
connector + `docs/ai-service.md`, mock provider driving 18 PHP + 9 browser
checks. AI screen under VergeLabs Library → AI, with demo mode as a
control on it rather than a REST-only setting.

**Phases 1, 2 and 3 are built.** Phase 3 (`plans/phase-3-librarian.md`,
executed 27-08-2026): two custom tables, six REST endpoints, the review
screen, apply and undo, the credits gate as an open filter. Seven
validation gates green; 68 PHP + 36 browser assertions. The Librarian is
reachable from the folder tree on the media library screen, not only from
its own submenu.

Two things about Phase 3 that a later session should not mistake for
oversights:

- **The visual pass is deferred by decision, not outstanding.** The
  screens work and are consistent with the admin's own colour scheme, but
  nobody has judged them as design. Deliberately held until every feature
  is in, so one pass covers all the screens rather than seven that never
  quite agree. The CSS is hand-written with no build step, so deferring
  costs nothing.
- **Two query budgets in the plan were unreachable arithmetic.** Apply is
  4 + 4 queries per file, not 4 + 2: `wp_set_object_terms()` costs four
  for one fresh assignment, measured, and going lower means writing
  `term_relationships` by hand and dropping every hook other plugins hang
  on term assignment. The pre-flight is 11, not 6, because the organise
  quote it wraps is 5 on its own. `tests/perf/bench.mjs` records the
  measured figures with their derivations. What is flat — and asserted —
  is that a step costs the same however many have run before it.

## Open questions that only real data answers

**Is the proposed tree any good?** Unanswerable today and not for want of
trying: the mock provider derives its embeddings from file names, so every
cluster on the test box groups files by what they are called rather than by
what they show. `tests/organize/test-organize.php` says the same thing in
its own header. Two separate questions to keep apart when the service is
live — whether the clustering behaves (branch sizes, depth, how much lands
in "Needs a look", whether labels are nameable), which is testable now with
synthetic vectors of known shape; and whether the folders are *right*,
which is not testable until real embeddings exist.

Collected via the Phase-0 telemetry + the five-user interviews: real
library sizes and kind mix, actual folder-tree shapes, upload volumes,
what people type into media search, what triggered existing purchases —
and the frontier question: does anyone still trigger an AI action in
month three, or is the product the first Saturday? The packaging holds
either way, but features 6–8 depth depends on it.
