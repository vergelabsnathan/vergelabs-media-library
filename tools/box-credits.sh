set -e
cd /var/www/wp
wp eval-file /tmp/vgml-credits.php --allow-root --skip-themes
rm -f /tmp/vgml-credits.php
