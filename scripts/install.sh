#!/usr/bin/env bash
set -Eeuo pipefail

APP_USER="grewire"
APP_GROUP="www-data"
APP_DIR="/var/www/grewire"
REPO_URL="https://github.com/ChockyPowder/grewire.git"
BRANCH="main"

DB_NAME="grewire"
DB_USER="grewire"
DB_HOST="127.0.0.1"
DB_PORT="5432"

DOMAIN="_"
CERTBOT_EMAIL=""
if [[ -n "${1:-}" ]]; then DOMAIN="$1"; fi
if [[ -n "${2:-}" ]]; then CERTBOT_EMAIL="$2"; fi

log() { printf '\n\033[1;36m==> %s\033[0m\n' "$*"; }
die() { printf '\n\033[1;31mERROR: %s\033[0m\n' "$*" >&2; exit 1; }
trap 'die "Installer failed on line $LINENO."' ERR

require_root() {
    [[ "$(id -u)" -eq 0 ]] || die "Run as root: sudo bash scripts/install.sh"
}

require_debian_13() {
    [[ -r /etc/os-release ]] || die "Cannot detect operating system."
    . /etc/os-release
    [[ "$ID" == "debian" && "$VERSION_ID" == "13" ]] || die "This installer targets Debian 13 (trixie)."
}

install_packages() {
    log "Installing Debian packages"
    apt-get update
    DEBIAN_FRONTEND=noninteractive apt-get install -y         ca-certificates curl git nginx openssl         postgresql postgresql-client composer         php8.4-cli php8.4-fpm php8.4-pgsql php8.4-mbstring         php8.4-xml php8.4-curl php8.4-zip

    systemctl enable --now postgresql
    systemctl enable --now php8.4-fpm
    systemctl enable --now nginx
}

create_app_user() {
    log "Creating service account"
    if ! id -u "$APP_USER" >/dev/null 2>&1; then
        useradd --system --home "$APP_DIR" --shell /usr/sbin/nologin "$APP_USER"
    fi
    install -d -o root -g "$APP_GROUP" -m 0755 /var/www
}

checkout_app() {
    log "Installing Grewire source"
    if [[ ! -d "$APP_DIR/.git" ]]; then
        rm -rf "$APP_DIR"
        git clone --branch "$BRANCH" --single-branch "$REPO_URL" "$APP_DIR"
    else
        git -C "$APP_DIR" fetch origin "$BRANCH"
        git -C "$APP_DIR" checkout "$BRANCH"
        git -C "$APP_DIR" pull --ff-only origin "$BRANCH"
    fi

    chown -R root:"$APP_GROUP" "$APP_DIR"
    find "$APP_DIR" -type d -exec chmod 0750 {} +
    find "$APP_DIR" -type f -exec chmod 0640 {} +
    chmod 0755 "$APP_DIR/public"
    find "$APP_DIR/public" -type f -exec chmod 0644 {} +
    install -d -o root -g "$APP_GROUP" -m 0770 "$APP_DIR/storage"

    if [[ -f "$APP_DIR/composer.json" ]]; then
        log "Installing PHP dependencies"
        composer install --working-dir="$APP_DIR" --no-dev --prefer-dist --no-interaction --optimize-autoloader
    fi
}

configure_database() {
    log "Configuring PostgreSQL"
    local db_password
    db_password="$(openssl rand -hex 24)"

    if ! runuser -u postgres -- psql -tAc "SELECT 1 FROM pg_roles WHERE rolname='$DB_USER'" | grep -q 1; then
        runuser -u postgres -- createuser --no-createdb --no-createrole --no-superuser "$DB_USER"
    fi

    runuser -u postgres -- psql -v ON_ERROR_STOP=1 -c "ALTER ROLE $DB_USER WITH LOGIN PASSWORD '$db_password';"

    if ! runuser -u postgres -- psql -tAc "SELECT 1 FROM pg_database WHERE datname='$DB_NAME'" | grep -q 1; then
        runuser -u postgres -- createdb --owner="$DB_USER" "$DB_NAME"
    fi

    runuser -u postgres -- psql -v ON_ERROR_STOP=1 -c "ALTER DATABASE $DB_NAME OWNER TO $DB_USER;"

    log "Writing application environment"
    local lan_ip
    lan_ip="$(hostname -I | awk '{print $1}')"
    [[ -n "$lan_ip" ]] || lan_ip="127.0.0.1"
    local app_url="http://$lan_ip"
    if [[ "$DOMAIN" != "_" ]]; then app_url="http://$DOMAIN"; fi

    cat > "$APP_DIR/.env" <<EOF
APP_NAME=Grewire
APP_ENV=production
APP_DEBUG=false
APP_URL=$app_url

DB_HOST=$DB_HOST
DB_PORT=$DB_PORT
DB_NAME=$DB_NAME
DB_USER=$DB_USER
DB_PASSWORD=$db_password

DEV_IDENTITY=true
DEV_USERNAME=local-user
EOF

    chown root:"$APP_GROUP" "$APP_DIR/.env"
    chmod 0640 "$APP_DIR/.env"

    if ! PGPASSWORD="$db_password" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -tAc "SELECT 1 FROM pg_tables WHERE schemaname='public' AND tablename='users'" | grep -q 1; then
        log "Applying initial database migration"
        PGPASSWORD="$db_password" psql -v ON_ERROR_STOP=1 -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -f "$APP_DIR/database/migrations/0001_initial.sql"
    else
        log "Database schema already exists; migration skipped"
    fi
}

configure_nginx() {
    log "Configuring Nginx"
    cat > /etc/nginx/sites-available/grewire.conf <<EOF
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN;

    root $APP_DIR/public;
    index index.php;
    client_max_body_size 25M;

    location / {
        try_files \$uri \$uri/ =404;
    }

    location ~ \.php$ {
        try_files \$uri =404;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT \$document_root;
        fastcgi_param HTTP_PROXY "";
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
    }

    location ~ /\. { deny all; }
    location ^~ /storage/ { return 404; }

    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
}
EOF

    rm -f /etc/nginx/sites-enabled/default
    ln -sfn /etc/nginx/sites-available/grewire.conf /etc/nginx/sites-enabled/grewire.conf
    nginx -t
    systemctl reload nginx
}

configure_https() {
    [[ "$DOMAIN" != "_" ]] || return 0
    [[ -n "$CERTBOT_EMAIL" ]] || { log "No certificate email supplied; keeping HTTP."; return 0; }

    log "Installing Certbot and attempting HTTPS"
    apt-get install -y certbot python3-certbot-nginx

    if certbot --nginx --non-interactive --agree-tos --redirect --email "$CERTBOT_EMAIL" -d "$DOMAIN"; then
        sed -i "s#APP_URL=http://$DOMAIN#APP_URL=https://$DOMAIN#" "$APP_DIR/.env"
        chown root:"$APP_GROUP" "$APP_DIR/.env"
        chmod 0640 "$APP_DIR/.env"
    else
        log "HTTPS certificate issuance failed; HTTP remains available."
        log "Ensure DNS points to this server and ports 80/443 are reachable, then run:"
        printf '  certbot --nginx -d %s --email %s --agree-tos --redirect\n' "$DOMAIN" "$CERTBOT_EMAIL"
    fi
}

health_check() {
    log "Running health checks"
    php -v | head -n 1
    runuser -u postgres -- pg_isready
    curl -fsS http://127.0.0.1/api/health.php || true
    printf '\n'
}

summary() {
    local host
    host="$DOMAIN"
    if [[ "$DOMAIN" == "_" ]]; then
        host="$(hostname -I | awk '{print $1}')"
        [[ -n "$host" ]] || host="SERVER-IP"
    fi

    log "Grewire installation complete"
    printf 'App:       %s\n' "$APP_DIR"
    printf 'URL:       http://%s\n' "$host"
    printf 'PHP:       8.4-FPM\n'
    printf 'Database:  PostgreSQL\n'
    printf 'Config:    %s/.env\n' "$APP_DIR"
    printf '\n'
    printf '\033[1;33mWARNING:\033[0m Authentication is not implemented yet. DEV_IDENTITY is enabled. Do not expose this build to untrusted users until authentication/authorization is added.\n'
}

main() {
    require_root
    require_debian_13
    install_packages
    create_app_user
    checkout_app
    configure_database
    configure_nginx
    configure_https
    health_check
    summary
}

main "$@"
