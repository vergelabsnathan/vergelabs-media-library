/*
 *  Competitor watch.
 *
 *  Polls the wordpress.org plugin API for the plugins we compete with, and tells us
 *  when one of them moves. On a version change it downloads the new build, hashes
 *  every file, and diffs the manifest against the last one we saw -- so the report
 *  is "these twelve files changed, four of them in the folder tree", not "there is
 *  a new version".
 *
 *      node tools/watch-competitors.mjs           check and report
 *      node tools/watch-competitors.mjs --seed    record current state, report nothing
 *      node tools/watch-competitors.mjs --json    machine-readable, for CI
 *
 *  State lives in tools/competitors.json and is committed, so the history of what
 *  they shipped is in git alongside what we shipped.
 *
 *  Only Node built-ins plus PowerShell for unzip. Nothing to install.
 */

import { readFileSync, writeFileSync, existsSync, mkdirSync, rmSync, readdirSync, statSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { execFileSync } from 'node:child_process';
import { join, dirname, relative } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
const STATE = join(HERE, 'competitors.json');
const CACHE = join(HERE, '.cache');

const SEED = process.argv.includes('--seed');
const JSON_OUT = process.argv.includes('--json');

/*
 *  Why each one is watched. A slug with no reason does not belong here -- the list
 *  gets long and then nobody reads the report.
 */
const WATCH = [
  // Competitors -- what they ship tells us what the category expects.
  { kind: 'rival', slug: 'filebird', why: 'market leader; custom tables, React' },
  { kind: 'rival', slug: 'folders', why: 'Premio; native terms, the substrate we also chose' },
  { kind: 'rival', slug: 'enhanced-media-library', why: 'upstream. If it revives, our fork story changes' },
  { kind: 'rival', slug: 'real-media-library-lite', why: 'devowl.io; T3 importer target' },
  { kind: 'rival', slug: 'media-library-organizer', why: 'WP Zinc; smaller but same pitch' },
  { kind: 'rival', slug: 'enable-media-replace', why: '600k installs for one feature Folders Pro also sells' },

  /*
   *  Builders -- these are the ones that can break an integration we ship, and the
   *  entire reason to watch them is to find out within hours rather than from a
   *  support ticket. A red compat run here is the signal to look; this list is the
   *  trigger for it.
   */
  { kind: 'builder', slug: 'elementor', why: 'widget API; Widget_Base and the controls API' },
  { kind: 'builder', slug: 'gutenberg', why: 'block API ahead of core; where breakage lands first' },
  { kind: 'builder', slug: 'beaver-builder-lite-version', why: 'module API' },
  {
    kind: 'builder', slug: 'divi', why: 'ET_Builder_Module. Divi 5 is a rewrite, not a bump',
    // Not on wordpress.org, so the version comes off the public changelog.
    probe: async () => {
      // Divi is not on wordpress.org. Its version is published on the theme changelog;
      // if this shape changes the watch reports an error rather than a stale version.
      const urls = [
        'https://www.elegantthemes.com/changelog/divi/',
        'https://www.elegantthemes.com/documentation/divi/changelog/',
      ];
      for (const u of urls) {
        try {
          const r = await fetch(u, { headers: { 'User-Agent': 'Mozilla/5.0 vergelabs-watch' } });
          if (!r.ok) continue;
          const html = await r.text();
          const m = html.match(/(?:Version|Divi)\s*(\d+\.\d+(?:\.\d+)*)/i);
          if (m) return { version: m[1], updated: null, installs: null, rating: null, download: null, changelog: '' };
        } catch { /* try the next url */ }
      }
      throw new Error('could not read a Divi version -- check the changelog URL by hand');
    },
  },
];

/* Files whose changes matter more than the rest, so the report can lead with them. */
const INTERESTING = [
  /folder/i, /tree/i, /taxonom/i, /rest|api/i, /model/i, /migrat|upgrade|install/i,
];

const api = (slug) =>
  `https://api.wordpress.org/plugins/info/1.2/?action=plugin_information` +
  `&request[slug]=${encodeURIComponent(slug)}` +
  `&request[fields][sections]=1&request[fields][active_installs]=1`;

async function fetchInfo(slug) {
  const res = await fetch(api(slug), { headers: { 'User-Agent': 'vergelabs-competitor-watch' } });
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  const j = await res.json();
  if (j.error) throw new Error(j.error);
  return {
    version: j.version,
    updated: j.last_updated,
    installs: j.active_installs,
    rating: j.rating,
    ratings: j.num_ratings,
    tested: j.tested,
    requires_php: j.requires_php,
    download: j.download_link,
    changelog: stripTags(j.sections?.changelog ?? '').slice(0, 4000),
  };
}

const stripTags = (h) =>
  h.replace(/<li>/g, '\n  - ').replace(/<h4>/g, '\n\n').replace(/<[^>]+>/g, '').replace(/&#8217;/g, "'").trim();

/* --- file manifest ------------------------------------------------------- */

function download(url, to) {
  return fetch(url).then(async (r) => {
    if (!r.ok) throw new Error(`download HTTP ${r.status}`);
    writeFileSync(to, Buffer.from(await r.arrayBuffer()));
  });
}

function unzip(zip, dest) {
  rmSync(dest, { recursive: true, force: true });
  execFileSync('powershell', ['-NoProfile', '-Command',
    `Expand-Archive -Path '${zip}' -DestinationPath '${dest}' -Force`], { stdio: 'pipe' });
}

function manifest(root) {
  const out = {};
  const walk = (dir) => {
    for (const name of readdirSync(dir)) {
      const p = join(dir, name);
      const s = statSync(p);
      if (s.isDirectory()) { walk(p); continue; }
      // Built bundles are hashed by content anyway; their filenames churn every build.
      const rel = relative(root, p).replace(/\\/g, '/');
      out[rel] = createHash('sha1').update(readFileSync(p)).digest('hex').slice(0, 12);
    }
  };
  walk(root);
  return out;
}

function diffManifests(before, after) {
  const added = [], removed = [], changed = [];
  for (const f of Object.keys(after)) {
    if (!(f in before)) added.push(f);
    else if (before[f] !== after[f]) changed.push(f);
  }
  for (const f of Object.keys(before)) if (!(f in after)) removed.push(f);
  return { added, removed, changed };
}

const interesting = (files) => files.filter((f) => INTERESTING.some((re) => re.test(f)));

/* --- run ----------------------------------------------------------------- */

const state = existsSync(STATE) ? JSON.parse(readFileSync(STATE, 'utf8')) : { plugins: {} };
mkdirSync(CACHE, { recursive: true });

const report = [];

for (const w of WATCH) {
  const { slug, why, kind } = w;
  let info;
  try {
    info = w.probe ? await w.probe() : await fetchInfo(slug);
  } catch (e) {
    report.push({ slug, why, kind, error: String(e.message) });
    continue;
  }

  const prev = state.plugins[slug];
  const moved = !prev || prev.version !== info.version;

  const entry = { slug, why, kind, version: info.version, updated: info.updated, installs: info.installs, rating: info.rating, moved };

  if (moved && prev) {
    // Only bother with the file diff when we have something to diff against.
    try {
      const zip = join(CACHE, `${slug}-${info.version}.zip`);
      const dir = join(CACHE, `${slug}-${info.version}`);
      if (!info.download) throw new Error('no download link -- version tracked only');
      await download(info.download, zip);
      unzip(zip, dir);
      const now = manifest(dir);
      if (prev.manifest) {
        const d = diffManifests(prev.manifest, now);
        entry.files = {
          added: d.added.length, removed: d.removed.length, changed: d.changed.length,
          interesting: interesting([...d.added, ...d.changed]).slice(0, 25),
        };
      }
      entry.manifestNew = now;
      rmSync(dir, { recursive: true, force: true });
      rmSync(zip, { force: true });
    } catch (e) {
      entry.diffError = String(e.message);
    }
    entry.changelog = info.changelog;
  }

  if (moved && !prev) {
    // First sight: take a manifest so the next run has a baseline.
    try {
      const zip = join(CACHE, `${slug}-${info.version}.zip`);
      const dir = join(CACHE, `${slug}-${info.version}`);
      if (!info.download) throw new Error('no download link -- version tracked only');
      await download(info.download, zip);
      unzip(zip, dir);
      entry.manifestNew = manifest(dir);
      rmSync(dir, { recursive: true, force: true });
      rmSync(zip, { force: true });
    } catch (e) { entry.diffError = String(e.message); }
  }

  report.push(entry);

  state.plugins[slug] = {
    version: info.version,
    updated: info.updated,
    installs: info.installs,
    rating: info.rating,
    tested: info.tested,
    requires_php: info.requires_php,
    manifest: entry.manifestNew ?? prev?.manifest,
    seen: state.plugins[slug]?.seen ?? null,
  };
  state.plugins[slug].seen = new Date(info.last_updated ?? Date.now()).toISOString?.() ?? null;
}

state.checked = null; // stamped by the caller; keeps the file diff-friendly run to run
writeFileSync(STATE, JSON.stringify(state, null, 2) + '\n');

if (JSON_OUT) {
  console.log(JSON.stringify(report, null, 2));
} else {
  const moved = report.filter((r) => r.moved && !r.error);
  console.log('\nCompetitor and builder watch\n');
  for (const group of ['rival', 'builder']) {
    console.log(group === 'rival' ? '  competitors' : '\n  builders (these can break what we ship)');
    for (const r of report.filter((x) => x.kind === group)) {
      if (r.error) { console.log(`  !  ${r.slug.padEnd(28)} ${r.error}`); continue; }
      const flag = r.moved ? ' **' : '   ';
      console.log(
        `${flag} ${r.slug.padEnd(28)} v${String(r.version).padEnd(10)} ` +
        `${String(r.installs ?? '-').padStart(9)} installs  ${String(r.rating ?? '-').padStart(3)}/100  ${r.updated ?? ''}`,
      );
    }
  }
  for (const r of moved) {
    if (!r.files && !r.changelog) continue;
    console.log(`\n--- ${r.slug} moved to ${r.version}`);
    if (r.files) {
      console.log(`    ${r.files.changed} changed, ${r.files.added} added, ${r.files.removed} removed`);
      if (r.files.interesting.length) {
        console.log('    worth reading:');
        for (const f of r.files.interesting) console.log(`      ${f}`);
      }
    }
    if (r.changelog) console.log('    changelog:' + r.changelog.split('\n').slice(0, 12).map((l) => '\n      ' + l).join(''));
  }
  console.log(SEED ? '\nbaseline recorded\n' : '');
}
