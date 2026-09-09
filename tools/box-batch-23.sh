set -e
cd /var/www/wp
wp eval-file /tmp/vgml-batch-23.php --allow-root --skip-themes 2>&1 | grep -v "^Deprecated:" || true
rm -f /tmp/vgml-batch-23.php
