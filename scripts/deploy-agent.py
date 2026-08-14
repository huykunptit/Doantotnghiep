#!/usr/bin/env python3
"""
Deploy agent cho LMS — chạy trên HOST Ubuntu.

- UI + log realtime:  http://127.0.0.1:9099/?token=SECRET
- Qua nginx public:   https://domain/update-lms/?token=SECRET

Bảo mật:
  - Bắt buộc DEPLOY_SECRET
  - Chỉ listen 127.0.0.1 (nginx proxy vào)
  - Chỉ 1 deploy tại một thời điểm (lock file)

Cài systemd: xem scripts/deploy-agent.service
"""

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

ROOT = Path(__file__).resolve().parent.parent
SCRIPT = ROOT / "scripts" / "deploy-ubuntu.sh"
LOCK = Path(os.environ.get("DEPLOY_LOCK", "/tmp/lms-deploy.lock"))
HOST = os.environ.get("DEPLOY_AGENT_HOST", "127.0.0.1")
PORT = int(os.environ.get("DEPLOY_AGENT_PORT", "9099"))
SECRET = os.environ.get("DEPLOY_SECRET", "").strip()

_state_lock = threading.Lock()
_busy = False
_last_exit: int | None = None
_log_lines: list[str] = []
_subscribers: list[threading.Event] = []
_proc: subprocess.Popen | None = None


def require_secret() -> None:
    if not SECRET or len(SECRET) < 16:
        print(
            "FATAL: set DEPLOY_SECRET (>=16 chars) before starting deploy-agent.",
            file=sys.stderr,
        )
        sys.exit(1)


def check_token(handler: BaseHTTPRequestHandler) -> bool:
    auth = handler.headers.get("Authorization", "")
    header_token = ""
    if auth.lower().startswith("bearer "):
        header_token = auth[7:].strip()
    qs = parse_qs(urlparse(handler.path).query)
    query_token = (qs.get("token") or [""])[0]
    provided = header_token or query_token or handler.headers.get("X-Deploy-Token", "")
    if not provided or not secrets.compare_digest(provided, SECRET):
        return False
    return True


def broadcast(line: str) -> None:
    with _state_lock:
        _log_lines.append(line)
        # giữ tối đa ~5000 dòng trong RAM
        if len(_log_lines) > 5000:
            del _log_lines[:-4000]
        events = list(_subscribers)
    for ev in events:
        ev.set()


def run_deploy() -> None:
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
    env["DEPLOY_BRANCH"] = env.get("DEPLOY_BRANCH", "main")
    env["DEPLOY_REMOTE"] = env.get("DEPLOY_REMOTE", "origin")

    broadcast(f"$ cd {ROOT}")
    broadcast(f"$ NUXT_PUBLIC_BUILD_ID={env['NUXT_PUBLIC_BUILD_ID']} bash scripts/deploy-ubuntu.sh")
    broadcast("— deploy started —")

    try:
        LOCK.write_text(str(os.getpid()), encoding="utf-8")
        _proc = subprocess.Popen(
            ["bash", str(SCRIPT)],
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
        broadcast(f"— deploy finished (exit {code}) —")
    except Exception as exc:  # noqa: BLE001
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


PAGE = """<!doctype html>
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
          <div class="meta">pull origin/main → docker compose build --no-cache → up</div>
        </div>
        <div class="actions">
          <span id="status" class="dot">idle</span>
          <button class="clear" id="btnClear" type="button">Xóa log</button>
          <button class="go" id="btnDeploy" type="button">Bắt đầu deploy</button>
        </div>
      </header>
      <pre id="log">Sẵn sàng. Bấm "Bắt đầu deploy" để pull + build --no-cache.
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
    let es = null;

    function setStatus(text, cls) {
      statusEl.className = 'dot ' + (cls || '');
      statusEl.textContent = text;
    }

    function append(line) {
      logEl.textContent += line + '\\n';
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
            btn.disabled = true;
          } else if (data.exit_code === 0) {
            setStatus('success', 'ok');
            btn.disabled = false;
          } else if (data.exit_code == null) {
            setStatus('idle', '');
            btn.disabled = false;
          } else {
            setStatus('failed (' + data.exit_code + ')', 'fail');
            btn.disabled = false;
          }
        }
      };
      es.onerror = () => setStatus('stream error', 'fail');
    }

    document.getElementById('btnClear').onclick = () => { logEl.textContent = ''; };
    btn.onclick = async () => {
      btn.disabled = true;
      setStatus('starting…', 'running');
      const res = await fetch('./run?token=' + encodeURIComponent(token), { method: 'POST' });
      if (!res.ok) {
        const t = await res.text();
        append('ERROR: ' + t);
        setStatus('failed', 'fail');
        btn.disabled = false;
      }
    };

    if (!token) {
      logEl.textContent = 'Thiếu token. Mở URL dạng /update-lms/?token=YOUR_SECRET\\n';
      btn.disabled = true;
      setStatus('unauthorized', 'fail');
    } else {
      connectStream();
      // ?autostart=1 → chạy ngay
      if (new URLSearchParams(location.search).get('autostart') === '1') btn.click();
    }
  </script>
</body>
</html>
"""


class Handler(BaseHTTPRequestHandler):
    server_version = "LmsDeployAgent/1.0"

    def log_message(self, fmt: str, *args) -> None:  # quieter
        sys.stderr.write("%s - %s\n" % (self.address_string(), fmt % args))

    def _send(self, code: int, body: bytes, content_type: str = "text/plain; charset=utf-8") -> None:
        self.send_response(code)
        self.send_header("Content-Type", content_type)
        self.send_header("Content-Length", str(len(body)))
        self.send_header("Cache-Control", "no-store")
        self.end_headers()
        self.wfile.write(body)

    def _deny(self) -> None:
        self._send(401, b"Unauthorized. Provide ?token= or Authorization: Bearer ...\n")

    def do_GET(self) -> None:  # noqa: N802
        parsed = urlparse(self.path)
        path = parsed.path.rstrip("/") or "/"

        if path in ("/", "/update-lms"):
            if not check_token(self):
                self._deny()
                return
            page = PAGE.encode("utf-8")
            self._send(200, page, "text/html; charset=utf-8")
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
                payload = {
                    "busy": _busy,
                    "exit_code": _last_exit,
                    "lines": len(_log_lines),
                }

            self._send(200, (json.dumps(payload) + "\n").encode(), "application/json")
            return

        self._send(404, b"Not found\n")

    def do_POST(self) -> None:  # noqa: N802
        parsed = urlparse(self.path)
        path = parsed.path.rstrip("/") or "/"
        if not (path.endswith("/run") or path == "/run"):
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
                self._send(409, b"Deploy already running\n")
                return
        threading.Thread(target=run_deploy, daemon=True).start()
        # chờ thread set busy
        time.sleep(0.15)
        self._send(202, b"Deploy started\n")

    def _sse(self) -> None:
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

        def send(obj: dict) -> None:
            data = json.dumps(obj, ensure_ascii=False)
            self.wfile.write(f"data: {data}\n\n".encode("utf-8"))
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
                # keepalive
                self.wfile.write(b": ping\n\n")
                self.wfile.flush()
        except (BrokenPipeError, ConnectionResetError):
            pass
        finally:
            with _state_lock:
                if wake in _subscribers:
                    _subscribers.remove(wake)


def main() -> None:
    require_secret()
    if not SCRIPT.is_file():
        print(f"FATAL: missing {SCRIPT}", file=sys.stderr)
        sys.exit(1)
    os.chmod(SCRIPT, 0o755)

    httpd = ThreadingHTTPServer((HOST, PORT), Handler)

    def _stop(signum, frame):  # noqa: ANN001, ARG001
        print("\nShutting down deploy-agent…")
        httpd.shutdown()

    signal.signal(signal.SIGINT, _stop)
    signal.signal(signal.SIGTERM, _stop)

    print(f"LMS deploy agent on http://{HOST}:{PORT}/")
    print(f"Public URL (via nginx): /update-lms/?token=***")
    print(f"Repo root: {ROOT}")
    httpd.serve_forever()


if __name__ == "__main__":
    main()
