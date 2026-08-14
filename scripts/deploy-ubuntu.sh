#!/usr/bin/env bash
# Deploy LMS trên Ubuntu: pull origin/main + build --no-cache + up.
# Chạy trên HOST (không chạy trong container).
#
# Cách dùng:
#   chmod +x scripts/deploy-ubuntu.sh
#   ./scripts/deploy-ubuntu.sh
#   DEPLOY_SKIP_PULL=1 ./scripts/deploy-ubuntu.sh   # chỉ rebuild, không pull
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

log() { printf '\n==> %s\n' "$*"; }
die() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }

command -v git >/dev/null || die "Thiếu git"
command -v docker >/dev/null || die "Thiếu docker"
docker compose version >/dev/null 2>&1 || die "Thiếu docker compose v2"

BRANCH="${DEPLOY_BRANCH:-main}"
REMOTE="${DEPLOY_REMOTE:-origin}"
BUILD_ID="${NUXT_PUBLIC_BUILD_ID:-$(date +%Y%m%d-%H%M%S)}"
export NUXT_PUBLIC_BUILD_ID="$BUILD_ID"

log "Repo: $ROOT"
log "Branch: $REMOTE/$BRANCH"
log "Build ID: $BUILD_ID"

# Tránh pull khi working tree bẩn (trừ khi FORCE=1)
if [[ "${DEPLOY_FORCE:-0}" != "1" ]]; then
  if [[ -n "$(git status --porcelain)" ]]; then
    die "Working tree đang bẩn. Commit/stash trước, hoặc DEPLOY_FORCE=1 ./scripts/deploy-ubuntu.sh"
  fi
fi

if [[ "${DEPLOY_SKIP_PULL:-0}" != "1" ]]; then
  log "Fetch + pull $REMOTE/$BRANCH"
  git fetch "$REMOTE" "$BRANCH"
  git checkout "$BRANCH"
  git pull --ff-only "$REMOTE" "$BRANCH"
  git log -1 --oneline
else
  log "Bỏ qua git pull (DEPLOY_SKIP_PULL=1)"
fi

log "Docker compose build --no-cache (toàn bộ stack)"
docker compose build --no-cache

log "docker compose up -d --force-recreate --remove-orphans"
docker compose up -d --force-recreate --remove-orphans

log "Trạng thái services"
docker compose ps

log "Xong. Build ID = $BUILD_ID"
log "Hard refresh trình duyệt (Ctrl+Shift+R) nếu UI chưa đổi."
