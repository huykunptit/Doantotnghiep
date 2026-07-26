# Start LMS stack + Cloudflare Tunnel (https://sylva-lms.io.vn)
$ErrorActionPreference = "Stop"
Set-Location (Split-Path -Parent $PSScriptRoot)

$envFile = Join-Path (Get-Location) ".env"
if (-not (Test-Path $envFile)) {
    Copy-Item ".env.example" ".env"
    Write-Host "Da tao .env tu .env.example — hay dán CLOUDFLARE_TUNNEL_TOKEN rồi chạy lại." -ForegroundColor Yellow
    exit 1
}

$tokenLine = Get-Content $envFile | Where-Object { $_ -match '^\s*CLOUDFLARE_TUNNEL_TOKEN\s*=' } | Select-Object -First 1
$token = ""
if ($tokenLine) {
    $token = ($tokenLine -split '=', 2)[1].Trim().Trim('"').Trim("'")
}
if (-not $token) {
    Write-Host @"

CHUA CO CLOUDFLARE_TUNNEL_TOKEN trong .env

1) Cloudflare Zero Trust → Networks → Tunnels → Create
2) Public Hostname: sylva-lms.io.vn  →  http://nginx:80
3) Copy token Docker → dán vào file .env:
     CLOUDFLARE_TUNNEL_TOKEN=eyJ...
4) Chi tiết: docs/cloudflare-tunnel.md

"@ -ForegroundColor Yellow
    exit 1
}

Write-Host "Starting stack + Cloudflare Tunnel (profile public)..." -ForegroundColor Cyan
docker compose --profile public up -d

Write-Host ""
Write-Host "Local:   http://localhost" -ForegroundColor Green
Write-Host "Public:  https://sylva-lms.io.vn" -ForegroundColor Green
Write-Host "Logs:    docker logs -f lms_cloudflared" -ForegroundColor DarkGray
