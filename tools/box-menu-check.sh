# What the menu and the shell nav list (read-only).
set -e
cd /var/www/wp
wp eval 'global $submenu, $menu; do_action("admin_menu"); $k = defined("VERGEML_MENU") ? VERGEML_MENU : "vergelabs-media"; echo "VERGEML_MENU = $k\n"; echo "WP submenu:\n"; foreach ( (array) ( $submenu[$k] ?? array() ) as $i ) { echo "  ", $i[2], "  (", wp_strip_all_tags($i[0]), ")\n"; }
$_GET["page"] = "media-ai"; ob_start(); vergeml_shell_open(); $h = ob_get_clean(); preg_match_all("/page=([a-z-]+)/", $h, $m); echo "shell nav links: ", implode(", ", array_unique($m[1])), "\n"; echo "shell nav mentions media-guide: ", false !== strpos($h, "media-guide") ? "yes" : "NO", "\n";' --user=1 --allow-root --skip-themes 2>&1 | grep -v "^Deprecated:" || true
