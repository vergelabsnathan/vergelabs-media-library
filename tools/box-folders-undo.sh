set -e
cd /var/www/wp
wp eval 'echo "before: " . wp_count_terms( array( "taxonomy" => "media_category", "hide_empty" => false ) ) . " terms\n"; $r = vergeml_talk_undo(); echo is_wp_error( $r ) ? "ERROR " . $r->get_error_message() : wp_json_encode( $r ); echo "\nafter: " . wp_count_terms( array( "taxonomy" => "media_category", "hide_empty" => false ) ) . " terms\n"; delete_option( "vergeml_guide_session" ); vergeml_journey_touch(); echo "session cleared\n";' --user=1 --allow-root --skip-themes 2>&1 | grep -v "^Deprecated:" || true
