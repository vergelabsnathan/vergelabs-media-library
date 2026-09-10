set -e
cd /var/www/wp
VGML_FIXTURE_REMOVE="${VGML_FIXTURE_REMOVE:-}" wp eval-file /tmp/vgml-fixture.php --allow-root --skip-themes 2>&1 | grep -vE "^Deprecated:|zion" || true
rm -f /tmp/vgml-fixture.php
