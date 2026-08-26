const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const BASE = 'http://127.0.0.1:8899';

/*
 *  This suite deliberately breaks a real plugin file, because the watchdog
 *  only counts fatals in the plugin's own code and a crash from anywhere else
 *  would prove nothing.
 *
 *  Which makes restoring it the most important thing the file does. Holding
 *  the original in a variable and restoring in `finally` was not enough: kill
 *  the process and the variable dies with it, leaving a fatal error committed
 *  in core/taxonomies.php for the next deploy to ship. That happened.
 *
 *  So the original goes to disk before the file is touched, every exit path
 *  restores it, and a run that finds a leftover backup puts it back before
 *  doing anything else. The only unrecoverable case is SIGKILL, and the next
 *  run heals that.
 */
const ROOT = path.resolve(__dirname, '..', '..');
const VICTIM = path.join(ROOT, 'core', 'taxonomies.php');
const BACKUP = path.join(__dirname, '.victim-backup');
const CRASH = "\n\nvergeml_crash_test_undefined_function_do_not_ship();\n";
const SETTINGS = '/wp-admin/options-general.php?page=media-library'; // registered by core/options-pages.php, which safe mode skips

function restoreVictim() {
  if (!fs.existsSync(BACKUP)) return false;
  fs.writeFileSync(VICTIM, fs.readFileSync(BACKUP, 'utf8'));
  fs.unlinkSync(BACKUP);
  return true;
}

// A previous run that was killed rather than finished.
if (restoreVictim()) {
  console.log('  restored core/taxonomies.php from an interrupted run');
}

if (fs.readFileSync(VICTIM, 'utf8').includes('vergeml_crash_test')) {
  console.error('\nFATAL: core/taxonomies.php still carries the crash marker and no backup exists.');
  console.error('Restore it before running anything else:  git checkout -- core/taxonomies.php\n');
  process.exit(2);
}

// Ctrl-C and a harness timeout both land here; exit handlers must be sync.
process.on('exit', restoreVictim);
process.on('SIGINT', () => process.exit(130));
process.on('SIGTERM', () => process.exit(143));
process.on('uncaughtException', (e) => { console.error(e); process.exit(1); });

const results = [];
const check = (n, ok, d = '') => { results.push({ n, ok }); console.log(`  ${ok ? 'ok  ' : 'FAIL'} ${n}${d ? '  — ' + d : ''}`); };

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1400, height: 900 } });
  page.setDefaultNavigationTimeout(90000);

  let cb = 0;
  async function hit(path = '/') {
    // Cache-busted: a repeated goto of the same URL is served from the browser
    // cache, which reports a healthy 200 for a site that is actually down.
    const url = BASE + path + (path.includes('?') ? '&' : '?') + 'cb=' + (++cb) + '_' + Date.now();
    const res = await page.goto(url, { waitUntil: 'domcontentloaded' });
    const body = await page.locator('body').innerText().catch(() => '');
    const status = res ? res.status() : 0;
    return { status, body, broken: status >= 500 || /There has been a critical error/i.test(body) || /^\s*Fatal error/im.test(body) };
  }

  async function clearSafeMode() {
    const r = await hit('/wp-admin/');
    if (/Switch the features back on/i.test(r.body)) {
      await page.click('text=Switch the features back on');
      await page.waitForTimeout(2000);
    }
  }

  // On disk, not just in memory: the whole point of the backup is that it
  // outlives this process.
  const original = fs.readFileSync(VICTIM, 'utf8');
  fs.writeFileSync(BACKUP, original);

  try {
    console.log('\n0. reset to a known state');
    await hit('/wp-admin/plugins.php');
    const activate = page.locator('tr:has-text("VergeLabs Media Library") a:text-is("Activate")').first();
    if (await activate.count()) { await activate.click(); await page.waitForTimeout(2500); console.log('  reactivated the plugin'); }
    await clearSafeMode();
    let r = await hit(SETTINGS);
    check('starting healthy, feature screens load', !r.broken && r.status === 200, `HTTP ${r.status}`);

    console.log('\n1. break it on every request');
    fs.writeFileSync(VICTIM, original + CRASH);
    let recovered = 0;
    for (let i = 2; i <= 6 && !recovered; i++) {
      r = await hit('/');
      if (!r.broken) recovered = i;
    }
    check('site RECOVERS BY ITSELF', !!recovered, recovered ? `on request ${recovered}` : 'never');

    for (let i = 0; i < 4; i++) r = await hit('/');
    check('stays up under continued load', !r.broken, `HTTP ${r.status}`);

    console.log('\n2. the plugin must still be ACTIVE, not deactivated');
    r = await hit('/wp-admin/plugins.php');
    const stillActive = await page.locator('tr:has-text("VergeLabs Media Library") a:text-is("Deactivate")').count();
    check('plugin is still active (safe mode, not rung 3)', stillActive > 0);

    console.log('\n3. what the admin sees');
    r = await hit('/wp-admin/');
    check('wp-admin reachable', !r.broken, `HTTP ${r.status}`);
    check('safe-mode notice shown', /safe mode/i.test(r.body));
    check('notice quotes the real error', /undefined function/i.test(r.body));
    await page.screenshot({ path: `${__dirname}/watchdog-notice.png` });
    r = await hit(SETTINGS);
    check('feature screens are genuinely gone in safe mode', r.status === 403, `HTTP ${r.status}`);

    console.log('\n4. fix, resume, verify');
    fs.writeFileSync(VICTIM, original);
    await clearSafeMode();
    r = await hit(SETTINGS);
    check('feature screens return after resume', !r.broken && r.status === 200, `HTTP ${r.status}`);
    r = await hit('/');
    check('site healthy', !r.broken, `HTTP ${r.status}`);
  } finally {
    fs.writeFileSync(VICTIM, original);
    if (fs.existsSync(BACKUP)) fs.unlinkSync(BACKUP);
    await browser.close();
  }

  const bad = results.filter((r) => !r.ok).length;
  console.log(`\n${results.length - bad}/${results.length} passed`);
  process.exit(bad ? 1 : 0);
})();
