# How long did the cron requests live around the last run, and what kills them? Read-only.
set -e
echo "=== php-fpm limits"
grep -RhE "^\s*(request_terminate_timeout|pm\.max_children|pm\s*=|memory_limit|max_execution_time)" /etc/php/*/fpm/pool.d/*.conf /etc/php/*/fpm/php.ini 2>/dev/null | sed 's/^/  /'
echo "=== wp-cron.php: the lock check WordPress core applies"
grep -nE "doing_cron_transient|doing_wp_cron|WP_CRON_LOCK_TIMEOUT" /var/www/wp/wp-cron.php | sed 's/^/  /'
echo "=== wp-cron.php requests in the last 40 minutes: end time minus the start time in the query (nginx logs at completion)"
python3 - <<'PY'
import re, datetime, collections
now = datetime.datetime.utcnow()
pat = re.compile(r'\[(\d+/\w+/\d+:\d+:\d+:\d+) [+-]\d+\] "(\w+) /wp-cron\.php\?doing_wp_cron=([\d.]+)')
per_min = collections.Counter(); long_ones = []
for line in open('/var/log/nginx/access.log', errors='replace'):
    m = pat.search(line)
    if not m: continue
    end = datetime.datetime.strptime(m.group(1), '%d/%b/%Y:%H:%M:%S')
    if (now - end).total_seconds() > 40*60: continue
    start = datetime.datetime.utcfromtimestamp(float(m.group(3)))
    d = (end - start).total_seconds()
    per_min[end.strftime('%H:%M')] += 1
    if d > 2: long_ones.append((start, end, d, m.group(2)))
for k in sorted(per_min): print(f"  {k}  {per_min[k]:3d} requests")
print("  --- lived longer than 2s:")
for s, e, d, meth in long_ones: print(f"  {meth} started {s:%H:%M:%S} ended {e:%H:%M:%S}  {d:.0f}s")
if not long_ones: print("  (none)")
PY
