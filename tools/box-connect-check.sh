# The licence row of the AI screen, as an administrator sees it (read-only).
set -e
cd /var/www/wp
wp eval 'ob_start(); vergeml_ai_page(); $h = ob_get_clean();
  $h = preg_replace( "/<a ([^>]*)href=\"([^\"]+)\"([^>]*)>/", "<a $1$3>[button -> $2] ", $h );
  $t = wp_strip_all_tags( $h ); $t = preg_replace( "/[ \t]+/", " ", $t ); $t = preg_replace( "/\n\s*\n+/", "\n", $t );
  $i = strpos( $t, "Licence and credits" ); echo trim( substr( $t, $i, 900 ) ), "\n";' --user=1 --allow-root --skip-themes 2>&1 | grep -v "^Deprecated:" || true
