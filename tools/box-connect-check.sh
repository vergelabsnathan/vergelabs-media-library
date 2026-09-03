# What the AI screen's connect banner renders for an administrator (read-only).
set -e
cd /var/www/wp
wp eval 'ob_start(); vergeml_connect_banner(); $h = ob_get_clean(); echo wp_strip_all_tags( preg_replace( "/<a [^>]*href=\"([^\"]+)\"/", "[link: $1] ", $h ) ), "\n";' --user=1 --allow-root --skip-themes 2>&1 | grep -v "^Deprecated:" || true
