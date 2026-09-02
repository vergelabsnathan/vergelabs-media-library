#!/usr/bin/env bash
#
#  Give the box the limits a real WordPress site needs.
#
#  Stock PHP is 128M of memory, thirty seconds of execution and 2M uploads.
#  Demo importers refuse to start on that, large media libraries time out
#  halfway through, and the machine reads as underpowered when what is
#  underpowered is the configuration.
#
#  Additive and reversible: one drop-in file per service, nothing edited in
#  place. Delete the drop-ins and the box is exactly as it was.
#
#  Run through the box-tune workflow, which holds the key:
#      ssh root@<box> 'MEM=512M UP=128M bash -s' < tools/box-tune.sh
set -euo pipefail

MEM="${MEM:-512M}"
UP="${UP:-128M}"

PHPV="$( php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || true )"
echo "php on this box: ${PHPV:-unknown}"

wrote=0
for d in "/etc/php/${PHPV}/fpm/conf.d" "/etc/php/${PHPV}/cli/conf.d" /etc/php.d /usr/local/etc/php/conf.d; do
	[ -d "$d" ] || continue
	{
		echo "; Raised for real WordPress work: demo imports, large media"
		echo "; libraries, bulk operations. Written by tools/box-tune.sh."
		echo "; Delete this file to put the box back as it was."
		echo "memory_limit = ${MEM}"
		echo "max_execution_time = 300"
		echo "max_input_time = 300"
		echo "max_input_vars = 5000"
		echo "upload_max_filesize = ${UP}"
		echo "post_max_size = ${UP}"
	} > "${d}/99-vergelabs.ini"
	echo "wrote ${d}/99-vergelabs.ini"
	wrote=1
done

if [ "$wrote" != "1" ]; then
	echo "no php conf.d directory found -- nothing changed" >&2
	exit 1
fi

# nginx refuses a large upload before PHP ever sees it, and the error it
# returns says nothing about the limit that caused it.
if [ -d /etc/nginx/conf.d ]; then
	echo "client_max_body_size ${UP};" > /etc/nginx/conf.d/99-vergelabs.conf
	echo "wrote /etc/nginx/conf.d/99-vergelabs.conf"
fi

systemctl reload "php${PHPV}-fpm" 2>/dev/null \
	|| systemctl reload php-fpm 2>/dev/null \
	|| echo "(could not reload php-fpm; a restart may be needed)"

if nginx -t >/dev/null 2>&1; then
	systemctl reload nginx 2>/dev/null || true
fi

echo "--- what the box reports now"
php -r 'foreach (["memory_limit","max_execution_time","max_input_time","max_input_vars","upload_max_filesize","post_max_size"] as $k) { printf("%-22s %s\n", $k, ini_get($k)); }'
