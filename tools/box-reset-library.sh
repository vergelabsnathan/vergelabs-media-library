set -e
cd /var/www/wp
VGML_RESET="${VGML_RESET:-}" wp eval-file /tmp/vgml-reset.php --allow-root --skip-themes 2>&1 | grep -v "^Deprecated:" || true
rm -f /tmp/vgml-reset.php
