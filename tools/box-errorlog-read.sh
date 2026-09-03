set -e
for f in /var/log/php*-fpm.log /var/log/php-fpm/*.log /var/www/wp/wp-content/debug.log /var/log/nginx/error.log; do
  [ -f "$f" ] || continue
  echo "--- $f (last 40 lines mentioning our plugin or a fatal, since 15:00) ---"
  grep -aE "vergelabs-media-library|Fatal|fatal|Uncaught" "$f" | tail -40 || true
done
echo "--- wp-config debug flags ---"; grep -E "WP_DEBUG|WP_DEBUG_LOG|display_errors" /var/www/wp/wp-config.php || echo "(none set)"
