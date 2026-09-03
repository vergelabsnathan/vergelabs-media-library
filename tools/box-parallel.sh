# Force the describe concurrency on the box: VGML_PARALLEL=16 sets it, 0 removes the override.
set -e
cd /var/www/wp
n="${VGML_PARALLEL:-0}"
sed -i "/define( 'VERGEML_AI_PARALLEL_FORCE'/d" wp-config.php
if [ "$n" != "0" ]; then
  sed -i "0,/\/\* That's all, stop editing/s//define( 'VERGEML_AI_PARALLEL_FORCE', $n );\n\n\/* That's all, stop editing/" wp-config.php
fi
grep -n "VERGEML_AI_PARALLEL_FORCE" wp-config.php || echo "override removed"
wp eval 'echo "parallel now: ", vergeml_ai_parallel(), "\n";' --allow-root --skip-themes 2>&1 | grep -v "^Deprecated:" || true
