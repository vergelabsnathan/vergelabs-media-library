const { chromium } = require('playwright');

// One compatibility pass. Run with a label for the companion plugin under test:
//   node compat-smoke.js "elementor"
//
// Asserts the surfaces this plugin actually touches, because those are where a
// conflict shows up: the media grid toolbar, the taxonomy search filters, the
// list view bulk controls, and the settings screens. Any JS error on any of
// them counts as a failure, because a broken media modal is exactly the class
// of bug that strands a customer.

const BASE = 'http://localhost:8888';
const LABEL = process.argv[2] || 'baseline';

const results = [];
function check(name, ok, detail) {
    results.push({ name, ok, detail: detail === undefined ? '' : String(detail) });
}

(async () => {
    const browser = await chromium.launch();
    const page = await browser.newPage({ viewport: { width: 1500, height: 950 } });
    page.setDefaultTimeout(180000);
    page.setDefaultNavigationTimeout(180000);

    // Errors are attributed by source URL. A companion plugin shouting about its
    // own missing API key is not this plugin's failure, and lumping the two
    // together would either hide a real conflict or invent one.
    const jsErrors = [];
    const OURS = /vergelabs-media-library|vergeml-|eml-/i;
    const record = (text, url) => jsErrors.push({ text: String(text).slice(0, 150), url: url || '', ours: OURS.test(url || '') || OURS.test(String(text)) });
    page.on('pageerror', e => record(e && e.stack ? e.stack.split(String.fromCharCode(10)).slice(0, 2).join(' ') : e, (e && e.stack) || ''));
    page.on('console', m => { if (m.type() === 'error') record(m.text(), (m.location() || {}).url); });

    try {
        await page.goto(BASE + '/wp-login.php', { waitUntil: 'domcontentloaded' });
        await page.fill('#user_login', 'admin');
        await page.fill('#user_pass', 'password');
        await page.click('#wp-submit');
        // WooCommerce and Smush hijack the first admin load with an onboarding
        // wizard, so landing on a specific URL is not a usable login signal.
        try { await page.waitForURL('**/wp-admin/**', { timeout: 120000 }); } catch (e) {}
        await page.goto(BASE + '/wp-admin/index.php', { waitUntil: 'domcontentloaded', timeout: 180000 });
        check('logged in', await page.evaluate(() => !!document.querySelector('#wpadminbar')));

        // ---- 1. media grid + toolbar ----
        await page.goto(BASE + '/wp-admin/upload.php?mode=grid', { waitUntil: 'domcontentloaded' });
        await page.waitForSelector('.media-toolbar-secondary select#media-attachment-filters', { timeout: 150000 });
        try { await page.waitForSelector('.attachments .attachment', { timeout: 120000 }); } catch (e) {}
        await page.waitForTimeout(2500);

        const grid = await page.evaluate(() => {
            const box = document.querySelector('.attachments-browser .media-toolbar-secondary');
            if (!box) return { error: 'no toolbar' };
            const kids = [...box.children].map(e => {
                const r = e.getBoundingClientRect();
                return { id: e.id || '', y: Math.round(r.y), h: Math.round(r.height), pos: getComputedStyle(e).position };
            });
            const flow = kids.filter(k => k.pos !== 'absolute' && k.h);
            const rows = [];
            for (const k of flow) {
                let b = rows.find(b => Math.abs(b.bottom - (k.y + k.h)) <= 8);
                if (!b) { b = { bottom: k.y + k.h }; rows.push(b); }
            }
            const ids = kids.map(k => k.id).filter(Boolean);
            return {
                filters: document.querySelectorAll('.attachment-filters').length,
                attachments: document.querySelectorAll('.attachments .attachment').length,
                rows: rows.length,
                height: Math.round(box.getBoundingClientRect().height),
                dupIds: ids.filter((v, i) => ids.indexOf(v) !== i),
                heading: document.querySelectorAll('.media-attachments-filter-heading').length,
            };
        });

        check('grid toolbar renders', !grid.error, grid.error || '');
        check('filters present', grid.filters >= 2, grid.filters + ' filters');
        check('attachments load', grid.attachments > 0, grid.attachments + ' items');
        check('toolbar is 2 rows', grid.rows === 2, grid.rows + ' rows, ' + grid.height + 'px');
        check('no duplicate ids', (grid.dupIds || []).length === 0, (grid.dupIds || []).join(','));
        check('screen-reader heading kept', grid.heading === 1, String(grid.heading));

        // ---- 2. taxonomy search through the real AJAX endpoint ----
        const search = await page.evaluate(async () => {
            const run = async (t) => {
                const q = new wp.media.model.Attachments(null, {
                    props: { s: t, type: 'image', orderby: 'date', order: 'DESC', query: true },
                });
                await q.more();
                return q.map(m => m.get('title'));
            };
            return { byTerm: await run('Logos'), byTitle: await run('Zephyr'), junk: await run('zzznomatch') };
        });

        check('search by taxonomy term', search.byTerm.length === 2, search.byTerm.join(', ') || 'none');
        check('search by title', search.byTitle.length === 1, search.byTitle.join(', ') || 'none');
        check('search rejects nonsense', search.junk.length === 0, search.junk.length + ' hits');

        // ---- 3. list view + bulk controls ----
        await page.goto(BASE + '/wp-admin/upload.php?mode=list', { waitUntil: 'domcontentloaded' });
        await page.waitForSelector('#the-list', { timeout: 150000 });
        await page.waitForTimeout(1200);

        const list = await page.evaluate(() => ({
            rows: document.querySelectorAll('#the-list tr').length,
            termSelect: !!document.querySelector('#vergeml_bulk_term'),
            addAction: !!document.querySelector('#bulk-action-selector-top option[value="vergeml-add-term"]'),
            taxColumn: !!document.querySelector('[class*="taxonomy-"]'),
        }));

        check('list view loads', list.rows > 0, list.rows + ' rows');
        check('bulk term picker present', list.termSelect);
        check('bulk action present', list.addAction);
        check('taxonomy column present', list.taxColumn);

        // ---- 4. settings screens ----
        for (const [name, url] of [
            ['settings: library', '/wp-admin/options-general.php?page=vergeml-library'],
            ['settings: taxonomies', '/wp-admin/options-general.php?page=vergeml-taxonomies'],
            ['settings: mime types', '/wp-admin/options-general.php?page=vergeml-mime-types'],
        ]) {
            await page.goto(BASE + url, { waitUntil: 'domcontentloaded' });
            await page.waitForTimeout(1500);
            const ok = await page.evaluate(() => !!document.querySelector('.wrap'));
            check(name, ok);
        }

        // ---- 5. media modal in the editor ----
        await page.goto(BASE + '/wp-admin/post-new.php', { waitUntil: 'domcontentloaded' });
        await page.waitForFunction(() => window.wp && window.wp.media, { timeout: 150000 });
        await page.waitForTimeout(3000);
        try { await page.click('button[aria-label="Close"]', { timeout: 2000 }); } catch (e) {}
        await page.evaluate(() => wp.media({ title: 'compat', multiple: true }).open());
        await page.waitForSelector('.media-modal', { timeout: 150000 });
        await page.waitForTimeout(1500);
        await page.evaluate(() => {
            const b = [...document.querySelectorAll('.media-router .media-menu-item, .media-frame-router button')]
                .find(x => /media library/i.test(x.textContent || ''));
            if (b) b.click();
        });
        let modalOk = false;
        try {
            await page.waitForSelector('.media-modal .attachments-browser .media-toolbar-secondary', { timeout: 150000 });
            modalOk = await page.evaluate(() => document.querySelectorAll('.media-modal .attachment-filters').length >= 2);
        } catch (e) {}
        check('media modal filters', modalOk);

    } catch (e) {
        check('run completed', false, String(e).split('\n')[0].slice(0, 140));
    }

    const mine = jsErrors.filter(e => e.ours);
    const theirs = jsErrors.filter(e => !e.ours);
    check('no JS errors from this plugin', mine.length === 0, mine.slice(0, 3).map(e => e.text).join(' | '));
    if (theirs.length) {
        console.log('  note: ' + theirs.length + ' error(s) from other code, not counted:');
        [...new Set(theirs.map(e => e.text))].slice(0, 4).forEach(t => console.log('        ' + t.slice(0, 110)));
    }

    const failed = results.filter(r => !r.ok);
    console.log('=== ' + LABEL + ' ===');
    results.forEach(r => console.log((r.ok ? '  PASS ' : '  FAIL ') + r.name.padEnd(28) + (r.detail ? ' ' + r.detail : '')));
    console.log(failed.length ? 'VERDICT: FAIL (' + failed.length + ')' : 'VERDICT: PASS');

    await browser.close();
    process.exit(failed.length ? 1 : 0);
})();
