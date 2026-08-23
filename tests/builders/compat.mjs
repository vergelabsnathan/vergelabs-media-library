/*
 *  Builder compatibility matrix.
 *
 *  Knowing that Elementor shipped is not useful on its own. What matters is whether
 *  it broke us. So for each builder: boot a WordPress with that builder AND this
 *  plugin, and check the things that would actually be broken.
 *
 *      node tests/builders/compat.mjs                 every builder
 *      node tests/builders/compat.mjs --only elementor,gutenberg
 *
 *  This runs today against a plugin that ships no builder integration yet, and it is
 *  still worth running: this plugin hooks the media library on every admin screen,
 *  and every one of these builders ships its own media modal. Two plugins patching
 *  wp.media is exactly where fatals live. When the gallery integration lands, the
 *  render assertions below stop being skipped and this becomes its gate.
 */

import { spawn, execFileSync } from 'node:child_process';
import { writeFileSync, mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const REPO = process.cwd();
const only = (() => {
  const i = process.argv.indexOf('--only');
  return i === -1 ? null : new Set(process.argv[i + 1].split(',').map((s) => s.trim()).filter(Boolean));
})();

const BUILDERS = [
  { slug: 'elementor', name: 'Elementor', path: 'elementor/elementor.php' },
  { slug: 'gutenberg', name: 'Gutenberg', path: 'gutenberg/gutenberg.php' },
  { slug: 'beaver-builder-lite-version', name: 'Beaver Builder', path: 'beaver-builder-lite-version/fl-builder.php' },
  { slug: 'divi', name: 'Divi', skip: 'not on wordpress.org -- needs a licensed zip, test by hand' },
];

const results = [];
let port = 8910;

for (const b of BUILDERS) {
  if (only && !only.has(b.slug)) continue;
  if (b.skip) { results.push({ ...b, status: 'skipped', note: b.skip }); continue; }

  const dir = mkdtempSync(join(tmpdir(), 'vgml-compat-'));
  const bpPath = join(dir, 'blueprint.json');

  // Install the builder from wordpress.org; activate OURS from the mount. Installing
  // the mounted plugin instead collides with --mount-dir ("Device or resource busy").
  writeFileSync(bpPath, JSON.stringify({
    $schema: 'https://playground.wordpress.net/blueprint-schema.json',
    login: true,
    preferredVersions: { php: '8.3', wp: 'latest' },
    steps: [
      { step: 'installPlugin', pluginData: { resource: 'wordpress.org/plugins', slug: b.slug }, options: { activate: true } },
      { step: 'activatePlugin', pluginPath: 'vergelabs-media-library/vergelabs-media-library.php' },
      {
        step: 'runPHP',
        code: `<?php require_once '/wordpress/wp-load.php';
          @mkdir('/wordpress/wp-content/mu-plugins', 0777, true);
          file_put_contents('/wordpress/wp-content/mu-plugins/bench-auth.php',
            "<?php add_filter('wp_is_application_passwords_available','__return_true');");
          $uid = get_user_by('login','admin')->ID;
          update_user_meta($uid, '_application_passwords', array(array(
            'uuid' => wp_generate_uuid4(), 'app_id' => '', 'name' => 'compat',
            'password' => function_exists('wp_fast_hash') ? wp_fast_hash('benchbenchbenchbench') : wp_hash_password('benchbenchbenchbench'),
            'created' => time(), 'last_used' => null, 'last_ip' => null )));`,
      },
    ],
  }, null, 2));

  const p = port++;
  const base = `http://127.0.0.1:${p}`;
  const server = spawn('npx', ['--yes', '@wp-playground/cli@latest', 'server',
    '--port', String(p),
    '--mount-dir', REPO, '/wordpress/wp-content/plugins/vergelabs-media-library',
    '--blueprint', bpPath,
  ], { cwd: REPO, env: { ...process.env, MSYS_NO_PATHCONV: '1' }, shell: true, stdio: ['ignore', 'pipe', 'pipe'] });

  let log = '';
  server.stdout.on('data', (d) => { log += d; });
  server.stderr.on('data', (d) => { log += d; });

  const ready = await new Promise((resolve) => {
    const t = setTimeout(() => resolve(false), 300000);
    const iv = setInterval(() => {
      if (/Ready!/.test(log)) { clearInterval(iv); clearTimeout(t); resolve(true); }
      if (/^Error|\bError:/m.test(log)) { clearInterval(iv); clearTimeout(t); resolve(false); }
    }, 1000);
  });

  const checks = [];
  if (!ready) {
    checks.push({ name: 'boots with both plugins active', ok: false, detail: log.split('\n').filter((l) => /error/i.test(l)).slice(0, 3).join(' | ') });
  } else {
    const H = {
      Authorization: 'Basic ' + Buffer.from('admin:benchbenchbenchbench').toString('base64'),
      Cookie: 'playground_auto_login_already_happened=1',
    };
    const get = async (path) => {
      try {
        const r = await fetch(base + path, { headers: H });
        return { status: r.status, body: await r.text() };
      } catch (e) { return { status: 0, body: String(e.message) }; }
    };

    checks.push({ name: 'boots with both plugins active', ok: true });

    // The media library screen is where two plugins patching wp.media collide.
    const upload = await get('/wp-admin/upload.php?mode=grid');
    checks.push({
      name: 'media library renders, no fatal',
      ok: upload.status === 200 && !/Fatal error|There has been a critical error/i.test(upload.body),
      detail: `HTTP ${upload.status}`,
    });

    const tree = await get('/wp-json/vergeml/v1/tree?taxonomy=media_category');
    let nodes = null;
    try { nodes = JSON.parse(tree.body)?.nodes?.length ?? null; } catch {}
    checks.push({ name: 'our tree endpoint still answers', ok: tree.status === 200 && nodes !== null, detail: `HTTP ${tree.status}, ${nodes} nodes` });

    const media = await get('/wp-json/wp/v2/media?per_page=1');
    checks.push({ name: 'core media REST unbroken', ok: media.status === 200, detail: `HTTP ${media.status}` });

    // Placeholder for the gallery integration. Turns into a real assertion the day
    // it ships; until then it is honestly reported as not applicable rather than
    // quietly passing.
    checks.push({ name: 'gallery integration renders', ok: null, detail: 'not built yet' });
  }

  server.kill();
  try { execFileSync('powershell', ['-NoProfile', '-Command', `Get-Process node -ErrorAction SilentlyContinue | Where-Object { $_.Id -eq ${server.pid} } | Stop-Process -Force`], { stdio: 'ignore' }); } catch {}
  rmSync(dir, { recursive: true, force: true });

  results.push({ ...b, status: checks.some((c) => c.ok === false) ? 'FAIL' : 'ok', checks });
}

console.log('\nBuilder compatibility\n');
let failed = false;
for (const r of results) {
  if (r.status === 'skipped') { console.log(`  --  ${r.name.padEnd(18)} skipped: ${r.note}`); continue; }
  console.log(`  ${r.status === 'ok' ? 'ok  ' : 'FAIL'} ${r.name}`);
  for (const c of r.checks) {
    const mark = c.ok === null ? ' -- ' : c.ok ? ' ok ' : 'FAIL';
    console.log(`       ${mark} ${c.name}${c.detail ? '  (' + c.detail + ')' : ''}`);
  }
  if (r.status === 'FAIL') failed = true;
}
console.log('');
process.exit(failed ? 1 : 0);
