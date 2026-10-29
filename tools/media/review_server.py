"""Локальный сервер страницы проверки: отдаёт папку партии и принимает решения.

Запуск: `python review_server.py <папка партии> [порт]` — страница на http://localhost:4174/,
кнопка «Сохранить решения» пишет <папка партии>/decisions.json. Слушает только localhost.
"""
import json
import os
import sys
from functools import partial
from http.server import SimpleHTTPRequestHandler, ThreadingHTTPServer

ROOT = os.path.abspath(sys.argv[1])
PORT = int(sys.argv[2]) if len(sys.argv) > 2 else 4174


class Handler(SimpleHTTPRequestHandler):
    def do_POST(self):
        if self.path != "/save":
            self.send_error(404)
            return
        body = self.rfile.read(int(self.headers.get("Content-Length") or 0))
        try:
            data = json.loads(body)
            assert isinstance(data, dict) and all(
                isinstance(v, dict) and v.get("action") in ("cut", "full", "keep") and isinstance(v.get("comment", ""), str)
                for v in data.values())
        except Exception:  # noqa: BLE001
            self.send_error(400, "bad decisions")
            return
        with open(os.path.join(ROOT, "decisions.json"), "w", encoding="utf-8") as f:
            json.dump(data, f, ensure_ascii=False, indent=1)
        self.send_response(200)
        self.send_header("Content-Type", "text/plain; charset=utf-8")
        self.end_headers()
        self.wfile.write(b"ok")


ThreadingHTTPServer(("127.0.0.1", PORT), partial(Handler, directory=ROOT)).serve_forever()
