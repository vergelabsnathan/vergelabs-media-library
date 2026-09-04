# Clear the guided-sorting session on the box, so the next visit starts at screen 1.
set -e
cd /var/www/wp
wp option delete vergeml_guide_session --allow-root 2>/dev/null || echo "no session to clear"
echo "guide session cleared"
