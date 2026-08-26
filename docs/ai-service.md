# The VergeLabs AI service — contract

The plugin never talks to a model provider. It talks to this service, which
holds the provider keys, chooses the model and prompt, meters credits
**before** proxying, and answers in the shape below. The plugin side of this
contract is `core/ai.php`; keep the two in lockstep.

Recommended stack (matches the rest of VergeLabs): Next.js route handlers on
Vercel, Neon Postgres for licences/credits/usage, Stripe for purchase.

## Base URL

`https://ai.vergelabs.nl/v1` — hard-coded in the plugin. The only override is
the `VERGEML_AI_SERVICE` wp-config constant (development), and non-https
values are refused client-side. There is deliberately no WordPress filter:
requests carry the licence key, and a filter would let any co-installed
plugin redirect them to a harvester.

## POST /describe

One image in, one description out, one credit down.

Request (JSON):

```json
{
  "license_key": "vgml_xxxxxxxxxxxx",
  "site": "https://customer-site.example",
  "filename": "IMG_4822.jpg",
  "mime": "image/jpeg",
  "image": "data:image/jpeg;base64,..."
}
```

The image is a downsized intermediate (the plugin sends the largest
thumbnail under ~1.5MB, never the original).

Response `200`:

```json
{
  "caption": "One factual sentence about what the image shows.",
  "alt": "Short alt text for accessibility.",
  "tags": ["three", "to", "six", "lowercase", "keywords"],
  "title": "A Short Human Title",
  "credits": { "remaining": 4312 }
}
```

`credits.remaining` rides along on every answer; the plugin stores it for
display so balance checks never need their own request.

Errors, by status code — the plugin maps these to behaviour:

| Status | Meaning | Plugin behaviour |
|---|---|---|
| `401` / `403` | licence unknown, revoked, or site mismatch | run stops, "licence not accepted" |
| `402` | out of credits | run stops cleanly, "get credits" |
| `429` | slow down | (future: retry with backoff) |
| other non-200 | service trouble | file skipped with a stub, run continues |

`401/402/403` are **fatal to the batch**: the plugin breaks the loop rather
than burning the remaining batch on calls that will fail identically.

Server-side obligations:

1. **Meter first.** Decrement the credit inside the same transaction that
   admits the request; refund on provider failure. Never proxy before the
   licence and balance have cleared.
2. **Validate the licence against the site.** A licence is bound to a site
   URL on first use; a second site using the same key gets `403` (or your
   chosen multi-site policy).
3. **Own the prompt.** The description prompt and model live here, not in
   the plugin, so quality upgrades ship without a plugin release. Reply
   STRICT JSON in the shape above.
4. **Keep nothing.** Images are processed and discarded; store usage
   counters, not customer media.

## GET /credits (optional, later)

`?license_key=...` → `{ "remaining": 4312 }` for a dashboard widget. The
plugin currently learns the balance from /describe responses and does not
require this.

## Roadmap endpoints (design before building)

- `POST /suggest-folder` — image + the site's folder names in, ranked folder
  suggestions out (auto-filing).
- `POST /embed` — image or text in, vector out (visual similarity, semantic
  search v2). Decide storage client-side (postmeta) vs service-side before
  building.

## Client-side key handling

The licence key is stored **sealed** (AES-256-GCM, key derived from the
site's `auth` salt), write-only through REST, masked in the UI, and never
exposed by `/ai-status` (a boolean `has_license` only). A copied database
does not yield working licences; changed salts read as "no licence" and
re-entering the key is the recovery.
