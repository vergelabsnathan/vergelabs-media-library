set -e
cd /var/www/wp
ACTION="${ACTION:-check}" wp eval-file /tmp/vgml-gate7-canary.php --allow-root --skip-themes 2>&1 | grep -v "^Deprecated:" || true
