# What the on-box service has been saying (read-only).
set -e
pm2 describe vgml-service 2>/dev/null | grep -E "status|restarts|uptime|memory|cpu" | sed 's/│//g;s/  */ /g'
echo "--- last 150 lines"
pm2 logs vgml-service --nostream --lines 150 2>/dev/null | grep -vE "^\[TAILING\]|^/root/.pm2/logs|^$" | tail -150
