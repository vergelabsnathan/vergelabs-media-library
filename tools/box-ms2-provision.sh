#!/usr/bin/env bash
#
#  A second network on the box, subdomain-shaped, for the matrix's last row.
#
#      scp tools/box-ms2-provision.sh root@46.225.66.194:/tmp/ && ssh root@46.225.66.194 bash /tmp/box-ms2-provision.sh
#
#  /var/www/ms is a subdirectory network and a network does not change shape
#  after install, so the subdomain cell needs its own WordPress: /var/www/ms2,
#  database wpms2 (the same `wp` database user as ms), nginx answering
#  ms2.46.225.66.194.nip.io and *.ms2.46.225.66.194.nip.io (nip.io resolves
#  any label), converted with --subdomains, one sub-site "two", and the
#  deployed plugin symlinked in and network-activated -- the shape ms has.
#  Idempotent: a step that has been done is skipped. The main site is not
#  touched. Provisioned on Nathan's word of 2026-09-11 ("all 4").
set -euo pipefail

MS2=/var/www/ms2
HOST=ms2.46.225.66.194.nip.io
WP="sudo -u www-data wp --allow-root"

if [ ! -f "$MS2/wp-load.php" ]; then
	mkdir -p "$MS2" && chown www-data:www-data "$MS2"
	( cd "$MS2" && $WP core download --version=7.1 --quiet )
	echo "downloaded WordPress 7.1"
fi

mysql -e "CREATE DATABASE IF NOT EXISTS wpms2; GRANT ALL ON wpms2.* TO 'wp'@'localhost'; FLUSH PRIVILEGES;"

if [ ! -f "$MS2/wp-config.php" ]; then
	PASS=$(grep DB_PASSWORD /var/www/ms/wp-config.php | cut -d"'" -f4)
	( cd "$MS2" && $WP config create --dbname=wpms2 --dbuser=wp --dbpass="$PASS" --dbhost=localhost --quiet )
	echo "wp-config written"
fi

if [ ! -f /etc/nginx/sites-available/ms2 ]; then
	sed "s/server_name ms\.46/server_name ms2.46/; s/\*\.ms\.46/*.ms2.46/; s#root /var/www/ms;#root /var/www/ms2;#" /etc/nginx/sites-available/ms > /etc/nginx/sites-available/ms2
	ln -sf /etc/nginx/sites-available/ms2 /etc/nginx/sites-enabled/ms2
	nginx -t && systemctl reload nginx
	echo "nginx answers $HOST and *.$HOST"
fi

cd "$MS2"
if ! $WP core is-installed 2>/dev/null; then
	ADMIN=$(head -c 12 /dev/urandom | base64 | tr -d "/+=")
	$WP core install --url="http://$HOST" --title="ms2" --admin_user=admin --admin_password="$ADMIN" --admin_email=admin@invalid.test --skip-email --quiet
	echo "installed (the admin password is not kept; the matrix makes its own user)"
fi

if ! $WP core is-installed --network 2>/dev/null; then
	$WP core multisite-convert --subdomains --title="ms2 network" 2>&1 | grep -v Deprecated || true
	echo "converted to a subdomain network"
fi

if ! $WP site list --field=url 2>/dev/null | grep -q "two\.$HOST"; then
	$WP site create --slug=two --title="Site two" 2>&1 | grep -v Deprecated || true
	echo "sub-site two.$HOST created"
fi

if [ ! -e "$MS2/wp-content/plugins/vergelabs-media-library" ]; then
	ln -s /var/www/wp/wp-content/plugins/vergelabs-media-library "$MS2/wp-content/plugins/vergelabs-media-library"
fi
$WP plugin activate vergelabs-media-library --network 2>&1 | grep -v Deprecated || true

echo "== sites";   $WP site list --fields=blog_id,url
echo "== plugins"; $WP plugin list --fields=name,status
grep -n "SUBDOMAIN_INSTALL\|DOMAIN_CURRENT_SITE" wp-config.php
echo "== http";    for u in "http://$HOST/" "http://two.$HOST/"; do printf '%s -> ' "$u"; php -r '$h = get_headers($argv[1]); echo $h[0], "\n";' "$u"; done
