# INITIAL — the watch, and support that knows what the watch knows

Two systems, asked for together on 2026-09-01, built in the PRP workflow
(`PRPs/`): this file is the request, each `PRPs/*.md` is a generated plan with
its own validation gates, and a plan is executed only against those gates.

## FEATURE

**System 1 — the update watch.** WordPress core, PHP and the plugins we
integrate with all release on their own clocks. The plugin must find out on the
day, prove for itself whether anything broke, and either fix the paperwork
alone (nothing broke) or hand Nathan a plan (something did) — with a fix already
drafted on a branch when the break is proven. Three outcomes:

- green: contract intact, suites pass → bump "Tested up to", commit, no human
- yellow: passes, but the changelog or deprecations touch our surface → plan as
  a GitHub issue, no code
- red: a symbol we depend on is gone, or a suite fails → issue with the plan
  AND a fix branch + PR from a non-interactive Claude Code run; a human merges

**System 2 — support that answers.** First-line support with the facts in
hand: the customer's plan/credits/sites/recent failures (service DB, Braintrust,
Stripe), the site's system report (already in the plugin), the docs, and the
known issues **the watch produces** — so "Elementor 3.30 broke the tree" becomes
a support answer with a workaround and an ETA the same hour it is found. It
resolves what it can, acts within a written policy (resend key, reset seat,
extend trial; refunds only when the failure is in our own logs and under a cap),
and escalates everything else to Nathan as a brief, never a raw forward.

## EXAMPLES

- `tools/verify.mjs` — the gate: 23 suites, skip-not-pass, box + Playground.
  The watch reuses it against a moving target; it does not invent a new runner.
- `tests/integrations/live.php`, `divi.php`, `polylang.php` — live checks against
  the real third-party plugin, one active at a time, run with `wp eval-file`.
  The stage runner calls these after an upgrade.
- `tools/deploy.mjs --box` — how code reaches the box and is verified by hash.
- `core/system-report.php` — the report the support system attaches.
- `service/lib/trace.ts`, `service/evals/describe.eval.ts` — Braintrust
  tracing and the eval shape every agent in system 2 must follow.
- `core/neighbours.php` — how the plugin already detects and adapts to other
  plugins; the contract file is the explicit version of what that file knows.

## DOCUMENTATION

- WordPress version check: https://api.wordpress.org/core/version-check/1.7/
- Plugin info: https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request[slug]=SLUG
- PHP releases: https://www.php.net/releases/index.php?json&version=8
- Trac (core changes by component): https://core.trac.wordpress.org/query?milestone=X&component=Media&format=csv
- PHPCompatibilityWP: https://github.com/PHPCompatibility/PHPCompatibilityWP
- WordPress Playground CLI `--php`: https://wordpress.github.io/wordpress-playground/developers/local-development/wp-playground-cli
- GitHub CLI for issues/secrets/workflows: `gh issue create`, `gh secret set`, `gh workflow run`
- OpenRouter (the only way any model is called): https://openrouter.ai/docs
- Braintrust logging/evals: https://www.braintrust.dev/docs
- wordpress.org forum guidelines (no automated posting): https://wordpress.org/support/guidelines/

## OTHER CONSIDERATIONS

- Models are reached through OpenRouter, always. No ANTHROPIC_API_KEY anywhere.
- The paid plugins (WPBakery, Divi, the Pro tiers) have no update API without a
  licence: their signal is a changelog page. Say so in the report rather than
  pretending.
- The box has one real site, one multisite network and (from PRP-1) one stage
  site the watch may upgrade freely. The watch never touches the other two.
- `wp eval-file` runs a suite inside a function; `global $pass` binds to
  nothing. Use `$GLOBALS['vgml_fail']` (see memory: suites that cannot fail).
- A green outcome may commit to main; nothing red or yellow may. No outcome
  ever deploys to wordpress.org.
- Refunds and anything with money: policy file, cap, escalation. Never
  "use judgement".
- wordpress.org forum: drafts only; a human posts.
- Tickets, reports and embeddings stay in the EU Supabase; the pending DPA must
  name OpenRouter as a processor.
- Notification channel is GitHub issues + an email digest from the service;
  Slack/Telegram is a later one-liner, not a design input.
- Build order: PRP-1 the watch → PRP-2 in-plugin Get help + known-issues feed
  → PRP-3 the support agent. Autonomous fix PRs and financial actions are the
  last increments of PRP-1 and PRP-3, not their first.
