set -e; cd /var/www/wp; wp eval-file /tmp/vgml-row.php --allow-root --skip-themes 2>/dev/null | grep -v "^Deprecated:"; rm -f /tmp/vgml-row.php
