set -e
cd /var/www/wp
wp eval-file /tmp/vgml-grid.php --allow-root --skip-themes
rm -f /tmp/vgml-grid.php
