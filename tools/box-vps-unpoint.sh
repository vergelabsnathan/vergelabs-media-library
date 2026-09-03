# Back to the Vercel service.
set -e
cd /var/www/wp
sed -i "/define( 'VERGEML_AI_SERVICE', 'http:\/\/127.0.0.1:3100\/v1' );/d" wp-config.php
grep -c "VERGEML_AI_SERVICE" wp-config.php || echo "override removed"
wp eval 'echo "plugin sees: ", vergeml_ai_service_url(), "\n";' --allow-root --skip-themes 2>&1 | grep -v "^Deprecated:" || true
