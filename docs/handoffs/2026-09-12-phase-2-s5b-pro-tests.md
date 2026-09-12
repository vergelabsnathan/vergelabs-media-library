# Handover — Phase 2, session S5b: task 2.7, Pro gets tests (Opus 5)

Run on 2026-09-12, the same session as 2.6 after Nathan's "none are mine,
all are yours": the two harness fixes, both phase cards and 2.7 itself. Pro
now has three suites and a runner; the licence key is sealed at rest and a
malformed key never leaves the site, which 2.7 lists as behaviour and 1.0.2
did not have. Every mutation named in a suite's header is red on the box.

## What changed

### Harness (`~/.claude/hooks/harness/`)

- `lib.mjs` — `isHandoffPath(p, active)` compares absolute paths, so a card
  whose `handoffDir` is `../plugin/docs/handoffs` (pro, service) recognises
  its handoffs; `buildEdits(state, active)` gives the first and last *build*
  edit of a session with handoff edits excluded against the card in force,
  whichever cwd recorded them.
- `audit-log.mjs` — records every edit by absolute path as well
  (`editsAbs`, latest; `editsAbsFirst`, first), so a handoff written from
  another repo's cwd is still a handoff at stop time.
- `phase-stop.mjs` — judges from `buildEdits()`; the last-edit-is-the-handoff
  race is gone.
- `phase-fence.mjs` — `inHandoff` by absolute path; the handed-off check uses
  `buildEdits().first`; and **the card is the switch**: `.harness/active.json`
  written after the newest handoff opens the next step. The agent still
  cannot edit the card with an edit tool.
- `lib.mjs` / `phase-fence.mjs` — `~/.claude/projects` (memory) and
  `~/.claude/hooks` are never fenced; they are not project work. Not all of
  `~/.claude`: the hook tests build fixtures under `~/.claude/harness/`.
- `test-hooks.mjs` — ten new checks (sibling repos, the hub's cwd, the card
  reopening, the memory dir). `node test-hooks.mjs --fast` → `115/115 passed`.

### Cards

Both written by a node script (delegated in so many words), committed:
pro `.harness/active.json` → "S5b: 2.7 Pro tests", scope adds
`includes/settings.php`; service → "S5b: 2.7", scope
`scripts/issue-box-licence.ts`, `docs/**`.

### Service — `956e8c2`, pushed

- `scripts/issue-box-licence.ts` — `EXPIRED=1` issues with a synthetic
  subscription id and marks it `past_due` with the period end 30 days back,
  past the seven grace days. Licence **13** (`single`, expired) was issued
  with it. Script only; the deploy carries no code change.

### Pro — `a57bc77`, pushed

- `includes/licence.php`:
  - `vgmlpro_seal()` / `vgmlpro_unseal()` — the free plugin's method verbatim
    (AES-256-GCM under `wp_salt('auth')`, `v1:` prefix).
  - `vgmlpro_set_key( $plain )` seals; `''` deletes the option.
  - `vgmlpro_get_key()` unseals a `v1:` value; a value starting `VGML-` is a
    1.0.2 site's plain key, returned as-is and written back sealed.
  - `vgmlpro_key_shape_ok()` — `VGML-` + 34 of `0123456789ABCDEFGHJKMNPQRSTVWXYZ`,
    the service's `isValidKeyShape`; `vgmlpro_refresh()` refuses anything
    else with reason `invalid_key` before any request.
- `includes/settings.php` — the Connect form writes through `vgmlpro_set_key()`.
- `tests/seats.php` — writes the test key through `vgmlpro_set_key()`.
- `tests/licence.php` (new, 5 checks), `tests/updates.php` (new, 4 checks).
- `tools/verify.mjs` — `licence`, `updates`, the four `api-base` modes, the
  three seat legs; `VGMLPRO_EXPIRED_KEY` for the licence suite.

The box's Pro carries the working tree's `includes/licence.php` and
`includes/settings.php` (shipped by scp for the run — the sealing has to be
on the box for the suite to test it). Its own key migrated on first load:
`vgmlpro_licence_key` now starts `v1:` and opens (`plan agency`). **No
release was cut**: 1.0.2 is still what the channel serves; the sealing and
the shape check are 1.0.3 material.

## Gates, with the lines

- `VGMLPRO_SEATS_KEY=… VGMLPRO_EXPIRED_KEY=… node tools/verify.mjs` →
  `licence 5/5`, `updates 4/4`, `seats 3/3`, `seats-staging 1/1`,
  `seats-deactivate 2/2` (Playground); `passed licence, updates, seats,
  seats-staging, seats-deactivate`.
- Mutations on the box's copy, restored after each:
  - `vgmlpro_set_key()` storing `$plain` → `FAIL the key is sealed at rest
    -- option starts "VGM", 39 chars` and `FAIL a 1.0.2 site's plain key …`,
    `3/5 passed`.
  - shape check dropped → `FAIL a malformed key is refused before any
    request -- valid no, reason "refused", requests sent 1`, `4/5 passed`.
  - update item routed to `no_update` → `FAIL … -- listed under no_update`,
    `3/4 passed`.
- `api-base` through the runner: `https 2/2`, `insecure 1/1`,
  `localhost 1/1`; **default mode `2/3`** — "the base agrees with the free
  plugin" fails on this box because `wp-config.php:101` defines
  `VERGEML_AI_SERVICE = http://127.0.0.1:3100/v1` (the local `vgml-service`
  under pm2, the capacity fixture) while Pro talks to `ai.vergelabs.nl`.
  A box condition, pre-existing; not in the brief's gate and not changed.
- Box afterwards: `plan agency`, `key opens`.
- `cd ../service && pnpm test` → `Tests 406 passed | 13 skipped (419)`;
  `pnpm typecheck` → exit 0.
- Deploy: `vercel ls --prod` showed `inx1005kn` Building 12 s after the
  push, Ready after 25 s; `/api/build` →
  `{"sha":"956e8c2dd78bdf825caf695c7ff558384c245e59","ref":"main","deployed":"dpl_CaHMDSPzpfmiU5rDwjXLzxCL5NEt"}`.
- Harness: `node ~/.claude/hooks/harness/test-hooks.mjs --fast` → `114/114 passed`.

## Copy for Phase 3.7 (not written here)

- A malformed key now reaches the licence screen as `invalid_key`, which the
  notice map in `settings.php` does not know — the customer sees the fallback
  "That did not work." A sentence for it is 3.7's.
- The `seat_limit` refusal names no plan or seat count (S5 handoff).

## Found, not done

- `api-base` default mode on the box, above — either the check exempts a
  `VERGEML_AI_SERVICE` override, or the box fixture is the finding.
- Pro 1.0.3 is due: sealed key, shape check, migration — the box already
  runs it, the channel does not.
- `hetzner-box-fixtures` memory line "`vgmlpro_licence_key` (plain)" is
  stale as of this session — updated in memory.
- From S5: `seat_limit` copy; "deactivating Pro on the box fatals" not
  reproduced; `lib/licence.test.ts` has nothing on the staging branch.

## State left behind

- Licences **12** (`single`, valid) and **13** (`single`, expired) on the
  production database, `box@vergelabs.nl`, 0 seats used, no credits spent.
  Keys in this session's conversation only — reissue with the script when a
  fresh session needs them (`PLAN=single`, `PLAN=single EXPIRED=1`).
- Box: Pro at working-tree `licence.php`/`settings.php` over 1.0.2, on
  Nathan's agency licence, key sealed; `/tmp/{seats,licence,updates}.php`,
  `/tmp/vgmlpro-*.php`, `/tmp/*.orig.php` left (test files, no keys).
- Scratchpad: `prod.vars` (production env — delete), `box.mjs`, `cards.mjs`,
  `build.mjs`, `pg-debug.mjs`, `pg-out/`.
