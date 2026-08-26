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

## What already exists (as of 26-08-2026)

Described Library v1 (postmeta `_vergeml_ai` — migrates to the Phase-1
table), Fix Missing Alt Text, caption-enriched media search, the credits
connector + `docs/ai-service.md`, mock provider driving 18 PHP + 9 browser
checks. AI screen under VergeLabs Library → AI.

## Open questions that only real data answers

Collected via the Phase-0 telemetry + the five-user interviews: real
library sizes and kind mix, actual folder-tree shapes, upload volumes,
what people type into media search, what triggered existing purchases —
and the frontier question: does anyone still trigger an AI action in
month three, or is the product the first Saturday? The packaging holds
either way, but features 6–8 depth depends on it.
