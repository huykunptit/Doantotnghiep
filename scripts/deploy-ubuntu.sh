#!/usr/bin/env bash
# =============================================================================
# LMS deploy (Ubuntu, chạy trên HOST — không trong container)
#
#   ./scripts/deploy-ubuntu.sh              # pull main + build --no-cache + up
#   ./scripts/deploy-ubuntu.sh run          # giống trên
#   DEPLOY_SKIP_PULL=1 ./scripts/deploy-ubuntu.sh   # chỉ rebuild
#   DEPLOY_FORCE=1 ./scripts/deploy-ubuntu.sh       # bỏ qua working-tree bẩn
#
#   ./scripts/deploy-ubuntu.sh seed-fresh   # XÓA DB + migrate:fresh --seed (toàn bộ seeder)
#   SEED_FRESH_YES=1 ./scripts/deploy-ubuntu.sh seed-fresh   # không hỏi confirm
#
#   sudo ./scripts/deploy-ubuntu.sh install # cài systemd + tạo .env.deploy
#   ./scripts/deploy-ubuntu.sh agent        # web UI /update-lms (systemd dùng lệnh này)
#
# Public: https://YOUR_DOMAIN/update-lms/?token=DEPLOY_SECRET
# =============================================================================
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SELF="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/$(basename "${BASH_SOURCE[0]}")"
cd "$ROOT"

CMD="${1:-run}"
if [[ $# -gt 0 ]]; then
  shift
fi

log() { printf '\n==> %s\n' "$*"; }
die() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }

usage() {
  sed -n '2,18p' "$SELF" | sed 's/^# \?//'
  exit "${1:-0}"
}

load_env_deploy() {
  if [[ -f "$ROOT/.env.deploy" ]]; then
    # shellcheck disable=SC1091
    set -a
    # shellcheck source=/dev/null
    source "$ROOT/.env.deploy"
    set +a
  fi
}

# ── pull + build --no-cache + up ─────────────────────────────────────────────
cmd_run() {
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

  # Bỏ qua thay đổi chỉ permission (chmod +x hay làm bẩn tree)
  if [[ "${DEPLOY_FORCE:-0}" != "1" ]]; then
    dirty="$(git -c core.fileMode=false status --porcelain)"
    if [[ -n "$dirty" ]]; then
      printf '%s\n' "$dirty"
      die "Working tree đang bẩn. Commit/stash trước, hoặc DEPLOY_FORCE=1 $SELF"
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
}

# ── XÓA DB rồi seed lại toàn bộ pipeline demo (User/Org/Course/Voucher/…) ────
cmd_seed_fresh() {
  command -v docker >/dev/null || die "Thiếu docker"
  docker compose version >/dev/null 2>&1 || die "Thiếu docker compose v2"

  local yes=0
  local arg
  for arg in "$@"; do
    if [[ "$arg" == "--yes" || "$arg" == "-y" ]]; then
      yes=1
    fi
  done
  if [[ "${SEED_FRESH_YES:-0}" == "1" ]]; then
    yes=1
  fi

  if [[ "$yes" != "1" ]]; then
    if [[ -t 0 ]]; then
      printf '\nCẢNH BÁO: lệnh này XÓA TOÀN BỘ dữ liệu MySQL rồi seed lại DemoDatabaseSeeder\n'
      printf '(roles, users, khóa, CTĐT, học vụ, điểm thưởng, voucher 5%%–75%%, …).\n'
      read -r -p "Gõ YES để tiếp tục: " ans
      [[ "$ans" == "YES" ]] || die "Đã hủy seed-fresh."
    else
      die "Cần SEED_FRESH_YES=1 hoặc --yes (không có TTY để confirm)."
    fi
  fi

  log "MySQL + Redis lên, tạm dừng backend (tránh đua migrate với entrypoint)"
  docker compose up -d --wait mysql redis
  docker compose stop backend

  # Tránh lần start sau của container tự db:seed canonical đè lên bản demo.
  rm -f "$ROOT/backend/storage/.seeded"

  artisan=(docker compose run --rm --no-deps --entrypoint php backend artisan)

  log "Publish avatar seed"
  "${artisan[@]}" avatars:publish || true

  log "migrate:fresh --seeder=DemoDatabaseSeeder (roles → users → khóa → học vụ → voucher 5%–75%)"
  "${artisan[@]}" migrate:fresh --force --seeder=DemoDatabaseSeeder
  "${artisan[@]}" avatars:publish || true

  touch "$ROOT/backend/storage/.seeded"

  log "Bật lại backend"
  docker compose up -d backend

  log "Xong seed-fresh. Dữ liệu demo + voucher đã nạp lại."
}

# ── cài systemd agent (1 lần trên server) ────────────────────────────────────
cmd_install() {
  if [[ "$(id -u)" -ne 0 ]]; then
    die "Chạy bằng sudo: sudo $SELF install"
  fi

  chmod +x "$SELF"
  command -v python3 >/dev/null || die "Thiếu python3"
  command -v openssl >/dev/null || die "Thiếu openssl"

  if [[ ! -f "$ROOT/.env.deploy" ]]; then
    SECRET="$(openssl rand -hex 24)"
    cat > "$ROOT/.env.deploy" <<EOF
DEPLOY_SECRET=$SECRET
DEPLOY_BRANCH=main
DEPLOY_REMOTE=origin
EOF
    chmod 600 "$ROOT/.env.deploy"
    log "Đã tạo .env.deploy với DEPLOY_SECRET mới."
  else
    load_env_deploy
    SECRET="${DEPLOY_SECRET:-}"
  fi

  cat > /etc/systemd/system/lms-deploy-agent.service <<EOF
[Unit]
Description=LMS deploy agent (/update-lms)
After=network.target docker.service
Wants=docker.service

[Service]
Type=simple
User=root
WorkingDirectory=$ROOT
EnvironmentFile=-$ROOT/.env.deploy
Environment=DEPLOY_AGENT_HOST=127.0.0.1
Environment=DEPLOY_AGENT_PORT=9099
ExecStart=$SELF agent
Restart=on-failure
RestartSec=3

[Install]
WantedBy=multi-user.target
EOF

  systemctl daemon-reload
  systemctl enable --now lms-deploy-agent.service
  systemctl --no-pager --full status lms-deploy-agent.service || true

  echo
  echo "============================================================"
  echo " Deploy agent OK"
  echo " UI:  https://YOUR_DOMAIN/update-lms/?token=${SECRET:-"(xem .env.deploy)"}"
  echo " CLI: $SELF"
  echo "============================================================"
  echo "Nhớ reload nginx: docker compose up -d nginx"
  echo "Secret: $ROOT/.env.deploy (đừng commit)"
}

# ── web console + SSE (Python nhúng trong file này) ───────────────────────────
cmd_agent() {
  load_env_deploy
  export DEPLOY_SELF="$SELF"
  export DEPLOY_ROOT="$ROOT"
  command -v python3 >/dev/null || die "Thiếu python3"
  exec python3 - <<'PY'
from __future__ import annotations

import json
import os
import secrets
import signal
import subprocess
import sys
import threading
import time
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import parse_qs, urlparse

ROOT = Path(os.environ["DEPLOY_ROOT"]).resolve()
SCRIPT = Path(os.environ["DEPLOY_SELF"]).resolve()
LOCK = Path(os.environ.get("DEPLOY_LOCK", "/tmp/lms-deploy.lock"))
HOST = os.environ.get("DEPLOY_AGENT_HOST", "127.0.0.1")
PORT = int(os.environ.get("DEPLOY_AGENT_PORT", "9099"))
SECRET = os.environ.get("DEPLOY_SECRET", "").strip()

_state_lock = threading.Lock()
_busy = False
_last_exit = None  # type: int | None
_log_lines = []  # type: list[str]
_subscribers = []  # type: list[threading.Event]
_proc = None  # type: subprocess.Popen | None


def require_secret():
    if not SECRET or len(SECRET) < 16:
        print("FATAL: set DEPLOY_SECRET (>=16 chars) in .env.deploy", file=sys.stderr)
        sys.exit(1)


def check_token(handler):
    auth = handler.headers.get("Authorization", "")
    header_token = auth[7:].strip() if auth.lower().startswith("bearer ") else ""
    qs = parse_qs(urlparse(handler.path).query)
    query_token = (qs.get("token") or [""])[0]
    provided = header_token or query_token or handler.headers.get("X-Deploy-Token", "")
    return bool(provided) and secrets.compare_digest(provided, SECRET)


def broadcast(line):
    with _state_lock:
        _log_lines.append(line)
        if len(_log_lines) > 5000:
            del _log_lines[:-4000]
        events = list(_subscribers)
    for ev in events:
        ev.set()


def run_job(args, title):
    global _busy, _last_exit, _proc, _log_lines
    with _state_lock:
        if _busy:
            return
        _busy = True
        _last_exit = None
        _log_lines = []
        _proc = None

    env = os.environ.copy()
    env["NUXT_PUBLIC_BUILD_ID"] = time.strftime("%Y%m%d-%H%M%S")
    env.setdefault("DEPLOY_BRANCH", "main")
    env.setdefault("DEPLOY_REMOTE", "origin")
    env["SEED_FRESH_YES"] = "1"

    cmd = ["bash", str(SCRIPT), *args]
    broadcast(f"$ cd {ROOT}")
    broadcast("$ " + " ".join(cmd))
    broadcast("--- %s started ---" % title)

    try:
        LOCK.write_text(str(os.getpid()), encoding="utf-8")
        _proc = subprocess.Popen(
            cmd,
            cwd=str(ROOT),
            env=env,
            stdout=subprocess.PIPE,
            stderr=subprocess.STDOUT,
            text=True,
            bufsize=1,
        )
        assert _proc.stdout is not None
        for line in _proc.stdout:
            broadcast(line.rstrip("\n"))
        code = _proc.wait()
        _last_exit = code
        broadcast("--- %s finished (exit %s) ---" % (title, code))
    except Exception as exc:
        _last_exit = 1
        broadcast(f"ERROR: {exc}")
    finally:
        try:
            LOCK.unlink(missing_ok=True)
        except OSError:
            pass
        with _state_lock:
            _busy = False
            _proc = None
            events = list(_subscribers)
        for ev in events:
            ev.set()


def run_deploy():
    run_job(["run"], "deploy")


def run_seed_fresh():
    run_job(["seed-fresh", "--yes"], "seed-fresh")


PAGE = r"""<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>LMS Deploy Console</title>
  <style>
    :root { color-scheme: dark; }
    * { box-sizing: border-box; }
    body {
      margin: 0; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
      background: #0b1220; color: #e5eefc;
    }
    .wrap { max-width: 1100px; margin: 0 auto; padding: 20px; }
    .card {
      background: #111827; border: 1px solid #243044; border-radius: 14px;
      overflow: hidden; box-shadow: 0 16px 40px rgba(0,0,0,.35);
    }
    header {
      display: flex; gap: 12px; flex-wrap: wrap; align-items: center;
      justify-content: space-between; padding: 16px 18px; border-bottom: 1px solid #243044;
      background: linear-gradient(180deg, #152033, #111827);
    }
    h1 { margin: 0; font-size: 1.05rem; letter-spacing: .02em; }
    .meta { color: #93a4bd; font-size: .82rem; }
    .actions { display: flex; gap: 8px; flex-wrap: wrap; }
    button {
      border: 0; border-radius: 10px; padding: 10px 14px; font: inherit; font-weight: 700;
      cursor: pointer;
    }
    .go { background: #0f766e; color: #fff; }
    .go:disabled { opacity: .5; cursor: not-allowed; }
    .seed { background: #b45309; color: #fff; }
    .seed:disabled { opacity: .5; cursor: not-allowed; }
    .clear { background: #1f2937; color: #dbe4f3; }
    #status {
      display: inline-flex; align-items: center; gap: 8px; padding: 4px 10px;
      border-radius: 999px; background: #1f2937; color: #93a4bd; font-size: .8rem;
    }
    #status.dot::before {
      content: ""; width: 8px; height: 8px; border-radius: 50%; background: #64748b;
    }
    #status.running::before { background: #f59e0b; box-shadow: 0 0 0 3px rgba(245,158,11,.2); }
    #status.ok::before { background: #22c55e; }
    #status.fail::before { background: #ef4444; }
    pre {
      margin: 0; padding: 16px 18px; min-height: 60vh; max-height: 72vh; overflow: auto;
      white-space: pre-wrap; word-break: break-word; line-height: 1.45; font-size: .86rem;
      background: #0a101a;
    }
    .warn {
      margin: 12px 0 0; padding: 10px 12px; border-radius: 10px;
      background: rgba(245,158,11,.1); border: 1px solid rgba(245,158,11,.35);
      color: #fbbf24; font-size: .85rem;
    }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <header>
        <div>
          <h1>LMS Deploy Console</h1>
          <div class="meta">pull origin/main → docker compose build --no-cache → up · seed-fresh = XÓA DB</div>
        </div>
        <div class="actions">
          <span id="status" class="dot">idle</span>
          <button class="clear" id="btnClear" type="button">Xóa log</button>
          <button class="seed" id="btnSeed" type="button">Seed fresh (xóa DB)</button>
          <button class="go" id="btnDeploy" type="button">Bắt đầu deploy</button>
        </div>
      </header>
      <pre id="log">Sẵn sàng. Deploy = pull + build. Seed fresh = XÓA DB rồi nạp lại toàn bộ seeder demo.
</pre>
    </div>
    <p class="warn">
      Endpoint này có quyền rebuild toàn bộ hệ thống. Chỉ dùng token bí mật, không chia sẻ URL có ?token=.
    </p>
  </div>
  <script>
    const token = new URLSearchParams(location.search).get('token') || '';
    const logEl = document.getElementById('log');
    const statusEl = document.getElementById('status');
    const btn = document.getElementById('btnDeploy');
    const btnSeed = document.getElementById('btnSeed');
    let es = null;

    function setStatus(text, cls) {
      statusEl.className = 'dot ' + (cls || '');
      statusEl.textContent = text;
    }

    function setBusy(busy) {
      btn.disabled = busy;
      btnSeed.disabled = busy;
    }

    function append(line) {
      logEl.textContent += line + '\n';
      logEl.scrollTop = logEl.scrollHeight;
    }

    function connectStream() {
      if (es) es.close();
      const url = './stream?token=' + encodeURIComponent(token);
      es = new EventSource(url);
      es.onmessage = (ev) => {
        const data = JSON.parse(ev.data);
        if (data.type === 'line') append(data.line);
        if (data.type === 'state') {
          if (data.busy) {
            setStatus('running', 'running');
            setBusy(true);
          } else if (data.exit_code === 0) {
            setStatus('success', 'ok');
            setBusy(false);
          } else if (data.exit_code == null) {
            setStatus('idle', '');
            setBusy(false);
          } else {
            setStatus('failed (' + data.exit_code + ')', 'fail');
            setBusy(false);
          }
        }
      };
      es.onerror = () => setStatus('stream error', 'fail');
    }

    document.getElementById('btnClear').onclick = () => { logEl.textContent = ''; };

    async function startJob(path, confirmMsg) {
      if (confirmMsg && !confirm(confirmMsg)) return;
      setBusy(true);
      setStatus('starting…', 'running');
      const res = await fetch('./' + path + '?token=' + encodeURIComponent(token), { method: 'POST' });
      if (!res.ok) {
        const t = await res.text();
        append('ERROR: ' + t);
        setStatus('failed', 'fail');
        setBusy(false);
      }
    }

    btn.onclick = () => startJob('run');
    btnSeed.onclick = () => startJob(
      'seed-fresh',
      'XÓA TOÀN BỘ database rồi seed lại (user demo, khóa, CTĐT, voucher 5%–75%, …). Tiếp tục?'
    );

    if (!token) {
      logEl.textContent = 'Thiếu token. Mở URL dạng /update-lms/?token=YOUR_SECRET\n';
      btn.disabled = true;
      btnSeed.disabled = true;
      setStatus('unauthorized', 'fail');
    } else {
      connectStream();
      if (new URLSearchParams(location.search).get('autostart') === '1') btn.click();
    }
  </script>
</body>
</html>
"""


class Handler(BaseHTTPRequestHandler):
    server_version = "LmsDeployAgent/1.0"

    def log_message(self, fmt, *args):
        sys.stderr.write("%s - %s\n" % (self.address_string(), fmt % args))

    def _send(self, code, body, content_type="text/plain; charset=utf-8"):
        self.send_response(code)
        self.send_header("Content-Type", content_type)
        self.send_header("Content-Length", str(len(body)))
        self.send_header("Cache-Control", "no-store")
        self.end_headers()
        self.wfile.write(body)

    def _deny(self):
        self._send(401, b"Unauthorized. Provide ?token= or Authorization: Bearer ...\n")

    def do_GET(self):
        parsed = urlparse(self.path)
        path = parsed.path.rstrip("/") or "/"

        if path in ("/", "/update-lms"):
            if not check_token(self):
                self._deny()
                return
            self._send(200, PAGE.encode("utf-8"), "text/html; charset=utf-8")
            return

        if path.endswith("/stream") or path == "/stream":
            if not check_token(self):
                self._deny()
                return
            self._sse()
            return

        if path.endswith("/status") or path == "/status":
            if not check_token(self):
                self._deny()
                return
            with _state_lock:
                payload = {"busy": _busy, "exit_code": _last_exit, "lines": len(_log_lines)}
            self._send(200, (json.dumps(payload) + "\n").encode(), "application/json")
            return

        self._send(404, b"Not found\n")

    def do_POST(self):
        parsed = urlparse(self.path)
        path = parsed.path.rstrip("/") or "/"
        action = path.split("/")[-1]
        if action not in ("run", "seed-fresh"):
            self._send(404, b"Not found\n")
            return
        if not check_token(self):
            self._deny()
            return
        if not SCRIPT.is_file():
            self._send(500, f"Missing script: {SCRIPT}\n".encode())
            return
        with _state_lock:
            if _busy:
                self._send(409, b"Job already running\n")
                return
        target = run_deploy if action == "run" else run_seed_fresh
        body = b"Deploy started\n" if action == "run" else b"Seed-fresh started\n"
        threading.Thread(target=target, daemon=True).start()
        time.sleep(0.15)
        self._send(202, body)

    def _sse(self):
        self.send_response(200)
        self.send_header("Content-Type", "text/event-stream")
        self.send_header("Cache-Control", "no-cache")
        self.send_header("Connection", "keep-alive")
        self.send_header("X-Accel-Buffering", "no")
        self.end_headers()

        wake = threading.Event()
        with _state_lock:
            _subscribers.append(wake)
            snapshot = list(_log_lines)
            busy = _busy
            exit_code = _last_exit

        def send(obj):
            data = json.dumps(obj, ensure_ascii=False)
            self.wfile.write(("data: %s\n\n" % data).encode("utf-8"))
            self.wfile.flush()

        try:
            send({"type": "state", "busy": busy, "exit_code": exit_code})
            for line in snapshot:
                send({"type": "line", "line": line})
            cursor = len(snapshot)
            while True:
                wake.wait(timeout=15)
                wake.clear()
                with _state_lock:
                    lines = list(_log_lines)
                    busy = _busy
                    exit_code = _last_exit
                if cursor < len(lines):
                    for line in lines[cursor:]:
                        send({"type": "line", "line": line})
                    cursor = len(lines)
                send({"type": "state", "busy": busy, "exit_code": exit_code})
                self.wfile.write(b": ping\n\n")
                self.wfile.flush()
        except (BrokenPipeError, ConnectionResetError):
            pass
        finally:
            with _state_lock:
                if wake in _subscribers:
                    _subscribers.remove(wake)


def main():
    require_secret()
    if not SCRIPT.is_file():
        print("FATAL: missing %s" % SCRIPT, file=sys.stderr)
        sys.exit(1)
    os.chmod(SCRIPT, 0o755)
    httpd = ThreadingHTTPServer((HOST, PORT), Handler)

    def _stop(signum, frame):
        print("\nShutting down…")
        httpd.shutdown()

    signal.signal(signal.SIGINT, _stop)
    signal.signal(signal.SIGTERM, _stop)
    print("LMS deploy agent on http://%s:%s/" % (HOST, PORT))
    print("Public URL (via nginx): /update-lms/?token=***")
    print("Repo root: %s" % ROOT)
    httpd.serve_forever()


if __name__ == "__main__":
    main()
PY
}

case "$CMD" in
  run|deploy)          cmd_run "$@" ;;
  seed-fresh|seedfresh|db-fresh) cmd_seed_fresh "$@" ;;
  install)             cmd_install "$@" ;;
  agent|serve)         cmd_agent "$@" ;;
  -h|--help|help)      usage 0 ;;
  *) die "Lệnh không hợp lệ: $CMD (dùng: run | seed-fresh | install | agent)" ;;
esac
