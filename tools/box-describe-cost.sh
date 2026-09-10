set -e
cd /var/www/wp
VGML_N="${VGML_N:-50}" wp eval-file /tmp/vgml-cost.php --allow-root --skip-themes 2>&1 | grep -vE "^Deprecated:|zion" || true
rm -f /tmp/vgml-cost.php
