/*
 *  What a REST endpoint actually costs, in a way Playground can answer honestly.
 *
 *      node tests/perf/bench.mjs http://127.0.0.1:8902 admin:benchbenchbenchbench
 *      node tests/perf/bench.mjs http://<vps-ip>      admin:<app-password>
 *
 *  Wall-clock is reported but is not the measurement: PHP-wasm spends seconds
 *  booting WordPress on every request, so in Playground it says almost nothing
 *  about the handler. Boot is printed separately for exactly that reason -- once
 *  it is subtracted, what is left is the code.
 *
 *  The figure that transfers between environments is the query count. It is a
 *  property of the algorithm, so Playground and real MariaDB must agree on it,
 *  and a disagreement is a bug rather than a hardware difference.
 */

const BASE = process.argv[2] ?? 'http://127.0.0.1:8902';
const CREDS = process.argv[3] ?? 'admin:benchbenchbenchbench';
const AUTH = 'Basic ' + Buffer.from(CREDS).toString('base64');

const RUNS = Number(process.env.RUNS ?? 6);

/*
 *  Two ways in, because the two environments disagree about authentication.
 *
 *  Real WordPress takes an application password over Basic auth. Playground does
 *  not -- but it hands out genuine login cookies unprompted: `login: true` answers
 *  the first request with a 302 back to the same URL carrying them, plus a marker
 *  saying the auto-login already ran. (A browser replays the marker and moves on;
 *  fetch has no cookie jar, so without it the redirect never terminates.)
 *
 *  So: try Basic first, and if the server says no, pick up the cookies it just
 *  gave us and pair them with a nonce scraped from wp-admin. One of the two
 *  always works, and the caller never has to care which.
 */
let HEADERS = { Authorization: AUTH, Cookie: 'playground_auto_login_already_happened=1' };

async function authenticate() {
  const probeUrl = BASE + '/wp-json/wp/v2/users/me';
  if ((await fetch(probeUrl, { headers: HEADERS })).status === 200) {
    console.log('auth: application password\n');
    return;
  }

  // Deliberately without the marker: that cookie is what tells Playground the
  // auto-login already ran, and sending it here means it never issues cookies.
  const seed = await fetch(BASE + '/', { redirect: 'manual' });
  const jar = (seed.headers.getSetCookie?.() ?? [])
    .map((c) => c.split(';')[0])
    .concat('playground_auto_login_already_happened=1')
    .join('; ');

  const admin = await fetch(BASE + '/wp-admin/', { headers: { Cookie: jar } });
  const html = await admin.text();
  const nonce =
    html.match(/"nonce"\s*:\s*"([a-f0-9]+)"/)?.[1] ??
    html.match(/createNonceMiddleware\(\s*"([a-f0-9]+)"/)?.[1] ??
    html.match(/wpApiSettings[^<]*?nonce"?\s*[:=]\s*"([a-f0-9]+)"/)?.[1];

  if (!nonce) throw new Error('could not authenticate: no application password, no nonce');

  HEADERS = { Cookie: jar, 'X-WP-Nonce': nonce };
  const check = await fetch(probeUrl, { headers: HEADERS });
  if (check.status !== 200) throw new Error(`cookie auth failed: HTTP ${check.status}`);
  console.log('auth: login cookie + nonce\n');
}

/*
 *  A request the way the endpoint expects it. The organise endpoints are POSTs
 *  carrying JSON, and a budget measured only against the readable half of an
 *  API is a budget with a hole in it -- the step endpoint is the one whose
 *  query count is allowed to grow with the library, and therefore the one
 *  worth watching.
 */
function send(path, body) {
  if (body === undefined) return fetch(BASE + path, { headers: HEADERS });
  return fetch(BASE + path, {
    method: 'POST',
    headers: { ...HEADERS, 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });
}

async function probe(label, path, body) {
  let head = null;
  const wall = [];

  for (let i = 0; i <= RUNS; i++) {
    const t0 = process.hrtime.bigint();
    const res = await send(path, body);
    const text = await res.text();
    const ms = Number(process.hrtime.bigint() - t0) / 1e6;

    if (i === 0) {
      // First run is cold: it is discarded, but it is also where a broken
      // request announces itself, so check it rather than the timings.
      if (res.status !== 200) return console.log(`${label.padEnd(28)} HTTP ${res.status}  ${text.slice(0, 90)}`);
      if (!res.headers.get('x-vgml-queries')) return console.log(`${label.padEnd(28)} no perf probe -- is mu-perf.php installed?`);
      head = {
        queries: Number(res.headers.get('x-vgml-queries')),
        handler: Number(res.headers.get('x-vgml-handler-ms')),
        db: Number(res.headers.get('x-vgml-db-ms')),
        boot: Number(res.headers.get('x-vgml-boot-ms')),
        bytes: text.length,
      };
      continue;
    }
    wall.push(ms);
    head.handler = Number(res.headers.get('x-vgml-handler-ms'));
    head.boot = Number(res.headers.get('x-vgml-boot-ms'));
    head.db = Number(res.headers.get('x-vgml-db-ms'));
  }

  wall.sort((a, b) => a - b);
  const median = wall[Math.floor(wall.length / 2)];

  console.log(
    `${label.padEnd(28)}` +
    `${String(head.queries).padStart(4)} q  ` +
    `handler ${head.handler.toFixed(1).padStart(8)}ms  ` +
    `db ${head.db.toFixed(1).padStart(7)}ms  ` +
    `boot ${head.boot.toFixed(0).padStart(6)}ms  ` +
    `wall ${median.toFixed(0).padStart(6)}ms  ` +
    `${(head.bytes / 1024).toFixed(0)}KB`,
  );
  return head;
}

console.log(`\n${BASE}`);
await authenticate();
/*
 *  The tree. Budget: 6, and it must not move.
 *
 *  Since 3.4.0 it also carries the AI folder group: thirteen more rows, their
 *  counts, and the described/total pair. All of it rides the smart-folder
 *  UNION that was already there, so the number below is the number this
 *  endpoint had before the group existed. If it reads 7, the counts did not
 *  land in that statement; if it moves with the size of the library, the join
 *  is not using the index columns' keys. Both are hard fails, not slow
 *  queries.
 *
 *  One caveat this file cannot remove: a library where nothing has been
 *  described has no AI counts to pay for, so a good number here proves less
 *  than it looks. tests/tree/ai-folders.php measures the difference between
 *  the group off and the group on directly, with files it seeds itself.
 */
await probe('ours: vergeml/v1/tree', '/wp-json/vergeml/v1/tree?taxonomy=media_category');
/*
 *  The duplicates report. Budget: three statements plus the one meta sweep --
 *  the grouped md5 count, the sweep that loads every picture hash, and the two
 *  that fetch titles and files for the ids it is about to show. Four, and flat:
 *  the comparing happens in PHP over data already in hand, so the count does
 *  not move with how much turns out to be wrong with the library.
 */
await probe('ours: vergeml/v1/health-report', '/wp-json/vergeml/v1/health-report');

/*
 *  The organise backend.
 *
 *  A step is budgeted at four and must be flat: it must not move with the
 *  number of folders, nor with how many steps have already been taken. An N+1
 *  here would be a query per file, and the file count is the whole point of
 *  the feature.
 *
 *  The step probed is a working one -- a run is created first, and the probe
 *  then hands back its id -- because a step that finds a finished run returns
 *  after a single read and would flatter the number being checked.
 */
const created = await send('/wp-json/vergeml/v1/organize-step', {}).then((r) => r.json()).catch(() => null);

if (created && created.run_id) {
  await probe('ours: organize-step', '/wp-json/vergeml/v1/organize-step', { run_id: created.run_id });
} else {
  console.log('ours: organize-step'.padEnd(28) + ' could not create a run -- is the AI index populated?');
}

// The read path. Budget two: the run row, and the posts for whatever ids it
// returns. The samples were hydrated when the run finished, so in practice it
// costs one however large the tree is.
await probe('ours: organize-run', '/wp-json/vergeml/v1/organize-run');
await probe('ours: organize-quote', '/wp-json/vergeml/v1/organize-quote');

/*
 *  Cancel, budgeted at two, and probed last because it ends the run it is
 *  given. A cancel that had to wait for the step it was cancelling would not
 *  be a cancel, which is why it is its own endpoint rather than a flag on the
 *  step -- and why its cost is worth stating.
 */
const doomed = await send('/wp-json/vergeml/v1/organize-step', {}).then((r) => r.json()).catch(() => null);

if (doomed && doomed.run_id) {
  await probe('ours: organize-cancel', '/wp-json/vergeml/v1/organize-cancel', { run_id: doomed.run_id });
}

/*
 *  The Librarian.
 *
 *  Budgets, measured on the box on 27-08-2026 rather than reasoned about:
 *
 *    librarian-schemes     1   flat; one row, or one grouped pass for the
 *                              date scheme
 *    librarian-batches     1   every count the list shows is a column
 *    librarian-preflight  11   cold. The organise quote it wraps is 5 of
 *                              those on its own, which is why the plan's
 *                              figure of 6 was never reachable: it budgeted
 *                              the wrapper without the thing wrapped
 *    librarian-apply-step      4 + 4 per file in the chunk, and up to
 *                              4 + 13 per file when every file in the chunk
 *                              lands in a different folder
 *    librarian-undo-step       4 + 2 per file in the chunk
 *
 *  The 4 is core's own: wp_set_object_terms costs four queries for one fresh
 *  assignment, measured, and a step cannot spend fewer without writing
 *  term_relationships itself and dropping every hook that watches it. The 13
 *  is WordPress's hierarchical term-count invalidation, which costs about
 *  nine queries per DISTINCT folder a chunk touches -- bounded by the chunk
 *  size, since a chunk of 25 files can touch at most 25 folders.
 *
 *  What must not change:
 *
 *  - a step must not cost more the later it runs. That is the N+1 that
 *    matters here, and tests/librarian/test-librarian.php asserts it.
 *  - nothing may grow with the size of the library. The work list is
 *    resolved once, when the batch is created, so a step never walks a tree
 *    and never counts a branch.
 *  - folders are made in a phase of their own, before any filing. Making
 *    them inside the filing loop cost 198 queries a step against 123 for the
 *    same files in fewer branches -- the shape of an N+1 even though nothing
 *    loops over a file.
 *
 *  The step probed is a steady-state one: a batch is created, one step is
 *  taken to get through the folder phase, and the probe measures the filing
 *  steps after it.
 */
await probe('ours: librarian-schemes', '/wp-json/vergeml/v1/librarian-schemes');
await probe('ours: librarian-batches', '/wp-json/vergeml/v1/librarian-batches');
await probe('ours: librarian-preflight', '/wp-json/vergeml/v1/librarian-preflight');

const batch = await send('/wp-json/vergeml/v1/librarian-apply-step', { scheme: 'datetype' })
  .then((r) => r.json())
  .catch(() => null);

if (!batch || !batch.batch_id) {
  console.log('ours: librarian-apply-step'.padEnd(28) + ' could not create a batch -- is a media taxonomy on?');
} else if (!batch.n) {
  /*
   *  Nothing in this library is unfiled, so the batch has no work and a step
   *  returns after one read. Printing that as the step's cost would be a
   *  number that looks excellent and measures nothing -- so it says what
   *  happened instead.
   *
   *  To measure it here, leave some files without a folder first. The per-step
   *  cost is asserted directly, with its own seeded files, in
   *  tests/librarian/test-librarian.php.
   */
  console.log('ours: librarian-apply-step'.padEnd(28) + ' NOT MEASURED -- nothing in this library is unfiled');
  console.log('ours: librarian-undo-step'.padEnd(28) + ' NOT MEASURED -- the batch above had no work');
} else {
  // Steps until the folder phase is behind us, so the probe measures filing
  // rather than folder creation.
  for (let i = 0; i < 40; i++) {
    const r = await send('/wp-json/vergeml/v1/librarian-apply-step', { batch_id: batch.batch_id })
      .then((res) => res.json())
      .catch(() => null);
    if (!r || r.phase !== 'folders') break;
  }
  await probe('ours: librarian-apply-step', '/wp-json/vergeml/v1/librarian-apply-step', {
    batch_id: batch.batch_id,
  });
  await probe('ours: librarian-undo-step', '/wp-json/vergeml/v1/librarian-undo-step', {
    batch_id: batch.batch_id,
  });
}

await probe('core: wp/v2/media pp=40', '/wp-json/wp/v2/media?per_page=40');
await probe('core: wp/v2/media pp=100', '/wp-json/wp/v2/media?per_page=100');
await probe('core: REST index', '/wp-json/');
console.log('');
