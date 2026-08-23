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

async function probe(label, path) {
  let head = null;
  const wall = [];

  for (let i = 0; i <= RUNS; i++) {
    const t0 = process.hrtime.bigint();
    const res = await fetch(BASE + path, { headers: HEADERS });
    const body = await res.text();
    const ms = Number(process.hrtime.bigint() - t0) / 1e6;

    if (i === 0) {
      // First run is cold: it is discarded, but it is also where a broken
      // request announces itself, so check it rather than the timings.
      if (res.status !== 200) return console.log(`${label.padEnd(28)} HTTP ${res.status}  ${body.slice(0, 90)}`);
      if (!res.headers.get('x-vgml-queries')) return console.log(`${label.padEnd(28)} no perf probe -- is mu-perf.php installed?`);
      head = {
        queries: Number(res.headers.get('x-vgml-queries')),
        handler: Number(res.headers.get('x-vgml-handler-ms')),
        db: Number(res.headers.get('x-vgml-db-ms')),
        boot: Number(res.headers.get('x-vgml-boot-ms')),
        bytes: body.length,
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
await probe('ours: vergeml/v1/tree', '/wp-json/vergeml/v1/tree?taxonomy=media_category');
await probe('core: wp/v2/media pp=40', '/wp-json/wp/v2/media?per_page=40');
await probe('core: wp/v2/media pp=100', '/wp-json/wp/v2/media?per_page=100');
await probe('core: REST index', '/wp-json/');
console.log('');
