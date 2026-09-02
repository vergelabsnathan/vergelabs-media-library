set -e
cd /var/www/wp

echo "before:"
wp eval 'global $wpdb; $ids=$wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type=\"attachment\" AND post_mime_type LIKE \"image/%\""); $n=0; foreach($ids as $i){$m=wp_get_attachment_metadata((int)$i); if(!is_array($m)||empty($m["sizes"]["thumbnail"]))$n++;} printf("  %d of %d images have no thumbnail\n",$n,count($ids));' --allow-root --skip-themes

echo
echo "regenerating only what is missing..."
wp media regenerate --only-missing --yes --allow-root --skip-themes 2>&1 | tail -5

echo
echo "after:"
wp eval 'global $wpdb; $ids=$wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type=\"attachment\" AND post_mime_type LIKE \"image/%\""); $n=0; foreach($ids as $i){$m=wp_get_attachment_metadata((int)$i); if(!is_array($m)||empty($m["sizes"]["thumbnail"]))$n++;} printf("  %d of %d images have no thumbnail\n",$n,count($ids));' --allow-root --skip-themes
