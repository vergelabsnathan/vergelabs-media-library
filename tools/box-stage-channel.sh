#!/usr/bin/env bash
#
#  The release channel the STAGE site sees, on the box -- for rehearsing a
#  pull-back without touching production's catalogue.
#
#  The box runs its own copy of the service (pm2, 127.0.0.1:3100, env in
#  /opt/vgml-service/.env). The stage site at /var/www/upd is pointed at it
#  with `define( 'VGMLPRO_API_BASE', 'http://127.0.0.1:3100' )` for the
#  rehearsal; the Pro plugin's updater accepts that override. Nothing here
#  reaches Vercel, and no customer site asks the box for anything.
#
#      ACTION=set   bash tools/box-stage-channel.sh < catalogue.json   # PLUGIN_RELEASES := the JSON on stdin
#      ACTION=restore bash tools/box-stage-channel.sh                  # the line as it was before the first `set`
#      ACTION=show  bash tools/box-stage-channel.sh                    # slug and version per row, never the whole env
#
#  `set` keeps the original line once, in .env.channel-before, and `restore`
#  puts it back and removes the keepsake. Each change restarts the service
#  (about four seconds; the describe path on the box is idle in Phase 1).
#
#  Run over ssh as root. Rehearsed 2026-09-11 for docs/runbooks/rollback.md.

set -e
ENV=/opt/vgml-service/.env
KEEP=/opt/vgml-service/.env.channel-before
ECO=/opt/vgml-service/ecosystem.config.cjs

versions() {
	node -e '
		const fs = require("fs");
		const line = fs.readFileSync(process.argv[1], "utf8").split(/\r?\n/).find(l => l.startsWith("PLUGIN_RELEASES="));
		let v = line ? line.slice(16) : "";
		if (/^".*"$/s.test(v)) { try { v = JSON.parse(v); } catch { v = v.slice(1, -1); } }
		else if (/^\x27.*\x27$/s.test(v)) v = v.slice(1, -1);
		for (const r of JSON.parse(v || "[]")) console.log("  " + r.slug + " " + r.version + "  <- " + r.source);
	' "$ENV"
}

case "${ACTION:-show}" in
	show)
		echo "the box service advertises:"; versions ;;
	set)
		json=$(cat)
		node -e 'JSON.parse(process.argv[1])' "$json" || { echo "stdin is not JSON" >&2; exit 1; }
		[ -f "$KEEP" ] || grep '^PLUGIN_RELEASES=' "$ENV" > "$KEEP"
		umask 077
		grep -v '^PLUGIN_RELEASES=' "$ENV" > "$ENV.next"
		printf 'PLUGIN_RELEASES=%s\n' "$(node -e 'process.stdout.write(JSON.stringify(process.argv[1]))' "$json")" >> "$ENV.next"
		mv "$ENV.next" "$ENV"
		pm2 startOrRestart "$ECO" --update-env > /dev/null
		echo "set; the box service now advertises:"; versions ;;
	restore)
		[ -f "$KEEP" ] || { echo "nothing kept -- nothing to restore" >&2; exit 1; }
		umask 077
		grep -v '^PLUGIN_RELEASES=' "$ENV" > "$ENV.next"
		cat "$KEEP" >> "$ENV.next"
		mv "$ENV.next" "$ENV"
		rm -f "$KEEP"
		pm2 startOrRestart "$ECO" --update-env > /dev/null
		echo "restored; the box service advertises:"; versions ;;
	*)
		echo "ACTION must be show, set or restore" >&2; exit 1 ;;
esac
