# The Licence screen, as an administrator sees it (read-only), twice: as the
# site is, and with its key blanked in memory. The demo-mode switch belongs
# only to the second render; the exit code says whether that held.
set -e
cd /var/www/wp
wp eval '
  function vgml_cc_render() {
    ob_start(); vergeml_licence_page(); $h = ob_get_clean();
    $t = preg_replace( "/<a ([^>]*)href=\"([^\"]+)\"([^>]*)>/", "<a $1$3>[link -> $2] ", $h );
    $t = wp_strip_all_tags( $t ); $t = preg_replace( "/[ \t]+/", " ", $t ); $t = preg_replace( "/\n\s*\n+/", "\n", $t );
    echo trim( $t ), "\n";
    return false !== strpos( $h, "id=\"vgml-demo-mode\"" );
  }
  $has_key = "" !== vergeml_ai_unseal( vergeml_ai_settings()["license_key"] );
  echo "=== as the site is (", $has_key ? "a key is set" : "no key", ") ===\n";
  $a = vgml_cc_render();
  echo "demo row: ", $a ? "present" : "absent", "\n";
  $own = get_option( "vergeml_ai", array() ); $own = is_array( $own ) ? $own : array(); $own["license_key"] = "";
  add_filter( "pre_option_vergeml_ai", function () use ( $own ) { return $own; } );
  echo "\n=== with no key ===\n";
  $b = vgml_cc_render();
  echo "demo row: ", $b ? "present" : "absent", "\n";
  $ok = $b && ( ! $has_key || ! $a );
  echo "\n", $ok ? "ok" : "FAIL", "  demo mode shows only while no key is present\n";
  exit( $ok ? 0 : 1 );
' --user=1 --allow-root --skip-themes 2>&1 | grep -v "^Deprecated:"
exit ${PIPESTATUS[0]}
