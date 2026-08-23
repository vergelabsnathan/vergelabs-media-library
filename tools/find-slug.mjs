/* Resolve a wordpress.org plugin slug from a name, so the watch list stops 404ing. */
const q = process.argv.slice(2).join(' ');
const url = 'https://api.wordpress.org/plugins/info/1.2/?action=query_plugins'
  + `&request[search]=${encodeURIComponent(q)}&request[per_page]=6`
  + '&request[fields][active_installs]=1&request[fields][sections]=0';
const r = await fetch(url, { headers: { 'User-Agent': 'vergelabs' } });
const j = await r.json();
for (const p of j.plugins ?? []) {
  console.log(`${String(p.slug).padEnd(34)} ${String(p.active_installs).padStart(9)}  ${p.name.replace(/<[^>]+>/g, '').slice(0, 60)}`);
}
