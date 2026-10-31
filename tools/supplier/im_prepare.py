"""Готовит JSON для im_import.php из выгрузки im_scrape.py.
python -I im_prepare.py <scrape.json> <out.json> <ID раздела RU> [ID_пропускаемых,…]
Названия приводятся к виду «Бензопила Бренд Модель» вручную через TITLES (поставщик пишет КАПСОМ и с повторами).
"""
import html, json, re, sys

SKIP_IMG = ('Logo',)  # логотип поставщика стоит первым в галерее


def desc(h):
    t = re.sub(r'</p>|<br ?/?>|</li>|</div>', '\n', h)
    lines = [html.unescape(re.sub(r'<[^>]+>', '', x)).replace('\xa0', ' ').strip() for x in t.split('\n')]
    lines = [re.sub(r'\s+', ' ', x) for x in lines if x.strip()]
    out, ul = [], []
    def flush():
        nonlocal ul
        if ul:
            out.append('<ul>' + ''.join(ul) + '</ul>')
            ul = []
    for x in lines:
        if x.endswith(':') and len(x) < 40:
            flush()
            out.append('<p><strong>%s</strong></p>' % html.escape(x))
        elif ' : ' in x or re.match(r'^[^:]{2,40}\s:\s', x):
            k, v = re.split(r'\s*:\s*', x, 1)
            ul.append('<li><strong>%s:</strong> %s</li>' % (html.escape(k.strip()), html.escape(v.strip())))
        else:
            ul.append('<li>%s</li>' % html.escape(x))
    flush()
    return ''.join(out)


TITLES = {
    '2762': 'Бензопила Rotor CS5200A',
    '3612': 'Бензопила Elefant EM-5.5',
    '2826': 'Бензопила цепная Eltos E-5200',
    '2766': 'Бензопила Elefant CS2500',
    '2659': 'Бензопила Elefant CS5800E',
    '1795': 'Бензопила Husqvarna Mark 120',
    '3611': 'Бензопила Partner Pro PP-450L',
    '2763': 'Бензопила Rotor CS5800A, 2 шины и 2 цепи',
    '3752': 'Бензопила Rotor RCS5200',
}
# фото из чужой папки товара (у Elefant CS5800E первое фото относится к другой модели)
DROP_IMG = {'2659': ['E_5800W']}

if __name__ == '__main__':
    src, out, term = sys.argv[1], sys.argv[2], int(sys.argv[3])
    skip = set(sys.argv[4].split(',')) if len(sys.argv) > 4 else set()
    res = []
    for p in json.load(open(src, encoding='utf8')):
        if p['id'] in skip or not p['in_stock'] or p['special'] or p['id'] not in TITLES:
            continue
        imgs = [u for u in p['images'] if not any(s in u for s in SKIP_IMG) and not any(s in u for s in DROP_IMG.get(p['id'], []))]
        res.append({
            'supplier': 'instrumentmarket', 'supplier_id': p['id'], 'supplier_url': p['url'],
            'sku': 'IM-' + p['model'], 'title': TITLES[p['id']], 'brand': p['brand'],
            'price': p['price'], 'content': desc(p['description_html']), 'images': imgs, 'cat': term,
        })
    json.dump(res, open(out, 'w', encoding='utf8'), ensure_ascii=False, indent=1)
    print(len(res), 'к загрузке')
