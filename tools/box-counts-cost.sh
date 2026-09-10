set -e
cd /var/www/wp
VGML_COUNTS_VERIFY="${VGML_COUNTS_VERIFY:-}" wp eval-file /tmp/vgml-counts.php --allow-root --skip-themes 2>&1 | grep -vE "^Deprecated:|zion" || true
rm -f /tmp/vgml-counts.php
