set -e; cd /var/www/wp; wp eval-file /tmp/vgml-rp.php --allow-root --skip-themes 2>/dev/null | grep -v "^Deprecated:"; rm -f /tmp/vgml-rp.php
