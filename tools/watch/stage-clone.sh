#!/usr/bin/env bash
#
#  Makes (or re-makes) the upgrade stage on the box: a clone of the main site at
#  /var/www/upd with its own database, served as http://upd.46.225.66.194.nip.io.
#  The watch may upgrade anything on it; it never touches /var/www/wp or /var/www/ms.
#
#      scp tools/watch/stage-clone.sh root@46.225.66.194:/tmp/ && ssh root@46.225.66.194 bash /tmp/stage-clone.sh
#      ssh root@46.225.66.194 bash /tmp/stage-clone.sh --fresh     # drop and re-clone
#
#  The plugin directory is a symlink to the deployed copy, so `deploy --box`
#  updates the stage along with the other two sites.
set -euo pipefail

SRC=/var/www/wp
DST=/var/www/upd
DB=wpupd
HOST=upd.46.225.66.194.nip.io
W="sudo -u www-data wp --allow-root"

if [ "${1:-}" = "--fresh" ] && [ -d "$DST" ]; then
    rm -rf "$DST"
    mysql -e "DROP DATABASE IF EXISTS $DB;"
fi

if [ -d "$DST" ]; then
    echo "stage exists at $DST"
    exit 0
fi

DBP=$(grep "DB_PASSWORD" "$SRC/wp-config.php" | awk -F"'" '{print $4}')

mysql -e "CREATE DATABASE IF NOT EXISTS $DB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL ON $DB.* TO 'wp'@'localhost'; FLUSH PRIVILEGES;"
mysqldump --single-transaction wp | mysql "$DB"

rsync -a --exclude wp-content/plugins/vergelabs-media-library --exclude wp-content/cache "$SRC/" "$DST/"
ln -s "$SRC/wp-content/plugins/vergelabs-media-library" "$DST/wp-content/plugins/vergelabs-media-library"

sed -i "s/define( 'DB_NAME', 'wp' )/define( 'DB_NAME', '$DB' )/" "$DST/wp-config.php"
if ! grep -q "WP_ENVIRONMENT_TYPE" "$DST/wp-config.php"; then
    sed -i "/DB_NAME/a define( 'WP_ENVIRONMENT_TYPE', 'staging' );\ndefine( 'VERGEML_AI_ALLOW_NONPROD', true );" "$DST/wp-config.php"
fi
chown -R www-data:www-data "$DST"

sed -e "s#ms.46.225.66.194.nip.io \*.ms.46.225.66.194.nip.io#$HOST#" -e "s#/var/www/ms#$DST#" \
    /etc/nginx/sites-available/ms > /etc/nginx/sites-available/upd
ln -sf /etc/nginx/sites-available/upd /etc/nginx/sites-enabled/upd
nginx -t
systemctl reload nginx

cd "$DST"
$W search-replace "http://46.225.66.194" "http://$HOST" --all-tables --quiet
echo "siteurl: $($W option get siteurl)"
echo "active:  $($W plugin list --status=active --field=name | tr '\n' ' ')"
$W eval 'echo "plugin ", VERGEML_VERSION, " env ", wp_get_environment_type(), "\n";'
$W eval '$r = wp_remote_get( home_url( "/" ) ); echo "home ", wp_remote_retrieve_response_code( $r ), "\n";'
