#!/usr/bin/env bash
set -Eeuo pipefail

APP_DIR="/var/www/grewire"
REPO_URL="https://github.com/ChockyPowder/grewire.git"
BRANCH="main"
APP_GROUP="www-data"

log() { printf '\n\033[1;36m==> %s\033[0m\n' "$*"; }
die() { printf '\n\033[1;31mERROR: %s\033[0m\n' "$*" >&2; exit 1; }
trap 'die "Update failed on line $LINENO."' ERR

require_root() {
  [[ "$(id -u)" -eq 0 ]] || die "Run as root: sudo bash scripts/update.sh"
}

update_source() {
  log "Updating Grewire source"
  [[ -d "$APP_DIR/.git" ]] || die "$APP_DIR is not a Git checkout"

  git -C "$APP_DIR" fetch origin "$BRANCH"
  git -C "$APP_DIR" checkout "$BRANCH"
  git -C "$APP_DIR" reset --hard "origin/$BRANCH"
}

install_dependencies() {
  if [[ -f "$APP_DIR/composer.json" ]]; then
    log "Refreshing PHP dependencies"
    composer install --working-dir="$APP_DIR" --no-dev --prefer-dist --no-interaction --optimize-autoloader
  fi
}

fix_permissions() {
  log "Fixing application permissions"
  chown -R root:"$APP_GROUP" "$APP_DIR"
  find "$APP_DIR" -type d -exec chmod 0750 {} +
  find "$APP_DIR" -type f -exec chmod 0640 {} +
  chmod 0755 "$APP_DIR/public"
  find "$APP_DIR/public" -type f -exec chmod 0644 {} +
  install -d -o root -g "$APP_GROUP" -m 0770 "$APP_DIR/storage"

  if [[ -f "$APP_DIR/.env" ]]; then
    chown root:"$APP_GROUP" "$APP_DIR/.env"
    chmod 0640 "$APP_DIR/.env"
  fi
}

restart_services() {
  log "Restarting Grewire services"
  systemctl restart php8.4-fpm
  systemctl reload nginx

  if systemctl list-unit-files --type=service | grep -q '^grewire-ws\.service'; then
    systemctl restart grewire-ws
  fi
}

health_check() {
  log "Running health checks"
  nginx -t
  curl -fsS http://127.0.0.1/api/health.php
  printf '\n'
}

summary() {
  local commit
  commit="$(git -C "$APP_DIR" rev-parse --short HEAD)"
  printf '\nGrewire updated successfully. Commit: %s\n' "$commit"
}

main() {
  require_root
  update_source
  install_dependencies
  fix_permissions
  restart_services
  health_check
  summary
}

main "$@"
