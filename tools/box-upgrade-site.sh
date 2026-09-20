#!/usr/bin/env bash
#
#  The upgrade fixture on the Hetzner box: a fifth WordPress beside wp, ms,
#  ms2 and upd, on the box's own nip.io pattern, real MySQL, nothing shared
#  with the other four but the PHP-FPM socket and the `wp` database user.
#
#      bash box-upgrade-site.sh create            # /var/www/upg, wpupg, nginx, admin vgmls22
#      bash box-upgrade-site.sh plugin <zip> [--inactive]   # wp plugin install <zip> --force [--activate]; prints the zip's sha256 and the installed slug's version
#      bash box-upgrade-site.sh url               # prints the site URL
#      bash box-upgrade-site.sh reset             # destroy + create: the fixture back to empty
#      bash box-upgrade-site.sh destroy           # dir, database, nginx block
#
#  Runs ON the box as root (copy it over first). `create` is idempotent: an
#  installed site is left alone and reported; a half-built one is finished.
#  The walk itself (3.16.1 in, fixture, freeze, 4.0.0 in, suite) is driven
#  from the repo -- tools/verify.mjs upgrade-3161 for the suite, the fixture
#  and snapshot files by hand or from the story's notes. Story 1.2 of
#  plans/suite-readiness.md; the fixture stays up between schema bumps
#  (Nathan, 2026-09-19), and `reset` re-arms it for the next one.

set -euo pipefail

SITE=upg
ROOT=/var/www/$SITE
DB=wp$SITE
HOST=$SITE.46.225.66.194.nip.io
URL=http://$HOST
ADMIN=vgmls22
NGINX=/etc/nginx/sites-available/$SITE
MIRROR=/var/www/upd/wp-config.php

db_pass() { { grep "DB_PASSWORD" "$MIRROR" 2>/dev/null || true; } | sed -E "s/.*'DB_PASSWORD', *'([^']*)'.*/\1/"; }

create() {
  if wp core is-installed --path="$ROOT" --allow-root 2>/dev/null && [ -L /etc/nginx/sites-enabled/$SITE ]; then
    echo "exists: $URL ($ROOT, $DB)"; return 0
  fi
  DBPASS=$(db_pass)
  if [ -z "$DBPASS" ]; then echo "no DB password readable from $MIRROR"; exit 1; fi
  mkdir -p "$ROOT"
  mysql -e "CREATE DATABASE IF NOT EXISTS \`$DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL ON \`$DB\`.* TO 'wp'@'localhost'; FLUSH PRIVILEGES;"
  [ -f "$ROOT/wp-settings.php" ] || wp core download --path="$ROOT" --allow-root --quiet
  if [ ! -f "$ROOT/wp-config.php" ]; then
    wp config create --path="$ROOT" --dbname="$DB" --dbuser=wp --dbpass="$DBPASS" --dbhost=localhost --allow-root --quiet \
      --extra-php <<'PHP'
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
PHP
  fi
  if ! wp core is-installed --path="$ROOT" --allow-root 2>/dev/null; then
  PASS=$(openssl rand -hex 12)
  wp core install --path="$ROOT" --url="$URL" --title="Upgrade fixture" --admin_user="$ADMIN" --admin_password="$PASS" \
    --admin_email="$ADMIN@vergelabs.nl" --skip-email --allow-root --quiet
  echo "$PASS" > "/root/.$SITE-admin-pass"; chmod 600 "/root/.$SITE-admin-pass"
  fi
  sed -e "s#upd.46.225.66.194.nip.io#$HOST#" -e "s#/var/www/upd#$ROOT#" /etc/nginx/sites-enabled/upd > "$NGINX"
  ln -sf "$NGINX" /etc/nginx/sites-enabled/$SITE
  nginx -t -q || { echo "nginx refused the $SITE block"; exit 1; }
  systemctl reload nginx
  chown -R www-data:www-data "$ROOT"
  echo "created: $URL ($ROOT, $DB, admin $ADMIN, password in /root/.$SITE-admin-pass)"
}

destroy() {
  rm -f /etc/nginx/sites-enabled/$SITE "$NGINX"
  nginx -t -q || { echo "nginx config invalid after removing $SITE"; exit 1; }
  systemctl reload nginx
  rm -rf "$ROOT"; mysql -e "DROP DATABASE IF EXISTS \`$DB\`"; rm -f "/root/.$SITE-admin-pass"
  echo "destroyed $SITE"
}

case "${1:-}" in
  create) create ;;
  plugin)
    # Says what it installed: the zip's digest and the version of the slug the
    # zip carries (its top directory), not the free plugin's whatever went in
    # -- after a Pro install it once printed the free plugin's 4.0.0 as if that
    # were Pro's. --inactive leaves the plugin as installed, for a fixture that
    # keeps Pro inactive between runs.
    ZIP=${2:?zip path}
    ACTIVATE=--activate; [ "${3:-}" = "--inactive" ] && ACTIVATE=
    SLUG=$(unzip -Z1 "$ZIP" | head -1 | cut -d/ -f1)
    [ -n "$SLUG" ] || { echo "no top directory in $ZIP"; exit 1; }
    wp plugin install "$ZIP" --path="$ROOT" --force $ACTIVATE --allow-root
    chown -R www-data:www-data "$ROOT/wp-content"
    echo "zip     $(sha256sum "$ZIP" | cut -c1-64)  $(basename "$ZIP")"
    echo "plugin  $SLUG $(wp plugin get "$SLUG" --field=version --path="$ROOT" --allow-root) $(wp plugin get "$SLUG" --field=status --path="$ROOT" --allow-root)"
    ;;
  url) echo "$URL" ;;
  reset) destroy; create ;;
  destroy) destroy ;;
  *) echo "usage: $0 create|plugin <zip>|url|reset|destroy"; exit 2 ;;
esac
