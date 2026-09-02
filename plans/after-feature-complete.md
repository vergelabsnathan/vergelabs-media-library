# After feature-complete — everything left, and how each of it gets solved

Written 29-08-2026, after Kamatera was retired and the battery went green on
Hetzner. The free plugin is feature-complete at 3.8.0; this is the list of what
is still open once the wordpress.org submission itself is set aside, plus a
concrete solution for each rather than a restatement of the problem.

Ordered by what it costs to leave undone, not by size.

---

## 1. Pro sends licence keys and customer images to a filterable URL

**Severity: this is the one to do first.**

`vgmlpro_api_base()` in `pro/includes/licence.php` line 46:

```php
return untrailingslashit( apply_filters( 'vgmlpro_api_base', 'https://vergelabsmedia.com' ) );
```

That URL is where Pro posts the licence key (`/api/licence/verify`) and the
customer's images (`/api/ai/describe`). It is a WordPress filter, so **any other
plugin on the same site can redirect both to a server it controls**, with one
line and no warning to the user.

The free plugin refuses to have this, and says why in `core/ai.php`:

> *"There is deliberately no WordPress filter: requests carry the licence key,
> and a filter would let any co-installed plugin redirect them to a harvester."*

Pro has the exact hole the free half was designed to avoid. The docblock even
records the reason it was added — "so a staging site can point somewhere else" —
which is a real need that a constant serves just as well.

**Solution.** Copy `vergeml_ai_service_url()`'s shape into Pro:

- Constant-only override: `VGMLPRO_API_BASE`, read with `defined()`.
- Drop the filter entirely.
- Refuse anything not `https://`, except explicit localhost/127.0.0.1 for
  development, falling back to the baked-in default rather than erroring.
- Keep the docblock, rewritten to say a staging site sets the constant in
  `wp-config.php` — where the person who can edit it already owns the site.

**Also settle the base URL disagreement while in there.** Free calls
`https://ai.vergelabs.nl/v1/describe`; Pro calls
`https://vergelabsmedia.com/api/ai/describe`. Two halves of one product, two
hostnames, two path shapes. Pro should use `ai.vergelabs.nl/v1` like the free
plugin, since the `/v1` rewrite already serves it and that hostname is the one
tied to the company rather than to one product.

**Validation:** a PHP suite asserting `vgmlpro_api_base()` ignores an added
filter, refuses `http://evil.example`, and honours the constant.

---

## 2. The overnight tier is sold and cannot run

`canBatch()` is false on OpenRouter, which is now the provider by rule, so
`/api/ai/batch` answers `503 batch_unavailable`. The overnight tier is priced at
**half a live call** precisely because batching is half price. Right now that is
a price for a thing that does not happen.

**This needs a decision from Nathan, and the options are not equal:**

- **(a) Retire the tier.** Remove `overnight` from `SPEEDS`, drop the batch
  routes, delete `ai_jobs`/`ai_job_items` and the collection half of the
  reconcile cron. Simplest, and removes a whole asynchronous subsystem — the
  batch tables, the collection loop, the refund-on-failure path. Loses a
  differentiator.
- **(b) Price it as standard.** Keep the tier and the wording ("we'll get to it
  overnight"), charge the same as standard, run it through the normal path.
  Honest, keeps the queue-shaped UX, gives up the margin story.
- **(c) Build the half-price path another way.** OpenRouter bills per token with
  no batch discount, so the saving has to come from somewhere else — a cheaper
  model for the overnight tier, or genuinely deferring to off-peak and
  amortising. This is the only option that keeps the current pricing honest,
  and it is a real piece of work.

**Recommendation: (b) now, (c) later if the margin matters.** (a) throws away
working asynchronous machinery that took real effort and that a scale customer
will want. (b) is a pricing-page edit and a one-line change in `jobCost()`.

**Do not** solve this by switching provider — that is the one answer excluded
by the standing rule, and `canBatch()` now says so in a comment.

---

## 3. The chain has never been walked as a buyer

Every component answers correctly in isolation. Nobody has bought a licence,
entered the key, and pressed Describe.

**Solution — walk it as a customer, in this order, on the Hetzner box:**

1. Issue a real licence (Stripe test mode, or `store.issueLicence` directly).
2. Enter the key in the plugin's AI screen; confirm `has_license` flips and the
   key is sealed at rest (`vergeml_ai_unseal` round-trip, and the option is not
   readable plaintext).
3. Activate the site; confirm a second site gets 403 `site_not_activated`.
4. Describe run over `ai.vergelabs.nl/v1` with the mock **off**. Confirm real
   text comes back, credits decrement, and `credits.remaining` rides the
   response.
5. The release test the roadmap specifies: **same file three times, same
   enums.** An attribute that is not stable is not an attribute.
6. Deliberately exhaust: set the balance to 1, run a batch of 10, confirm the
   run stops cleanly on 402 and resumes after a top-up.
7. Deliberately break: wrong key → the run stops with "licence not accepted"
   rather than stubbing every file. This is the fix from `f3b5d98`, unproven
   end to end.

**Needs from Nathan:** nothing but permission to spend a few real credits, once
the legal package allows a live call.

---

## 4. Legal package finished and published

Drafts exist at `service/docs/legal/{dpa,sub-processors,retention}.md`, honest
about what the code does. Blocked on decisions, not writing.

**Solution:**

- Fill every bracketed item. Most are periods; the substantive ones are the
  contracting entity, the breach-notification window, and the transfer basis.
- **The OpenRouter routing question is the real one.** The roadmap promises "EU
  zero-retention routing"; a router picks its upstream per request. OpenRouter
  exposes provider preferences that can require zero-retention providers — the
  fix is to pin that in the request and name the resulting provider set in the
  sub-processor list, not to weaken the promise and not to change provider.
- Confirm the two `CONFIRM` rows: Resend's contracting entity and region, and
  the Postgres host and region.
- Then publish as pages under `/legal/` on the service, and repoint
  `readme.txt` at them instead of the general `vergelabs.nl/privacy`.

---

## 5. The visual pass

Deferred by decision until every feature was in. That condition is now met, and
Nathan has confirmed the free plugin is feature-complete.

Six screens: `media-library`, `media-taxonomies`, `media-health`, `media-ai`,
`media-librarian`, `media-import-folders`, plus the tree, the modal panel and
the utilities surfaces.

**Solution — and this one has a hard constraint.** Nathan's standing rule bans
generating visuals from imagination: reuse exact existing on-brand geometry, or
copy a concrete reference, or stop and propose the concept in one line first.

So the pass is:

1. Screenshot all six screens as they are, on the Hetzner box.
2. Extract what already exists as a de-facto system — the shard fan, the eight
   folder colours, the admin colour scheme the CSS already follows.
3. **Propose the direction in one line and stop.** Nothing gets drawn before
   that is approved.
4. Then apply, screen by screen, checking each against a screenshot.

`~125KB` of hand-written CSS and no build step, so this is time rather than
tooling.

---

## 6. CSV import and export

Next in `plans/roadmap.md`, and the answer to *"how do I set up two hundred
folders without clicking two hundred times"*.

**The seam already exists and is clean.** `vergeml_import_read( $key )` in
`core/import-sources.php` returns one normalised shape for all seven supported
plugins:

> folders as `id => array( name, parent )` in the source's own ids, and files as
> `source folder id => array of attachment ids`

**Solution:** CSV becomes an eighth source. Import reads a file into that same
shape, so the entire plan/run/undo machinery — chunked, resumable, reversible —
works unchanged. Export is the inverse walk over the taxonomy.

Format: one row per assignment, `folder path,attachment id,filename`, with the
path using `/` separators so a tree round-trips. Filename is redundant for the
import and present so a human can read the file.

**Validation:** export a tree, wipe it, import the file, assert the tree is
identical — the round-trip is the test.

---

## 7. WPML and Polylang

**Solution:** mostly testing plus a shim. Both translate taxonomies natively,
which is the whole reason this is cheap on taxonomies and cost FileBird a
`Support/WPML.php` to keep custom tables in sync.

The work is finding where the tree passes term ids around and making sure a
translated term resolves to the right one. Start by installing both on the
Hetzner box and running the standing battery — the failures will point at the
seams.

---

## 8. Per-user folders

**Solution:** term meta for an owner, plus a filter on the tree query. Storage
is trivial.

The actual work is the question the roadmap already names: **what does a file
inside a folder only one person can see do when somebody else opens the
library?** That needs answering before code — the honest options are that the
file is visible but the folder is not, or that both are hidden, and they imply
different things about whether this is organisation or access control. It is
not access control, and the UI must not imply it is.

---

## 9. The "copy shortcode" folder action

**Smaller than the roadmap implies: the shortcode already exists.**
`add_shortcode( 'vergeml_gallery', ... )` is registered in
`core/gallery-widgets.php`. What is missing is only the affordance — an action
on a folder row in the tree that copies `[vergeml_gallery folder="..."]` to the
clipboard.

**Solution:** one item in the existing folder-tools menu, `navigator.clipboard`
with a `document.execCommand` fallback for older browsers, and a confirmation
toast. An afternoon.

---

## 10. Debt

- **`docs/testing.md` does not exist** but the working notes send every session to it
  "for the full detail". Either write it — Playground invocations, the box, the
  suites, the traps — or remove the reference. Writing it is better; those
  details are currently spread across the working notes, the validate gates and folklore.
- **The 20,000-attachment benchmark dataset is gone.** The FileBird head-to-head
  in `docs/benchmarks.md` cannot be reproduced. Rebuild the seed on Hetzner with
  `tools/fixtures.php` at scale so the comparison is re-runnable, then re-measure
  and replace the provenance note with fresh numbers.
- **Gate 6 and 7 have not run since `core/health.php`, `core/quarantine.php` and
  `core/smart-folders.php` changed.** Plugin Check reads a clean archive of HEAD
  and has not seen the `imagedestroy` fix. Run the full seven before anything
  else ships.

---

## Order

1. **Pro's filterable URL** (§1) — security, and small.
2. **Re-run the seven gates** (§10) — cheap, and everything else builds on HEAD
   being clean.
3. **Overnight tier decision** (§2) — Nathan; a live pricing claim that cannot
   currently be honoured.
4. **Legal + OpenRouter routing** (§4) — gates the first real customer call.
5. **End-to-end as a buyer** (§3) — needs 4 done first.
6. Then the product work: CSV (§6), copy-shortcode (§9), visual pass (§5),
   WPML (§7), per-user folders (§8).
7. Debt (§10) alongside, whenever a gap is in the way.

## What needs Nathan before I can move

- §2: which of the three options for the overnight tier.
- §4: the bracketed items, and the two `CONFIRM` rows.
- §5: the one-line design direction, before anything is drawn.
- §8: what a private folder means when somebody else opens the library.

Everything else in this plan I can do without asking.
