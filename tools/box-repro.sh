set -e; cd /var/www/wp; wp eval-file /tmp/vgml-repro.php --allow-root --skip-themes 2>&1 | grep -v "^Deprecated:" || true; rm -f /tmp/vgml-repro.php
