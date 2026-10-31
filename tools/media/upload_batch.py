"""Заливка утверждённых фото партии на casa24.md (WP REST + WooCommerce REST, доступ из .env).

Запуск: `python upload_batch.py <папка партии> [--only ID ...]` — берёт <партия>/decisions.json
(action "cut" | "full" | "ai" | "web" | "own" — заливать вариант out/<id>.<action>.webp, gallery — доп. фото после главного, повёрнутый на rotate°; "keep", "search" и "hide" (скрыть навсегда — hide_forever.py) — пропустить), загружает фото
в медиатеку (alt = название товара), ставит главным фото у RU-товара и его RO-перевода (WPML)
и метку casa24_photo_lock = 1 — обмен с 1С больше не меняет фото этих товаров
(wp-content/mu-plugins/casa24-photo-lock.php). Галерея сохраняется.
Каждая замена — строка в <партия>/journal.jsonl; уже залитые товары повторно не трогаются.

Старые фото остаются в медиатеке, пока их не удалить:
  `python upload_batch.py <папка партии> --purge-old` — удалить с хостинга прежние главные фото
  залитых товаров (только если фото больше не стоит ни у одного товара партии). Удаление
  безвозвратное; оригиналы остаются локально в <партия>/src/.
Откат: `python upload_batch.py <папка партии> --rollback [--only ID ...]` — вернуть прежние фото
и снять метку (до --purge-old); у дозаливок галереи (action "gallery") — убрать добавленные доп. фото;
у замены главного (action "replace", рабочая копия out/<id>.re.webp; mode "swap" — прежнее главное
вторым в доп. фото, "drop" — убрать с сайта) — вернуть прежнее.
"""
import base64
import html
import io
import json
import os
import re
import sys
import time
import urllib.error
import urllib.request

from PIL import Image

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
TR = dict(zip("абвгдеёжзийклмнопрстуфхцчшщъыьэюя",
              ["a", "b", "v", "g", "d", "e", "e", "zh", "z", "i", "y", "k", "l", "m", "n", "o", "p", "r", "s", "t",
               "u", "f", "h", "ts", "ch", "sh", "sch", "", "y", "", "e", "yu", "ya"]))
RO = str.maketrans("ăâîșşțţ", "aaisstt")
LOCK = "casa24_photo_lock"


def slug(name):
    s = "".join(TR.get(c, c) for c in name.lower()).translate(RO)
    return re.sub(r"[^a-z0-9]+", "-", s).strip("-")[:60] or "foto"


def load_env():
    env = {}
    for line in open(os.path.join(ROOT, ".env"), encoding="utf-8"):
        if "=" in line and not line.lstrip().startswith("#"):
            k, v = line.strip().split("=", 1)
            env[k] = v.strip().strip('"').strip("'")
    return env


ENV = load_env()
BASE = ENV["WP_URL"].rstrip("/")
H = {"Authorization": "Basic " + base64.b64encode(f'{ENV["WP_APP_USER"]}:{ENV["WP_APP_PASSWORD"]}'.encode()).decode(),
     "User-Agent": "casa24-media/1.0 (owner)"}


def call(method, path, data=None, headers=None):
    body = json.dumps(data).encode() if isinstance(data, (dict, list)) else data
    hdr = {**H, **({"Content-Type": "application/json"} if isinstance(data, (dict, list)) else {}), **(headers or {})}
    for i in range(3):
        try:
            with urllib.request.urlopen(urllib.request.Request(BASE + path, data=body, method=method, headers=hdr),
                                        timeout=120) as r:
                return json.load(r)
        except urllib.error.HTTPError:
            raise
        except Exception:  # noqa: BLE001 — сетевой сбой, повторить
            if i == 2:
                raise
            time.sleep(5)


def images_of(pid):
    return [i["id"] for i in call("GET", f"/wp-json/wc/v3/products/{pid}")["images"]]


def set_main(pid, media_id, lock, old, gallery=()):
    """Поставить media_id главным фото, за ним новые фото галереи, затем прежняя галерея old[1:]; метка замка."""
    new = [media_id] + list(gallery) + [i for i in old[1:] if i != media_id and i not in gallery]
    call("PUT", f"/wp-json/wc/v3/products/{pid}", {
        "images": [{"id": i} for i in new],
        "meta_data": [{"key": LOCK, "value": "1" if lock else ""}],
    })


def read_journal(jpath):
    return [json.loads(l) for l in open(jpath, encoding="utf-8")] if os.path.exists(jpath) else []


def write_journal(jpath, journal):
    with open(jpath, "w", encoding="utf-8") as f:
        f.writelines(json.dumps(e, ensure_ascii=False) + "\n" for e in journal)


def rollback(jpath, only):
    journal = read_journal(jpath)
    for e in journal:
        if e.get("rolled_back") or e.get("purged") or (only and e["id"] not in only):
            continue
        for pid, old in e["old"].items():
            if e.get("kind") == "replace":          # вернуть прежнее главное фото, замок оставить
                call("PUT", f"/wp-json/wc/v3/products/{pid}", {"images": [{"id": i} for i in old]})
                continue
            if e.get("kind") == "gallery_add":      # убрать только добавленные доп. фото, замок оставить
                cur = images_of(pid)
                call("PUT", f"/wp-json/wc/v3/products/{pid}", {"images": [{"id": i} for i in cur if i not in e["gallery"]]})
                continue
            call("PUT", f"/wp-json/wc/v3/products/{pid}", {
                "images": [{"id": i} for i in old], "meta_data": [{"key": LOCK, "value": ""}]})
        e["rolled_back"] = True
        print("откат", e["id"], e["old"])
    write_journal(jpath, journal)


def purge_old(jpath):
    journal = read_journal(jpath)
    live = [e for e in journal if not e.get("rolled_back") and e.get("kind") != "gallery_add"]
    latest = {}
    for e in live:                       # по порядку: последняя заливка/замена — текущее главное фото
        latest[e["id"]] = e["media_id"]
    in_use = set(latest.values())
    for e in live:
        if e.get("purged"):
            continue
        if e.get("mode") == "swap":          # прежнее главное оставлено в доп. фото — не удалять
            e["purged"] = True
            continue
        olds = {o[0] for o in e["old"].values() if o}
        for mid in olds - in_use:
            try:
                call("DELETE", f"/wp-json/wp/v2/media/{mid}?force=true")
                print("удалено старое фото", mid, "товар", e["id"])
            except urllib.error.HTTPError as err:
                if err.code != 404:
                    raise
        e["purged"] = True
    write_journal(jpath, journal)


def main():
    sys.stdout.reconfigure(encoding="utf-8")
    args = sys.argv[1:]
    d = args[0]
    only = set(args[args.index("--only") + 1:]) if "--only" in args else None
    jpath = os.path.join(d, "journal.jsonl")
    if "--rollback" in args:
        return rollback(jpath, only)
    if "--purge-old" in args:
        return purge_old(jpath)

    journal = read_journal(jpath)
    decisions = json.load(open(os.path.join(d, "decisions.json"), encoding="utf-8"))
    pick = {str(x["id"]): x for x in json.load(open(os.path.join(d, "pick.json"), encoding="utf-8"))}
    done = {e["id"] for e in journal if not e.get("rolled_back") and e.get("kind") != "gallery_add"}
    todo = [(k, v["action"], v.get("rotate", 0)) for k, v in decisions.items()
            if v["action"] not in ("keep", "search", "hide", "gallery", "replace") and k not in done and (not only or k in only)]
    print(f"к заливке: {len(todo)}")
    for n, (pid, variant, rotate) in enumerate(todo, 1):
        p = call("GET", f"/wp-json/wc/v3/products/{pid}")
        name = html.unescape(pick[pid]["name"])
        ids = {pid} | {str(v) for v in (p.get("translations") or {}).values()}
        fname = f"{slug(name)}-{pid}.webp"
        src = os.path.join(d, "out", f"{pid}.{variant}.webp")
        if rotate:
            # rotate на странице — по часовой стрелке, PIL поворачивает против
            buf = io.BytesIO()
            Image.open(src).rotate(-rotate, expand=True, fillcolor="white").save(buf, "WEBP", quality=88, method=6)
            data = buf.getvalue()
        else:
            data = open(src, "rb").read()
        media = call("POST", "/wp-json/wp/v2/media", data, {
            "Content-Type": "image/webp", "Content-Disposition": f'attachment; filename="{fname}"'})
        call("POST", f"/wp-json/wp/v2/media/{media['id']}", {"alt_text": name})
        gallery, gfiles = upload_gallery(d, pid, name, decisions[pid].get("gallery", []), set(), 2)
        # прежние фото — у всех версий до замены: WPML копирует новое фото RU в RO сразу при сохранении
        old = {t: images_of(t) for t in sorted(ids)}
        for t in sorted(ids):
            set_main(t, media["id"], True, old[t], gallery)
        e = {"id": pid, "variant": variant, "rotate": rotate, "media_id": media["id"], "gallery": gallery, "gallery_files": gfiles,
             "url": media["source_url"], "old": old,
             "at": time.strftime("%Y-%m-%d %H:%M:%S")}
        with open(jpath, "a", encoding="utf-8") as f:
            f.write(json.dumps(e, ensure_ascii=False) + "\n")
        print(f"{n}/{len(todo)} {pid} → {media['id']} {fname}" + (f" + галерея {gallery}" if gallery else "")
              + f"  товары: {', '.join(sorted(ids))}", flush=True)

    replace_main(d, jpath, decisions, pick, only)
    add_gallery(d, jpath, decisions, pick, only)


def replace_main(d, jpath, decisions, pick, only):
    """action "replace": у уже залитого товара заменить главное фото рабочей копией out/<id>.re.webp (RU + RO).
    Доп. фото остаются; после заливки рабочая копия переименовывается в re-<время>.webp."""
    for pid, v in decisions.items():
        src = os.path.join(d, "out", f"{pid}.re.webp")
        if v["action"] != "replace" or not os.path.exists(src) or (only and pid not in only):
            continue
        p = call("GET", f"/wp-json/wc/v3/products/{pid}")
        name = html.unescape(pick[pid]["name"])
        ids = {pid} | {str(x) for x in (p.get("translations") or {}).values()}
        stamp = time.strftime("%Y%m%d-%H%M%S")
        media = call("POST", "/wp-json/wp/v2/media", open(src, "rb").read(), {
            "Content-Type": "image/webp", "Content-Disposition": f'attachment; filename="{slug(name)}-{pid}-{stamp}.webp"'})
        call("POST", f"/wp-json/wp/v2/media/{media['id']}", {"alt_text": name})
        old = {t: images_of(t) for t in sorted(ids)}
        mode = v.get("mode", "drop")          # прежнее главное: "swap" — вторым в доп. фото, "drop" — убрать с сайта
        for t in sorted(ids):
            set_main(t, media["id"], True, old[t], old[t][:1] if mode == "swap" else ())
        e = {"id": pid, "kind": "replace", "mode": mode, "variant": "re", "media_id": media["id"], "url": media["source_url"],
             "old": old, "at": time.strftime("%Y-%m-%d %H:%M:%S")}
        with open(jpath, "a", encoding="utf-8") as f:
            f.write(json.dumps(e, ensure_ascii=False) + "\n")
        os.replace(src, os.path.join(d, "out", f"{pid}.re-{stamp}.webp"))
        print(f"{pid}: главное фото заменено → {media['id']}"
              + (" (прежнее — в доп. фото)" if mode == "swap" else " (прежнее убрано)") + f"  товары: {', '.join(sorted(ids))}", flush=True)


def upload_gallery(d, pid, name, files, skip, start):
    """Залить файлы out/<gN>.webp в медиатеку. Вернуть (id вложений, имена файлов)."""
    ids, names = [], []
    for k, g in enumerate([f for f in files if f not in skip], start):
        gpath = os.path.join(d, "out", g)
        if not os.path.exists(gpath):
            continue
        gm = call("POST", "/wp-json/wp/v2/media", open(gpath, "rb").read(), {
            "Content-Type": "image/webp", "Content-Disposition": f'attachment; filename="{slug(name)}-{pid}-{k}.webp"'})
        call("POST", f"/wp-json/wp/v2/media/{gm['id']}", {"alt_text": f"{name} — фото {k}"})
        ids.append(gm["id"])
        names.append(g)
    return ids, names


def add_gallery(d, jpath, decisions, pick, only):
    """action "gallery": к уже залитому товару добавить новые доп. фото в конец галереи (RU + RO), главное не трогать."""
    journal = read_journal(jpath)
    live = [e for e in journal if not e.get("rolled_back")]
    sent = {g for e in live for g in e.get("gallery_files", [])}
    todo = [k for k, v in decisions.items() if v["action"] in ("gallery", "replace") and (not only or k in only)
            and [g for g in v.get("gallery", []) if g not in sent]]
    if not todo:
        return
    print(f"дозалить доп. фото: {len(todo)} товар.")
    for pid in todo:
        p = call("GET", f"/wp-json/wc/v3/products/{pid}")
        name = html.unescape(pick[pid]["name"])
        ids = {pid} | {str(v) for v in (p.get("translations") or {}).values()}
        old = {t: images_of(t) for t in sorted(ids)}
        gallery, gfiles = upload_gallery(d, pid, name, decisions[pid]["gallery"], sent, len(old[pid]) + 1)
        for t in sorted(ids):
            cur = old[t]
            call("PUT", f"/wp-json/wc/v3/products/{t}", {
                "images": [{"id": i} for i in cur + [g for g in gallery if g not in cur]],
                "meta_data": [{"key": LOCK, "value": "1"}]})
        e = {"id": pid, "kind": "gallery_add", "gallery": gallery, "gallery_files": gfiles, "old": old,
             "at": time.strftime("%Y-%m-%d %H:%M:%S")}
        with open(jpath, "a", encoding="utf-8") as f:
            f.write(json.dumps(e, ensure_ascii=False) + "\n")
        print(f"{pid}: + доп. фото {gallery}  товары: {', '.join(sorted(ids))}", flush=True)


if __name__ == "__main__":
    main()
