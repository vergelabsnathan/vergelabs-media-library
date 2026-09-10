#!/usr/bin/env bash
#
#  Stop the scanners eating the box.
#
#  Found 2026-09-10, while trying to measure why the media library felt slow:
#  a single address was sending 843 requests every five minutes, probing for
#  /.env, /mysqldump.sql, /new/.env.production and a hundred variations. Every
#  one of them boots WordPress to render a 404, so five php-fpm workers sat at
#  30% CPU each and the load average stood at 6 with nobody using the site.
#
#  That is what "slow and jerky" was. Not the plugin -- an unprotected public
#  IP doing what unprotected public IPs do.
#
#      ssh -i ~/.ssh/hetzner_vgml root@46.225.66.194 'bash -s' < tools/box-block-scanners.sh
#
#  Two layers. A deny for whoever is loudest right now, and fail2ban so the
#  next one is caught without anybody watching -- because there is always a
#  next one.
#
#  Safe to run twice: it writes the same config and reloads.
set -eu

CONF=/etc/nginx/conf.d/98-vergelabs-scanners.conf

#  The paths a scanner asks for and a real visitor never does. Answered with a
#  closed connection rather than a 404, so WordPress is never booted for them.
cat > "$CONF" <<'NGINX'
#  Requests that are only ever hostile. Returning 444 closes the connection
#  without a response, which costs nothing and tells the scanner nothing.
map $request_uri $vgml_scanner {
	default                 0;
	"~*/\.env"              1;
	"~*/\.git"              1;
	"~*mysqldump"           1;
	"~*/wp-config\.php\."   1;
	"~*/\.aws/"             1;
	"~*/config\.json$"      1;
	"~*/telescope/"         1;
	"~*/phpinfo"            1;
	"~*/vendor/phpunit"     1;
}
NGINX

#  The map lives in http; the rule that uses it lives in the server block, so
#  it goes in a snippet the site includes rather than here.
SNIP=/etc/nginx/snippets/vergelabs-scanners.conf
mkdir -p /etc/nginx/snippets
cat > "$SNIP" <<'NGINX'
if ($vgml_scanner) { return 444; }
NGINX

for site in /etc/nginx/sites-available/*; do
	[ -f "$site" ] || continue
	grep -q 'vergelabs-scanners' "$site" && continue
	#  After the opening of the first server block, and only there.
	awk 'BEGIN{done=0} /^\s*server\s*{/ && !done { print; print "\tinclude snippets/vergelabs-scanners.conf;"; done=1; next } { print }' "$site" > "$site.new"
	mv "$site.new" "$site"
	echo "included the snippet in $( basename "$site" )"
done

if nginx -t >/dev/null 2>&1; then
	systemctl reload nginx
	echo "nginx reloaded"
else
	echo "!! nginx refused the config; rolling back" >&2
	nginx -t 2>&1 | tail -2 >&2
	rm -f "$CONF" "$SNIP"
	for site in /etc/nginx/sites-available/*; do
		[ -f "$site" ] || continue
		sed -i '/vergelabs-scanners/d' "$site"
	done
	nginx -t >/dev/null 2>&1 && systemctl reload nginx
	exit 1
fi

#  fail2ban for everything the list above does not name. The jail watches
#  nginx's own log for a burst of 404s and 444s from one address.
if ! command -v fail2ban-server >/dev/null 2>&1; then
	DEBIAN_FRONTEND=noninteractive apt-get install -y -qq fail2ban >/dev/null 2>&1 || true
fi

if command -v fail2ban-server >/dev/null 2>&1; then

	cat > /etc/fail2ban/filter.d/vgml-scan.conf <<'F2B'
[Definition]
failregex = ^<HOST> .* "(GET|POST|HEAD) [^"]*" (404|444|403)
ignoreregex =
F2B

	cat > /etc/fail2ban/jail.d/vgml-scan.local <<'F2B'
[vgml-scan]
enabled  = true
port     = http,https
filter   = vgml-scan
logpath  = /var/log/nginx/access.log
maxretry = 40
findtime = 60
bantime  = 86400
F2B

	systemctl enable --now fail2ban >/dev/null 2>&1 || true
	systemctl restart fail2ban >/dev/null 2>&1 || true
	sleep 2
	fail2ban-client status vgml-scan 2>/dev/null || echo "(fail2ban installed; the jail reports on its next scan)"
else
	echo "fail2ban is not available; the nginx rules above still stand"
fi

echo
echo "load average now: $( uptime | sed 's/.*load average: //' )"
