"""Поиск фото товаров casa24.md с небелым фоном (только чтение публичных страниц).

Запуск: `python photo_bg_audit.py` — список товаров через Store API (RU), миниатюра главного фото,
оценка фона по полосам вдоль краёв кадра. Повторный запуск докачивает только недостающее.
Результаты (*.json, *.xlsx, thumbs/) пишутся рядом со скриптом и в git не попадают.
"""
import io
import json
import os
import sys
import time
import urllib.request
from concurrent.futures import ThreadPoolExecutor

import numpy as np
from PIL import Image

OUT = os.path.dirname(os.path.abspath(__file__))
BASE = "https://casa24.md"
UA = {"User-Agent": "casa24-photo-audit/1.0 (owner check)"}
THUMBS = os.path.join(OUT, "thumbs")
WORKERS = 2


def get(url, tries=4):
    for i in range(tries):
        try:
            with urllib.request.urlopen(urllib.request.Request(url, headers=UA), timeout=60) as r:
                return r.read(), dict(r.headers)
        except Exception as e:  # noqa: BLE001
            if getattr(e, "code", None) == 404:
                return None, {}
            time.sleep(3 * (i + 1))
    raise RuntimeError("failed: " + url)


def fetch_products():
    path = os.path.join(OUT, "products.json")
    if os.path.exists(path):
        return json.load(open(path, encoding="utf-8"))
    items, page, pages = [], 1, None
    while True:
        body, h = get(f"{BASE}/wp-json/wc/store/v1/products?per_page=100&page={page}")
        pages = pages or int(h.get("X-WP-TotalPages") or h.get("x-wp-totalpages") or 1)
        for p in json.loads(body):
            img = (p.get("images") or [None])[0]
            cats = [c["name"] for c in p.get("categories") or []]
            items.append({
                "id": p["id"], "sku": p.get("sku") or "", "name": p["name"], "url": p["permalink"],
                "in_stock": (p.get("is_in_stock")), "cat": ", ".join(cats),
                "img_id": img and img["id"], "src": img and img["src"], "thumb": img and img["thumbnail"],
            })
        print(f"page {page}/{pages}: {len(items)}", flush=True)
        if page >= pages:
            break
        page += 1
        time.sleep(0.3)
    json.dump(items, open(path, "w", encoding="utf-8"), ensure_ascii=False)
    return items


def analyse(im):
    """Фон по краям: доля «белых» пикселей на каждой из 4 сторон и средний цвет рамки."""
    a = np.asarray(im.convert("RGB")).astype(int)
    h, w, _ = a.shape
    b = max(2, round(min(h, w) * 0.04))
    sides = [a[:b], a[-b:], a[:, :b], a[:, -b:]]
    white = lambda s: ((s.min(-1) >= 235) & (s.max(-1) - s.min(-1) <= 18)).mean()
    fr = [float(white(s)) for s in sides]
    border = np.concatenate([s.reshape(-1, 3) for s in sides])
    mean = border.mean(0)
    n_white = sum(f >= 0.85 for f in fr)
    if n_white >= 3:
        verdict = "белый"
    elif mean.min() >= 205 and mean.max() - mean.min() <= 20:
        verdict = "светло-серый / не совсем белый"
    else:
        verdict = "не белый"
    return verdict, fr, [round(x) for x in mean]


def check(item):
    key = str(item["img_id"])
    cache = os.path.join(THUMBS, key + ".json")
    if os.path.exists(cache):
        return {**item, **json.load(open(cache, encoding="utf-8"))}
    try:
        body, _ = get(item["thumb"] or item["src"])
    except RuntimeError:
        return {**item, "verdict": "фото не скачалось (повторить)", "sides": [], "border_rgb": []}  # без кэша
    if body is None:
        res = {"verdict": "фото не открывается (404)", "sides": [], "border_rgb": []}
    else:
        try:
            im = Image.open(io.BytesIO(body))
            if im.mode in ("RGBA", "LA", "P"):
                im = im.convert("RGBA")
                bg = Image.new("RGBA", im.size, "white")
                im = Image.alpha_composite(bg, im)  # прозрачный фон на сайте выглядит белым
            v, fr, mean = analyse(im)
            res = {"verdict": v, "sides": [round(f, 2) for f in fr], "border_rgb": mean}
            im.convert("RGB").save(os.path.join(THUMBS, key + ".jpg"), quality=80)
        except Exception as e:  # noqa: BLE001
            res = {"verdict": f"ошибка чтения: {e}", "sides": [], "border_rgb": []}
    json.dump(res, open(cache, "w", encoding="utf-8"), ensure_ascii=False)
    return {**item, **res}


def main():
    sys.stdout.reconfigure(encoding="utf-8")
    os.makedirs(THUMBS, exist_ok=True)
    items = fetch_products()
    with_img = [p for p in items if p["img_id"]]
    # одно фото может стоять у нескольких товаров — проверяем каждое вложение один раз
    uniq = {p["img_id"]: p for p in with_img}
    print(f"товаров: {len(items)}, с фото: {len(with_img)}, уникальных фото: {len(uniq)}", flush=True)
    done = {}
    with ThreadPoolExecutor(WORKERS) as ex:
        for i, r in enumerate(ex.map(check, uniq.values()), 1):
            done[r["img_id"]] = r
            if i % 500 == 0:
                print(f"проверено {i}/{len(uniq)}", flush=True)
    rows = [{**p, **{k: done[p["img_id"]][k] for k in ("verdict", "sides", "border_rgb")}} if p["img_id"]
            else {**p, "verdict": "нет фото", "sides": [], "border_rgb": []} for p in items]
    json.dump(rows, open(os.path.join(OUT, "audit.json"), "w", encoding="utf-8"), ensure_ascii=False)
    from collections import Counter
    print(Counter(r["verdict"] for r in rows).most_common())


if __name__ == "__main__":
    main()
