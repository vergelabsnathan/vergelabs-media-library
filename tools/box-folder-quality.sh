set -e
# VGML_SITE=shop: the second library on the box's second network (every-picture-a-home C.5); default the tech library.
if [ "${VGML_SITE:-tech}" = "shop" ]; then
  cd /var/www/ms2
  sudo -u www-data env VGML_DRY="${VGML_DRY:-}" VGML_SEED="${VGML_SEED:-}" wp eval-file /tmp/box-folder-quality.php --url=http://ms2.46.225.66.194.nip.io --user=1 --allow-root --skip-themes 2>&1 | grep -v "^Deprecated:" || true
else
  cd /var/www/wp
  wp eval-file /tmp/box-folder-quality.php --user=1 --allow-root --skip-themes 2>&1 | grep -v "^Deprecated:" || true
fi
rm -f /tmp/box-folder-quality.php
