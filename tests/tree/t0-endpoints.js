/*
 *  T0: the two endpoints the folder tree will run on.
 *
 *  Driven through a signed-in browser rather than raw HTTP, because that is how
 *  the tree will call them: cookie auth plus a nonce. A test that authenticates
 *  differently from the thing it is testing proves less than it looks.
 *
 *      node tests/tree/t0-endpoints.js
 *
 *  Use 127.0.0.1, never localhost. WordPress builds its own URLs from siteurl,
 *  and the other name fails every nonce.
 */

const { chromium } = require('playwright');

const BASE = 'http://127.0.0.1:8899';
const TAXONOMY = 'media_category';

const results = [];
const check = (name, ok, detail = '') => {
  results.push({ name, ok });
  console.log(`  ${ok ? 'ok  ' : 'FAIL'} ${name}${detail ? '  — ' + detail : ''}`);
};

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1400, height: 900 } });
  page.setDefaultNavigationTimeout(90000);

  // The block editor reliably enqueues wp-api-fetch, which carries the nonce.
  await page.goto(`${BASE}/wp-admin/post-new.php`, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(4000);

  const nonce = await page.evaluate(() => {
    if (window.wpApiSettings && window.wpApiSettings.nonce) return window.wpApiSettings.nonce;
    if (window.wp && window.wp.apiFetch && window.wp.apiFetch.nonceMiddleware) {
      return window.wp.apiFetch.nonceMiddleware.nonce;
    }
    return null;
  });
  check('a REST nonce is available', nonce !== null, nonce ? 'got one' : 'none found');
  if (nonce === null) { await browser.close(); process.exit(1); }

  // Everything below runs in the page, so the cookies travel with it.
  const call = (path, options) =>
    page.evaluate(
      async ([p, o, n]) => {
        const res = await fetch(p, {
          method: o.method || 'GET',
          headers: { 'content-type': 'application/json', 'X-WP-Nonce': n },
          body: o.body ? JSON.stringify(o.body) : undefined,
        });
        let body = null;
        try { body = await res.json(); } catch { body = null; }
        return { status: res.status, body };
      },
      [path, options || {}, nonce],
    );

  console.log('\n1. the tree endpoint');
  const started = Date.now();
  const tree = await call(`/wp-json/vergeml/v1/tree?taxonomy=${TAXONOMY}`);
  const took = Date.now() - started;

  check('tree responds', tree.status === 200, `HTTP ${tree.status}`);
  check('returns nodes', Array.isArray(tree.body?.nodes), `${tree.body?.nodes?.length ?? 0} terms`);
  check('says whether it is hierarchical', typeof tree.body?.hierarchical === 'boolean');
  check('counts the unassigned', typeof tree.body?.unassigned === 'number', String(tree.body?.unassigned));
  check('carries this user\'s state', tree.body?.state !== undefined);
  check('one round trip is quick', took < 1500, `${took}ms`);

  const nodes = tree.body?.nodes ?? [];
  check('every node has a parent, count and colour field', nodes.every(
    (n) => typeof n.id === 'number' && typeof n.parent === 'number' && typeof n.count === 'number' && typeof n.color === 'string',
  ));

  console.log('\n2. refusals');
  const bogusTax = await call('/wp-json/vergeml/v1/tree?taxonomy=not_a_taxonomy');
  check('unknown taxonomy is 404', bogusTax.status === 404, `HTTP ${bogusTax.status}`);

  const bogusTerm = await call('/wp-json/vergeml/v1/assign', {
    method: 'POST',
    body: { taxonomy: TAXONOMY, attachments: [1], add: [999999] },
  });
  check('unknown term is refused', bogusTerm.status === 400, `HTTP ${bogusTerm.status}`);

  const nothing = await call('/wp-json/vergeml/v1/assign', {
    method: 'POST',
    body: { taxonomy: TAXONOMY, attachments: [], add: [] },
  });
  check('empty request is refused', nothing.status === 400, `HTTP ${nothing.status}`);

  console.log('\n3. assigning');
  const ids = await page.evaluate(async (n) => {
    const res = await fetch('/wp-json/wp/v2/media?per_page=3', { headers: { 'X-WP-Nonce': n } });
    const items = await res.json();
    return items.map((i) => i.id);
  }, nonce);
  const termId = nodes[0]?.id;
  check('there is media and a term to work with', ids.length > 0 && termId !== undefined, `${ids.length} files, term ${termId}`);

  if (ids.length > 0 && termId !== undefined) {
    const before = nodes.find((n) => n.id === termId)?.count ?? 0;

    const add = await call('/wp-json/vergeml/v1/assign', {
      method: 'POST',
      body: { taxonomy: TAXONOMY, attachments: ids, add: [termId] },
    });
    check('assign succeeds', add.status === 200, `HTTP ${add.status}`);
    check('reports what changed', (add.body?.changed?.length ?? 0) === ids.length, `${add.body?.changed?.length} of ${ids.length}`);
    check('returns a fresh count', typeof add.body?.counts?.[termId] === 'number', `${before} -> ${add.body?.counts?.[termId]}`);
    check('hands back the inverse for undo', add.body?.undo?.remove?.includes(termId) === true);

    // The undo it gave us should put things back.
    const undo = await call('/wp-json/vergeml/v1/assign', { method: 'POST', body: add.body.undo });
    check('undo succeeds', undo.status === 200, `HTTP ${undo.status}`);
    check('undo restores the count', undo.body?.counts?.[termId] === before, `back to ${undo.body?.counts?.[termId]}`);

    console.log('\n4. a file can live in two folders');
    if (nodes.length > 1) {
      const second = nodes[1].id;
      await call('/wp-json/vergeml/v1/assign', { method: 'POST', body: { taxonomy: TAXONOMY, attachments: [ids[0]], add: [termId] } });
      await call('/wp-json/vergeml/v1/assign', { method: 'POST', body: { taxonomy: TAXONOMY, attachments: [ids[0]], add: [second] } });

      const both = await page.evaluate(async ([id, tax, n]) => {
        const res = await fetch(`/wp-json/wp/v2/media/${id}`, { headers: { 'X-WP-Nonce': n } });
        const item = await res.json();
        return item[tax] ?? item['media_category'] ?? [];
      }, [ids[0], TAXONOMY, nonce]);

      check('the same file is in both folders', Array.isArray(both) && both.length >= 2, `in ${both.length} folders`);

      console.log('\n5. move mode replaces rather than adds');
      const moved = await call('/wp-json/vergeml/v1/assign', {
        method: 'POST',
        body: { taxonomy: TAXONOMY, attachments: [ids[0]], add: [termId], mode: 'move' },
      });
      check('move succeeds', moved.status === 200, `HTTP ${moved.status}`);

      const after = await page.evaluate(async ([id, n]) => {
        const res = await fetch(`/wp-json/wp/v2/media/${id}`, { headers: { 'X-WP-Nonce': n } });
        const item = await res.json();
        return item['media_category'] ?? [];
      }, [ids[0], nonce]);
      check('move leaves exactly one folder', after.length === 1, `in ${after.length}`);

      // Put it back so the seeded library is as it was.
      await call('/wp-json/vergeml/v1/assign', { method: 'POST', body: { taxonomy: TAXONOMY, attachments: [ids[0]], remove: [termId] } });
    } else {
      check('(skipped: only one term seeded)', true);
    }
  }

  await browser.close();

  const bad = results.filter((r) => !r.ok).length;
  console.log(`\n${results.length - bad}/${results.length} passed`);
  process.exit(bad ? 1 : 0);
})();
