#!/usr/bin/env bash
# Cài deploy agent + nginx route trên Ubuntu (chạy 1 lần trên server).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

if [[ "$(id -u)" -ne 0 ]]; then
  echo "Chạy bằng sudo: sudo bash scripts/install-deploy-agent.sh"
  exit 1
fi

chmod +x "$ROOT/scripts/deploy-ubuntu.sh" "$ROOT/scripts/deploy-agent.py"

if [[ ! -f "$ROOT/.env.deploy" ]]; then
  SECRET="$(openssl rand -hex 24)"
  cat > "$ROOT/.env.deploy" <<EOF
DEPLOY_SECRET=$SECRET
DEPLOY_BRANCH=main
DEPLOY_REMOTE=origin
EOF
  chmod 600 "$ROOT/.env.deploy"
  echo "Đã tạo .env.deploy với DEPLOY_SECRET mới."
else
  # shellcheck disable=SC1091
  source "$ROOT/.env.deploy"
  SECRET="${DEPLOY_SECRET:-}"
fi

# Sửa WorkingDirectory trong unit theo path thật
sed "s|/opt/Doantotnghiep|$ROOT|g" "$ROOT/scripts/deploy-agent.service" \
  > /etc/systemd/system/lms-deploy-agent.service

systemctl daemon-reload
systemctl enable --now lms-deploy-agent.service
systemctl --no-pager --full status lms-deploy-agent.service || true

echo
echo "============================================================"
echo " Deploy agent OK"
echo " UI:  https://YOUR_DOMAIN/update-lms/?token=$SECRET"
echo " CLI: $ROOT/scripts/deploy-ubuntu.sh"
echo "============================================================"
echo "Nhớ: docker compose up -d nginx  (đã có location /update-lms)"
echo "File secret: $ROOT/.env.deploy  (đừng commit)"
