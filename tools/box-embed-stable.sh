set -e
cd /var/www/wp
for i in 1 2 3; do wp eval-file /tmp/vgml-embed-stable.php --allow-root --skip-themes 2>&1 | grep -v "^Deprecated:" | grep -E "checksum|dimensions|head"; done
rm -f /tmp/vgml-embed-stable.php
