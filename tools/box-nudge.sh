set -e; cd /var/www/wp; wp eval-file /tmp/vgml-nudge.php --allow-root --skip-themes 2>&1 | grep -v "^Deprecated:" || true; rm -f /tmp/vgml-nudge.php
