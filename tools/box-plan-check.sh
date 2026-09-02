set -e
cd /var/www/wp
wp eval-file /tmp/vgml-plan.php --allow-root --skip-themes
rm -f /tmp/vgml-plan.php
