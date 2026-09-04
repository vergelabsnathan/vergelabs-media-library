set -e
cd /var/www/wp
wp eval-file /tmp/vgml-tree-restore.php --user=1 --allow-root --skip-themes 2>&1 | grep -v "^Deprecated:" || true
rm -f /tmp/vgml-tree-restore.php
