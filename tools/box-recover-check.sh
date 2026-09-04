set -e
cd /var/www/wp
wp eval-file /tmp/vgml-recover-check.php --user=1 --allow-root --skip-themes 2>&1 | grep -v "^Deprecated:" || true
rm -f /tmp/vgml-recover-check.php
ls -la /var/backups 2>/dev/null | tail -5 || true
ls -la /root/*.sql* /var/www/*.sql* 2>/dev/null || echo "no sql dumps in /root or /var/www"
