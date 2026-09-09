set -e
cd /var/www/wp
VGML_WALK_N="${VGML_WALK_N:-8}" wp eval-file /tmp/vgml-why-walk.php --allow-root --skip-themes 2>&1 | grep -v "^Deprecated:" || true
rm -f /tmp/vgml-why-walk.php
