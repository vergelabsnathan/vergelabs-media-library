set -e
cd /var/www/wp
wp eval-file /tmp/vgml-folders.php --allow-root --skip-themes
rm -f /tmp/vgml-folders.php
