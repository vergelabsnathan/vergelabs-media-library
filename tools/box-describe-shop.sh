# The shop library's describe pass, on the box's second network. SPENDS: one credit a picture. Run with Nathan's yes only.
#     nohup env VGML_N=500 bash /tmp/vgml-describe-shop.sh > /tmp/vgml-describe-shop.log 2>&1 &
set -e
cd /var/www/ms2
sudo -u www-data env VGML_N="${VGML_N:-500}" wp eval-file /tmp/vgml-describe-shop.php --url=http://ms2.46.225.66.194.nip.io --allow-root --skip-themes 2>&1 | grep --line-buffered -vE "^Deprecated:" || true
