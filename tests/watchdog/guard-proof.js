// Asks the plugin directly whether the guarded feature files loaded, instead of
// inferring it from an admin screen.
const { chromium } = require('playwright');
const fs = require('fs');
const W = 'C:/dev/media-plugin/plugin/core/watchdog.php';
const V = 'C:/dev/media-plugin/plugin/core/taxonomies.php';
const wOrig = fs.readFileSync(W, 'utf8');
const vOrig = fs.readFileSync(V, 'utf8');
const PROBE = `

add_action( 'admin_notices', function () {
    printf(
        '<div class="notice"><p>PROBE safe=%s features=%s</p></div>',
        vergeml_safe_mode() ? 'YES' : 'no',
        function_exists( 'vergeml_search_columns' ) ? 'LOADED' : 'skipped'
    );
} );
`;
const CRASH = "\n\nvergeml_crash_test_undefined_function_do_not_ship();\n";

(async () => {
  const b = await chromium.launch();
  const p = await b.newPage({ viewport: { width: 1300, height: 800 } });
  const go = async (u) => { await p.goto('http://127.0.0.1:8899' + u + (u.includes('?') ? '&' : '?') + 'cb=' + Date.now(), { waitUntil: 'domcontentloaded', timeout: 60000 }); return await p.locator('body').innerText(); };
  const probe = (t) => (t.match(/PROBE safe=\w+ features=\w+/) || ['(probe did not render)'])[0];

  try {
    fs.writeFileSync(W, wOrig + PROBE);
    // Make sure it is actually running before asking it anything.
    await go('/wp-admin/plugins.php');
    const act = p.locator('tr:has-text("VergeLabs Media Library") a:text-is("Activate")').first();
    if (await act.count()) { await act.click(); await p.waitForTimeout(3000); console.log('(activated the plugin)'); }
    let t = await go('/wp-admin/');
    if (/Switch the features back on/i.test(t)) { await p.click('text=Switch the features back on'); await p.waitForTimeout(2500); t = await go('/wp-admin/'); }
    console.log('healthy      :', probe(t));

    fs.writeFileSync(V, vOrig + CRASH);
    for (let i = 0; i < 5; i++) await go('/');
    t = await go('/wp-admin/');
    console.log('after crashes:', probe(t), '| notice:', /safe mode/i.test(t));

    // Restore, then give every PHP worker time to pick the file back up and
    // confirm the site is genuinely healthy BEFORE resuming -- clicking resume
    // while a worker still has the broken copy just trips safe mode again.
    fs.writeFileSync(V, vOrig);
    await p.waitForTimeout(9000);
    for (let i = 0; i < 4; i++) await go('/');
    t = await go('/wp-admin/');
    console.log('before resume:', probe(t), '| notice:', /safe mode/i.test(t));
    if (/Switch the features back on/i.test(t)) {
      await p.click('text=Switch the features back on');
      await p.waitForTimeout(3000);
    }
    t = await go('/wp-admin/');
    console.log('after resume :', probe(t), '| notice:', /safe mode/i.test(t));
  } finally {
    fs.writeFileSync(W, wOrig);
    fs.writeFileSync(V, vOrig);
    await b.close();
  }
})();
