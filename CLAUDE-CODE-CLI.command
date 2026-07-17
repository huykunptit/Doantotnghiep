#!/bin/bash
# ============================================================
#   CLAUDE CODE CLI (macOS) - POWERED BY NGHIMMO
# ============================================================
# Cach dung:
#   1. Copy file nay vao thu muc project (cho ban muon code)
#   2. Double-click vao file nay (CLAUDE-CODE-CLI.command)
#   3. Neu macOS bao "khong mo duoc", chuot phai -> Open -> Open
#   4. Nhap API Key (sk-...) khi duoc hoi
# ============================================================

# UTF-8 cho terminal (dong bo voi ban Windows)
export LANG=en_US.UTF-8
export LC_ALL=en_US.UTF-8
export PYTHONUTF8=1

# Mau chu cho de nhin
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Chuyen vao thu muc chua file nay de Claude Code mo dung project
cd "$(dirname "$0")" || exit 1

clear
echo ""
echo "============================================================"
echo "          CLAUDE CODE CLI - POWERED BY NGHIMMO"
echo "============================================================"
echo ""
echo "  Server : https://api.nghimmo.com"
echo "  Check  : https://api.nghimmo.com/check"
echo ""
echo "============================================================"
echo ""

# Nhap API key cua khach
printf "Nhap API Key cua ban (sk-...): "
read APIKEY

# Kiem tra rong
if [ -z "$APIKEY" ]; then
    echo ""
    echo -e "${RED}[LOI] Ban chua nhap API Key. Dong cua so va mo lai.${NC}"
    echo ""
    read -n 1 -s -r -p "Nhan phim bat ky de thoat..."
    exit 1
fi

# Tro Claude Code ve server Nghimmo (chi trong phien nay, dong la mat)
export ANTHROPIC_BASE_URL="https://api.nghimmo.com"
export ANTHROPIC_AUTH_TOKEN="$APIKEY"
export ANTHROPIC_MODEL="nghi/claude-sonnet-4.6"
export ANTHROPIC_SMALL_FAST_MODEL="nghi/claude-haiku-4.5"
# Xoa API key cu (neu may da tung set) de khong bi xung dot
unset ANTHROPIC_API_KEY

echo ""
echo -e "${GREEN}[OK] Da cau hinh xong. Dang mo Claude Code tai thu muc nay...${NC}"
echo -e "      (Thu muc: $(pwd))"
echo ""

# Kiem tra da cai claude chua
if ! command -v claude >/dev/null 2>&1; then
    echo -e "${YELLOW}[CHU Y] Chua tim thay lenh 'claude' tren may.${NC}"
    echo "         Can cai Node.js va Claude Code truoc:"
    echo "           npm install -g @anthropic-ai/claude-code"
    echo ""
    read -n 1 -s -r -p "Nhan phim bat ky de thoat..."
    exit 1
fi

# Mo Claude Code ngay tai thu muc dat file nay
claude

echo ""
echo "============================================================"
echo "  Claude Code da dong. Cua so nay co the dong."
echo ""
echo "  LUU Y: Bien moi truong chi song trong phien nay."
echo "  Lan sau muon dung lai, chay lai file nay."
echo "============================================================"
echo ""
read -n 1 -s -r -p "Nhan phim bat ky de thoat..."
