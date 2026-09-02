set -e
cd /var/www/wp
wp eval-file wp-content/plugins/vergelabs-media-library/tools/box-projection-recall.php --allow-root --skip-themes
