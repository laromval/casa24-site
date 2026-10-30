"""Локальный сервер страницы проверки: отдаёт папку партии и принимает решения.

Запуск: `python review_server.py <папка партии> [порт]` — страница на http://localhost:4174/,
кнопка «Сохранить решения» пишет <папка партии>/decisions.json. Слушает только localhost.
POST /upload?id=<товар> — своё фото товара (найдено вручную): оригинал в <партия>/own/,
белый квадрат 1000×1000 в out/<id>.own.webp (вариант «own»).
?to=gallery / "to": "gallery" — доп. фото в галерею: out/<id>.gN.webp (POST /gallery-delete — убрать, /gallery-promote — сделать главным).
POST /fetch — то же по ссылке на картинку (перетащили из Google или вставили адрес).
POST /open-chrome {url} — открыть поиск Google в Google Chrome: новой вкладкой, если Chrome уже открыт,
иначе окном на правой половине экрана.
POST /re-edit {id, url, deg} — рабочая копия главного фото уже залитого товара (out/<id>.re.webp), поворот;
/upload?to=re и /crop с "to": "re" — тоже в неё (замена главного, action "replace").
POST /cut-bg {id, from} — кнопка «Удалить фон»: в очередь на вырезку моделью (cut_bg.py в .venv, по одному фото)
→ out/<id>.own.webp; GET /cut-status — состояние очереди.
POST /crop — кадрирование оригинала или варианта рамкой со страницы, результат туда же (вариант «own»).
GET /search?q=<название, артикул или ID> — поиск RU-товаров на сайте (WooCommerce REST, доступ из .env, как upload_batch.py);
POST /add {id} — добавить найденный товар в партию: его главное фото с сайта в src/ и out/<id>.full.webp
(без фото — карточка «нет фото»), запись в pick.json и report.json ("added" — время, такие карточки идут первыми).

С телефона: `python review_server.py <партия> <порт> --key <секрет>` и туннель `cloudflared tunnel --url http://localhost:<порт>`.
Запрос не с localhost (Host — адрес туннеля) пускается только с ?key=<секрет> (ставит cookie на 30 дней) или с этой cookie;
открыть Chrome на компьютере (/open-chrome) с телефона нельзя. Без --key страница, как раньше, только для этого компьютера.
"""
import hmac
import html
import io
import json
import os
import re
import subprocess
import sys
import threading
import time
import urllib.error
import urllib.request
from collections import deque
from functools import partial
from http.server import SimpleHTTPRequestHandler, ThreadingHTTPServer
from urllib.parse import parse_qs, quote, urlparse

import numpy as np
from PIL import Image, ImageOps

ROOT = os.path.abspath(sys.argv[1])
PORT = int(sys.argv[2]) if len(sys.argv) > 2 and sys.argv[2].isdigit() else 4174
KEY = sys.argv[sys.argv.index("--key") + 1] if "--key" in sys.argv else ""
SIZE, PAD = 1000, 0.05
MAX_UPLOAD = 30 * 1024 * 1024
VARIANTS = ("cut", "full", "ai", "web", "own")
FIELDS = "_fields=id,name,sku,permalink,images,categories,stock_status,status"
BATCH_LOCK = threading.Lock()        # pick.json / report.json пишет /add — по одному


def wc(path):
    """GET к WooCommerce REST сайта с паролем приложения из .env (тот же доступ, что у upload_batch.py)."""
    from upload_batch import call      # читает .env при импорте — только когда нужен поиск
    return call("GET", path)


def short(p, in_batch):
    imgs = p.get("images") or []
    return {"id": p["id"], "name": html.unescape(p["name"]), "sku": p.get("sku", ""),
            "cat": html.unescape((p.get("categories") or [{}])[0].get("name", "")), "thumb": imgs[0]["src"] if imgs else "",
            "instock": p.get("stock_status") == "instock", "in_batch": str(p["id"]) in in_batch}


def to_square(im):
    im = ImageOps.exif_transpose(im)
    if im.mode in ("RGBA", "LA", "P"):
        im = im.convert("RGBA")
        bg = Image.new("RGBA", im.size, "white")
        im = Image.alpha_composite(bg, im)       # прозрачный фон → белый
    im = im.convert("RGB")
    inner = round(SIZE * (1 - 2 * PAD))
    k = inner / max(im.size)
    im = im.resize((max(1, round(im.width * k)), max(1, round(im.height * k))), Image.LANCZOS)
    canvas = Image.new("RGB", (SIZE, SIZE), "white")
    canvas.paste(im, ((SIZE - im.width) // 2, (SIZE - im.height) // 2))
    return canvas


def warp(im, quad):
    """Четырёхугольник (углы по часовой от левого верхнего) → ровный прямоугольник, как Perspective Crop."""
    (x0, y0), (x1, y1), (x2, y2), (x3, y3) = quad
    dist = lambda a, b: ((a[0] - b[0]) ** 2 + (a[1] - b[1]) ** 2) ** 0.5
    w = round(max(dist(quad[0], quad[1]), dist(quad[3], quad[2])))
    h = round(max(dist(quad[0], quad[3]), dist(quad[1], quad[2])))
    if w < 10 or h < 10:
        raise ValueError("рамка слишком маленькая")
    # коэффициенты PIL: точка результата (u, v) → точка исходника (x, y)
    rows, rhs = [], []
    for (u, v), (x, y) in zip([(0, 0), (w, 0), (w, h), (0, h)], quad):
        rows.append([u, v, 1, 0, 0, 0, -u * x, -v * x]); rhs.append(x)
        rows.append([0, 0, 0, u, v, 1, -u * y, -v * y]); rhs.append(y)
    try:
        coeffs = np.linalg.solve(np.array(rows, float), np.array(rhs, float))
    except np.linalg.LinAlgError:
        raise ValueError("углы рамки лежат на одной линии") from None
    im = im.convert("RGBA") if im.mode in ("RGBA", "LA", "P") else im.convert("RGB")
    return im.transform((w, h), Image.PERSPECTIVE, tuple(coeffs), Image.BICUBIC, fillcolor="white")


def find_chrome():
    for path in (r"C:\Program Files\Google\Chrome\Application\chrome.exe",
                 r"C:\Program Files (x86)\Google\Chrome\Application\chrome.exe",
                 os.path.expandvars(r"%LOCALAPPDATA%\Google\Chrome\Application\chrome.exe")):
        if os.path.exists(path):
            return path
    return None


def chrome_running():
    try:
        out = subprocess.run(["tasklist", "/FI", "IMAGENAME eq chrome.exe", "/NH"], capture_output=True, text=True, timeout=10).stdout
    except Exception:  # noqa: BLE001
        return False
    return "chrome.exe" in out.lower()


# «Удалить фон»: одна вырезка занимает до ~7 ГБ памяти, поэтому фото идут строго по одному.
# Воркер держит один процесс cut_bg.py --queue, пока очередь не пуста (модель грузится один раз),
# и закрывает его, когда очередь кончилась, — память освобождается.
CUT_QUEUE, CUT_STATE, CUT_CV = deque(), {}, threading.Condition()


def cut_status():
    """{id: {state: queued|working|done|none|error, pos?, src?, size?, error?}}; pos — место в очереди (1 — следующее)."""
    st = {k: dict(v) for k, v in CUT_STATE.items()}
    for i, (pid, _) in enumerate(CUT_QUEUE, 1):
        st[pid]["pos"] = i
    return st


def cut_worker():
    here = os.path.dirname(os.path.abspath(__file__))
    log = os.path.join(ROOT, "out", "cut_bg.log")
    while True:
        with CUT_CV:
            while not CUT_QUEUE:
                CUT_CV.wait()
        with open(log, "a", encoding="utf-8") as err:
            proc = subprocess.Popen([os.path.join(here, ".venv", "Scripts", "python.exe"), os.path.join(here, "cut_bg.py"), "--queue"],
                                    stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=err, cwd=here,
                                    text=True, encoding="utf-8", env={**os.environ, "PYTHONIOENCODING": "utf-8"})
            while True:
                with CUT_CV:
                    if not CUT_QUEUE:
                        break
                    pid, path = CUT_QUEUE.popleft()
                    CUT_STATE[pid] = {"state": "working"}
                tmp = os.path.join(ROOT, "out", f"{pid}.cutting.webp")
                try:
                    proc.stdin.write(path + "\t" + tmp + "\n")
                    proc.stdin.flush()
                    line = proc.stdout.readline().strip()
                except OSError:
                    line = ""
                if line.startswith("ok "):
                    os.replace(tmp, os.path.join(ROOT, "out", f"{pid}.own.webp"))
                    res = {"state": "done", "src": f"out/{pid}.own.webp?t={int(time.time() * 1000)}",
                           "size": [int(v) for v in line.split()[1:3]]}
                elif line == "none":
                    res = {"state": "none", "error": "модель не нашла товар на фото"}
                else:                       # err … или процесс упал (память) — остальным нужен новый процесс
                    res = {"state": "error", "error": "вырезка не удалась: " + (line[4:] or "процесс модели завершился, см. out/cut_bg.log")}
                with CUT_CV:
                    CUT_STATE[pid] = res
                if not line or proc.poll() is not None:
                    break
            try:
                proc.stdin.close()
            except OSError:
                pass
            proc.wait()


class Handler(SimpleHTTPRequestHandler):
    set_cookie = False

    def end_headers(self):
        # страница пересобирается между партиями — браузер не должен показывать старую копию
        self.send_header("Cache-Control", "no-store")
        if self.set_cookie:
            self.send_header("Set-Cookie", f"casa24key={KEY}; Path=/; Max-Age=2592000; HttpOnly; Secure; SameSite=Lax")
        super().end_headers()

    def local(self):
        return (self.headers.get("Host") or "").split(":")[0] in ("localhost", "127.0.0.1")

    def allowed(self):
        """С этого компьютера — всегда; через туннель (с телефона) — только с ключом в ссылке или в cookie."""
        if self.local():
            return True
        if not KEY:
            return False
        given = (parse_qs(urlparse(self.path).query).get("key") or [""])[0]
        if given and hmac.compare_digest(given, KEY):
            self.set_cookie = True
            return True
        cookie = dict(c.strip().split("=", 1) for c in (self.headers.get("Cookie") or "").split(";") if "=" in c)
        return hmac.compare_digest(cookie.get("casa24key", ""), KEY)

    def do_GET(self):
        if not self.allowed():
            return self.send_error(403, "Forbidden", "Откройте ссылку с ключом, которую прислал Claude.")
        if urlparse(self.path).path == "/cut-status":
            with CUT_CV:
                return self.reply(200, cut_status())
        if urlparse(self.path).path == "/search":
            return self.search()
        # страница пересобирается при каждом открытии: загруженные фото, галерея и кадры видны после F5
        if urlparse(self.path).path in ("/", "/index.html"):
            subprocess.run([sys.executable, os.path.join(os.path.dirname(os.path.abspath(__file__)), "compare_page.py"), ROOT],
                           capture_output=True)
        super().do_GET()

    def reply(self, code, payload):
        body = json.dumps(payload, ensure_ascii=False).encode()
        self.send_response(code)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def search(self):
        """?q= — до 20 опубликованных RU-товаров по названию; число — ещё и точное совпадение артикула или ID."""
        q = (parse_qs(urlparse(self.path).query).get("q") or [""])[0].strip()
        if len(q) < 2:
            return self.reply(400, {"error": "введите хотя бы 2 буквы или цифры"})
        try:
            found = []
            if q.isdigit():
                found += wc(f"/wp-json/wc/v3/products?sku={q}&lang=ru&{FIELDS}")
                try:
                    found.append(wc(f"/wp-json/wc/v3/products/{q}?{FIELDS}"))
                except urllib.error.HTTPError:
                    pass                    # такого ID нет
            found += wc(f"/wp-json/wc/v3/products?search={quote(q)}&status=publish&lang=ru&per_page=20&{FIELDS}")
        except Exception as e:  # noqa: BLE001
            return self.reply(502, {"error": f"сайт не ответил: {e}"})
        in_batch = set(json.load(open(os.path.join(ROOT, "out", "report.json"), encoding="utf-8")))
        seen, out = set(), []
        for p in found:
            if p["id"] not in seen and p.get("status", "publish") == "publish":
                seen.add(p["id"])
                out.append(short(p, in_batch))
        self.reply(200, out)

    def add(self):
        """{id} — найденный товар в партию: главное фото с сайта → src/<id>.<ext> и белый квадрат out/<id>.full.webp."""
        try:
            pid = str(json.loads(self.rfile.read(int(self.headers.get("Content-Length") or 0)))["id"])
            assert re.fullmatch(r"\d{1,10}", pid)
        except Exception:  # noqa: BLE001
            return self.reply(400, {"error": "нужен ID товара"})
        rpath, ppath = os.path.join(ROOT, "out", "report.json"), os.path.join(ROOT, "pick.json")
        if pid in json.load(open(rpath, encoding="utf-8")):
            return self.reply(200, {"ok": True, "already": True})
        try:
            p = wc(f"/wp-json/wc/v3/products/{pid}")
        except Exception as e:  # noqa: BLE001
            return self.reply(502, {"error": f"не нашёл товар на сайте: {e}"})
        cats = ", ".join(html.unescape(c["name"]) for c in p.get("categories", []))
        entry = {"id": p["id"], "sku": p.get("sku", ""), "name": p["name"], "url": p.get("permalink", ""),
                 "in_stock": p.get("stock_status") == "instock", "cat": cats, "img_id": 0, "src": "", "thumb": ""}
        rep = {"src": "", "choice": "none", "flags": ["нет фото"], "has_cut": False, "no_photo": True}
        if p.get("images"):
            img = p["images"][0]
            try:
                raw = urllib.request.urlopen(urllib.request.Request(img["src"], headers={"User-Agent": "Mozilla/5.0"}), timeout=30).read()
                im = Image.open(io.BytesIO(raw))
                im.load()
            except Exception as e:  # noqa: BLE001
                return self.reply(502, {"error": f"не скачалось фото с сайта: {e}"})
            ext = (im.format or "jpg").lower().replace("jpeg", "jpg")
            with open(os.path.join(ROOT, "src", f"{pid}.{ext}"), "wb") as f:
                f.write(raw)
            to_square(im).save(os.path.join(ROOT, "out", f"{pid}.full.webp"), quality=88, method=6)
            entry.update(img_id=img["id"], src=img["src"], thumb=img["src"])
            rep = {"src": f"{pid}.{ext}", "choice": "full", "flags": [], "has_cut": False}
        else:
            entry.update(verdict="нет фото", no_photo=True)
        rep["added"] = int(time.time())
        with BATCH_LOCK:
            report, pick = json.load(open(rpath, encoding="utf-8")), json.load(open(ppath, encoding="utf-8"))
            report[pid] = rep
            pick = [x for x in pick if str(x["id"]) != pid] + [entry]
            json.dump(pick, open(ppath, "w", encoding="utf-8"), ensure_ascii=False)
            json.dump(report, open(rpath, "w", encoding="utf-8"), ensure_ascii=False, indent=1)
        self.reply(200, {"ok": True, "name": html.unescape(p["name"])})

    def fetch(self):
        """{id, url} — картинка по ссылке (перетащена из Google или вставлена) → как своё фото."""
        try:
            req = json.loads(self.rfile.read(int(self.headers.get("Content-Length") or 0)))
            pid, url, to = str(req["id"]), str(req["url"]), req.get("to", "main")
            assert re.fullmatch(r"\d{1,10}", pid) and urlparse(url).scheme in ("http", "https")
        except Exception:  # noqa: BLE001
            return self.reply(400, {"error": "нужна ссылка http(s) на картинку"})
        try:
            r = urllib.request.urlopen(urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0"}), timeout=30)
            raw = r.read(MAX_UPLOAD + 1)
        except Exception as e:  # noqa: BLE001
            return self.reply(400, {"error": f"не удалось скачать: {e}"})
        if len(raw) > MAX_UPLOAD:
            return self.reply(400, {"error": "файл больше 30 МБ"})
        self.save_own(pid, raw, to)

    def upload(self):
        q = parse_qs(urlparse(self.path).query)
        pid, to = (q.get("id") or [""])[0], (q.get("to") or ["main"])[0]
        length = int(self.headers.get("Content-Length") or 0)
        if not re.fullmatch(r"\d{1,10}", pid) or not 0 < length <= MAX_UPLOAD:
            return self.reply(400, {"error": "нужен id товара и файл до 30 МБ"})
        self.save_own(pid, self.rfile.read(length), to)

    def gallery_rotate(self):
        """{id, name: "<id>.gN.webp", deg: ±90} — повернуть доп. фото по часовой (+) / против (−), файл на месте."""
        try:
            req = json.loads(self.rfile.read(int(self.headers.get("Content-Length") or 0)))
            pid, name, deg = str(req["id"]), str(req["name"]), int(req["deg"])
            assert re.fullmatch(r"\d{1,10}", pid) and re.fullmatch(rf"{re.escape(pid)}\.g\d+\.webp", name) and deg in (90, -90, 180)
            path = os.path.join(ROOT, "out", name)
            Image.open(path).rotate(-deg, expand=True, fillcolor="white").save(path, quality=88, method=6)
        except Exception:  # noqa: BLE001
            return self.reply(400, {"error": "не удалось повернуть"})
        self.reply(200, {"src": f"out/{name}?t={int(time.time() * 1000)}"})

    def gallery_promote(self):
        """{id, name: "<id>.gN.webp", to: "own" | "re"} — доп. фото становится новым главным (файл переносится)."""
        try:
            req = json.loads(self.rfile.read(int(self.headers.get("Content-Length") or 0)))
            pid, name, to = str(req["id"]), str(req["name"]), str(req["to"])
            assert re.fullmatch(r"\d{1,10}", pid) and re.fullmatch(rf"{re.escape(pid)}\.g\d+\.webp", name) and to in ("own", "re")
            os.replace(os.path.join(ROOT, "out", name), os.path.join(ROOT, "out", f"{pid}.{to}.webp"))
        except Exception:  # noqa: BLE001
            return self.reply(400, {"error": "не удалось сделать главным"})
        self.reply(200, {"src": f"out/{pid}.{to}.webp?t={int(time.time() * 1000)}", "size": [1000, 1000]})

    def gallery_delete(self):
        """{id, name: "<id>.gN.webp"} — убрать доп. фото из галереи партии."""
        try:
            req = json.loads(self.rfile.read(int(self.headers.get("Content-Length") or 0)))
            pid, name = str(req["id"]), str(req["name"])
            assert re.fullmatch(rf"{re.escape(pid)}\.g\d+\.webp", name) and re.fullmatch(r"\d{1,10}", pid)
        except Exception:  # noqa: BLE001
            return self.reply(400, {"error": "неверное имя фото"})
        path = os.path.join(ROOT, "out", name)
        if os.path.exists(path):
            os.remove(path)
        self.reply(200, {"ok": True})

    def save_own(self, pid, raw, to="main"):
        """to = "main" → новое главное фото out/<id>.own.webp; "gallery" → доп. фото out/<id>.gN.webp."""
        try:
            im = Image.open(io.BytesIO(raw))
            im.load()
        except Exception:  # noqa: BLE001
            return self.reply(400, {"error": "это не картинка (нужен JPG, PNG или WebP)"})
        os.makedirs(os.path.join(ROOT, "own"), exist_ok=True)
        ext = (im.format or "img").lower().replace("jpeg", "jpg")
        with open(os.path.join(ROOT, "own", f"{pid}-{int(time.time() * 1000)}.{ext}"), "wb") as f:
            f.write(raw)
        if to == "gallery":
            n = 1
            while os.path.exists(os.path.join(ROOT, "out", f"{pid}.g{n}.webp")):
                n += 1
            name = f"{pid}.g{n}.webp"
        elif to == "re":                    # замена главного фото у уже залитого товара
            name = f"{pid}.re.webp"
        else:
            name = f"{pid}.own.webp"
        to_square(im).save(os.path.join(ROOT, "out", name), quality=88, method=6)
        self.reply(200, {"src": f"out/{name}?t={int(time.time())}", "name": name, "size": list(im.size)})

    def crop(self):
        """{id, from: "src" | <вариант>, quad: [[x, y] × 4]} — углы по часовой от левого верхнего в пикселях
        исходной картинки (или box: [x, y, w, h]) → фото выравнивается в прямоугольник → out/<id>.own.webp."""
        try:
            req = json.loads(self.rfile.read(int(self.headers.get("Content-Length") or 0)))
            pid, src, to = str(req["id"]), req["from"], req.get("to", "own")
            assert to in ("own", "re")
            if "quad" in req:
                quad = [(float(x), float(y)) for x, y in req["quad"]]
                assert len(quad) == 4
            else:
                x, y, w, h = [int(v) for v in req["box"]]
                quad = [(x, y), (x + w, y), (x + w, y + h), (x, y + h)]
            assert re.fullmatch(r"\d{1,10}", pid)
            if src == "src":
                name = next(f for f in os.listdir(os.path.join(ROOT, "src")) if f.rsplit(".", 1)[0] == pid)
                path = os.path.join(ROOT, "src", name)
            else:
                assert src in VARIANTS + ("re",)
                path = os.path.join(ROOT, "out", f"{pid}.{src}.webp")
            im = ImageOps.exif_transpose(Image.open(path))
        except Exception:  # noqa: BLE001
            return self.reply(400, {"error": "не удалось прочитать фото или рамку"})
        try:
            flat = warp(im, quad)
        except ValueError as e:
            return self.reply(400, {"error": str(e)})
        to_square(flat).save(os.path.join(ROOT, "out", f"{pid}.{to}.webp"), quality=88, method=6)
        self.reply(200, {"src": f"out/{pid}.{to}.webp?t={int(time.time() * 1000)}", "size": list(flat.size)})

    def cut_bg(self):
        """{id, from: <вариант>} — поставить фото в очередь «Удалить фон» (вырезка по одному, см. cut_worker).
        Вариант «full» — это оригинал на белом, поэтому режем сам оригинал из src/ (больше пикселей)."""
        try:
            req = json.loads(self.rfile.read(int(self.headers.get("Content-Length") or 0)))
            pid, src = str(req["id"]), str(req["from"])
            assert re.fullmatch(r"\d{1,10}", pid) and src in VARIANTS + ("src",)
            if src in ("src", "full"):
                name = next(f for f in os.listdir(os.path.join(ROOT, "src")) if f.rsplit(".", 1)[0] == pid)
                path = os.path.join(ROOT, "src", name)
            else:
                path = os.path.join(ROOT, "out", f"{pid}.{src}.webp")
            assert os.path.exists(path)
        except Exception:  # noqa: BLE001
            return self.reply(400, {"error": "не нашёл фото для этого товара"})
        with CUT_CV:
            if CUT_STATE.get(pid, {}).get("state") not in ("queued", "working"):
                CUT_STATE[pid] = {"state": "queued"}
                CUT_QUEUE.append((pid, path))
                CUT_CV.notify()
        self.reply(200, cut_status())

    def re_prepare(self, pid, url):
        """Рабочая копия главного фото уже залитого товара: out/<id>.re.webp; если её нет — скачать с сайта."""
        path = os.path.join(ROOT, "out", f"{pid}.re.webp")
        if not os.path.exists(path):
            assert urlparse(url).netloc.endswith("casa24.md")
            raw = urllib.request.urlopen(urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0"}), timeout=30).read()
            to_square(Image.open(io.BytesIO(raw))).save(path, quality=88, method=6)
        return path

    def open_chrome(self):
        """{url} — открыть поиск Google в Google Chrome на этом компьютере (только адреса Google)."""
        try:
            url = str(json.loads(self.rfile.read(int(self.headers.get("Content-Length") or 0)))["url"])
            host = urlparse(url).netloc
            assert urlparse(url).scheme == "https" and (host == "google.com" or host.endswith(".google.com"))
        except Exception:  # noqa: BLE001
            return self.reply(400, {"error": "открываю только поиск Google"})
        chrome = find_chrome()
        if chrome and chrome_running():
            subprocess.Popen([chrome, url])   # Chrome уже открыт — новая вкладка в нём
        elif chrome:
            # Chrome не запущен — окно на правой половине экрана, страница проверки остаётся видна слева
            subprocess.Popen([chrome, "--new-window", "--window-position=960,0", "--window-size=960,1040", url])
        else:
            os.startfile(url)  # Chrome не найден — браузер по умолчанию
        self.reply(200, {"ok": True, "chrome": bool(chrome)})

    def re_edit(self):
        """{id, url, deg}: подготовить рабочую копию главного фото (и повернуть на deg°, если задан)."""
        try:
            req = json.loads(self.rfile.read(int(self.headers.get("Content-Length") or 0)))
            pid, deg = str(req["id"]), int(req.get("deg", 0))
            assert re.fullmatch(r"\d{1,10}", pid) and deg in (0, 90, -90, 180)
            path = self.re_prepare(pid, str(req.get("url", "")))
            if deg:
                Image.open(path).rotate(-deg, expand=True, fillcolor="white").save(path, quality=88, method=6)
        except Exception as e:  # noqa: BLE001
            return self.reply(400, {"error": f"не удалось: {e}"})
        self.reply(200, {"src": f"out/{pid}.re.webp?t={int(time.time() * 1000)}"})

    def do_POST(self):
        if not self.allowed() or (self.path == "/open-chrome" and not self.local()):
            return self.send_error(403, "Forbidden", "Откройте ссылку с ключом, которую прислал Claude.")
        if self.path.startswith("/upload"):
            return self.upload()
        if self.path == "/crop":
            return self.crop()
        if self.path == "/fetch":
            return self.fetch()
        if self.path == "/gallery-delete":
            return self.gallery_delete()
        if self.path == "/gallery-rotate":
            return self.gallery_rotate()
        if self.path == "/gallery-promote":
            return self.gallery_promote()
        if self.path == "/re-edit":
            return self.re_edit()
        if self.path == "/open-chrome":
            return self.open_chrome()
        if self.path == "/cut-bg":
            return self.cut_bg()
        if self.path == "/add":
            return self.add()
        if self.path != "/save":
            self.send_error(404)
            return
        body = self.rfile.read(int(self.headers.get("Content-Length") or 0))
        try:
            data = json.loads(body)
            assert isinstance(data, dict) and all(
                isinstance(v, dict) and v.get("action") in VARIANTS + ("search", "hide", "keep", "gallery", "replace") and isinstance(v.get("comment", ""), str)
                and v.get("rotate", 0) in (0, 90, 180, 270)
                and all(re.fullmatch(r"\d{1,10}\.g\d+\.webp", g) for g in v.get("gallery", []))
                for v in data.values())
        except Exception:  # noqa: BLE001
            self.send_error(400, "bad decisions")
            return
        # страница показывает только ещё не залитые товары — решения по остальным сохраняем как были
        path = os.path.join(ROOT, "decisions.json")
        merged = json.load(open(path, encoding="utf-8")) if os.path.exists(path) else {}
        merged.update(data)
        with open(path, "w", encoding="utf-8") as f:
            json.dump(merged, f, ensure_ascii=False, indent=1)
        self.send_response(200)
        self.send_header("Content-Type", "text/plain; charset=utf-8")
        self.end_headers()
        self.wfile.write(b"ok")


threading.Thread(target=cut_worker, daemon=True).start()
ThreadingHTTPServer(("127.0.0.1", PORT), partial(Handler, directory=ROOT)).serve_forever()
