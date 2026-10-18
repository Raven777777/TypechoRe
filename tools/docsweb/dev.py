#!/usr/bin/env python3
"""本地预览: python dev.py（改 posts/ 或 static/ 自动重建）"""
from __future__ import annotations

import http.server
import os
import threading
import time

from build import DIST, POSTS, STATIC, build

PORT = int(os.environ.get("PORT", "3000"))


def snapshot() -> dict[str, int]:
    files = []
    for root in (POSTS, STATIC):
        if root.exists():
            files.extend(p for p in root.rglob("*") if p.is_file())
    return {str(p): p.stat().st_mtime_ns for p in files}


def watch() -> None:
    last = snapshot()
    while True:
        time.sleep(0.5)
        current = snapshot()
        if current != last:
            last = current
            try:
                build()
            except Exception as exc:  # 构建失败时继续服务旧页面
                print("[build error]", exc, flush=True)


class Handler(http.server.SimpleHTTPRequestHandler):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, directory=str(DIST), **kwargs)

    def send_error(self, code, message=None, explain=None):
        page = DIST / "404.html"
        if code == 404 and page.exists():
            body = page.read_bytes()
            self.send_response(404)
            self.send_header("Content-Type", "text/html; charset=utf-8")
            self.send_header("Content-Length", str(len(body)))
            self.end_headers()
            if self.command != "HEAD":
                self.wfile.write(body)
            return
        super().send_error(code, message, explain)


build()
threading.Thread(target=watch, daemon=True).start()
print(f"-> http://localhost:{PORT}", flush=True)
http.server.ThreadingHTTPServer(("", PORT), Handler).serve_forever()
