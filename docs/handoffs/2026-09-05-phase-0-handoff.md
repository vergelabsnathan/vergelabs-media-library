# Session handover — 2026-09-05, Phase 0 done (Fable 5.1)

For the next session. Read in this order, then open Phase 1 of the plan:

1. `plans/folders-one-tree.md` — Phase 0 is done; Phase 1 (the stream and
   the token, service side) is next and wants Fable 5.1.
2. `docs/superpowers/specs/2026-09-05-folders-screen-design.md` §8 for the
   stream contract, §1–§2 for what the plugin will consume.
3. `docs/handoffs/2026-09-05-session-handoff.md` — the morning's handoff;
   its operating notes still hold.
4. `~/.claude/harness/model-profiles.md` — state the model, follow the
   profile; Phase 1 is a Fable phase (whole problem, gates at the end and
   after anything that touches the service or a schema).

Both repos on `main`. Plugin: `1709fe0`, `40aa88c`, `d16ef38` (tasks 1–3,
4, 5) on top of `9b2f461`. Service: `1953de6` on top of `e9a4337`. The
nightly watch commits to `main` around 05:17 UTC: `git pull --rebase`
before pushing.

## One thing only Nathan can do

**Migration 018 is not applied on the production database.** The classifier
blocked `node --env-file=<prod.env> <script>` in this session, so the table
does not exist yet. Until it does, `/v1/counts` answers
`500` (`relation "library_counts" does not exist`, seen in the box service's
pm2 log at 11:29 UTC); the plugin treats anything but 200 as not sent and
tries again the next day, so nothing else is affected.

From `service/`, as `vgml_media`, file by file as memory `the-watch` says:

```
npx vercel env pull --environment=production --yes /tmp/prod.env
node --env-file=/tmp/prod.env -e "
import('pg').then(async ({ default: pg }) => {
  const pool = new pg.Pool({ connectionString: process.env.DATABASE_URL, max: 1 });
  await pool.query(require('fs').readFileSync('db/migrations/018_library_counts.sql', 'utf8'));
  console.log((await pool.query(\"select to_regclass('library_counts')\")).rows[0]);
  await pool.end();
});"
```

Then on the box: `wp eval 'var_dump( vergeml_stats_send( vergeml_stats_snapshot() ) );' --allow-root`
should print `true`, and `select * from library_counts` shows one row for
licence 5, site `http://46.225.66.194`.

## What landed

**Task 1 — four progress rows replace the score** (`core/journey.php`,
`css/vergeml-journey.css`). `vergeml_journey_progress()`: Alt text,
Described, Filed, Checked for copies; each "N of M", the import bar at N/M,
one action link; a row at M of M has none. `vergeml_journey_score()` is
gone; `tools/box-numbers.php` prints the rows instead.

**Task 2 — to-do rows only with something to do.** `vergeml_journey_todo()`
returns only rows with a count above zero. Describing is the first row
(the separate describe card above the list is gone); with no key and demo
mode off it appears once, with "Add a licence key or switch on demo mode
first." as its line, no count, no button. `vergeml_journey_facts()` gained
the `vergeml_journey_facts` filter so the suite can force these states.

**Task 3 — files, not folders.** Title "268 files in no folder", action
"Put them in folders", link still the Sort screen (`media-librarian`) until
Phase 3 re-points it. The row's number column is empty because the title
carries the number.

**Task 4 — demo mode on the Licence screen.** The "Try it free" row and
`#vgml-ai-mock` left `core/ai.php` and `js/vergeml-ai.js`.
`core/licence-page.php` shows a "Demo mode" section (`#vgml-demo-mode`)
under the connect controls only while no key is present; forced on and
disabled with `VERGEML_AI_MOCK`, with the line saying so. Saves through
`/vergeml/v1/ai-settings` on change. `tools/box-connect-check.sh` renders
the screen twice (as the site is; key blanked in memory) and exits 1 unless
the row is present only without a key.

**Task 5 — size counts made true and moved.** Service: migration
`db/migrations/018_library_counts.sql`, `app/api/counts/route.ts`
(`/v1/counts` rewrite), `storeLibraryCounts()` / `libraryCounts()` on the
store, `tests/counts.test.ts`. Plugin: `core/instrument.php` posts the
snapshot with the key and site once a day when opted in (nothing without a
key); the switch is "Share library counts · Send the counts" with three
lines in Library settings (`core/options-pages.php`); the dashboard card and
its CSS are gone; `readme.txt` discloses the call; `docs/ai-service.md`
carries the contract. The snapshot gained `plugin` (VERGEML_VERSION) so the
second line, "Plugin, WordPress and PHP versions, and the site language",
is true.

## Evidence

- `node tools/verify.mjs journey` on the box: `61/61 passed`. Mutations:
  a total printed under the rows → `60/61` (red); count filter dropped →
  `56/61` (red); restored → `61/61`.
- `tests/tree/counts.php` on the box: `26/26 passed`. Mutation: a folder
  name added to the payload → `22/26` (red); restored → `26/26`.
- `box-connect-check.sh`: `ok  demo mode shows only while no key is present`,
  exit 0 (row absent with the key, present without).
- Service: `pnpm test` → `Test Files 15 passed | 5 skipped (20)`,
  `Tests 308 passed | 12 skipped (320)` (was 289 passed with one file
  failing to load); `pnpm typecheck` clean.
  Vercel: production build age 2m after the push; live probe of
  `https://ai.vergelabs.nl/v1/counts` with a bad key answers `401 bad_key`
  (a stale build would 404). Box service: `vps.yml` run 33963328980
  success; `127.0.0.1:3100/v1/counts` answers 401 on a bad key.
- Playwright against the box after tasks 1–4 (dashboard, shots, screens):
  `18 passed, 1 skipped` (the guide walk is gated). After task 5: the
  dashboard spec `5/5`, the shots spec `9 passed` (its own admin
  `vgml-shots`), and `box-ui.yml` run 33963396429 green on the re-run
  (`drive` 7m46s) after its first attempt died at the login because a local
  run had removed the shared `vgml-ui` admin under it.
- Playground (no key): `tools/shots-nokey.mjs` — the Licence screen shows
  the demo switch, Library settings the counts switch, the dashboard
  neither. The five screenshots were sent into the conversation.
- Gate 1: `php -l` on the box's PHP 8.5 at every deploy. Gate 3: the two
  PHP suites above; `node tools/rtl.mjs --check` up to date. Gate 5: the
  rows cost 0 queries of their own (asserted in section F); the send is one
  request a day.

## Decisions taken here, for Nathan to overrule

- The four rows have no kicker above them; the labels carry the block.
- The row style lives in `css/vergeml-journey.css` beside the dashboard's
  other rules, not in `vergeml-shell.css` as the plan's Files line said; the
  bar itself is the shell's `.vgml-import-bar`.
- A blocked describe row has no button; the Licence screen is in the nav.
- `library_counts` is keyed per (licence, site, day), not per licence per
  day: an agency licence has many sites and one would overwrite the other.
- The Library settings section sits before "Media Shortcodes", whose
  postbox holds the page's save bar (Phase 4 unpins it).
- `.vgml-facts` in `vergeml-shell.css` is the brand-mark list (7px rounded
  square, accent, the mock's geometry) — the first instance for the Phase 4
  copy pass to reuse.
- The licence stage's text lost "Try it free" ("Add a licence key to start,
  or switch on demo mode first.") and points at the Licence screen; the
  only copy touched outside the plan's verbatim strings.
- Service: vitest now resolves `@/` (it never had); the guide route test's
  store mock gained the three metering methods it lacked since `e9a4337`.
  Both were found, not planned; both are one-line fixes to make a red suite
  honest.

## Found, not done

- `vergeml_journey_screen()` still computes `$pct`, `$described_pct`,
  `$alt_pct`, `$figures` and never reads them (dead since the redesign).
- `service/tsconfig.tsbuildinfo` is tracked and changes on every typecheck.
- The counts switch's saved notes ("Saved. Thank you.", "Off, and what was
  collected has been deleted.") are the old conversational copy — Phase 4.
- `core/help.php` still carries the `mock` help entry; nothing reads it now.
- `@since 3.16.2` tags assume the next release is 3.16.2; VERGEML_VERSION
  is still 3.16.1 and the readme's stable tag 3.16.0.
- Local Playwright and `box-ui.yml` both use the admin `vgml-ui`; the CI
  step resets its password, so a local run alongside CI dies at the login.
  Use `UI_USER=vgml-<something-else>` locally, or wait for CI.
- `tools/shots-nokey.mjs` (new): Licence, dashboard and Library settings on
  Playground, where there is no key — the way to see the demo-mode row.
- No end-to-end row in `library_counts` yet — the migration above.

## Phase 1 opener, to paste

```
Read docs/handoffs/2026-09-05-phase-0-handoff.md, then plans/folders-one-tree.md.
State which model you are and follow that profile in ~/.claude/harness/model-profiles.md.
This session is Phase 1, tasks 6 to 8 (service: /v1/guide/session token, /v1/guide/stream SSE, evals against the stream).
Stop points: the token's secret and lifetime if they differ from HS256/one hour; any change to the daily site limits; anything that spends more than a dollar of model calls.
Gates: pnpm test, pnpm typecheck, the guide evals (say their cost first), a streamed-byte count on prod by deployment age.
End with a handoff in docs/handoffs/.
```
