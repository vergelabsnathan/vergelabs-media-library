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

## Contract version

Every `/describe` response carries the three fields the plugin stamps each
description with, and stores them verbatim:

| Field | Meaning |
|---|---|
| `model` | the exact model that answered — never a `latest` alias |
| `model_version` | the provider's version string for it |
| `prompt_hash` | a stable hash of the prompt that produced this answer |

Without them a stored description is unreproducible: nothing says what asked
for it, so nothing can decide whether it is worth re-running. The plugin
invents none of the three — a hash the client made up would answer the
question wrongly rather than not at all — so a service that omits them leaves
the columns empty and every description looks equally unattributable.

## The attributes

`/describe` also returns a fixed set of enums per image. They are enums, not
free text, because their whole purpose is to be queried: `kind = document`
has to mean the same thing on every row or the column is worth nothing.

```json
{
  "kind":          "photo | illustration | screenshot | document | diagram | logo | other",
  "has_people":    true,
  "has_text":      false,
  "document_type": "invoice | receipt | contract | form | slide | report | other | null"
}
```

- `document_type` is `null` unless `kind` is `document`.
- `orientation` is **not** asked for. It is portrait, landscape or square,
  the plugin already knows the dimensions, and paying a model to measure a
  rectangle would be absurd.
- Unknown values are stored as given but never matched — a new enum member
  must ship in this document before it ships in a response.

The release test the plugin runs against these: the same file, three times,
same enums. An attribute that is not stable across runs is not an attribute,
it is a guess, and it must not become a column people filter on.

## POST /embed (designed, not built)

Image or text in, one vector out. Powers visual similarity, semantic search,
and the clustering the Librarian phase is built on.

```json
{ "license_key": "...", "site": "...", "image": "data:image/jpeg;base64,..." }
```

```json
{ "embedding": [0.0123, -0.0044, ...], "dims": 768, "model": "...", "credits": { "remaining": 4311 } }
```

**Storage is client-side, settled.** The vector lives in the plugin's own
table (`{prefix}vergeml_ai_index.embedding`) as packed single-precision
floats with a `dims` count beside it. The service stores nothing per file —
that is the same promise `/describe` already makes, and a service that held
vectors would be holding a representation of customer media.

`dims` is whatever the model returns, recorded per row rather than assumed,
so changing embedding model does not silently mix incomparable vectors: a
row whose `dims` or `model` differs from the current one is re-embedded
rather than compared.

## Roadmap endpoints (design before building)

- `POST /suggest-folder` — image + the site's folder names in, ranked folder
  suggestions out (auto-filing).

## Client-side key handling

The licence key is stored **sealed** (AES-256-GCM, key derived from the
site's `auth` salt), write-only through REST, masked in the UI, and never
exposed by `/ai-status` (a boolean `has_license` only). A copied database
does not yield working licences; changed salts read as "no licence" and
re-entering the key is the recovery.

## `/v1/guide`

`POST` with `license_key`, `site`, `mode` (`propose` | `turn`), `summary` (the plugin's library summary), `goal`, `current` (folders that exist), and for `turn` also `draft`, the last 20 `turns` and one `input` (`text` | `choice` | `edit`). `propose` answers `{ proposals: [{ name, tree }, { name, tree }] }`; `turn` answers `{ message, choices?, draft? }` where `draft` is the whole tree when it changed. Model `claude-opus-5` through OpenRouter, `thinking` disabled, 6,000 output tokens, one retry with the validation error. No credit is charged; more than 60 turns of history is refused (`too_many_turns`). Errors follow `/v1/folders`.

## `/v1/counts`

`POST` with `license_key`, `site` and `counts`: the plugin's snapshot (`vergeml_stats_snapshot()` in `core/instrument.php`), exactly nine keys -- `attachments`, `mimes` (type family to count, at most twelve families), `folders`, `depth`, `recent` (files added in the last thirty days), `plugin`, `wp`, `php`, `locale`. Sent once a day while "Share library counts" is on in Library settings; off by default; not sent without a key. The service authenticates as `/v1/guide` does (licence known, entitled, site activated; `401 bad_key`, `403`), refuses any other key or any non-integer count whole with `400 invalid_counts`, and upserts one row per licence, site and day into `library_counts`. Not metered. Answers `{ ok: true }`.

## `/v1/guide/session` and `/v1/guide/stream`

The streamed conversation, in two calls. `POST /v1/guide/session` from the plugin server-side with `license_key`, `site` and `summary` (the library summary the conversation is about) answers `{ token, expires_at, summary_hash }`: a JWT, HS256, one hour, claims `licence_id`, `site`, `summary_hash`. Metered as a guide turn with the same daily site limits. Signed with `GUIDE_TOKEN_SECRET`, or a key derived from `LICENCE_KEY_SECRET` when that is unset; rotating either revokes every token.

`POST /v1/guide/stream` from the browser with `Authorization: Bearer <token>` and `{ conversation: [{ role, text }], tree, input: { text | choice | edit | open: true }, summary, goal?, current? }`. The summary must hash to the token's claim. Answers `text/event-stream`: `say` events `{ text }` as the model writes, exactly one `tree` `{ tree }` (the whole tree, validated), then `done` `{ usage: { input, output }, choices }` (two or three chips, or none); or `error` `{ code }` with `provider_busy` (and `retry_after` seconds), `turn_cap` (25 assistant turns), `bad_tree` (the block failed twice), `failed`. Before the stream: `401 bad_token` with `why` in `missing | malformed | bad_signature | expired | site | summary | licence`, `403 not_entitled`, `400 empty_input`, `429` on the limits. CORS allows exactly the token's site; the preflight (no token) is answered for any origin that is an activated site. Metered as a guide turn. The model talks first and ends with a fenced ` ```tree ` block holding the tree and the chips; a block that does not parse is asked for once more, silently, with the words already streamed kept.
