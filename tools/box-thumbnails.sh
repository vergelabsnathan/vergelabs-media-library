set -e
cd /var/www/wp

echo "before:"
wp eval-file /tmp/vgml-thumbs-count.php --allow-root --skip-themes

# Detached, because regenerating four hundred images outlives the ssh
# connection that starts it -- the first attempt died on a broken pipe with
# the job half done and nothing to show for it.
rm -f /tmp/vgml-thumbs.log
nohup wp media regenerate --only-missing --yes --allow-root --skip-themes \
  > /tmp/vgml-thumbs.log 2>&1 &

echo "started in the background; watching"

for i in $(seq 1 60); do
  sleep 20
  if ! pgrep -f "media regenerate" > /dev/null; then
    echo "finished after about $(( i * 20 )) seconds"
    break
  fi
  echo "  still going ($(( i * 20 ))s)"
done

echo
tail -4 /tmp/vgml-thumbs.log || true
echo
echo "after:"
wp eval-file /tmp/vgml-thumbs-count.php --allow-root --skip-themes
