#!/usr/bin/env bash
#
#  The upgrade fixture on the Hetzner box: a fifth WordPress beside wp, ms,
#  ms2 and upd, on the box's own nip.io pattern, real MySQL, nothing shared
#  with the other four but the PHP-FPM socket and the `wp` database user.
#
#      bash box-upgrade-site.sh create            # /var/www/upg, wpupg, nginx, admin vgmls22
#      bash box-upgrade-site.sh plugin <zip>      # wp plugin install <zip> --force --activate
#      bash box-upgrade-site.sh url               # prints the site URL
#      bash box-upgrade-site.sh destroy           # dir, database, nginx block (not run by default)
#
#  Runs ON the box as root (copy it over first). `create` is idempotent: a
#  site that exists is left alone and reported. Story 1.2 of
#  plans/suite-readiness.md; the fixture stays up between schema bumps
#  (Nathan, 2026-09-19).

set -euo pipefail

SITE=upg
ROOT=/var/www/$SITE
DB=wp$SITE
HOST=$SITE.46.225.66.194.nip.io
URL=http://$HOST
ADMIN=vgmls22
NGINX=/etc/nginx/sites-available/$SITE
MIRROR=/var/www/upd/wp-config.php

db_pass() { grep "DB_PASSWORD" "$MIRROR" | sed -E "s/.*'DB_PASSWORD', *'([^']*)'.*/\1/"; }

case "${1:-}" in
  create)
    if [ -f "$ROOT/wp-config.php" ]; then
      echo "exists: $URL ($ROOT, $DB)"; exit 0
    fi
    mkdir -p "$ROOT"
    mysql -e "CREATE DATABASE IF NOT EXISTS \`$DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL ON \`$DB\`.* TO 'wp'@'localhost'; FLUSH PRIVILEGES;"
    wp core download --path="$ROOT" --allow-root --quiet
    wp config create --path="$ROOT" --dbname="$DB" --dbuser=wp --dbpass="$(db_pass)" --dbhost=localhost --allow-root --quiet \
      --extra-php <<'PHP'
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
PHP
    PASS=$(openssl rand -hex 12)
    wp core install --path="$ROOT" --url="$URL" --title="Upgrade fixture" --admin_user="$ADMIN" --admin_password="$PASS" \
      --admin_email="$ADMIN@vergelabs.nl" --skip-email --allow-root --quiet
    echo "$PASS" > "/root/.$SITE-admin-pass"; chmod 600 "/root/.$SITE-admin-pass"
    sed -e "s#upd.46.225.66.194.nip.io#$HOST#" -e "s#/var/www/upd#$ROOT#" /etc/nginx/sites-enabled/upd > "$NGINX"
    ln -sf "$NGINX" /etc/nginx/sites-enabled/$SITE
    nginx -t -q && systemctl reload nginx
    chown -R www-data:www-data "$ROOT"
    echo "created: $URL ($ROOT, $DB, admin $ADMIN, password in /root/.$SITE-admin-pass)"
    ;;
  plugin)
    ZIP=${2:?zip path}
    wp plugin install "$ZIP" --path="$ROOT" --force --activate --allow-root
    chown -R www-data:www-data "$ROOT/wp-content"
    wp plugin get vergelabs-media-library --field=version --path="$ROOT" --allow-root
    ;;
  url) echo "$URL" ;;
  destroy)
    rm -f /etc/nginx/sites-enabled/$SITE "$NGINX"; nginx -t -q && systemctl reload nginx
    rm -rf "$ROOT"; mysql -e "DROP DATABASE IF EXISTS \`$DB\`"; rm -f "/root/.$SITE-admin-pass"
    echo "destroyed $SITE"
    ;;
  *) echo "usage: $0 create|plugin <zip>|url|destroy"; exit 2 ;;
esac
