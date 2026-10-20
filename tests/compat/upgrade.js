/*
 *  Upgrade test: Enhanced Media Library 2.9.4 -> VergeLabs Media Library.
 *
 *  This is the path every existing install takes, and the one where breakage
 *  costs most -- someone's library of thousands of files and hundreds of terms.
 *  A fresh-install test cannot cover it.
 *
 *  Runs against WordPress Playground rather than Docker:
 *
 *      npx @wp-playground/cli server --port=9400 --php=8.3 --wp=latest --login \
 *        --define-bool WP_DEBUG true --define-bool WP_DEBUG_DISPLAY false \
 *        --define VGML_TEST_KEY "s3cr3t" \
 *        --mount-dir "<repo>" "/wordpress/wp-content/plugins/vergelabs-media-library" \
 *        --mount-dir "<eml-2.9.4>" "/wordpress/wp-content/plugins/enhanced-media-library" \
 *        --mount-dir "<dir with upgrade-helper.php>" "/wordpress/wp-content/mu-plugins"
 *
 *      node tests/compat/upgrade.js
 */

const BASE = 'http://127.0.0.1:9400';
const KEY = 's3cr3t';
const EML = 'enhanced-media-library/enhanced-media-library.php';
const MINE = 'vergelabs-media-library/vergelabs-media-library.php';

const KEYS = ['version', 'lib_options', 'taxonomies', 'tax_options', 'mimes', 'backup'];

const results = [];
const check = (name, ok, detail) => results.push({ name, ok, detail: detail === undefined ? '' : String(detail) });

/*
 *  Playground's auto-login answers the first request with a redirect that sets
 *  cookies. fetch() does not carry cookies across redirects, so it loops until
 *  it gives up -- hence the jar and the manual hop.
 */
const jar = new Map();

async function get(url, depth) {
    if ((depth || 0) > 5) throw new Error('too many redirects: ' + url);
    const cookie = [...jar.entries()].map(([k, v]) => `${k}=${v}`).join('; ');
    const res = await fetch(url, { redirect: 'manual', headers: cookie ? { cookie } : {} });
    const set = typeof res.headers.getSetCookie === 'function' ? res.headers.getSetCookie() : [];
    for (const line of set) {
        const [pair] = line.split(';');
        const i = pair.indexOf('=');
        if (i > 0) jar.set(pair.slice(0, i).trim(), pair.slice(i + 1).trim());
    }
    if (res.status >= 300 && res.status < 400 && res.headers.get('location'))
        return get(new URL(res.headers.get('location'), BASE).href, (depth || 0) + 1);
    return res;
}

async function call(action, extra) {
    const url = `${BASE}/?vergeml_test=${action}&key=${KEY}${extra || ''}`;
    const res = await get(url);
    const text = await res.text();
    try {
        return JSON.parse(text);
    } catch (e) {
        throw new Error(`${action} returned non-JSON (${res.status}): ${text.slice(0, 300)}`);
    }
}

const stable = (v) => JSON.stringify(v);

(async () => {
    try {
        // start from nothing, so a leftover vergeml_* option cannot fake a pass
        await call('reset');
        await call('activate', `&plugin=${encodeURIComponent(EML)}`);
        const seeded = await call('seed_eml');
        check('seed created attachments', Object.keys(seeded.attachments || {}).length === 4,
            (seeded.error || '') + ' ' + Object.keys(seeded.attachments || {}).join(', '));
        const before = await call('dump');

        check('EML active before upgrade', (before.active || []).includes(EML), (before.active || []).join(', '));
        check('EML wrote its own options', before.eml.lib_options !== '__absent__');
        check('fork options absent before upgrade', before.vergeml.lib_options === '__absent__',
            'a leftover vergeml_* option would make the migration skip and still look like a pass');
        check('files are filed under terms', Object.keys(before.assignments || {}).length >= 4,
            Object.keys(before.assignments || {}).length + ' attachments with terms');

        // the upgrade itself
        await call('deactivate', `&plugin=${encodeURIComponent(EML)}`);
        const act = await call('activate', `&plugin=${encodeURIComponent(MINE)}`);
        check('fork activates without error', act.result === 'activated', act.result);

        const after = await call('dump');

        /*
         *  Not equality. On activation the fork merges in its own defaults,
         *  including settings Enhanced Media Library never had, so the migrated
         *  option is legitimately a superset. What must not happen is a value
         *  the user had chosen coming back changed or missing -- that is the
         *  actual failure this test exists to catch.
         */
        const missing = (src, dst, path, acc) => {
            if (src === null || typeof src !== 'object') {
                if (stable(src) !== stable(dst)) acc.push(`${path}: ${stable(src)} -> ${dst === undefined ? 'MISSING' : stable(dst)}`);
                return acc;
            }
            if (dst === null || typeof dst !== 'object') { acc.push(`${path}: object -> ${dst === undefined ? 'MISSING' : stable(dst)}`); return acc; }
            for (const key of Object.keys(src)) missing(src[key], dst[key], path ? `${path}.${key}` : key, acc);
            return acc;
        };

        for (const k of KEYS) {
            const src = before.eml[k];
            const dst = after.vergeml[k];
            if (src === '__absent__') { check(`migrated: ${k}`, true, 'not set upstream, nothing to carry'); continue; }
            // version is expected to advance: the fork records its own on activation
            if (k === 'version') {
                check('migrated: version', dst !== '__absent__', `${stable(src)} -> ${stable(dst)}`);
                continue;
            }
            if (dst === '__absent__') { check(`migrated: ${k}`, false, 'option not created at all'); continue; }
            const lost = missing(src, dst, '', []);
            check(`migrated: ${k}`, lost.length === 0, lost.length ? lost.slice(0, 4).join(' | ') : 'every EML value preserved');
        }

        check('original EML options left intact', KEYS.every(k => stable(before.eml[k]) === stable(after.eml[k])),
            'a rollback to EML must still find its settings');

        check('term assignments survive', stable(before.assignments) === stable(after.assignments),
            stable(before.assignments) === stable(after.assignments)
                ? Object.keys(after.assignments).length + ' attachments unchanged'
                : `before ${stable(before.assignments).slice(0, 120)} / after ${stable(after.assignments).slice(0, 120)}`);

        check('both taxonomies still registered on attachments',
            (after.registered_taxonomies || []).length === 2, (after.registered_taxonomies || []).join(', '));

        check('custom MIME types carried over',
            after.vergeml.mimes !== '__absent__' && Object.keys(after.vergeml.mimes || {}).includes('psd'),
            Object.keys(after.vergeml.mimes || {}).join(', '));

        /*
         *  The careless upgrade: the fork installed while Enhanced Media
         *  Library is still active. Plenty of people will do this, and both
         *  plugins then register the same taxonomies and hook the same media
         *  views. If that is fatal, it is fatal on their live site.
         */
        const both = await call('activate', `&plugin=${encodeURIComponent(EML)}`);
        check('both plugins can be active at once', both.result === 'activated', both.result);

        for (const [name, path] of [['media library', '/wp-admin/upload.php'], ['settings', '/wp-admin/options-general.php?page=vergeml-library']]) {
            const res = await get(BASE + path);
            const html = await res.text();
            const fatal = /Fatal error|There has been a critical error/i.test(html);
            check(`${name} loads with both active`, res.status === 200 && !fatal,
                `HTTP ${res.status}${fatal ? ' -- FATAL in output' : ''}`);
        }

    } catch (e) {
        check('run completed', false, e.message);
    }

    console.log('=== UPGRADE: Enhanced Media Library 2.9.4 -> fork ===');
    results.forEach(r => console.log((r.ok ? '  PASS ' : '  FAIL ') + r.name.padEnd(42) + (r.detail ? ' ' + r.detail : '')));
    const failed = results.filter(r => !r.ok);
    console.log(failed.length ? `VERDICT: FAIL (${failed.length})` : 'VERDICT: PASS');
    process.exit(failed.length ? 1 : 0);
})();
