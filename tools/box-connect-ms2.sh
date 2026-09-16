# The main site's licence key, unsealed there and sealed again on the second network. The key stays in this shell.
#     scp tools/box-connect-ms2.php root@box:/tmp/vgml-connect-ms2.php && bash tools/box-connect-ms2.sh
set -e
K=$(cd /var/www/wp && wp eval 'echo vergeml_ai_unseal( get_option( "vergeml_ai" )["license_key"] );' --allow-root --skip-themes 2>/dev/null | tail -n 1)
if [ -z "$K" ]; then echo "the main site's key did not unseal"; exit 1; fi
cd /var/www/ms2
sudo -u www-data env VGML_KEY="$K" wp eval-file /tmp/vgml-connect-ms2.php --url=http://ms2.46.225.66.194.nip.io --allow-root --skip-themes 2>&1 | grep -vE "^Deprecated:" || true
rm -f /tmp/vgml-connect-ms2.php
