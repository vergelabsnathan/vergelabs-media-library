# What the shell nav lists (read-only). No admin_menu firing: other plugins fatal on it under wp-cli.
set -e
cd /var/www/wp
wp eval '$slugs = array_map( function ( $p ) { return $p["slug"]; }, vergeml_shell_pages() ); echo "shell pages: ", implode( ", ", $slugs ), "\n";
$_GET["page"] = "media-ai"; ob_start(); vergeml_shell_open(); $h = ob_get_clean(); preg_match_all("/page=([a-z-]+)/", $h, $m); echo "nav links rendered: ", implode( ", ", array_unique( $m[1] ) ), "\n";
echo "nav has media-guide: ", false !== strpos( $h, "media-guide" ) ? "yes" : "NO", "\n"; echo "admin-shell.php mentions media-guide: ", substr_count( file_get_contents( WP_PLUGIN_DIR . "/vergelabs-media-library/core/admin-shell.php" ), "media-guide" ), " times\n";' --user=1 --allow-root --skip-themes 2>&1 | grep -v "^Deprecated:" || true
