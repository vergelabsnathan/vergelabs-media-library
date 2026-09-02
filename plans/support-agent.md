# Support that answers — Get help in the plugin, an agent with the facts, escalation as a brief

## Problem

There is no support system because there are no customers yet. That is the
right moment to decide what one looks like, and the wrong moment to build all
of it. What exists that support will need:

- `core/system-report.php` — versions, PHP, active plugins, safe-mode state,
  the AI status. Rendered on a screen; not sendable.
- The service knows everything about a licence: plan, credits, sites, seat
  activations, every describe outcome (Braintrust spans carry licence id, site,
  model, reason), Stripe subscription and invoices.
- `readme.txt`, the changelog and `docs/` — the product knowledge, in prose.
- After PRP-1, `tools/watch/known-issues.json` — what is broken by which
  release, the workaround and the fix status.

What does not exist: a way for a customer to reach us that carries those
facts, anything that reads them, and any rule about what may be done without
Nathan.

## User story

As a customer whose describes stopped working after a plugin update, I want an
answer that already knows my site, my plan and whether this is a known problem,
so that I get a fix or a workaround in minutes rather than a "please send your
system report" the next morning.

As Nathan, I want what reaches me to be a brief — what they asked, what is
known, what was tried, what is recommended — so that the hour I have for
support goes on decisions, not on discovery.

## Decisions taken

- **Three increments, shipped in order; each useful alone.**
  1. In-plugin **Get help**: a screen that shows the system report, the
     known issues that match this site (by installed plugin + version), and a
     one-click "send this with my question" that posts report + question to
     the service with explicit consent text. No agent yet — the question lands
     in an inbox table and mails Nathan.
  2. The **agent** on the inbox: tools for customer lookup, report parsing,
     knowledge search and known issues; drafts an answer; sends it when
     confidence is high and no policy line is touched; otherwise escalates
     with a brief.
  3. **Actions under policy**: resend key, reset seat, extend trial
     autonomously; credit refunds only when the failure is in our own logs
     and under the cap; everything else escalates.
- **Channels**: Get help (1), email to support@vergelabs.nl parsed into the
  same inbox (2), wordpress.org forum as drafts only — a human posts, per the
  forum guidelines (2), site chat not before there are tickets to learn from.
- **Facts before words.** The agent may not answer a technical question
  without the report, or a financial one without the customer record. If
  either is missing it asks for it, with the Get help link.
- **Knowledge** = readme, changelog, docs, resolved tickets, known issues.
  Embeddings in the same EU Supabase (pgvector), not a second vector store.
- **Every conversation is traced to Braintrust** under a `support` project,
  and evaluated against a fixture set of tickets with scorers for: invented
  no fact not in a tool result; escalated when a policy line applied; used
  the report when it was there; answer length. The same discipline as
  `evals/describe.eval.ts`.
- **Escalation brief** format: question, customer facts, matching known
  issues, what was tried, recommendation, one-click actions. Delivered as a
  GitHub issue in a private `support` repo and an email.
- **Policy is a file**, `service/support/policy.md`, with numbers. Refund cap
  default: one month of the plan's credits. Anything with the words refund,
  chargeback, GDPR, delete my data, lawyer, or an angry tone score above the
  threshold: escalate, do not answer.
- **Models through OpenRouter.** Answer drafting: Sonnet (through OpenRouter); brief
  writing: same; classification: Haiku.
- **Privacy**: the report contains site URL and plugin list; the consent line
  says so; reports are deleted 90 days after a ticket closes; the DPA names
  OpenRouter as a processor.
- OPEN: the refund cap and whether refunds are credits-back or money-back.
  Increment 3 does not start until this is a number in `policy.md`.
- OPEN: whether the escalation channel is email only, or also Slack/Telegram.

## Out of scope

- Live chat, phone, WhatsApp.
- Posting to wordpress.org automatically.
- Any action on Stripe beyond reading, until increment 3 and the policy line.
- Multilingual answers beyond English and Dutch.
- A public help centre site (the docs stay in the repo and the readme).

## Context

**Files to read first**
- `core/system-report.php` — what the report contains and how it is built.
- `core/journey.php` — how admin screens are composed; Get help is one more.
- `core/options-pages.php` — the settings screen registration pattern.
- `service/lib/store.ts`, `service/lib/db.ts` — how the service reads and
  writes; the inbox and knowledge tables follow.
- `service/app/api/ai/describe/route.ts` — auth by licence key, tracing,
  the shape a new route copies.
- `service/lib/trace.ts` — Braintrust init; the support project is a second
  logger.
- `service/evals/describe.eval.ts` — the eval shape.
- `tools/watch/known-issues.json` (PRP-1) — the feed's shape.
- `readme.txt`, `docs/` — the knowledge corpus.

**Files that change**
- `vergelabs-media-library.php` — include the Get help screen inside the
  safe-mode guard? No: **outside** it, so a site in safe mode can still ask
  for help. Load after `core/watchdog.php`.
- `core/system-report.php` — add `vergeml_system_report_array()` returning
  the report as data.
- `service/lib/store.ts` — inbox, knowledge, policy-event tables.
- `service/lib/anthropic.ts` — a second call shape for chat with tools.

**Files created**
- `core/get-help.php` — the screen, the known-issues match, the consent
  send.
- `service/app/api/support/ticket/route.ts` — receives report + question,
  authenticated by licence key or, for free users, a per-site token.
- `service/app/api/support/known-issues/route.ts` — serves the feed, filtered
  by the plugin list the site sends.
- `service/support/policy.md`, `service/support/tools.ts`,
  `service/support/agent.ts`, `service/support/brief.ts`.
- `service/evals/support.eval.ts`, `service/eval/tickets/*.json`.
- `service/scripts/index-knowledge.ts` — readme/changelog/docs → pgvector.

**Prior art**
- The licence-key auth and describe tracing in the service.
- The Health screen's "what it collects" copy — the consent text follows it.
- The journey list: Get help becomes a card there and a menu item.

**External docs**
- https://openrouter.ai/docs/features/tool-calling
- https://supabase.com/docs/guides/ai/vector-columns
- https://www.braintrust.dev/docs/guides/logging
- https://wordpress.org/support/guidelines/

## Status

- **Increment 1 shipped 2026-09-01** (plugin `a440d03`, service): `core/get-help.php`
  (loads outside the safe-mode guard), `/v1/support/ticket` → `support_tickets`
  (migration 008) → mail to SUPPORT_EMAIL. `tests/tree/get-help.mjs` 10/10 on the
  box; ticket #1 is the wiring test. Decision taken in execution: the known-issues
  feed is read straight from the public repo's raw
  `tools/watch/known-issues.json` (cached 12h in a transient), so no service route
  and no table for it — the watch commits the file, every install sees it within a
  day. Task 3's "synced table" is therefore not needed.
- **Increment 2 shipped 2026-09-01 in draft mode** (service `0b2bc95`): `support/agent.ts`
  + `support/tools.ts` + `support/policy.{md,ts}`; knowledge in `support_knowledge`
  (pgvector, 151 chunks via `pnpm support:index`); `/api/support/inbound` for Resend
  `email.received`; `evals/support.eval.ts` — escalated_when_required 100%,
  invented_no_number 100%, answered_when_clean 85.7% (the safe-mode case asks for the
  fatal log at 0.55 confidence, which is the right call). Ticket #2 is the live proof:
  drafted, briefed, escalated. Decisions taken in execution: the draft is a forced
  tool call (OpenRouter did not honour the structured-output format); one Braintrust
  project with span name `support`, not a second project; escalation is email only
  (no private GitHub repo) until Nathan asks otherwise.
- **Email-in live 2026-09-01**: `in.vergelabs.nl` verified on Resend (EU), webhook →
  `/api/support/inbound`, secret in Vercel. Ticket #3 = Svix-signed synthetic event, ticket
  #4 = a real mail to support@in.vergelabs.nl, both drafted and briefed. First real mail
  exposed a language slip (English question, Dutch draft, swayed by the .nl sender): the
  agent now gets a language hint from the question's own words and a mismatch is a
  reason to brief. Still Nathan's: the Google Workspace forward
  support@vergelabs.nl → support@in.vergelabs.nl (a Group with one external member).
- ~~**Needs Nathan before email-in works**~~ (done, see above): a receiving domain on Resend (do NOT put an MX
  on vergelabs.nl itself — that would take over his mailbox; use a subdomain such as
  `inbound.vergelabsmedia.com` and forward support@ to it, or the Resend-managed
  `<id>.resend.app` address), a webhook for `email.received` pointing at
  `https://ai.vergelabs.nl/api/support/inbound`, and its secret in Vercel as
  `RESEND_WEBHOOK_SECRET`.
- `SUPPORT_AUTOSEND` stays unset. The two must-be-100% scorers are, but there are no
  real tickets yet; switch it on after the first twenty real briefs read well.
- **Increment 2b, the ticket desk, shipped 2026-09-01**: `/admin/tickets` (queue by status,
  thread, facts, the agent's draft in an editor: send / edit and send / private note / close /
  ask again), replies from `support@vergelabs.nl` tagged `[#id]` so the customer's answer
  threads back (`/api/support/inbound` continues the ticket and the agent drafts against the
  whole thread), `ticket_messages` (migration 012) keeps drafts beside sent replies for the
  eval set. Proven with ticket #5 (two messages, two drafts, `continued: true`).
  Not yet: the morning digest, grouping by known issue, and admin pages for customers/licences.
- Increment 3 not started; the refund-cap OPEN line still stands.

## Tasks

Increment 1 — Get help (plugin + one route)
1. `vergeml_system_report_array()` in `core/system-report.php`; the screen
   renders from it.
2. `core/get-help.php`: menu item under the plugin's menu; shows the report,
   fetches `/api/support/known-issues?plugins=slug:version,...` (cached one
   hour in a transient), lists matches with workaround and fix status;
   question textarea; consent checkbox with the exact list of what is sent;
   POST to `/api/support/ticket`. Loads outside the safe-mode guard.
3. Service: `inbox` table (id, licence_id nullable, site, email, question,
   report jsonb, known_issue_ids, status, created); `/api/support/ticket`
   inserts and emails Nathan; `/api/support/known-issues` reads the JSON
   file committed by the watch (fetched from the repo at build time, or
   synced into a table by a script — decide in execution, prefer the table).
4. Playwright: open Get help on Playground, see the report, tick consent,
   send, assert the row exists via the service's test DB.

Increment 2 — the agent
5. `policy.md` with the escalation words and the (OPEN) numbers.
6. `tools.ts`: `customer(licence|email)`, `report(ticket)`, `knowledge(q)`,
   `knownIssues(plugins)`, `escalate(brief)`. Read-only except escalate.
7. `agent.ts`: classify (Haiku) → gather (tools) → draft (Sonnet) → check
   against policy → send or escalate. Every step a Braintrust span.
8. Email in: a Resend/Postmark inbound webhook (whichever the service already
   uses for outbound — check `service/lib/` first) → inbox row.
9. `support.eval.ts` over 20 fixture tickets (real shapes: safe-mode site,
   expired licence, describe 402, Elementor known issue, refund demand, GDPR
   request); scorers as decided.

Increment 3 — actions under policy
10. `actions.ts`: resend key, reset seat, extend trial, credit refund ≤ cap;
    each writes a `policy_events` row; refund requires a matching failed
    describe in Braintrust within 30 days.
11. Escalation brief gains one-click action links that call the same
    functions with Nathan's session.

## Validation strategy

- Gate 1–3 for the plugin side; gate 6 (Plugin Check) because a new admin
  screen and an outbound request are exactly what it inspects.
- Gate 4 with the new Get help Playwright suite added to `tools/verify.mjs`.
- Service: `pnpm typecheck` before every push (the batch route lesson);
  `support.eval.ts` must score 100% on "invented no fact" and "escalated when
  a policy line applied" before increment 2 answers a real person.
- Query-count budget for the known-issues route: 1 (one select on the
  synced table).

## Risks

- **A confident wrong answer** is worse than no answer. The eval's two
  100%-required scorers exist for this; below 100% the agent drafts and
  Nathan sends.
- **Safe mode**: Get help must load when the rest of the plugin does not,
  which is why it sits outside the guard — and therefore must be trivially
  safe itself: no table, no REST route, one outbound request on click.
- **PII in Braintrust**: spans carry the report; the report carries the
  site URL. Redact emails in span inputs; keep licence id as the key.
- **Free users have no licence key**: the per-site token is a random value
  stored in an option, sent with the ticket, good only for that site's own
  tickets.
- **wordpress.org forum**: never automated; the draft lives in the inbox
  for Nathan to paste.

**2026-09-01 — the investigator (increment: analysis before answers).** `support/investigate.ts`
runs a tool loop (licence_state, describe_telemetry via Braintrust BTQL, credit_ledger,
site_report, known_issues, search_docs → forced `diagnose`) before drafting; `support/telemetry.ts`
reads per-licence describe spans and the ledger. runAgent uses it with single-shot draftAnswer as
fallback; the diagnosis (checked/findings/cause/could-not-verify) is stored on the ticket, shown
in Nathan's brief. Shared shapes moved to `support/draft.ts` (circular-import TDZ broke next build).
Proven live on ticket #7: zero activations + zero telemetry + untouched balance → "key never
activated on the site", confidence 0.55, escalated with the one missing fact named.
