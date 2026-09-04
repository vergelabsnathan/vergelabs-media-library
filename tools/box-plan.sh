set -e
cd /var/www/wp
VGML_APPLY="${VGML_APPLY:-0}" VGML_INSTRUCTION="${VGML_INSTRUCTION:-}" VGML_MODE="${VGML_MODE:-suggested}" wp eval-file /tmp/vgml-plan.php --allow-root --skip-themes 2>&1 | grep -v "^Deprecated:" || true
rm -f /tmp/vgml-plan.php
