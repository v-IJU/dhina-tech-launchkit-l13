#!/usr/bin/env bash
#
# Production deploy for one server (EC2, VPS) with compose.prod.yaml.
# Run it from the project folder on the server:
#
#   ./deploy.sh              pull the latest code, build images, migrate, restart, health check
#   ./deploy.sh --down       the same, with "maintenance mode" while migrations run
#   ./deploy.sh rollback     back to the images of the previous deploy (database is NOT rolled back)
#   ./deploy.sh restart      restart the containers (after editing .env)
#   ./deploy.sh status       running containers, current version, health
#
# First install on a new server: see DEPLOY.md section 2.
#
# Settings (environment variables, all optional):
#   DEPLOY_BRANCH   branch to deploy            (default: the branch that is checked out)
#   HEALTH_URL      URL that must answer 200    (default: http://127.0.0.1:${WEB_PORT}/up)
#   KEEP_IMAGES     old versions to keep        (default: 3)
#   DEPLOY_IMPORT_WAIT  seconds to wait for running background imports before restarting workers (default: 900, 0 = don't wait)

set -euo pipefail
cd "$(dirname "$0")"

COMPOSE=(docker compose -f compose.prod.yaml)
STATE_DIR=.deploy
mkdir -p "$STATE_DIR"

# Read a value from .env without "source"-ing it (Laravel .env files are not shell scripts).
# Strips inline comments ("WEB_PORT=8081   # note") and quotes, like Docker Compose and Laravel do.
env_value() { grep -E "^$1=" .env 2>/dev/null | tail -1 | cut -d= -f2- | sed -E 's/[[:space:]]+#.*$//; s/^[[:space:]]+|[[:space:]]+$//g' | tr -d '"' || true; }

IMAGE="$(env_value APP_IMAGE)"; IMAGE="${IMAGE:-laravel13-app}"
WEB_PORT="$(env_value WEB_PORT)"; WEB_PORT="${WEB_PORT:-80}"
HEALTH_URL="${HEALTH_URL:-http://127.0.0.1:${WEB_PORT}/up}"
KEEP_IMAGES="${KEEP_IMAGES:-3}"

say()  { printf '\n\033[1;34m==> %s\033[0m\n' "$*"; }
fail() { printf '\n\033[1;31m✗ %s\033[0m\n' "$*" >&2; exit 1; }
artisan() { "${COMPOSE[@]}" run --rm --no-TTY app php artisan "$@"; }

healthy() {
    for _ in $(seq 1 30); do
        if curl -fsS -o /dev/null --max-time 5 "$HEALTH_URL"; then return 0; fi
        sleep 2
    done
    return 1
}

use_tag() {
    docker image inspect "$IMAGE:$1" "$IMAGE-web:$1" >/dev/null 2>&1 || fail "Images for version $1 not found."
    docker tag "$IMAGE:$1" "$IMAGE:latest"
    docker tag "$IMAGE-web:$1" "$IMAGE-web:latest"
}

start() {
    say "Starting containers"
    "${COMPOSE[@]}" up -d --remove-orphans --wait --wait-timeout 180 "$@"
}

prune_images() {
    for repo in "$IMAGE" "$IMAGE-web"; do
        docker images "$repo" --format '{{.Tag}}' | grep -v '^latest$' | tail -n "+$((KEEP_IMAGES + 1))" \
            | xargs -r -I{} docker rmi "$repo:{}" >/dev/null 2>&1 || true
    done
    docker image prune -f >/dev/null
    # Building on the server leaves a build cache that grows on every deploy until the disk is full.
    docker builder prune -f --filter until=168h >/dev/null 2>&1 || true
}

deploy() {
    local maintenance="${1:-}"
    [ -f .env ] || fail ".env not found. cp .env.production.example .env and fill it in (DEPLOY.md section 2)."
    grep -qE '^APP_KEY=.+' .env || fail "APP_KEY is empty in .env (DEPLOY.md section 2)."
    command -v docker >/dev/null || fail "Docker is not installed."
    docker compose version >/dev/null 2>&1 || fail "Docker Compose v2 is not installed."

    local branch="${DEPLOY_BRANCH:-$(git rev-parse --abbrev-ref HEAD)}"
    say "Pulling $branch"
    git fetch --quiet origin "$branch"
    git checkout --quiet "$branch"
    git pull --ff-only --quiet origin "$branch"

    local tag previous
    tag="$(git rev-parse --short HEAD)"
    previous="$(cat "$STATE_DIR/current" 2>/dev/null || true)"

    say "Building $IMAGE:$tag"
    APP_TAG="$tag" "${COMPOSE[@]}" build app web
    use_tag "$tag"

    if [ "$maintenance" = "--down" ] && [ -n "$previous" ]; then
        say "Maintenance mode on"
        "${COMPOSE[@]}" exec -T app php artisan down --retry=15 || true
    fi

    say "Database migrations"
    artisan migrate --force
    artisan module:sync
    if grep -qE '^STARTER_TENANCY=true' .env 2>/dev/null; then
        say "Tenant database migrations"
        artisan tenants:migrate --force
        artisan tenants:run module:sync
    fi

    # Don't cut off a big Excel import halfway: wait for running background imports (max DEPLOY_IMPORT_WAIT s).
    if [ -n "$previous" ] && [ "${DEPLOY_IMPORT_WAIT:-900}" != "0" ]; then
        say "Waiting for running imports"
        artisan imports:wait --timeout="${DEPLOY_IMPORT_WAIT:-900}" || true
    fi

    local ok=true
    start || ok=false

    if [ "$maintenance" = "--down" ]; then
        "${COMPOSE[@]}" exec -T app php artisan up || true
    fi

    say "Health check: $HEALTH_URL"
    if $ok && healthy; then
        [ -n "$previous" ] && echo "$previous" > "$STATE_DIR/previous"
        echo "$tag" > "$STATE_DIR/current"
        echo "$(date -Iseconds) $tag" >> "$STATE_DIR/history"
        prune_images
        say "Deployed $tag ✓"
    else
        printf '\n\033[1;31m✗ Health check failed.\033[0m Last app logs:\n'
        "${COMPOSE[@]}" logs --tail=40 app web || true
        if [ -n "$previous" ]; then
            say "Rolling back to $previous"
            use_tag "$previous"
            start || true
        fi
        fail "Deploy of $tag failed. Fix the problem and run ./deploy.sh again."
    fi
}

rollback() {
    local previous current
    previous="$(cat "$STATE_DIR/previous" 2>/dev/null || true)"
    current="$(cat "$STATE_DIR/current" 2>/dev/null || true)"
    [ -n "$previous" ] || fail "No previous version recorded."
    say "Rolling back $current → $previous (code only; migrations stay)"
    use_tag "$previous"
    start
    healthy || fail "Health check failed after rollback."
    echo "$previous" > "$STATE_DIR/current"
    echo "$current" > "$STATE_DIR/previous"
    echo "$(date -Iseconds) $previous (rollback)" >> "$STATE_DIR/history"
    say "Running $previous ✓"
}

status() {
    "${COMPOSE[@]}" ps
    echo
    echo "Version:  $(cat "$STATE_DIR/current" 2>/dev/null || echo 'not deployed yet')"
    echo "Previous: $(cat "$STATE_DIR/previous" 2>/dev/null || echo '-')"
    if curl -fsS -o /dev/null --max-time 5 "$HEALTH_URL"; then echo "Health:   OK ($HEALTH_URL)"; else echo "Health:   FAILING ($HEALTH_URL)"; fi
    "${COMPOSE[@]}" exec -T app php artisan health:check || true
}

case "${1:-}" in
    ""|--down) deploy "${1:-}" ;;
    rollback)  rollback ;;
    restart)   if start --force-recreate && healthy; then say "Restarted ✓"; else fail "Health check failed."; fi ;;
    status)    status ;;
    *)         sed -n '3,17p' "$0"; exit 1 ;;
esac
