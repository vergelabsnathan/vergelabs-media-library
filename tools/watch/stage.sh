#!/usr/bin/env bash
#
#  The stage leg of the watch. Runs ON THE BOX, against /var/www/upd only:
#  upgrades one thing, proves the plugin against it, puts the old version back.
#
#      bash stage.sh core   7.2          # wp core update --version=7.2  (RCs too: 7.2-RC1)
#      bash stage.sh plugin elementor 3.30.0
#      bash stage.sh theme  Divi 5.12.0  # needs the licensed update available to WP
#
#  Prints one JSON object on the last line: { kind, slug, version, passed, failed, steps: [...] }.
#  Everything before it is the raw output, for the issue body.
#
#  Refuses any site but the stage. The two other sites on this box are not for
#  the watch to upgrade.
set -uo pipefail

KIND=${1:?kind: core|plugin|theme}
SLUG=${2:?slug or version}
VERSION=${3:-}
SITE=/var/www/upd
KEEP=/var/www/.stage-keep
URL=http://upd.46.225.66.194.nip.io
PLUGIN=/var/www/wp/wp-content/plugins/vergelabs-media-library
W="sudo -u www-data wp --allow-root --path=$SITE"

[ -d "$SITE" ] || { echo '{"error":"no stage site at /var/www/upd -- run tools/watch/stage-clone.sh"}'; exit 1; }
case "$SITE" in /var/www/upd) ;; *) echo '{"error":"refusing: not the stage"}'; exit 1;; esac
mkdir -p "$KEEP"

STEPS=()
PASSED=0
FAILED=0
step() { # name, exit code
    if [ "$2" -eq 0 ]; then PASSED=$((PASSED+1)); STEPS+=("{\"name\":\"$1\",\"ok\":true}"); echo "  ok   $1";
    else FAILED=$((FAILED+1)); STEPS+=("{\"name\":\"$1\",\"ok\":false,\"code\":$2}"); echo "  FAIL $1 (exit $2)"; fi
}

# --- remember what is there, so it can go back ------------------------------
case "$KIND" in
    core)   VERSION=${SLUG}; SLUG=wordpress; BEFORE=$($W core version 2>/dev/null | tail -1) ;;
    plugin) BEFORE=$($W plugin get "$SLUG" --field=version 2>/dev/null | tail -1) ;;
    theme)  BEFORE=$($W theme get "$SLUG" --field=version 2>/dev/null | tail -1) ;;
    *) echo '{"error":"kind must be core|plugin|theme"}'; exit 1 ;;
esac
echo "stage: $KIND $SLUG $BEFORE -> $VERSION"

# --- upgrade -----------------------------------------------------------------
case "$KIND" in
    core)
        $W core update --version="$VERSION" --force 2>&1 | grep -v Deprecated | tail -3
        step "core updated to $VERSION" $([ "$($W core version 2>/dev/null | tail -1)" = "$VERSION" ] && echo 0 || echo 1)
        $W core update-db 2>&1 | grep -v Deprecated | tail -1
        ;;
    plugin)
        # Keep the current build so the restore below is exact, even for a paid plugin.
        tar -C "$SITE/wp-content/plugins" -czf "$KEEP/$SLUG-$BEFORE.tgz" "$SLUG"
        if [ -n "$VERSION" ]; then
            $W plugin update "$SLUG" --version="$VERSION" 2>&1 | grep -v Deprecated | tail -2 \
            || $W plugin install "$SLUG" --version="$VERSION" --force 2>&1 | grep -v Deprecated | tail -2
        else
            $W plugin update "$SLUG" 2>&1 | grep -v Deprecated | tail -2
        fi
        AFTER=$($W plugin get "$SLUG" --field=version 2>/dev/null | tail -1)
        step "plugin now $AFTER" $([ "$AFTER" != "$BEFORE" ] && echo 0 || echo 1)
        $W plugin activate "$SLUG" >/dev/null 2>&1
        ;;
    theme)
        tar -C "$SITE/wp-content/themes" -czf "$KEEP/$SLUG-$BEFORE.tgz" "$SLUG"
        $W theme update "$SLUG" 2>&1 | grep -v Deprecated | tail -2
        AFTER=$($W theme get "$SLUG" --field=version 2>/dev/null | tail -1)
        step "theme now $AFTER" $([ "$AFTER" != "$BEFORE" ] && echo 0 || echo 1)
        PREV_THEME=$($W theme list --status=active --field=name 2>/dev/null | tail -1)
        $W theme activate "$SLUG" >/dev/null 2>&1
        ;;
esac

# --- prove the plugin against it ----------------------------------------------
echo "--- php -l"
LINT=$(find "$PLUGIN" -name '*.php' -not -path '*/node_modules/*' -exec php -l {} \; 2>&1 | grep -v "No syntax errors" | head -5)
step "plugin parses" $([ -z "$LINT" ] && echo 0 || echo 1); [ -n "$LINT" ] && echo "$LINT"

echo "--- site answers"
CODE=$(curl -s -o /dev/null -w '%{http_code}' "$URL/wp-login.php")
step "login page 200" $([ "$CODE" = "200" ] && echo 0 || echo 1)

echo "--- plugin still loads and the tree answers"
$W eval 'exit( defined( "VERGEML_VERSION" ) && function_exists( "vergeml_tree_taxonomies" ) && count( vergeml_tree_taxonomies() ) ? 0 : 1 );' >/dev/null 2>&1
step "plugin loaded, tree taxonomies present" $?

# As an administrator: the route is capability-gated, and wp-cli has no user.
$W eval '$u = get_users( array( "role" => "administrator", "number" => 1, "fields" => "ID" ) ); wp_set_current_user( (int) $u[0] ); rest_get_server(); $q = new WP_REST_Request( "GET", "/vergeml/v1/tree" ); $q->set_param( "taxonomy", "media_category" ); $r = rest_do_request( $q ); fwrite( STDERR, "tree status " . $r->get_status() . "
" ); exit( 200 === $r->get_status() ? 0 : 1 );' 2>&1 | grep "tree status" 
step "REST tree 200 as an administrator" ${PIPESTATUS[0]}

echo "--- watchdog"
SAFE=$($W eval 'echo function_exists( "vergeml_safe_mode" ) && vergeml_safe_mode() ? "safe" : "normal";' 2>/dev/null | tail -1)
step "not in safe mode ($SAFE)" $([ "$SAFE" = "normal" ] && echo 0 || echo 1)

# The live integration check for this dependency, when one exists.
case "$SLUG" in
    wordpress-seo)          T=yoast ;;
    seo-by-rank-math)       T=rankmath ;;
    wp-seopress)            T=seopress ;;
    all-in-one-seo-pack)    T=aioseo ;;
    woocommerce)            T=woo ;;
    advanced-custom-fields) T=acf ;;
    *) T="" ;;
esac
if [ -n "$T" ] && [ -f /tmp/vgml-live.php ]; then
    echo "--- tests/integrations/live.php $T"
    $W eval-file /tmp/vgml-live.php "$T" 2>&1 | grep -v Deprecated | grep -E "ok|FAIL|passed"
    step "integration check $T" ${PIPESTATUS[0]}
fi
if [ "$SLUG" = "Divi" ] && [ -f /tmp/vgml-divi.php ]; then
    FOLDER=$($W term list media_category --fields=term_id --orderby=count --order=DESC --format=csv 2>/dev/null | sed -n 2p)
    $W eval-file /tmp/vgml-divi.php "$FOLDER" 2>&1 | grep -v Deprecated | grep -E "ok|FAIL|passed"
    step "Divi render check" ${PIPESTATUS[0]}
fi
if [ "$SLUG" = "polylang-pro" ] || [ "$SLUG" = "polylang" ]; then
    if [ -f /tmp/vgml-pll.php ]; then
        $W eval-file /tmp/vgml-pll.php 2>&1 | grep -v Deprecated | grep -E "ok|FAIL|passed"
        step "Polylang check" ${PIPESTATUS[0]}
    fi
fi

# --- put it back ---------------------------------------------------------------
echo "--- restore"
case "$KIND" in
    core)   $W core update --version="$BEFORE" --force >/dev/null 2>&1; $W core update-db >/dev/null 2>&1 ;;
    plugin) $W plugin deactivate "$SLUG" >/dev/null 2>&1; rm -rf "$SITE/wp-content/plugins/$SLUG"; tar -C "$SITE/wp-content/plugins" -xzf "$KEEP/$SLUG-$BEFORE.tgz"; chown -R www-data:www-data "$SITE/wp-content/plugins/$SLUG" ;;
    theme)  $W theme activate "${PREV_THEME:-twentytwentyfive}" >/dev/null 2>&1; rm -rf "$SITE/wp-content/themes/$SLUG"; tar -C "$SITE/wp-content/themes" -xzf "$KEEP/$SLUG-$BEFORE.tgz"; chown -R www-data:www-data "$SITE/wp-content/themes/$SLUG" ;;
esac
echo "restored to $BEFORE"

printf '{"kind":"%s","slug":"%s","version":"%s","before":"%s","passed":%d,"failed":%d,"steps":[%s]}\n' \
    "$KIND" "$SLUG" "$VERSION" "$BEFORE" "$PASSED" "$FAILED" "$(IFS=,; echo "${STEPS[*]}")"
[ "$FAILED" -eq 0 ]
