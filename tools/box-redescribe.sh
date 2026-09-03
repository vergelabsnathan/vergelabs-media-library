set -e
cd /var/www/wp
wp eval-file /tmp/vgml-redescribe.php --allow-root --skip-themes 2>&1 | grep -v "^Deprecated:" || true
rm -f /tmp/vgml-redescribe.php
