set -e
cd /var/www/wp
wp eval-file /tmp/vgml-recall.php --allow-root --skip-themes
rm -f /tmp/vgml-recall.php
