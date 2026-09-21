# S29b — Wave 4: the walk on 4.0.2 + Pro 1.0.3, in two halves

**Date:** 2026-09-21, afternoon. **Model:** Opus 5 (the S29 session, continued
on "go on wave 4"). **From:** `docs/handoffs/2026-09-21-s29-the-release-train-4-0-2.md`.
**Plan:** `plans/finish-the-suite.md` — "Wave 4 — done in two halves" holds
the evidence line. Plugin `bdaef62` → this handoff's commit; service and pro
untouched (one licence row added, see below). **Spent: €0.50 on Nathan's
card, refunded by him the same hour; 1 credit of a box-issued row.** Nothing
on ms2 or the real shop.

## What Nathan said

"go on wave 4" → the walk started. At the account page: "asks me check
inbox", "yes confirmed and downloaded", "it renders but is not clickable …
because it says canceled" → the cause found (his own Refund in the Stripe
Dashboard, "yes i did") → a second `buy` opened → "i dont want to do the
whole thing again" → stopped, no second charge → "whats next" / "use the
methods" → the site half with the existing tools and an issued key.

## What was proven

| Step | Result |
|---|---|
| Cart with `WALK0921` | One site €39 → **€0.50**, "Then, each year €39.00" |
| Checkout | **Pay €0.50**; receipt to `nathan+buyer-0921@vergelabs.nl` |
| Payment | `pi_3UI5jVENqcXgcnrD176cYtNA` succeeded 11:51:54 UTC, card …7703; invoice **AWTXBCFC-0020** paid €0.50, subscription active to 2027-09-21 |
| Order page | "Your One site licence is ready", Paid €0.50, key `VGML-ZERQ…YYGC` on the page (shot 03) |
| Registration → confirmation → account | done in Nathan's own browser (the mail opened there); **Download Pro 1.0.3 (zip)** shown and downloaded |
| **Money back** (unscheduled) | Refund `re_…MojeI` from the Dashboard 11:56:22 → `moneyBack()` cancelled `sub_…SGaGPppU` 11:56:24 → account *Canceled*, download disabled. Exactly the written behaviour, seen live for the first time |
| Code reuse | `WALK0921` applied again on a fresh checkout (S25's "no redemption for subscriptions" confirmed); that checkout abandoned, its incomplete subscription expires on its own |
| `install` | Pro 1.0.3 from the served zip `9bb6c8ff5088` (26,380 bytes) on `upg`, beside free **4.0.2** (`d57c3020567e`, installed from the shelf first) |
| `connect` | licence **22** → **Connected 1 / 1, 2,000**, key option 95 chars (sealed), verify `sites_used 1` |
| `describe` | picture 23 in **14.4 s**: real alt and caption, **We wrote this**, credits **2,000 → 1,999**, `debug.log` unchanged |
| `restore` | seat **0/1**, key and state absent, Pro inactive 1.0.3, rows **0 differences**, 0 Pro lines |
| `final` | not run — the script's window never held the account session ("the walk session is gone"); S25's Billing/PDF/cancel-at-period-end stands |

Shots: `docs/superpowers/mocks/shots/2026-09-21-buyer-walk-01-cart, 02-checkout`
(the second run's — same name, overwritten), `03-order` (first run),
`10-licence-empty, 11-licence-connected, 12-media-list-described,
13-attachment-described`.

## What is left behind

- `upg`: free **4.0.2** active (was 4.0.1), Pro **1.0.3** inactive on disk
  (was 1.0.2), key absent, mock 1, picture 23 as snapshotted;
  `/root/walk-0921/` holds the two free zips, `walk-pro.zip`, the script
  and `pic23-before.sql`.
- Production DB: licence **22** (`box@vergelabs.nl`, single, 1,999) stays,
  as licence 20 and seven older box rows do — S26's "deleted after" was the
  env file. `deferred-work.md`.
- Stripe: customer `cus_VIfbP8ZXXvAGgH` with one refunded €0.50 and three
  abandoned incomplete subscriptions (0017–0019) from the morning's and
  this afternoon's checkouts; a fourth customer for `…0921b` with one more.
  All expire on their own.
- Account `nathan+buyer-0921@vergelabs.nl`: verified, one canceled licence.
  Nathan's to keep or delete.
- Scratch: `%TEMP%\vgml-walk-0921\` (profile, `buy.log`, `walk-state.json`,
  `walk-pro.zip`; the key file deleted by `restore`), `vgml-walk-0921b\`
  (profile, `buy.log`). Pulled env files deleted.

## Found, not done (rows in `deferred-work.md`)

1. `buyer-walk.mjs` watches its own window for the account page; the
   confirmation mail opens the default browser, so a real buyer's steps
   happen out of its sight and `buy` stalls, `final` cannot sign in. Poll
   the service instead. Same-day reruns overwrite the 01/02 shots.
2. `npx @wp-playground/cli` unpinned → 3.1.55, which cannot install; pin
   3.1.54 in one place.
3. Box-issued licence rows accumulate in production (nine).

## Next — S30, wave 5

```
Read docs/handoffs/2026-09-21-s29b-wave-4-the-walk-on-4-0-2.md, then
plans/finish-the-suite.md ("Waves", "The decision sitting"). State which
model you are and follow that profile in ~/.claude/harness/model-profiles.md.
This session is wave 5: write docs/decisions-2026-09.md, one row per
question with the evidence on file and a recommended answer; then I answer
in one pass and you write each answer where it lives. Stop points: nothing
on ms2 or the real shop; no answer lands in a doc or the code before I give
it; every user-facing string is mine. End with a handoff in docs/handoffs/.
```
