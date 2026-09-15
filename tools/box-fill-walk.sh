set -e
cd /var/www/wp
wp eval-file /tmp/box-fill-walk.php --allow-root --skip-themes 2>&1 | grep -v "^Deprecated:" || true
rm -f /tmp/box-fill-walk.php
