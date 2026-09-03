set -e
cd /var/www/wp
wp eval-file /tmp/vgml-sample.php --allow-root --skip-themes
rm -f /tmp/vgml-sample.php
