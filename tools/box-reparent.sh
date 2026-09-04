set -e
cd /var/www/wp
VGML_APPLY="${VGML_APPLY:-0}" wp eval-file /tmp/vgml-reparent.php --allow-root --skip-themes 2>&1 | grep -v "^Deprecated:" || true
rm -f /tmp/vgml-reparent.php
