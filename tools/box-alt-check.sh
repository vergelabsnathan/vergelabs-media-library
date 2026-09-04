set -e
cd /var/www/wp
wp eval-file /tmp/vgml-alt-check.php --user=1 --allow-root --skip-themes 2>&1 | grep -v "^Deprecated:" || true
rm -f /tmp/vgml-alt-check.php
