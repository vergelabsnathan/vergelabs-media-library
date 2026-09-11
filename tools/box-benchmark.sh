#!/usr/bin/env bash
#
#  Two library sizes on real MariaDB: the numbers docs/benchmarks.md carries.
#
#      scp tools/scale.php           root@box:/root/vgml-bench/scale.php
#      scp tools/box-counts-cost.php root@box:/root/vgml-bench/counts.php
#      scp tools/box-grid-speed.php  root@box:/root/vgml-bench/grid.php
#      VGML_WP_DIR=/var/www/ms VGML_SCALE_N=10000 bash tools/box-benchmark.sh
#
#  One size per run: `scale.php up`, then three rounds of `scale.php time`,
#  the smart-counts cost and the grid probe, then `scale.php down`. The middle
#  of the three is the number that gets written. Every path timed is database
#  and PHP -- the fixture has no files on disk -- which is what the plan asks:
#  the numbers for the paths that never need the model.
#
#  Written 2026-09-11 (Phase 1, task 1.5). Never leaves the fixture standing:
#  `down` runs even when a round fails.

set -u
DIR="${VGML_WP_DIR:-/var/www/wp}"
N="${VGML_SCALE_N:-10000}"
FOLDERS="${VGML_SCALE_FOLDERS:-500}"
B=/root/vgml-bench
WP="wp --allow-root --skip-themes"
quiet() { grep -vE "^Deprecated:|zion|^PHP Warning|^Warning:" || true; }

cd "$DIR"
echo "== $DIR · n=$N · folders=$FOLDERS · $(date -u +%FT%TZ)"
echo "== before: $($WP post list --post_type=attachment --post_status=any --format=count 2>/dev/null) attachments, $($WP term list media_category --format=count 2>/dev/null) folders"

$WP eval-file "$B/scale.php" up "n=$N" "folders=$FOLDERS" 2>&1 | quiet

for round in 1 2 3; do
	echo; echo "== round $round · scale.php time"
	$WP eval-file "$B/scale.php" time 2>&1 | quiet
	echo "== round $round · smart counts"
	$WP eval-file "$B/counts.php" 2>&1 | quiet | head -4
	echo "== round $round · grid probe"
	$WP eval-file "$B/grid.php" 2>&1 | quiet | grep -E "WP_Query|picking"
done

echo; echo "== down"
$WP eval-file "$B/scale.php" down 2>&1 | quiet
echo "== after: $($WP post list --post_type=attachment --post_status=any --format=count 2>/dev/null) attachments, $($WP term list media_category --format=count 2>/dev/null) folders · $(date -u +%FT%TZ)"
