set -e
cd /var/www/wp
wp eval-file /tmp/vgml-why-walk-undo.php --allow-root --skip-themes 2>&1 | grep -v "^Deprecated:" || true
rm -f /tmp/vgml-why-walk-undo.php
