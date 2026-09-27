#!/usr/bin/env bash
set -Eeuo pipefail
APP_DIR="/var/www/grewire"
CERT_DIR="/etc/grewire"
NGINX_SITE="/etc/nginx/sites-available/grewire"
SERVICE="/etc/systemd/system/grewire-ws.service"
LAN_IP="$(hostname -I | awk '{print $1}')"

echo "==> Creating voice certificate for $LAN_IP"
install -d -m 0755 "$CERT_DIR"
if [[ ! -f "$CERT_DIR/grewire.key" || ! -f "$CERT_DIR/grewire.crt" ]]; then
  openssl req -x509 -nodes -newkey rsa:2048 -days 825     -keyout "$CERT_DIR/grewire.key" -out "$CERT_DIR/grewire.crt"     -subj "/CN=$LAN_IP" -addext "subjectAltName=IP:$LAN_IP"
  chmod 0600 "$CERT_DIR/grewire.key"
  chmod 0644 "$CERT_DIR/grewire.crt"
fi

echo "==> Installing WebSocket service"
cat > "$SERVICE" <<EOF
[Unit]
Description=Grewire WebRTC signaling server
After=network.target
[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=$APP_DIR
ExecStart=/usr/bin/php $APP_DIR/app/WebSocketServer.php
Restart=always
RestartSec=2
[Install]
WantedBy=multi-user.target
EOF
systemctl daemon-reload
systemctl enable --now grewire-ws

echo "==> Configuring HTTPS and WebSocket proxy"
cat > "$NGINX_SITE" <<EOF
server {
 listen 80;
 server_name _;
 return 301 https://\$host\$request_uri;
}
server {
 listen 443 ssl;
 server_name _;
 root $APP_DIR/public;
 index index.php;
 ssl_certificate $CERT_DIR/grewire.crt;
 ssl_certificate_key $CERT_DIR/grewire.key;
 location / { try_files \$uri \$uri/ /index.php?\$query_string; }
 location /api/ { try_files \$uri =404; }
 location /assets/ { try_files \$uri =404; }
 location /ws/ {
   proxy_pass http://127.0.0.1:8081/;
   proxy_http_version 1.1;
   proxy_set_header Upgrade \$http_upgrade;
   proxy_set_header Connection "upgrade";
   proxy_set_header Host \$host;
   proxy_read_timeout 3600s;
   proxy_send_timeout 3600s;
 }
 location ~ \.php$ {
   include snippets/fastcgi-php.conf;
   fastcgi_pass unix:/run/php/php8.4-fpm.sock;
 }
 location ~ /\. { deny all; }
}
EOF
ln -sf "$NGINX_SITE" /etc/nginx/sites-enabled/grewire
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl reload nginx
echo
echo "HTTPS: https://$LAN_IP/"
echo "Open that address, accept the certificate warning, select Lobby voice, then Join voice."
