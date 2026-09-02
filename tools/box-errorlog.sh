#!/usr/bin/env bash
#
#  What PHP complained about while the suite was running.
#
#  A screen can render perfectly and still be logging a warning on every load.
#  Nothing a browser can see catches that, so this reads the log itself and
#  reports only the lines pointing at our own files -- WooCommerce, Elementor
#  and the rest emit plenty on PHP 8.5 and none of it is ours to fix.
#
#      ACTION=mark  bash box-errorlog.sh   # remember where the log is now
#      ACTION=since bash box-errorlog.sh   # print our lines written since
set -euo pipefail

ACTION="${ACTION:-since}"
MARK=/tmp/vgml-errorlog.mark

find_log() {
	for f in /var/www/wp/wp-content/debug.log /var/log/php*-fpm.log /var/log/php_errors.log /var/log/nginx/error.log; do
		[ -f "$f" ] && { echo "$f"; return; }
	done
	php -r 'echo ini_get("error_log");' 2>/dev/null
}

LOG="$( find_log )"

if [ -z "$LOG" ] || [ ! -f "$LOG" ]; then
	echo "no php error log found -- nothing to check"
	exit 0
fi

case "$ACTION" in
	mark)
		wc -c < "$LOG" > "$MARK"
		echo "marked $LOG at $( cat "$MARK" ) bytes"
		;;
	since)
		FROM="$( cat "$MARK" 2>/dev/null || echo 0 )"
		NEW="$( tail -c "+$(( FROM + 1 ))" "$LOG" 2>/dev/null || true )"
		OURS="$( printf '%s\n' "$NEW" | grep -i 'vergelabs-media-library' || true )"

		if [ -z "$OURS" ]; then
			echo "php logged nothing about our code during the run"
			exit 0
		fi

		echo "php complained about our code while the suite ran:"
		printf '%s\n' "$OURS" | head -40
		exit 1
		;;
	*)
		echo "ACTION must be mark or since" >&2
		exit 1
		;;
esac
