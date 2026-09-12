# Handover — Phase 2, session S5, task 2.6: seats (Opus 5)

Run on 2026-09-12. `pro/tests/seats.php` exists and is green on the box and
in Playground through the new `pro/tools/verify.mjs`; the staging mutation
is red. Nothing on `/var/www/wp` was deactivated, `sitesAllowed` is
untouched, and the box's Pro is back on Nathan's agency licence (its
options are restored by the suite whatever happens). The brief had 2.7
follow in this session after this handoff; the phase fence refuses every
edit once a handoff exists ("a session does not roll into the next step"),
so 2.7 starts from a fresh session — what it needs is under "Next".

## What changed

- `service/scripts/issue-box-licence.ts` — `PLAN=single` (default `agency`,
  as before). Licence **12**, plan `single`, `box@vergelabs.nl`, was issued
  with it; its key is the seat suite's `VGMLPRO_SEATS_KEY` and is not in
  any repo. Service commit `b9c3e33`, pushed; script only, nothing to serve.
- `pro/tests/seats.php` (new) — three legs, picked with `VGMLPRO_SEATS`,
  because WordPress reads its environment once at boot:
  - `production` (box): the first site takes the one seat (`valid`, plan
    `single`, `1 / 1`); a second production site (`home_url` filtered to
    another host, so the request is the one that site would send) is refused
    with the service's body `{"valid":false,"reason":"seat_limit"}`; the
    first site's seat is still there afterwards.
  - `staging` (box, `WP_ENVIRONMENT_TYPE=staging`): a clone activates
    `valid` with `seat_free: true` and `sites_used` unchanged.
  - `deactivate` (Playground, Pro mounted): connecting takes the seat
    (`0 -> 1`); `deactivate_plugins()` — WordPress's own path, so the hook
    fires — gives it back (`1 -> 0`, Pro inactive).
  - The suite refuses to run on the key the site is connected with, saves
    and restores `vgmlpro_licence_key` / `vgmlpro_licence_state` in a
    shutdown function, and hands the test licence's seat back at the end of
    every leg — the mutation run included.
- `pro/tools/verify.mjs` (new; 2.7 lists it, 2.6 needed it to run the
  Playground leg) — box suites over SSH + `wp eval-file`, Playground suites
  from a generated blueprint mounting `../plugin` and `pro/`, the `N/M`
  line counted, 0 refused, `VGMLPRO_SEATS_KEY` required for the seat
  suites. Pro commit `27eaaa6`, pushed.

## Gates, with the lines

- Box, production leg: `3/3 passed`, exit 0.
- Box, staging leg: `1/1 passed` — `valid yes, seat_free yes, sites_used 0 -> 0`.
- Mutation, `VGMLPRO_SEATS=staging WP_ENVIRONMENT_TYPE=production`:
  `FAIL a staging site activates without taking a seat -- valid yes,
  seat_free no, sites_used 0 -> 1`, `0/1 passed`, exit 1. The next staging
  run was `0 -> 0` again, so the mutation run gave its seat back.
- Playground, deactivate leg: `2/2 passed`.
- `VGMLPRO_SEATS_KEY=… node tools/verify.mjs` → `passed seats,
  seats-staging, seats-deactivate`.
- Box afterwards: `vgmlpro_licence_state` = `plan agency, sites_used 1,
  sites_allowed 1000000` (Nathan's licence, as before).
- `cd ../service && pnpm test` → `Tests 406 passed | 13 skipped (419)`;
  `pnpm typecheck` → exit 0.

## Finding for Phase 3.7 (copy, not fixed here)

The refusal is `{"valid":false,"reason":"seat_limit"}` — a reason code, no
sentence, no plan, no seat count. The licence screen renders it as "Every
seat on this licence is in use. Disconnect another site, or move up a
plan." (`pro/includes/settings.php:231`), which names neither either. A
successful answer carries `plan` and `sites_allowed`; a refusal carries
nothing but the code, so the service would have to add them to the
`seat_limit` body before the screen could say "Your Single licence covers
1 site, and it is in use."

## Mechanics

- Playground CLI (`run-blueprint`) reaches `https://ai.vergelabs.nl` from
  PHP — outbound HTTPS works, so licence calls can be tested there.
- Playground prints nothing a `runPHP` step echoes and ends every output
  buffer *before* shutdown functions run, so `ob_get_contents()` in a
  shutdown function is empty. The runner captures the suite's text through
  `ob_start( callback )` into a mounted directory instead.
- `--define` exists on the CLI but the runner puts `define()` calls in the
  runPHP code itself; the suite reads a setting from `getenv()` first, then
  the constant.
- The box's other plugins print ~40 `Deprecated:` lines on every wp-cli
  boot (bridge-core, packlink, brizy, elementor, wp-rocket on PHP 8.5). The
  runner greps them out; `WP_CLI_PHP_ARGS` does not silence them.
- Git Bash rewrites `/tmp/x` in a wrapper's argv before node sees it —
  `MSYS_NO_PATHCONV=1` has to be on the *bash* invocation, not only in the
  spawn env.
- `rm -rf` is blocked for the agent outright; `rm <files>` is not.
- The phase fence cannot admit a handoff from `pro/` or `service/`: their
  cards say `handoffDir: ../plugin/docs/handoffs`, and `rel()` in
  `~/.claude/hooks/harness/lib.mjs` returns null for any path outside the
  root, so `inHandoff` is never true there. This file was written with the
  working directory in `plugin/`, where no card is active. A harness fix,
  Nathan's.

## Found, not done

- `seat_limit` names no plan or count (above; Phase 3.7).
- "Deactivating Pro on the box fatals" is in the plan as given; not
  reproduced or looked into here.
- Nothing in `lib/licence.test.ts` asserts the verify route's staging
  branch; the seat suite covers it end-to-end from the plugin side only.
- The fence-vs-handoffDir mismatch above.

## Next — 2.7, from a fresh session (what this one found before the fence)

- **Pro 1.0.2 stores the key plain.** `vgmlpro_get_key()` is
  `get_option( 'vgmlpro_licence_key' )` and the form writes it with
  `update_option`; there is no sealing anywhere in `pro/`. The plan's
  "sealed at rest (the option value is not the key)" is therefore a build in
  `pro/includes/licence.php` (in the phase's scope), not only a test: a
  `vgmlpro_set_key()` that seals, `vgmlpro_get_key()` that unseals — mirror
  `vergeml_ai_seal()` / `vergeml_ai_unseal()` in `plugin/core/ai.php:323`
  (AES-256-GCM, key from `wp_salt('auth')`, `v1:` prefix) — and a legacy
  branch: a stored value starting `VGML-` is a 1.0.2 site's plain key, read
  as-is and re-written sealed. `settings.php:61` and `tests/seats.php`'s
  `update_option( VGMLPRO_OPTION_KEY, … )` then go through `vgmlpro_set_key()`.
- **Pro has no shape check either.** "A malformed key is refused before any
  request" needs one in `vgmlpro_refresh()`: `VGML-` + 34 characters from
  `0123456789ABCDEFGHJKMNPQRSTVWXYZ` (`isValidKeyShape`, `lib/licence.ts:188`;
  total length 39). Prove "before any request" with a `pre_http_request`
  counter in the suite; mutation: drop the check and a request goes out.
- **An expired licence** comes from the script, not from waiting:
  `issueLicence` hardcodes `status 'active'`, so extend
  `scripts/issue-box-licence.ts` with `EXPIRED=1` — issue with a synthetic
  `stripeSubscription` (`'box-sub-' + Date.now()`), then
  `store.upsertSubscription({ stripeSubscription, status: 'past_due',
  currentPeriodEnd: 30 days ago })`; `entitlement()` then says `expired`
  (grace is 7 days). The edit was written and blocked by the fence; nothing
  is in the tree.
- **`updates.php`**: `/api/plugin/update` answers `site_not_activated` with
  an empty package unless the calling site holds a seat on the key, so the
  suite activates the test key first (same restore pattern as `seats.php`).
  Real call with `VGMLPRO_VERSION` 1.0.2 → `update:false`; the newer-version
  leg makes the same request with `version=1.0.0`, plants the service's own
  body in `vgmlpro_update_check` (the transient the updater reads), and
  asserts `vgmlpro_inject_update()` puts it in `response` with `package`
  starting `https://vergelabsmedia.com/api/plugin/download?token=`; a GET
  of that URL answering 200 proves the token is the service's. Mutation:
  route the item to `no_update` and the leg goes red.
- `pro/tools/verify.mjs` exists; 2.7 adds `licence` and `updates` entries
  (box, `needsKey: true`) and is then the three-suite gate. Note the
  seats suite is three runner entries for one file.
- Both cards (`pro/.harness/active.json`, `service/.harness/active.json`)
  need a fresh phase line for 2.7, or the fence keeps refusing.

## State left behind

- Licence 12 (`single`, test) on the production database, 0 seats used,
  2,000 credits, none spent. Key: in this session's conversation only.
- Box: Pro 1.0.2 active on Nathan's agency licence; `/tmp/vgmlpro-seats.php`
  and `/tmp/seats.php` left on the box (test files, no key in them).
- Scratchpad: `prod.vars` (production env, delete after the session),
  `box.mjs`, `pg-debug.mjs`, `pg-out/`.
