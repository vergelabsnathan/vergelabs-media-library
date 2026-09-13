set -e
#  What the box's logs say about our plugin. The output of this script is what
#  gets pasted into a ticket, so it is the last place a licence key may pass
#  through: every log is counted for the key shape first (VGML- and 34 of
#  0-9A-Z; docs/security-secrets.md), and any match in a printed line is
#  redacted. A count above zero is itself the finding.
LOGS="/var/log/php*-fpm.log /var/log/php-fpm/*.log /var/www/wp/wp-content/debug.log /var/log/nginx/error.log"
echo "--- key-shaped strings per log (must be 0 before any of this reaches a ticket) ---"
for f in $LOGS; do
  [ -f "$f" ] || continue
  echo "$f: $(grep -aioE 'VGML-[0-9A-Z]{20,}' "$f" | wc -l)"
done
for f in $LOGS; do
  [ -f "$f" ] || continue
  echo "--- $f (last 40 lines mentioning our plugin or a fatal, since 15:00) ---"
  grep -aE "vergelabs-media-library|Fatal|fatal|Uncaught" "$f" | tail -40 | sed -E 's/VGML-[0-9A-Za-z]{20,}/VGML-<redacted>/g' || true
done
echo "--- wp-config debug flags ---"; grep -E "WP_DEBUG|WP_DEBUG_LOG|display_errors" /var/www/wp/wp-config.php || echo "(none set)"
