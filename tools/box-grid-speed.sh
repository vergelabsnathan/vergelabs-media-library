set -e
cd "${VGML_WP_DIR:-/var/www/wp}"
wp eval-file /tmp/vgml-grid.php --allow-root --skip-themes
rm -f /tmp/vgml-grid.php
