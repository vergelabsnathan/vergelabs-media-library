set -e
cd /var/www/wp
wp eval-file /tmp/vgml-refile.php --allow-root --skip-themes
rm -f /tmp/vgml-refile.php
