"""Сверка цен RU<->RO на casa24.md (только чтение публичных страниц).

Запуск: `python fetch.py ru`, `python fetch.py ro`, затем `python compare.py`.
RU/RO: Store API (/wp-json/wc/store/v1/products), RO — с `&lang=ro`.
`ru_html` — запасной вариант через HTML-листинг /shop/ (только товары в наличии).
Пара RU<->RO (в compare.py) — по имени файла главного фото (в нём GUID из 1С), запасной ключ — SKU.
Результаты (*.json, *.csv) пишутся рядом со скриптом и в git не попадают.
"""
import html
import json
import os
import re
import sys
import time
import urllib.request

OUT = os.path.dirname(os.path.abspath(__file__))
BASE = "https://casa24.md"
UA = {"User-Agent": "casa24-price-audit/1.0 (owner check)"}
DELAY = 0.4


def get(url, tries=4):
    for i in range(tries):
        try:
            req = urllib.request.Request(url, headers=UA)
            with urllib.request.urlopen(req, timeout=60) as r:
                return r.read().decode("utf-8", "replace"), dict(r.headers)
        except Exception as e:  # noqa: BLE001
            if getattr(e, "code", None) == 404:
                return None, {}
            time.sleep(3 * (i + 1))
    raise RuntimeError("failed: " + url)


def img_key(src):
    if not src:
        return ""
    name = src.rsplit("/", 1)[-1].split("?")[0]
    name = re.sub(r"\.(jpe?g|png|webp|gif)$", "", name, flags=re.I)
    name = re.sub(r"-\d+x\d+$", "", name)
    name = re.sub(r"-scaled$", "", name)
    return name.lower()


def money(s):
    s = s.replace("&nbsp;", " ").replace("\xa0", " ")
    m = re.search(r"([\d.]+,\d{2})", s)
    return float(m.group(1).replace(".", "").replace(",", ".")) if m else None


def fetch_api(lang):
    # Без lang (и без cookie wp-wpml_current_language) Store API отдаёт RU — основной язык.
    q = "&lang=ro" if lang == "ro" else ""
    items, page, pages = [], 1, None
    while True:
        body, h = get(f"{BASE}/wp-json/wc/store/v1/products?per_page=100&page={page}{q}")
        pages = pages or int(h.get("X-WP-TotalPages") or h.get("x-wp-totalpages") or 1)
        for p in json.loads(body):
            pr = p.get("prices") or {}
            unit = 10 ** int(pr.get("currency_minor_unit", 2))
            rng = pr.get("price_range")
            price = int(rng["min_amount"]) / unit if rng else (int(pr["price"]) / unit if pr.get("price") else None)
            items.append({
                "id": p["id"], "name": html.unescape(p["name"]), "sku": p.get("sku") or "",
                "price": price, "type": p.get("type"), "url": p["permalink"],
                "img": img_key(p["images"][0]["src"]) if p.get("images") else "",
            })
        print(f"{lang} {page}/{pages} ({len(items)})", flush=True)
        if page >= pages:
            return items
        page += 1
        time.sleep(DELAY)


LI = re.compile(r'<li class="product type-product post-(\d+)[^"]*">(.*?)</li>\s*(?=<li class="product|</ul>)', re.S)


def fetch_ru():
    items, page = [], 1
    while True:
        body, _ = get(f"{BASE}/shop/page/{page}/" if page > 1 else f"{BASE}/shop/")
        if body is None:
            return items
        found = 0
        for chunk in body.split('<li class="product type-product post-')[1:]:
            pid, block = re.match(r"(\d+)(.*)", chunk, re.S).groups()
            block = block[:12000]
            found += 1
            name = re.search(r'aria-label="([^"]*)"', block)
            img = re.search(r'<img[^>]+src="([^"]+)"', block)
            sku = re.search(r'data-product_sku="([^"]*)"', block)
            url = re.search(r'href="(https://casa24\.md/product/[^"]+)"', block)
            pr = re.search(r'<span class="price">(.*?)</span>\s*(?:</div>|<div)', block, re.S)
            ptxt = pr.group(1) if pr else ""
            ins = re.search(r"<ins[^>]*>(.*?)</ins>", ptxt, re.S)
            items.append({
                "id": int(pid), "name": html.unescape(name.group(1)) if name else "",
                "sku": sku.group(1) if sku else "", "price": money(ins.group(1) if ins else ptxt),
                "url": url.group(1) if url else "", "img": img_key(img.group(1)) if img else "",
                "variable": "product-type-variable" in block,
            })
        print(f"RU page {page} (+{found}, {len(items)})", flush=True)
        if found == 0:
            return items
        page += 1
        time.sleep(DELAY)


if __name__ == "__main__":
    which = sys.argv[1]
    data = fetch_ru() if which == "ru_html" else fetch_api(which)
    with open(os.path.join(OUT, f"{which}.json"), "w", encoding="utf-8") as f:
        json.dump(data, f, ensure_ascii=False)
    print("DONE", which, len(data))
