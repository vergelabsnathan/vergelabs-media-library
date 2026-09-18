#!/usr/bin/env bash
#
#  The real shop on the box (S19): a new subsite of the ms2 network with
#  WooCommerce and its own sample catalogue, the product photos fetched.
#
#      scp tools/box-tree-snapshot.php root@box:/tmp/vgml-tree-snapshot.php
#      scp tools/box-shop-real-import.php root@box:/tmp/vgml-shop-real-import.php
#      scp tools/box-connect-ms2.php root@box:/tmp/vgml-connect-ms2.php
#      ssh root@box bash -s < tools/box-shop-real-build.sh
#
#  What it does, in order, each step skipped when already done:
#    1. freezes the C.5 site's tree (the ms2 main site) to
#       /var/www/ms2/wp-content/vgml-shop-tree-before-real.json -- the belt;
#       nothing below touches that site;
#    2. creates the subsite shop.ms2.46.225.66.194.nip.io (nginx already
#       answers *.ms2, nip.io resolves any label); the network-activated
#       plugin provisions it on creation;
#    3. links the tech site's WooCommerce (11.1.0) into the ms2 plugin
#       directory and activates it on the shop only, as a shop owner would --
#       not network-wide -- and drops the setup-wizard redirect;
#    4. imports sample_products.csv (25 products, 22 photos) and
#       experimental_fashion_sample_9_products.csv (9 products, 10 photos)
#       through tools/box-shop-real-import.php: WooCommerce's own importer,
#       every photo fetched into the shop's library with its product as parent;
#    5. seals the box's licence key on the shop (the same seat as ms2; an
#       agency licence, sites are not counted) with tools/box-connect-ms2.php.
#  Spends nothing: no describe starts on an upload. Never the C.5 site.
set -euo pipefail

MS2=/var/www/ms2
NET=ms2.46.225.66.194.nip.io
HOST=shop.$NET
URL=http://$HOST
WP="sudo -u www-data wp --allow-root --skip-themes"
WOO=/var/www/wp/wp-content/plugins/woocommerce

cd "$MS2"

echo "== 1 the C.5 tree, frozen"
if [ ! -f "$MS2/wp-content/vgml-shop-tree-before-real.json" ]; then
	sudo -u www-data env VGML_MODE=snapshot VGML_FILE=$MS2/wp-content/vgml-shop-tree-before-real.json wp eval-file /tmp/vgml-tree-snapshot.php --url=http://$NET --allow-root --skip-themes 2>&1 | grep -v "^Deprecated:" || true
else
	echo "already frozen"
fi

echo "== 2 the subsite"
if ! $WP site list --field=url 2>/dev/null | grep -q "^$URL/"; then
	$WP site create --slug=shop --title="Sample shop" 2>&1 | grep -v "^Deprecated:" || true
fi
$WP site list --fields=blog_id,url 2>/dev/null | grep "$HOST"
$WP db query "SHOW TABLES LIKE 'wp_%vergeml%'" --skip-column-names 2>/dev/null | grep -c "vergeml" | sed 's/^/vergeml tables on the network: /'

echo "== 3 WooCommerce on the shop"
if [ ! -e "$MS2/wp-content/plugins/woocommerce" ]; then
	ln -s "$WOO" "$MS2/wp-content/plugins/woocommerce"
fi
$WP plugin activate woocommerce --url=$URL 2>&1 | grep -v "^Deprecated:" || true
$WP transient delete _wc_activation_redirect --url=$URL 2>/dev/null || true
$WP option update woocommerce_task_list_hidden yes --url=$URL --quiet 2>/dev/null || true
$WP plugin list --url=$URL --fields=name,status,version 2>/dev/null | grep -E "woocommerce|vergelabs"

echo "== 4 the catalogue"
if [ "$($WP post list --post_type=product --format=count --url=$URL 2>/dev/null)" = "0" ]; then
	sudo -u www-data env VGML_CSV=$WOO/sample-data/sample_products.csv,$WOO/sample-data/experimental_fashion_sample_9_products.csv \
		wp eval-file /tmp/vgml-shop-real-import.php --url=$URL --user=1 --allow-root --skip-themes 2>&1 | grep -v "^Deprecated:" || true
else
	echo "already imported: $($WP post list --post_type=product --format=count --url=$URL) products, $($WP post list --post_type=attachment --format=count --url=$URL) pictures"
fi

echo "== 5 the licence"
K=$(cd /var/www/wp && wp eval 'echo vergeml_ai_unseal( get_option( "vergeml_ai" )["license_key"] );' --allow-root --skip-themes 2>/dev/null | tail -n 1)
if [ -z "$K" ]; then echo "the main site's key did not unseal"; exit 1; fi
sudo -u www-data env VGML_KEY="$K" wp eval-file /tmp/vgml-connect-ms2.php --url=$URL --allow-root --skip-themes 2>&1 | grep -v "^Deprecated:" || true

rm -f /tmp/vgml-tree-snapshot.php /tmp/vgml-shop-real-import.php /tmp/vgml-connect-ms2.php
echo "== http"; php -r '$h = get_headers($argv[1]); echo $argv[1], " -> ", $h[0], "\n";' "$URL/wp-login.php"
