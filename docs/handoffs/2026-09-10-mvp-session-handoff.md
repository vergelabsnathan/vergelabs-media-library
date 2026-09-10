# Session handover — 2026-09-10, the MVP day (Opus 5)

**This session ran far too long.** It covered the traces close-out, a library
reset, a pricing re-rate, a scale investigation, a submission clean-up, a
security surface count, a site redesign mock and four phases of planning. The
harness rule is one phase per fresh session with a handoff at the end of each;
that was not followed, and this handoff exists to make the next session clean
rather than to excuse the last one.

**Start the next session fresh.** `/clear`, then open with:

> Read `docs/handoffs/2026-09-10-mvp-session-handoff.md`, then
> `plans/four-yesses.md`. State which model you are and follow that profile in
> `~/.claude/harness/model-profiles.md`. This session is **Phase N only**.

---

## The plan everything now hangs off

`plugin/plans/four-yesses.md` — five phases, five yesses, each with proof
somebody other than the author can check.

| phase | what it answers | blocked on |
|---|---|---|
| 1 | the free plugin holds up across 2,000 installs | Nathan: WordPress.org username (last task only) |
| 2 | Pro is worth paying for, at ~100 paying customers | **Nathan: the Stripe live webhook check, then a real card** |
| 3 | the UI is ready for people who will not ask questions | **Nathan: approve the Folders mock** |
| 4 | the system survives 2,000 sites | — mostly done, see below |
| 5 | the plugin is not a hazard | — |

**Order if time is short: 2, 4, 5, 1, 3.** A hole shipped to 2,000 sites is the
only item in the plan that cannot be taken back, which is why security sits
ahead of the board and the polish.

**Rollout: 20, then 200, then 2,000.** Not one step.

## What shipped today, all merged and pushed

**Plugin** (`main`, deployed to the box and verified by digest)

- `plans/traces.md` closed; the seven-phase record is straight.
- **Counts at scale**: 10,038ms → **8.6ms** cold at 251,000 attachments, 0ms
  warm. Cache with invalidation, a sargable `recent`, and a threshold above
  which nothing is counted on a page load.
- **The folders panel is in list view**, with a suite covering both views.
- **The cache-busting bug**: the deploy stamped every file 1980-01-01, so
  `vergeml_asset_ver()` never changed and browsers kept old JavaScript. This is
  why three deployed fixes were invisible. Fixed at both ends.
- **The deploy syncs instead of overlaying** — the box went from 1,988 files to
  136. `tools/`, `tests/`, `docs/`, `plans/`, `node_modules/` were sitting in a
  public plugin directory.
- **Plugin Check 1,045 → 2 errors**, neither a submission blocker.
- **The fixture**: `tools/box-fixture.php` builds 18 folders and 1,000
  described pictures, deterministic, free, rebuildable.
- **17 suites now read `UI_USER`/`UI_PASS`** instead of a hardcoded password
  that no longer worked.

**Service** (`main`, deployed)

- **Pricing re-rated** on measured cost (€0.00483/image): €0.020 → €0.012, live
  on the API and the site.
- **`/api/health`** — database, release catalogue, Stripe, webhook secret, relay.
- **`/api/cron/release-check`** — daily, compares the advertised version with
  the version inside the zip it serves.
- **`.github/workflows/health.yml`** — every ten minutes, from outside.
- **`/api/order`** answered 500 for an unknown payment; now 400, so a declined
  card is told immediately instead of after 40 seconds of spinner.
- **The connect loop** — `/account` was unreachable for anyone with a licence.

**The box**

- Scanner flood blocked, fail2ban installed; load 6.30 → 1.83.
- nginx repaired (a duplicate directive had it failing since an upgrade).
- 159MB debug log truncated.
- Library: 1,000 tech-news pictures, 18 fixture folders, all described by
  fixture. **The 251k scale fixture was removed.**

## Where Phase 4 actually stands

Done: 4.1 health endpoint, 4.2 external watcher, 4.3 release check.
**Not done: 4.4 rate limits, 4.5 global spend cap, 4.6 restore drill, 4.7
provider failure, 4.8 runbook, 4.9 secret rotation.**

**One thing needs Nathan, thirty seconds:** confirm GitHub Actions failure
emails reach him, or the watcher shouts into a void.

## The board, as it stands

- **Service:** 365 tests green, typecheck clean.
- **Plugin:** 21 of 33 green, 2 skipped, **10 red and uncharacterised** —
  `search-try`, `ai-folders`, `auto-file`, `ai-background`, `ai`, `smart`,
  `organize`, `voice`, `librarian`, `librarian-ui`.
- **Pro:** no automated tests at all.

**Phase 1 task 1.1 is the next real work**: characterise those ten before fixing
any. Read `librarian` first — it was green at 14:30 and red at 17:00 with no
commit touching it, which means the fixture or the counts cache moved it.

## Traps, learned today, that will cost the next session hours

1. **A timeout waiting for a selector is usually a failed login.** Eighteen
   suites died at the login form and reported it as a missing element. Check
   authentication before markup.
2. **`wp eval-file` runs inside a function.** Globals need declaring.
3. **`vergeml_ai_describe()` returns and does not persist.** A hundred calls
   cost $0.48 and left zero rows. Use the AI screen to describe a library.
4. **Deactivating any plugin on the box fatals** in core's FTP filesystem
   class — `hello` fatals identically. Not ours; do not chase it.
5. **The box is a compatibility fixture, not a performance one.** 28 plugins
   including four page builders. Judge speed elsewhere.
6. **Verify for the user, not for the server.** A digest match on the box is not
   proof that a browser received it.
7. **Do not run `tests/librarian/gate7-schema.php`** — it destroys the
   librarian tables.
8. **The suites need `UI_USER`/`UI_PASS`** from `tools/box-ui-user.sh`, and the
   admin is deleted at the end of a run.

## Cost today

Roughly **$0.50** of provider spend — 99 describes measured for the pricing
work, and nothing else reached a model. The fixture, the scale test and every
suite spend nothing.

## Found, not done

- **The sticky sidebar in grid mode is not ours** — proven by blocking all seven
  of our scripts at the network layer and getting identical behaviour. It
  belongs to one of the other 28 plugins on that box.
- **Nathan's 3D Secure failure is unexplained.** Every page in the payment path
  answers 200. It needs the address bar contents from when it happened.
- **The OpenRouter key was pasted into a transcript** and should be rotated.
- **The filing baseline is void** — it indexes the 641 pictures the library
  reset deleted, and needs re-taking against the new library.
- **The Folders mock is a draft** with the column layout wrong; it must be
  finished and approved before Phase 3 builds anything.
