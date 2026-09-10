set -e
cd /var/www/wp
VGML_SCALE_N="${VGML_SCALE_N:-250000}" VGML_SCALE_REMOVE="${VGML_SCALE_REMOVE:-}" wp eval-file /tmp/vgml-scale.php --allow-root --skip-themes 2>&1 | grep -vE "^Deprecated:|zion" || true
