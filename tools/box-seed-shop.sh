# The second library's seed, on the box's second network (/var/www/ms2), as www-data so the uploads are the site's own.
set -e
cd /var/www/ms2
sudo -u www-data env VGML_SEED_N="${VGML_SEED_N:-500}" wp eval-file /tmp/vgml-seed-shop.php --url=http://ms2.46.225.66.194.nip.io --allow-root --skip-themes 2>&1 | grep -vE "^Deprecated:" || true
