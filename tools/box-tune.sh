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
#
# Written, then tested, then kept -- in that order, and never kept untested.
# On 2026-09-10 this file carried 128M while a hand edit in nginx.conf carried
# 64M; nginx refuses a duplicate directive, so the config was fatal from the
# moment it was written. Nothing noticed, because nginx keeps running on the
# config it already loaded. Eight days later an unattended upgrade restarted
# it and the whole box went dark on 80 and 443. A config this script cannot
# prove good is a config it must not leave behind.
if [ -d /etc/nginx/conf.d ]; then
	ours=/etc/nginx/conf.d/99-vergelabs.conf
	had=0
	[ -f "$ours" ] && { had=1; cp "$ours" "${ours}.prev"; }

	echo "client_max_body_size ${UP};" > "$ours"

	if nginx -t >/dev/null 2>&1; then
		echo "wrote ${ours}"
	else
		echo "!! ${ours} makes nginx refuse its config -- putting it back:" >&2
		nginx -t 2>&1 | tail -2 >&2
		if [ "$had" = "1" ]; then
			mv "${ours}.prev" "$ours"
		else
			rm -f "$ours"
		fi
		nginx -t >/dev/null 2>&1 \
			&& echo "   restored; nginx will still start" >&2 \
			|| echo "   RESTORED AND STILL BROKEN -- nginx.conf needs a person" >&2
		echo "   the upload ceiling was NOT changed" >&2
	fi
	rm -f "${ours}.prev"
fi

systemctl reload "php${PHPV}-fpm" 2>/dev/null \
	|| systemctl reload php-fpm 2>/dev/null \
	|| echo "(could not reload php-fpm; a restart may be needed)"

if nginx -t >/dev/null 2>&1; then
	systemctl reload nginx 2>/dev/null || true
else
	# Not ours to fix here -- but silence is what let the last one sit for
	# eight days, so it is said plainly and the exit code carries it.
	echo "!! nginx will not accept its own config, and did not reload:" >&2
	nginx -t 2>&1 | tail -2 >&2
	bad_nginx=1
fi

echo "--- what the box reports now"
php -r 'foreach (["memory_limit","max_execution_time","max_input_time","max_input_vars","upload_max_filesize","post_max_size"] as $k) { printf("%-22s %s\n", $k, ini_get($k)); }'

# A tuner that leaves nginx unable to start must not report success. The next
# restart is the thing that finds out, and by then nobody is looking here.
if [ "${bad_nginx:-0}" = "1" ]; then
	echo "--- nginx config is broken; fix it before the next restart" >&2
	exit 1
fi
