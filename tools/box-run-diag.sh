set -e
cd /var/www/wp
wp eval-file /tmp/vgml-diag.php --allow-root --skip-themes 2>/dev/null | grep -v "^Deprecated:"
rm -f /tmp/vgml-diag.php
