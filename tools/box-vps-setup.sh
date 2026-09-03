set -e
# Idempotent: Node 20 from NodeSource, pm2 globally, a home for the service.
if ! command -v node >/dev/null || [ "$(node -v | cut -c2-3)" -lt 20 ]; then
  curl -fsSL https://deb.nodesource.com/setup_20.x | bash - >/dev/null 2>&1
  apt-get install -y nodejs >/dev/null 2>&1
fi
command -v pm2 >/dev/null || npm install -g pm2 >/dev/null 2>&1
mkdir -p /opt/vgml-service && chmod 750 /opt/vgml-service
echo "node $(node -v)  npm $(npm -v)  pm2 $(pm2 -v)  /opt/vgml-service ready"
