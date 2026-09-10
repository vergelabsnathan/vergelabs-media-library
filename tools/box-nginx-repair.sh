#!/usr/bin/env bash
#
#  nginx will not start: client_max_body_size is set twice in the http block.
#
#  Found 2026-09-10, with the box unreachable and the whole site refusing
#  connections on 80 and 443 while ssh answered. nginx had been up for eight
#  days and failed the moment something restarted it -- an unattended upgrade,
#  at 06:29 UTC. The fault was already on disk and invisible until then:
#
#    /etc/nginx/nginx.conf              30 Aug, hand edited: 64M
#    /etc/nginx/conf.d/99-vergelabs.conf 2 Sep, tools/box-tune.sh: 128M
#
#  Both land in http, and nginx refuses a duplicate directive outright. The
#  tuner's file is the one that is meant to win -- the upload ceiling is
#  deliberate and belongs with the rest of the tuning -- so the hand edit goes.
#
#      ssh -i ~/.ssh/hetzner_vgml root@46.225.66.194 'bash -s' < tools/box-nginx-repair.sh
#
#  Safe to run twice: it does nothing if the config already tests clean, and it
#  restores its backup rather than leaving a broken config behind.
set -eu

CONF=/etc/nginx/nginx.conf

if nginx -t >/dev/null 2>&1; then
	echo "config already tests clean"
	systemctl is-active --quiet nginx || { systemctl start nginx; echo "nginx was down; started it"; }
	exit 0
fi

echo "before:"
nginx -t 2>&1 | tail -2

BACKUP="/root/nginx.conf.bak-$(date +%Y%m%d-%H%M%S)"
cp "$CONF" "$BACKUP"
echo "backed up to $BACKUP"

#  Only the hand-added line in the http block, and only when the tuner's file
#  is the one still carrying the value. Anything else is left alone.
if [ -f /etc/nginx/conf.d/99-vergelabs.conf ] && grep -q 'client_max_body_size' /etc/nginx/conf.d/99-vergelabs.conf; then
	sed -i 's|^[[:space:]]*client_max_body_size[[:space:]]*64M;|\t# client_max_body_size lives in conf.d/99-vergelabs.conf (tools/box-tune.sh). A second one here stopped nginx on 2026-09-10.|' "$CONF"
else
	echo "conf.d/99-vergelabs.conf does not set it; refusing to touch $CONF" >&2
	exit 1
fi

echo "after:"
if nginx -t 2>&1 | tail -2; then
	systemctl start nginx
	sleep 1
	systemctl is-active --quiet nginx && echo "nginx is running" || { echo "nginx still not running; restoring"; cp "$BACKUP" "$CONF"; exit 1; }
else
	echo "still broken; restoring the backup" >&2
	cp "$BACKUP" "$CONF"
	exit 1
fi

echo "listening on:"
ss -lnt 2>/dev/null | grep -E ':80|:443' || echo "  nothing on 80 or 443"
