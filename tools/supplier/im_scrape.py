"""Сбор карточек поставщика instrumentmarket.md (OpenCart) в JSON.
Запуск: python -I im_scrape.py <path категории, напр. 486_487> <файл.json>
"""
import html, json, re, sys, time, urllib.parse, urllib.request

BASE = 'https://instrumentmarket.md'


def get(url):
    url = urllib.parse.quote(url, safe=":/?&=%#")
    req = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0 (Casa24 catalog sync)'})
    return urllib.request.urlopen(req, timeout=30).read().decode('utf8', 'ignore')


def clean(s):
    return re.sub(r'\s+', ' ', html.unescape(re.sub(r'<[^>]+>', ' ', s))).strip()


def list_category(path):
    """Ссылки на товары категории (все страницы), с признаком out-of-stock из списка."""
    items, page = {}, 1
    while True:
        t = get(f'{BASE}/index.php?route=product/category&path={path}&limit=100&page={page}')
        found = 0
        for blk in re.split(r'<div class="product-grid-item', t)[1:]:
            m = re.search(r'<div class="name"><a href="([^"]+)"', blk)
            if not m:
                continue
            found += 1
            items[html.unescape(m.group(1))] = 'label-outofstock' in blk
        if not found or f'page={page + 1}' not in t:
            break
        page += 1
        time.sleep(0.5)
    return items


def parse_product(url):
    t = get(url)
    d = {'url': url}
    m = re.search(r'name="product_id" value="(\d+)"', t)
    d['id'] = m.group(1) if m else ''
    m = re.search(r'<h1[^>]*>(.*?)</h1>', t, re.S)
    d['name'] = clean(m.group(1)) if m else ''
    m = re.search(r'itemprop="price" content="([\d.]+)"', t)
    d['price'] = float(m.group(1)) if m else None
    d['in_stock'] = 'schema.org/InStock' in t and 'journal-stock instock' in t
    m = re.search(r'class="p-brand">[^<]*<a[^>]*>(.*?)</a>', t, re.S)
    d['brand'] = clean(m.group(1)) if m else ''
    m = re.search(r'itemprop="model">(.*?)</span>', t, re.S)
    d['model'] = clean(m.group(1)) if m else ''
    # старая (зачёркнутая) цена / акция
    d['special'] = bool(re.search(r'price-old|product-price-old|class="price-new"|special', t.split('<footer')[0][:200000])) and bool(re.search(r'price-old|product-price-old', t))
    imgs = re.findall(r'(?:data-largeimg|href)="(https://instrumentmarket\.md/image/(?:cache/)?[^"]+\.(?:jpg|jpeg|png|webp))"', t)
    d['images'] = list(dict.fromkeys(imgs))[:8]
    m = re.search(r'<div[^>]+id="tab-description"[^>]*>(.*?)</div>\s*(?:<div[^>]+id="tab-|</div>)', t, re.S)
    d['description_html'] = m.group(1).strip() if m else ''
    d['attrs'] = [[clean(a), clean(b)] for a, b in re.findall(r'<tr>\s*<td[^>]*>(.*?)</td>\s*<td[^>]*>(.*?)</td>', t, re.S)][:40]
    return d


if __name__ == '__main__':
    path, out = sys.argv[1], sys.argv[2]
    lst = list_category(path)
    res = []
    for u, oos in lst.items():
        p = parse_product(u)
        p['list_outofstock'] = oos
        res.append(p)
        time.sleep(0.7)
    json.dump(res, open(out, 'w', encoding='utf8'), ensure_ascii=False, indent=1)
    print(len(res), 'товаров')
