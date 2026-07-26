#!/usr/bin/env bash
# Start LMS stack + Cloudflare Tunnel (https://sylva-lms.io.vn)
set -euo pipefail
cd "$(dirname "$0")/.."

if [[ ! -f .env ]]; then
  cp .env.example .env
  echo "Created .env from .env.example — paste CLOUDFLARE_TUNNEL_TOKEN then re-run."
  exit 1
fi

# shellcheck disable=SC1091
set -a
# shellcheck source=/dev/null
source .env
set +a

if [[ -z "${CLOUDFLARE_TUNNEL_TOKEN:-}" ]]; then
  cat <<'EOF'
CLOUDFLARE_TUNNEL_TOKEN is empty in .env

1) Cloudflare Zero Trust → Networks → Tunnels → Create
2) Public Hostname: sylva-lms.io.vn → http://nginx:80
3) Paste Docker token into .env
4) See docs/cloudflare-tunnel.md
EOF
  exit 1
fi

echo "Starting stack + Cloudflare Tunnel (profile public)..."
docker compose --profile public up -d

echo
echo "Local:  http://localhost"
echo "Public: https://sylva-lms.io.vn"
echo "Logs:   docker logs -f lms_cloudflared"
