# Point the box's plugin at the service running on the box itself.
set -e
cd /var/www/wp
if ! grep -q "VERGEML_AI_SERVICE" wp-config.php; then
  sed -i "0,/\/\* That's all, stop editing/s//define( 'VERGEML_AI_SERVICE', 'http:\/\/127.0.0.1:3100\/v1' );\n\n\/* That's all, stop editing/" wp-config.php
fi
grep -n "VERGEML_AI_SERVICE" wp-config.php
echo "service: /api/pricing -> HTTP $(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:3100/api/pricing)"
wp eval 'echo "plugin sees: ", VERGEML_AI_SERVICE, "\n"; delete_transient("vergeml_ai_credits_check"); $r = vergeml_ai_refresh_credits(); echo "handshake: ", is_wp_error($r) ? $r->get_error_message() : wp_json_encode(get_option("vergeml_ai_credits")), "\n";' --allow-root --skip-themes 2>&1 | grep -v "^Deprecated:" || true
