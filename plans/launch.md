# Plan — launch

Written 2026-09-09. Spans three repos: `plugin`, `pro`, `service`.

**The point of this plan: you can start selling Pro without WordPress.org.** The
shop is already live at `vergelabsmedia.com`, the plans are priced, the cart
links work and the licence machinery answers. WordPress.org is a distribution
channel with weeks of review latency; it is not a gate on revenue. So the
plan is two tracks, and only the first one has to happen before a first sale.

## What is already true, measured 2026-09-09

- Plugin **3.16.1**, pro **1.0.1**, everything committed and pushed.
- The shop answers: `/api/pricing` returns the real plans (€39 / €79 / €249 /
  €149, credits €29–€179), the credits floor reads **500** — the `CREDITS_MIN=69`
  override from the €1 walk is gone from production.
- `vergelabsmedia.com` serves the site with working `/cart?plan=…` links.
- `/api/plugin/update` answers and is licence-gated for pro; `/api/plugin/download`
  refuses without a token (403), which is correct.
- The submission assets exist: banner and icon are in `plugin/assets/`.

## The one thing that would embarrass a launch

**The release catalogue in production is two and a half weeks stale.**

| what | production says | the repo says |
|---|---|---|
| free plugin | **3.0.0**, a 332 KB zip from **23 Aug** | 3.16.1, 952 KB |
| pro | 1.0.1, a 20 KB zip from **22 Aug** | 1.0.1, 24.5 KB, built 7 Sep |

`PLUGIN_RELEASES` (a Vercel env var, read by `service/lib/updates.ts`) and the
files in `service/public/releases/` are what a customer actually receives. A
buyer today pays for the Librarian, Folders and traces work and downloads a
build that predates all of it.

**And `dist/` is stale too**: `vergelabs-media-library-3.16.1.zip` was built on
7 Sep at 19:09, and **56 commits** have landed since — every phase of
`plans/traces.md`.

---

## Track A · Selling, today

### A1 · The artefacts are current — about an hour

**Files.** `plugin/dist/`, `pro/`, `service/public/releases/`, and the
`PLUGIN_RELEASES` env var in Vercel production.

**Do.**
1. Rebuild the free zip from `main` (`node tools/deploy.mjs --zip`) and the pro
   zip. Assert the header version, `VERGEML_VERSION` and `Stable tag` agree —
   the build already asserts this; read its output rather than trusting it.
2. Copy both into `service/public/releases/`, keeping the hashed-name
   convention the pro file already uses.
3. Update `PLUGIN_RELEASES` in Vercel production: free **3.16.1**, pro **1.0.1**,
   with a real changelog line for each.
4. Deploy the service.

**Proof — build identity, not a status code.** After the deploy, ask the live
update route and check the *bytes*, not the 200:

```
/api/plugin/update?slug=vergelabs-media-library&version=0.0.1   -> version 3.16.1
HEAD on the package URL it names                                -> the new size
```

Then install that served zip on a clean Playground and confirm it reports
3.16.1 and no fatal. A zip that 200s and installs 3.0.0 is the failure this
step exists to catch.

### A2 · The money is real — about forty-five minutes, and Nathan is in it

**Do.**
1. Confirm production is on **live** Stripe keys. `service/scripts/stripe-live-setup.ts`
   refuses to run unless `STRIPE_SECRET_KEY` is a live key; it is the script that
   creates the live-mode products, prices and webhook. Confirm the webhook
   endpoint and its signing secret are the live ones.
2. **Nathan buys one Single (€39) with a real card**, from
   `vergelabsmedia.com/cart?plan=single`, as a buyer — not as a sequence of
   component checks.

**Proof — the whole path, end to end.** Every one of these, in order:
the charge in the Stripe **live** dashboard; the licence email arrives; the key
activates on a real WordPress site; pro downloads through `/api/plugin/download`
and installs; the invoice renders; `/account` shows the purchase and the credits.

Then refund the charge and confirm the licence behaves as designed afterwards.

**Why it is a person and not a suite.** The last time this was checked by
component, every part returned 200 and the customer still got nothing.

### A3 · Announce

Only after A1 and A2 are both green. Nothing here is a code change.

---

## Track B · WordPress.org, in parallel — does not block selling

1. **Nathan: the WordPress.org username**, with two-factor enabled.
   `readme.txt` still reads `Contributors: vergelabsnathan`, which is a GitHub
   handle. Nobody else can supply this.
2. **Plugin Check on 3.16.1**, all five categories, in Playground. It was last
   verified clean at **3.3.0** — before the Librarian, Folders and traces. Fix
   to zero errors and zero warnings.
3. Set `Contributors:`, rebuild, submit.
4. Expect weeks of review. Plan around it rather than waiting on it.

`plugin/docs/wordpress-org-submission.md` is the checklist, and it is written
against 3.3.0 — treat its "done" column as needing re-verification, not as
fact.

## Nathan's, before the first invoice

- **VAT.** Tenerife means a non-EU supplier selling into the EU: destination VAT
  from the first euro, and Stripe will not run the right OSS scheme by itself.
  Decide how invoices handle it before there is a stack of them, not after.

## Out of scope for launch

The file renamer stays gated behind `VERGEML_FILE_RENAME`. The three hand-kept
RTL sheets stay hand-kept. The "share of drafts changed before filing" number
stays recorded and unshown. None of these blocks a sale.
