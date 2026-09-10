set -e
cd /var/www/wp
VGML_SEED_N="${VGML_SEED_N:-1000}" wp eval-file /tmp/vgml-seed.php --allow-root --skip-themes 2>&1 | grep -vE "^Deprecated:|^Warning: Undefined array key .zion" || true
