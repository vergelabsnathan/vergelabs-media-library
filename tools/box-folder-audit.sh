set -e
cd /var/www/wp
wp eval-file /tmp/vgml-folder-audit.php --allow-root --skip-themes 2>&1 | grep -v "^Deprecated:" || true
rm -f /tmp/vgml-folder-audit.php
