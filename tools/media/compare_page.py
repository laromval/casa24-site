"""Редактор фото товаров casa24.md (страница проверки партии): оригинал, выбранный вариант и запасной, галочка «заливать на сайт».

Запуск: `python compare_page.py <папка партии>` — в папке ждёт pick.json, src/ и out/ (с report.json);
пишет index.html рядом. На сайт уходят только фото с галочкой, у остальных остаётся старое фото.
Клик по маленькому варианту — поменять местами. Кнопка «Сохранить решения» пишет через review_server.py
<папка партии>/decisions.json {id: {action: "cut" | "full" | "keep", variant, comment}} — по нему идёт
загрузка на сайт, а comment объясняет, почему фото не утверждено
(без сервера файл просто скачивается браузером).
Кнопка «Найти в Google» сразу открывает Google Lens по текущему фото товара с сайта и его названию вместе; выбранную картинку перетаскивают
мышью на карточку или вставляют её ссылку в поле «Ссылка на фото» — review_server.py скачивает её (POST /fetch)
и ставит на белый квадрат как вариант «own». «Поручить Claude» помечает товар action "search": Claude ищет его фото в Google Картинках
и Google Lens (по названию и по текущему фото), выбирает похожие и готовит их отдельной партией на проверку;
рядом — ссылки, чтобы открыть тот же поиск самому.
«Скачать» — новое фото или оригинал с сайта. «Кадрировать» — рамка с 4 углами, как Perspective Crop в Photoshop: углы тянут к углам товара,
фото выравнивается в прямоугольник, результат на белом квадрате становится вариантом «own» (POST /crop). «Загрузить фото» — загрузить найденный вручную файл
(review_server.py делает из него белый квадрат out/<id>.own.webp, вариант «own» становится основным).
«Удалить фон» — по выбору пользователя вырезать товар моделью (POST /cut-bg), результат — вариант «own».
Клик по фото — оно открывается в оригинальном размере, повторный клик закрывает.
Кнопки ⟲ ⟳ поворачивают новое фото на 90° (rotate в решении, поворот делает upload_batch.py).
Товары без фото (no_photo в report.json, make_batch.py ставит их первыми): слева и справа заглушка «нет фото»,
«Найти в Google» ищет по названию в Картинках, утвердить можно только после того, как добавлено своё фото.
Кнопка с фотоаппаратом — своя камера страницы (getUserMedia, нужен https — туннель): видоискатель с квадратной рамкой,
«снять» → «Переснять» / «Готово»; квадратный кадр уходит на сервер как своё фото (POST /upload): первый — новым главным,
следующие — в доп. фото. Родная камера телефона (input capture) — запасной пункт «Родная камера» в меню «⋯»
и сама включается, если браузер не даёт камеру странице.
Страница работает и с телефона через туннель (review_server.py с ключом, см. docs/setup-shop-pc-photo-phone.md):
там «Найти в Google» открывается соседней вкладкой телефона, а не в Chrome на компьютере.
Товары, уже залитые по journal.jsonl любой партии, на странице не показываются.
Отметки держатся в localStorage браузера, чтобы не потерять их при перезагрузке страницы.
"""
import glob
import html
import json
import os
import re
import subprocess
import sys
import tempfile
import urllib.parse

d = sys.argv[1]
batch = os.path.basename(os.path.abspath(d))
pick = {str(x["id"]): x for x in json.load(open(os.path.join(d, "pick.json"), encoding="utf-8"))}
report = json.load(open(os.path.join(d, "out", "report.json"), encoding="utf-8"))
# залитые товары — по журналам всех партий в tools/media (товар мог уйти на сайт из другой партии)
uploaded = set()
here = {}            # товары, залитые из ЭТОЙ партии: id → запись журнала (для раздела «Уже на сайте»)
on_site_gallery = set()   # файлы галереи, уже залитые на сайт
jhere = os.path.join(d, "journal.jsonl")
if os.path.exists(jhere):
    for e in map(json.loads, open(jhere, encoding="utf-8")):
        if e.get("rolled_back"):
            continue
        on_site_gallery |= set(e.get("gallery_files", []))
        if e.get("kind") != "gallery_add":
            here[e["id"]] = e
for jpath in glob.glob(os.path.join(os.path.dirname(os.path.abspath(d)), "*", "journal.jsonl")):
    uploaded |= {e["id"] for e in map(json.loads, open(jpath, encoding="utf-8"))
                 if not e.get("rolled_back") and e.get("kind") != "gallery_add"}
NOPHOTO = ("data:image/svg+xml," + urllib.parse.quote(
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200"><rect width="200" height="200" fill="#f5f5f5"/>'
    '<g fill="none" stroke="#b0bec5" stroke-width="6" stroke-linejoin="round"><path d="M40 70h28l10-16h44l10 16h28v76H40z"/>'
    '<circle cx="100" cy="106" r="22"/></g><text x="100" y="178" font-family="sans-serif" font-size="18" fill="#90a4ae" '
    'text-anchor="middle">нет фото</text></svg>'))
CAMERA = ('<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" '
          'stroke-linejoin="round" aria-hidden="true"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>'
          '<circle cx="12" cy="13" r="4"/></svg>')
SHOT = f'<button type="button" class="shb" title="Сфотографировать товар — квадратный кадр" aria-label="Сфотографировать">{CAMERA}</button>'
CAMN = '<label class="camn">Родная камера<input type="file" accept="image/*" capture="environment" hidden></label>'
order = sorted((k for k in report if k not in uploaded), key=lambda k: (not report[k]["flags"], report[k]["choice"], k))



def gallery_html(pid, done=False):
    files = sorted(glob.glob(os.path.join(d, "out", f"{pid}.g*.webp")), key=lambda x: int(re.search(r"\.g(\d+)\.", x).group(1)))
    imgs = "".join(
        f'<span class="gi" data-name="{os.path.basename(g)}"><img src="out/{os.path.basename(g)}" loading="lazy"><b title="убрать из галереи">✕</b>'
        f'<i><u data-main="1" title="сделать главным фото">★</u><u data-r="-90" title="повернуть против часовой">⟲</u><u data-r="90" title="повернуть по часовой">⟳</u><u data-dl="1" title="скачать">⤓</u></i></span>'
        for g in files if os.path.basename(g) not in on_site_gallery)
    to = ('' if done else
          '<label class="gto" title="ссылка, перетаскивание и Ctrl+V пойдут в галерею, а не в новое фото">'
          '<input type="checkbox"> ссылку / перетаскивание — в галерею</label>')
    return (f'<div class="gal"><span class="gl">{"Новые доп. фото:" if done else "Галерея (доп. фото):"}</span><span class="gis">{imgs}</span>'
            f'<label class="up gup">+ <span class="lg">добавить в галерею</span><span class="sm">доп. фото</span><input type="file" accept="image/*" multiple hidden></label>{to}</div>')


cards = []
for pid in order:
    r, p = report[pid], pick.get(pid, {})
    nophoto = bool(r.get("no_photo"))
    fl = "".join(f'<span class="fl">{html.escape(f)}</span>' for f in r["flags"])
    main = r["choice"]
    alt = r.get("alt") or ("full" if main == "cut" else "cut")
    if nophoto or not os.path.exists(os.path.join(d, "out", f"{pid}.{alt}.webp")):
        alt = ""
    if os.path.exists(os.path.join(d, "out", f"{pid}.own.webp")):   # своё фото, загруженное раньше
        main, alt = "own", ("" if nophoto else main)
    alt_img = f'<img class="alt" src="out/{pid}.{alt}.webp" title="запасной вариант — клик, чтобы выбрать">' if alt else ""
    q = urllib.parse.quote(html.unescape(p.get("name", "")))
    lens = urllib.parse.quote(p.get("src", ""), safe="")
    # Google Lens: наше фото + название товара одновременно (multisearch) — находит точнее, чем одно из двух
    # у товара без фото искать можно только по названию — в Google Картинках
    gurl = f"https://www.google.com/search?tbm=isch&q={q}" if nophoto else f"https://lens.google.com/uploadbyurl?url={lens}&q={q}"
    gtitle = "поиск картинок по названию товара" if nophoto else "поиск по нашему фото и названию товара сразу"
    find = (f'<span class="find"><a class="gbtn" href="{gurl}" target="_blank" title="{gtitle}"><span class="lg">Найти в </span>Google</a>'
            f'<button type="button" class="fbtn" title="я сам поищу и покажу найденное отдельной страницей"><span class="lg">Поручить </span>Claude</button>'
            f'{SHOT}</span>')
    url = (f'<div class="url"><input type="url" placeholder="Ссылка на фото — или перетащите картинку из Google прямо на карточку">'
            f'<button type="button" class="ubtn">Взять</button></div>')
    tools = (f'<div class="tools"><div class="menu"><button type="button" class="mbtn" title="скачать / загрузить фото">⋯</button>'
             f'<div class="mlist" hidden><button type="button" class="dl" data-what="old">Скачать оригинал</button>'
             f'<button type="button" class="dl" data-what="new">Скачать новое</button>'
             f'<label class="up">Загрузить фото<input type="file" accept="image/*" hidden></label>{CAMN}</div></div>'
             f'<button type="button" class="cropb">Кадрировать</button>'
             f'<button type="button" class="cutb" title="вырезать товар и поставить на белый; можно нажать на нескольких — пойдут по очереди">Удалить фон</button>{find}'
             f'<div class="rot"><button type="button" data-r="-90" title="повернуть против часовой">⟲</button><button type="button" data-r="90" title="повернуть по часовой">⟳</button></div></div>')
    gallery = gallery_html(pid)
    old_img = NOPHOTO if nophoto else f"src/{html.escape(r['src'])}"
    new_img = NOPHOTO if main == "none" else f"out/{pid}.{main}.webp"
    old_cap = "на сайте фото нет — товар скрыт" if nophoto else "сейчас на сайте"
    cards.append(f'''<div class="card{" nophoto" if nophoto else ""}" data-id="{pid}" data-choice="{main}" data-alt="{alt}" data-default="{main}">
<div class="pair"><figure><img src="{old_img}" loading="lazy"><figcaption>{old_cap}</figcaption></figure>
<figure><img class="main" src="{new_img}" loading="lazy"><figcaption>новое</figcaption></figure></div>
<div class="row">{tools}</div>
{url}
{gallery}
<textarea class="note" rows="1" placeholder="Почему не утверждено? (например: обрезана ручка, лучше кадр целиком, нужен другой ракурс)"></textarea>
<div class="meta">{alt_img}<a href="{html.escape(p.get("url", ""))}" target="_blank">{html.escape(p.get("name", pid))}</a>
<div class="cat">{html.escape((p.get("cat") or "").split(", ")[0])} · ID {pid} · вариант <b class="ch">{main}</b></div>{fl}</div>
<label class="ok"><input type="checkbox"> Утвердить — заливать на сайт</label></div>''')

# текущие фото залитых товаров прямо с сайта — одним запросом (видно, сколько доп. фото уже стоит)
site_imgs = {}
if here:
    try:
        import urllib.request
        q_ids = ",".join(sorted(here))
        req = urllib.request.Request(f"https://casa24.md/wp-json/wc/store/v1/products?include={q_ids}&per_page=100",
                                     headers={"User-Agent": "casa24-media/1.0 (owner)"})
        for pr in json.load(urllib.request.urlopen(req, timeout=20)):
            site_imgs[str(pr["id"])] = [(i.get("thumbnail") or i["src"], i["src"]) for i in pr.get("images", [])]
    except Exception:  # noqa: BLE001 — сайт недоступен: страница всё равно открывается
        site_imgs = {}

done_cards = []
for pid, e in sorted(here.items()):
    p = pick.get(pid, {})
    n_site = sum(len(x.get("gallery", [])) for x in map(json.loads, open(jhere, encoding="utf-8"))
                 if x["id"] == pid and not x.get("rolled_back"))
    q = urllib.parse.quote(html.unescape(p.get("name", "")))
    lens = urllib.parse.quote(e.get("url", ""), safe="")
    has_re = os.path.exists(os.path.join(d, "out", f"{pid}.re.webp"))
    done_cards.append(f'''<div class="card dcard{" on" if has_re else ""}" data-id="{pid}" data-site="{html.escape(e.get("url", ""))}">
<div class="pair one"><figure><img class="main" src="{f"out/{pid}.re.webp" if has_re else html.escape(e.get("url") or f"out/{pid}.{e['variant']}.webp")}" loading="lazy">
<figcaption class="cap">{"изменённое главное фото — ещё НЕ на сайте" if has_re else "главное фото на сайте"}{f" · доп. фото на сайте: {n_site}" if n_site else ""}</figcaption></figure></div>
<div class="row"><div class="lft"><div class="menu"><button type="button" class="mbtn" title="действия с главным фото">⋯</button>
<div class="mlist" hidden><button type="button" class="dl1">Скачать главное</button>
<label class="reup">Загрузить фото<input type="file" accept="image/*" hidden></label>{CAMN}<button type="button" class="recrop">Кадрировать</button></div></div>
<span class="find"><a class="gbtn" href="https://lens.google.com/uploadbyurl?url={lens}&q={q}" target="_blank" title="поиск по фото и названию товара">Найти в Google</a>{SHOT}</span></div>
<div class="rot"><button type="button" class="rer" data-r="-90" title="повернуть против часовой">⟲</button><button type="button" class="rer" data-r="90" title="повернуть по часовой">⟳</button></div></div>
<div class="url"><input type="url" placeholder="Ссылка на фото — или перетащите картинку прямо на карточку"><button type="button" class="ubtn">Взять</button></div>
{(f'<div class="site"><span class="gl">Доп. фото на сайте ({len(site_imgs[pid]) - 1}):</span>'
   + f'<span class="oldm" hidden><img src="{html.escape(site_imgs[pid][0][1])}" title="прежнее главное"><em></em></span>'
   + "".join(f'<img src="{html.escape(big)}" loading="lazy" title="уже на сайте">' for _, big in site_imgs[pid][1:])
   + ('<span class="gl">нет — можно искать</span>' if len(site_imgs[pid]) < 2 else "") + '</div>') if pid in site_imgs else ""}
{gallery_html(pid, done=True)}
<div class="meta"><a href="{html.escape(p.get("url", ""))}" target="_blank">{html.escape(p.get("name", pid))}</a>
<div class="cat">{html.escape((p.get("cat") or "").split(", ")[0])} · ID {pid}</div></div>
<label class="ok rok"{"" if has_re else " hidden"}><input type="checkbox"{" checked" if has_re else ""}> Заменить главное фото на сайте</label>
<div class="rmode"{"" if has_re else " hidden"}>Прежнее главное фото: <select><option value="drop">убрать с сайта</option><option value="swap">оставить в доп. фото (вторым)</option></select></div>
<label class="ok gok"><input type="checkbox"> Добавить новые доп. фото на сайт</label></div>''')

page = f'''<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Редактор фото — Casa24</title><style>
body{{font:14px system-ui,sans-serif;margin:16px;background:#eceff1;color:#222}}
h1{{font-size:20px}} .grid{{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:12px}}
.top{{display:flex;align-items:center;gap:12px;margin:0 0 10px}} .top b{{font-size:16px}} .helpb{{padding:6px 14px;border:1px solid #b0bec5;border-radius:6px;background:#fff;font-weight:600}} .helpb.act{{background:#263238;color:#fff}}
.card{{background:#fff;border-radius:8px;padding:8px;border:3px solid transparent}}
.card.on{{border-color:#2e7d32;background:#f1f8e9}}
.pair{{display:grid;grid-template-columns:1fr 1fr;gap:6px}} .pair.one{{grid-template-columns:1fr}} .pair.one img{{max-height:260px}}
h2{{font-size:17px;margin:28px 0 4px}} summary{{cursor:pointer;font-weight:600;margin:6px 0 10px}} .dcard.on{{border-color:#2e7d32;background:#f1f8e9}} .dcard .ok{{margin-top:6px}} .dcard .ok[hidden]{{display:none}} .dcard .cap{{font-weight:600}} figure{{margin:0}}
#zoom{{position:fixed;inset:0;background:rgba(0,0,0,.85);overflow:auto;z-index:20;cursor:zoom-out;text-align:center}} #zoom[hidden]{{display:none}}
#zoom img{{max-width:none;max-height:none;margin:40px auto 16px;display:block;background:#fff}} #zoom div{{color:#fff;position:sticky;top:0;padding:8px;background:rgba(0,0,0,.6)}}
.pair img{{cursor:zoom-in;width:100%;aspect-ratio:1;object-fit:contain;background:#fafafa;border:1px solid #ddd}}
figcaption{{font-size:11px;color:#888;text-align:center}}
.ok{{display:flex;align-items:center;gap:8px;margin:8px 0 4px;padding:8px;background:#eceff1;border-radius:6px;font-weight:600;cursor:pointer;user-select:none}}
.ok input{{width:22px;height:22px;accent-color:#2e7d32}} .card.on .ok{{background:#c8e6c9;color:#1b5e20}}
.note{{width:100%;box-sizing:border-box;font:inherit;font-size:13px;padding:6px;border:1px solid #cfd8dc;border-radius:6px;margin-bottom:6px;resize:vertical}}
.card.on .note{{display:none}}
.row{{display:flex;justify-content:space-between;align-items:center;gap:6px;margin-top:6px}} .row .rot{{margin-top:0;flex:none}} .tools{{display:flex;flex-wrap:wrap;gap:6px}} .tools button,.up{{font-size:12px;padding:4px 10px;border:1px solid #b0bec5;border-radius:4px;background:#fff;cursor:pointer}}
#cropm{{position:fixed;inset:0;background:rgba(0,0,0,.65);display:flex;align-items:center;justify-content:center;z-index:10}} #cropm[hidden]{{display:none}}
.cbox{{background:#fff;padding:12px;border-radius:8px;max-width:96vw}} .ctop,.cbot{{display:flex;flex-wrap:wrap;gap:8px;align-items:center}}
.ctop button.act{{background:#263238;color:#fff}} .cwrap{{position:relative;display:inline-block;user-select:none;margin:8px 0;touch-action:none}}
#cimg{{max-width:90vw;max-height:70vh;display:block;-webkit-user-drag:none}} #cquad{{position:absolute;inset:0;width:100%;height:100%;overflow:visible}} #cquad polygon{{fill:rgba(242,140,40,.12);stroke:#f28c28;stroke-width:2;stroke-dasharray:6 4}} #cquad .hd{{cursor:crosshair;touch-action:none}} #cquad .hd circle{{fill:transparent}} #cquad .xo{{stroke:#fff;stroke-width:5}} #cquad .xi{{stroke:#f28c28;stroke-width:2}} #cquad .hd:hover .xi{{stroke:#d32f2f}}
#cscroll{{max-width:calc(90vw + 28px);max-height:calc(70vh + 28px);overflow:auto;padding:14px;box-sizing:border-box}} #cscroll .cwrap{{margin:0}}
.up:hover,.tools button:hover{{border-color:#f28c28}} .up.busy{{opacity:.5;pointer-events:none}}
.find{{display:flex;gap:10px;align-items:center;margin:8px 0 0;font-size:12px}} .fbtn{{padding:3px 8px;font-size:12px}}
.gbtn{{background:#1a73e8;color:#fff!important;text-decoration:none;padding:5px 12px;border-radius:4px;font-weight:600}}
.url{{display:flex;gap:6px;margin-top:6px}} .url input{{flex:1;font:inherit;font-size:12px;padding:4px 6px;border:1px solid #cfd8dc;border-radius:4px}} .url button{{font-size:12px}}
.gal{{display:flex;flex-wrap:wrap;gap:6px;align-items:center;margin-top:6px;font-size:12px}} .gl{{color:#555}} .gis{{display:contents}}
.gi{{position:relative;display:inline-block}} .gi img{{width:56px;height:56px;object-fit:contain;border:1px solid #ddd;background:#fff;cursor:zoom-in}}
.gi{{margin-bottom:16px}} .gi img{{width:64px!important;height:64px!important}} .gi i{{position:absolute;left:0;right:0;bottom:-17px;display:flex;justify-content:space-between;font-style:normal}}
.gi u{{text-decoration:none;cursor:pointer;font-size:13px;line-height:15px;padding:0 3px;border-radius:3px}} .gi u:hover{{background:#f28c28;color:#fff}}
.gi b{{position:absolute;top:-6px;right:-6px;background:#d32f2f;color:#fff;border-radius:50%;width:16px;height:16px;line-height:16px;text-align:center;font-size:10px;cursor:pointer}}
.gto{{display:flex;align-items:center;gap:4px;color:#555}} .card.on:not(.dcard) .gal .gto{{display:none}}
.card.drop{{outline:3px dashed #1a73e8;outline-offset:-6px}} .card.target{{box-shadow:0 0 0 2px #f28c28}} .card.on:not(.dcard) .url{{display:none}}
.shb{{display:inline-flex;align-items:center;justify-content:center;padding:3px 8px!important;border:1px solid #b0bec5;border-radius:4px;background:#fff;color:#455a64;cursor:pointer}}
#cam{{position:fixed;inset:0;background:#000;z-index:30}} #cam[hidden]{{display:none}}
#cam video{{position:absolute;inset:0;width:100%;height:100%;object-fit:contain}} #cam .cprev{{position:absolute;object-fit:cover}}
#cam .cfr{{position:absolute;border:2px solid #fff;box-shadow:0 0 0 100vmax rgba(0,0,0,.6);pointer-events:none}}
#cam .cmsg{{position:absolute;top:12px;left:12px;right:12px;color:#fff;text-align:center;font-size:15px}}
#cam .cbar{{position:absolute;left:0;right:0;bottom:0;padding:16px 16px calc(20px + env(safe-area-inset-bottom));display:grid;grid-template-columns:1fr auto 1fr;align-items:center;gap:12px}}
#cam .cx,#cam .cre{{grid-column:1;justify-self:start}} #cam .csnap{{grid-column:2}} #cam .cok{{grid-column:3;justify-self:end}}
#cam .csnap{{width:76px;height:76px;border-radius:50%;border:6px solid #fff;background:#e65100;padding:0}} #cam .csnap:active{{transform:scale(.92)}}
#cam .cx,#cam .cre,#cam .cok{{font-size:17px;padding:12px 18px;border-radius:8px;border:0}} #cam .cx{{background:rgba(255,255,255,.25);color:#fff}}
#cam .cre{{background:#fff;color:#222}} #cam .cok{{background:#2e7d32;color:#fff;font-weight:600}} #cam [hidden]{{display:none}}
.shb:hover{{border-color:#f28c28;color:#e65100}} .shb.busy{{opacity:.5;pointer-events:none}}
.sm{{display:none}}
@media (max-width:700px){{body{{margin:6px}} .grid{{grid-template-columns:1fr;gap:8px}} .help p{{font-size:13px}}
  html{{scroll-snap-type:y proximity}} .top{{scroll-snap-align:start}} .card{{scroll-snap-align:start;scroll-margin-top:4px;display:flex;flex-direction:column;padding:6px}}
  .card .meta{{order:-1;margin-bottom:4px}} .card .meta a{{font-weight:600;font-size:15px}} .card .cat{{font-size:11px}} .fl{{margin-top:2px}}
  .lg,.gto,.gal .gl,figcaption{{display:none}} .sm{{display:inline}} .pair img{{max-height:34vh}}
  .row{{margin-top:6px}} .tools{{flex-wrap:nowrap;gap:5px;width:100%}} .tools .find{{gap:5px}}
  .tools button,.tools .gbtn,.tools .fbtn{{font-size:14px!important;padding:8px 10px!important;white-space:nowrap}}
  .tools .shb{{padding:6px 12px!important;background:#e65100;color:#fff;border-color:#e65100}} .shb svg{{width:24px;height:24px}}
  .tools .rot button{{padding:6px 9px!important;font-size:17px!important}} .url{{margin-top:5px}} .url input{{font-size:14px;padding:7px}} .url button{{padding:7px 10px}}
  .gal{{margin-top:5px}} .gal .up{{font-size:13px;padding:6px 10px}} .note{{margin:5px 0 0;font-size:13px;padding:6px}} .ok{{margin:6px 0 0;padding:9px}}
  .bar{{gap:8px;padding:8px;margin-top:8px;font-size:14px}} .bar .all{{display:none}} .bar .save{{padding:9px 14px;margin-left:auto}} #msg{{flex-basis:100%;font-size:12px}} #msg:empty{{display:none}}}}
.card.nophoto .cutb,.card.nophoto[data-choice="none"] .cropb{{display:none}}
.card.search{{border-color:#1565c0;background:#e3f2fd}} .card.search .fbtn{{background:#1565c0;color:#fff;border:0;border-radius:4px}} .card.on:not(.dcard) .find{{display:none}}
.rmode{{font-size:12px;margin:4px 2px 0;color:#555}} .rmode[hidden]{{display:none}} .rmode select{{font:inherit;font-size:12px}}
.smenu{{position:absolute;left:0;top:100%;z-index:6;background:#fff;border:1px solid #cfd8dc;border-radius:6px;box-shadow:0 4px 14px rgba(0,0,0,.2);padding:4px;width:260px}}
.smenu button{{all:unset;display:block;cursor:pointer;padding:8px 10px;border-radius:4px;font-size:12px}} .smenu button:hover{{background:#fff3e0}}
.site{{display:flex;flex-wrap:wrap;gap:6px;align-items:center;margin-top:6px;font-size:12px}} .site img{{width:56px;height:56px;object-fit:contain;border:1px solid #c8e6c9;background:#fff;cursor:zoom-in}}
.oldm{{display:flex;align-items:center;gap:4px}} .oldm[hidden]{{display:none}} .oldm img{{border:2px dashed #f28c28!important}} .oldm em{{font-size:11px;color:#e65100;font-style:normal}}
.oldm.drop img{{opacity:.35}} .oldm.drop em{{color:#d32f2f}}
.lft{{display:flex;align-items:center;gap:8px}} .lft .find,.tools .find{{margin:0;gap:6px}} .tools{{align-items:center;flex:1}} .tools .rot{{margin-left:auto}} .tools .gbtn{{padding:4px 10px}} .menu{{position:relative}} .mbtn,.tools .mbtn{{font-size:18px;line-height:14px;padding:3px 12px;font-weight:700}}
.mlist{{position:absolute;left:0;top:calc(100% + 4px);z-index:5;background:#fff;border:1px solid #cfd8dc;border-radius:6px;box-shadow:0 4px 14px rgba(0,0,0,.18);
  display:flex;flex-direction:column;min-width:170px;padding:4px}} .mlist[hidden]{{display:none}}
.mlist button,.mlist label{{all:unset;cursor:pointer;padding:8px 12px;border-radius:4px;font-size:13px}} .mlist button:hover,.mlist label:hover{{background:#fff3e0}}
.main{{transition:transform .2s}} .rot{{display:flex;justify-content:flex-end;gap:4px;margin-top:4px}} .rot button{{padding:2px 10px;font-size:16px}}
.meta{{overflow:hidden}} .alt{{float:right;width:64px;height:64px;object-fit:contain;border:2px dashed #90a4ae;margin-left:6px;cursor:pointer}}
.alt:hover{{border-color:#f28c28}} .cat{{color:#777;font-size:12px}}
.fl{{display:inline-block;background:#fff3e0;color:#e65100;font-size:12px;border-radius:4px;padding:1px 6px;margin:4px 4px 0 0}}
.bar{{position:sticky;bottom:0;background:#263238;color:#fff;padding:10px;margin-top:16px;border-radius:8px;display:flex;flex-wrap:wrap;gap:8px;align-items:center}}
button{{font:inherit;padding:6px 12px;cursor:pointer}} .save{{background:#f28c28;color:#fff;border:0;border-radius:4px;font-weight:600}}
</style></head><body>
<div class="top"><button type="button" class="helpb" onclick="this.classList.toggle('act');help.hidden=!help.hidden">Инструкция</button>
<b>Загружено {len(order)} {"товар" if len(order) % 10 == 1 and len(order) % 100 != 11 else "товара" if 2 <= len(order) % 10 <= 4 and not 12 <= len(order) % 100 <= 14 else "товаров"}</b></div>
<div id="help" class="help" hidden>
<p>Слева — как на сайте сейчас, справа — новое фото. <b>Галочка «Утвердить»</b> — это фото будет залито на сайт.
<b>«Найти в Google»</b> — откроет поиск Google по нашему фото и названию товара сразу: кликните нужную картинку, чтобы она открылась крупно, и <b>перетащите её мышью на карточку</b> (или в Google правой кнопкой «Копировать картинку», вернитесь сюда, кликните карточку и нажмите <b>Ctrl+V</b>; или вставьте адрес картинки в поле «Ссылка на фото» → «Взять»). Она станет новым фото на белом квадрате. «Поручить Claude» — поищу сам и покажу отдельной страницей. <b>Фотоаппарат</b> — сфотографировать товар: на телефоне сразу откроется камера, снимок станет новым фото (следующие — доп. фото). Товары <b>без фото</b> идут первыми: на сайте они сейчас скрыты и появятся сами, как только фото будет залито. Без галочки у товара останется старое фото — напишите в поле под галочкой, что не так, чтобы это учесть в доработке. <b>cut</b> — товар вырезан, <b>full</b> — кадр целиком на белом;
клик по фото — открыть в оригинальном размере (повторный клик — закрыть); ★ на миниатюре доп. фото — сделать его главным; клик по маленькой картинке справа — выбрать её вместо основной; ⟲ ⟳ — повернуть новое фото.
<b>«Кадрировать»</b> — потянуть углы рамки к углам товара: фото выровняется, как в Photoshop. <b>«Загрузить фото»</b> — загрузить файл, найденный самостоятельно: он станет новым фото (вариант <b>own</b>), поставлю его на белый квадрат. <b>«Удалить фон»</b> — вырезать товар и поставить на белый (около минуты), только там, где вы решите. «Скачать» — сохранить новое фото или оригинал к себе. Когда закончите — «Сохранить решения» и пришлите файл (или скажите, что сохранили).</p></div>
<div class="grid">{"".join(cards)}</div>
{f'''<h2>Уже на сайте — добавить фото ({len(done_cards)})</h2>
<p>Фото в интерьере, в другом ракурсе, детали. Добавьте их в карточку (файлы, ссылка, перетаскивание из Google или Ctrl+V — всё идёт в доп. фото),
поставьте галочку «Добавить новые фото на сайт» и сохраните решения — дозалью их после уже стоящих фото, главное фото не трогаю.</p>
<details{" open" if not cards else ""}><summary>Показать товары ({len(done_cards)})</summary><div class="grid">{"".join(done_cards)}</div></details>''' if done_cards else ""}
<div id="zoom" hidden><div></div><img alt=""></div>
<div id="cam" hidden><video playsinline muted autoplay></video><img class="cprev" alt="" hidden><div class="cfr"></div><div class="cmsg"></div>
<div class="cbar"><button type="button" class="cx" aria-label="Закрыть">✕</button><button type="button" class="csnap" aria-label="Снять"></button>
<button type="button" class="cre" hidden>Переснять</button><button type="button" class="cok" hidden>Готово</button></div></div>
<div id="cropm" hidden><div class="cbox">
<div class="ctop"><b>Кадрирование</b> <button type="button" data-f="src">Оригинал</button><button type="button" data-f="new">Новое фото</button> <span id="cinfo"></span></div>
<div id="cscroll"><div class="cwrap"><img id="cimg" alt=""><svg id="cquad"><polygon></polygon>{'<g class="hd"><circle r="13"></circle><path class="xo" d="M-12 0H12M0-12V12"></path><path class="xi" d="M-12 0H12M0-12V12"></path></g>' * 4}</svg></div></div>
<div class="cbot"><button type="button" id="capply" class="save">Готово</button><button type="button" id="ccancel">Отмена</button> <span style="color:#777">Потяните углы рамки к углам товара (как Perspective Crop в Photoshop). «Готово» — фото выровняется в ровный прямоугольник и встанет на белый квадрат.</span><button type="button" id="creset">Сбросить рамку</button></div>
</div></div>
<div class="bar"><span>Утверждено: <b id="n">0</b> из {len(order)}</span>
<button class="all" onclick="setAll(true)">Утвердить все</button><button class="all" onclick="setAll(false)">Снять все</button>
<button class="save" onclick="save()">Сохранить решения</button> <span id="msg"></span></div>
<script>
const KEY='casa24-photo-review-{batch}';
let st={{}};try{{st=JSON.parse(localStorage.getItem(KEY)||'{{}}')}}catch(e){{}}
function persist(){{try{{localStorage.setItem(KEY,JSON.stringify(st))}}catch(e){{}}}}
const cards=[...document.querySelectorAll('.card:not(.dcard)')],dcards=[...document.querySelectorAll('.dcard')];
function swap(c){{const main=c.querySelector('.main'),alt=c.querySelector('.alt');if(!alt)return;
  const t=main.src;main.src=alt.src;alt.src=t;const ch=c.dataset.choice;c.dataset.choice=c.dataset.alt;c.dataset.alt=ch;c.querySelector('.ch').textContent=c.dataset.choice;}}
function own(c,src){{const main=c.querySelector('.main');let alt=c.querySelector('.alt');
  if(c.dataset.choice==='none'){{c.dataset.choice='own';c.querySelector('.ch').textContent='own';}}   // товар без фото: заглушку в запасной вариант не ставим
  else if(c.dataset.choice!=='own'){{if(!alt){{alt=document.createElement('img');alt.className='alt';alt.title='запасной вариант — клик, чтобы выбрать';
      c.querySelector('.meta').prepend(alt);alt.addEventListener('click',()=>{{swap(c);rec(c);}});}}
    alt.src=main.src;c.dataset.alt=c.dataset.choice;c.dataset.choice='own';c.querySelector('.ch').textContent='own';}}
  main.src=src;setRot(c,0);}}
const crop={{c:null,from:'src',pts:null,drag:-1,z:1,bw:0}};
const cwrap=document.querySelector('.cwrap'),cquad=document.getElementById('cquad'),handles=[...cquad.querySelectorAll('.hd')];
// колёсико мыши — увеличить / уменьшить фото в рамке (точка под курсором остаётся на месте), 1×…8×
function setZoom(){{const s=cimg.style;if(crop.z===1){{s.width=s.maxWidth=s.maxHeight='';}}else{{s.width=crop.bw*crop.z+'px';s.maxWidth=s.maxHeight='none';}}}}
cscroll.addEventListener('wheel',e=>{{e.preventDefault();const r=cimg.getBoundingClientRect(),fx=(e.clientX-r.left)/r.width,fy=(e.clientY-r.top)/r.height;
  if(crop.z===1)crop.bw=cimg.clientWidth;const z=Math.min(8,Math.max(1,crop.z*(e.deltaY<0?1.2:1/1.2)));if(z===crop.z)return;
  crop.z=Math.abs(z-1)<0.05?1:z;setZoom();const r2=cimg.getBoundingClientRect();
  cscroll.scrollLeft+=r2.left+fx*r2.width-e.clientX;cscroll.scrollTop+=r2.top+fy*r2.height-e.clientY;cropDraw();}},{{passive:false}});
// углы рамки в долях картинки (0..1) — не зависят от размера окна
function cropDraw(){{const W=cimg.clientWidth,H=cimg.clientHeight,P=crop.pts.map(([x,y])=>[x*W,y*H]);
  cquad.setAttribute('viewBox','0 0 '+W+' '+H);cquad.querySelector('polygon').setAttribute('points',P.map(p=>p.join(',')).join(' '));
  handles.forEach((h,i)=>h.setAttribute('transform','translate('+P[i][0]+' '+P[i][1]+')'));
  const nw=cimg.naturalWidth,nh=cimg.naturalHeight,Q=crop.pts.map(([x,y])=>[x*nw,y*nh]),d=(a,b)=>Math.hypot(a[0]-b[0],a[1]-b[1]);
  cinfo.textContent='результат ≈ '+Math.round(Math.max(d(Q[0],Q[1]),d(Q[3],Q[2])))+'×'+Math.round(Math.max(d(Q[0],Q[3]),d(Q[1],Q[2])))+' px';}}
function cropReset(){{crop.pts=[[0,0],[1,0],[1,1],[0,1]];if(cimg.complete&&cimg.naturalWidth)cropDraw();}}
cimg.addEventListener('load',()=>cropDraw());
function cropFrom(f){{crop.from=f;crop.pts=[[0,0],[1,0],[1,1],[0,1]];crop.z=1;setZoom();
  cimg.src=f==='src'?crop.c.querySelector('.pair img').src:crop.c.querySelector('.main').src;
  document.querySelectorAll('.ctop button').forEach(b=>b.classList.toggle('act',b.dataset.f===f));cinfo.textContent='';}}
handles.forEach((h,i)=>h.addEventListener('pointerdown',e=>{{e.preventDefault();crop.drag=i;h.setPointerCapture(e.pointerId);h.style.cursor='grabbing';}}));
handles.forEach(h=>h.addEventListener('pointermove',e=>{{if(crop.drag<0)return;const r=cimg.getBoundingClientRect();
  crop.pts[crop.drag]=[Math.min(Math.max((e.clientX-r.left)/r.width,0),1),Math.min(Math.max((e.clientY-r.top)/r.height,0),1)];cropDraw();}}));
handles.forEach(h=>h.addEventListener('pointerup',()=>{{crop.drag=-1;h.style.cursor='';}}));
window.addEventListener('resize',()=>{{if(!cropm.hidden&&crop.pts)cropDraw();}});
document.querySelectorAll('.ctop button').forEach(b=>b.addEventListener('click',()=>cropFrom(b.dataset.f)));
creset.addEventListener('click',cropReset);
ccancel.addEventListener('click',()=>{{cropm.hidden=true;}});
capply.addEventListener('click',()=>{{const c=crop.c,nw=cimg.naturalWidth,nh=cimg.naturalHeight,quad=crop.pts.map(([x,y])=>[Math.round(x*nw),Math.round(y*nh)]);
  const dc=c.classList.contains('dcard');
  fetch('/crop',{{method:'POST',headers:{{'Content-Type':'application/json'}},body:JSON.stringify({{id:c.dataset.id,from:dc?'re':(crop.from==='src'?'src':c.dataset.choice),to:dc?'re':'own',quad}})}})
   .then(x=>x.json().then(j=>{{if(!x.ok)throw j.error;if(dc)reChanged(c,j.src);else{{own(c,j.src);rec(c);}}cropm.hidden=true;msg.textContent='✓ выровнено для ID '+c.dataset.id+' ('+j.size.join('×')+')';}}))
   .catch(e=>{{cinfo.textContent='не получилось: '+(e||'сервер не ответил');}});}});
function ownDone(c,j,what){{own(c,j.src);rec(c);msg.textContent='✓ '+what+' для ID '+c.dataset.id+' ('+j.size.join('×')+')'+(Math.max(...j.size)<600?' — фото маленькое, на сайте будет мутным':'');}}
function toGal(c){{return c.classList.contains('dcard')||c.querySelector('.gto input').checked;}}
function galAdd(c,j){{const s=document.createElement('span');s.className='gi';s.dataset.name=j.name;
  s.innerHTML='<img src="'+j.src+'"><b title="убрать из галереи">✕</b><i><u data-main="1" title="сделать главным фото">★</u><u data-r="-90" title="повернуть против часовой">⟲</u><u data-r="90" title="повернуть по часовой">⟳</u><u data-dl="1" title="скачать">⤓</u></i>';c.querySelector('.gis').appendChild(s);rec(c);
  if(c.classList.contains('dcard')){{c.querySelector('.gok input').checked=true;dOn(c);}}   // доп. фото добавлено — сразу отметка «Добавить на сайт»
  msg.textContent='✓ в галерею добавлено для ID '+c.dataset.id+' ('+j.size.join('×')+')';}}
// кнопки на миниатюре доп. фото — для всех карточек сразу
document.addEventListener('click',e=>{{const u=e.target.closest('.gi u');if(!u)return;e.stopPropagation();
  const gi=u.closest('.gi'),c=u.closest('.card'),img=gi.querySelector('img');
  if(u.dataset.dl)return download(img.src.split('?')[0],gi.dataset.name);
  if(u.dataset.main&&c.classList.contains('dcard')&&!u.dataset.mode){{   // у залитого товара — сначала выбор: прежнее главное куда
    document.querySelectorAll('.smenu').forEach(m=>m.remove());const m=document.createElement('div');m.className='smenu';
    m.innerHTML='<button data-mode="swap">Сделать главным — прежнее главное оставить в доп. фото</button>'+
                '<button data-mode="drop">Сделать главным — прежнее главное убрать с сайта</button>';
    m.addEventListener('click',ev=>{{const b=ev.target.closest('button');if(!b)return;ev.stopPropagation();m.remove();
      c.querySelector('.rmode select').value=b.dataset.mode;rmodeSave(c);u.dataset.mode=b.dataset.mode;u.click();delete u.dataset.mode;}});
    gi.appendChild(m);return;}}
  if(u.dataset.main){{const dc=c.classList.contains('dcard');   // доп. фото → новое главное (у залитого товара — замена главного)
    return fetch('/gallery-promote',{{method:'POST',headers:{{'Content-Type':'application/json'}},body:JSON.stringify({{id:c.dataset.id,name:gi.dataset.name,to:dc?'re':'own'}})}})
     .then(x=>x.json().then(j=>{{if(!x.ok)throw j.error;gi.remove();if(dc)reChanged(c,j.src);else ownDone(c,j,'доп. фото стало главным');}}))
     .catch(e=>{{msg.textContent='не удалось: '+(e||'');}});}}
  fetch('/gallery-rotate',{{method:'POST',headers:{{'Content-Type':'application/json'}},body:JSON.stringify({{id:c.dataset.id,name:gi.dataset.name,deg:+u.dataset.r}})}})
   .then(x=>x.json().then(j=>{{if(!x.ok)throw j.error;img.src=j.src;}})).catch(e=>{{msg.textContent='не удалось повернуть: '+(e||'');}});}},true);
document.addEventListener('click',e=>{{const b=e.target.closest('.dl1');if(!b)return;const c=b.closest('.card');
  const s=c.querySelector('.pair img').src;download(s,c.dataset.id+'-main.webp');}});
function done(c,j,what,gal){{if(gal)galAdd(c,j);else ownDone(c,j,what);}}
function putFile(c,f,gal){{gal=gal===undefined?toGal(c):gal;msg.textContent='загружаю…';
  return fetch('/upload?id='+c.dataset.id+(gal?'&to=gallery':''),{{method:'POST',body:f}})
  .then(x=>x.json().then(j=>{{if(!x.ok)throw j.error;done(c,j,'фото добавлено',gal);}})).catch(e=>{{msg.textContent='не удалось: '+(e||'сервер не ответил');}});}}
function takeUrl(c,u){{const gal=toGal(c);if(u.startsWith('data:image'))return fetch(u).then(r=>r.blob()).then(b=>putFile(c,b,gal));
  msg.textContent='скачиваю картинку…';fetch('/fetch',{{method:'POST',headers:{{'Content-Type':'application/json'}},body:JSON.stringify({{id:c.dataset.id,url:u,to:gal?'gallery':'main'}})}})
  .then(x=>x.json().then(j=>{{if(!x.ok)throw j.error;c.querySelector('.url input').value='';done(c,j,'фото из интернета добавлено',gal);}}))
  .catch(e=>{{msg.textContent='не удалось: '+(e||'сервер не ответил');}});}}
// Google — в новой вкладке или окне справа; страница проверки не уходит никогда (во встроенном браузере Claude
// окно справа невозможно — там это соседняя вкладка, картинку переносят копированием: Ctrl+V на карточке)
const REMOTE=!['localhost','127.0.0.1'].includes(location.hostname);
function openSearch(url){{if(REMOTE){{window.open(url,'_blank');return;}}   // поиск открывает в Google Chrome сам сервер страницы — работает и из встроенного браузера Claude
  fetch('/open-chrome',{{method:'POST',headers:{{'Content-Type':'application/json'}},body:JSON.stringify({{url}})}})
   .then(x=>x.json()).then(j=>{{msg.textContent=(j.chrome?'Google открыт в Chrome справа':'Google открыт в браузере')+
     ' — перетащите картинку на карточку или «Копировать картинку» → клик по карточке → Ctrl+V.';}})
   .catch(()=>{{msg.textContent='не удалось открыть Chrome — сервер страницы не ответил';}});}}
document.addEventListener('click',e=>{{const a=e.target.closest('.find a');if(!a)return;e.preventDefault();openSearch(a.href);}});
// Ctrl+V — картинка или ссылка из буфера обмена уходит в карточку, по которой кликнули последней
let pasteCard=null;
document.addEventListener('pointerdown',e=>{{const c=e.target.closest('.card');if(!c)return;
  document.querySelectorAll('.card.target').forEach(x=>x.classList.remove('target'));pasteCard=c;c.classList.add('target');}});
document.addEventListener('paste',e=>{{if(!pasteCard||e.target.closest('textarea,input'))return;const dt=e.clipboardData;
  const f=[...dt.items].find(it=>it.kind==='file'&&it.type.startsWith('image/'));
  if(f){{e.preventDefault();return putFile(pasteCard,f.getAsFile());}}
  const t=(dt.getData('text/plain')||'').trim();if(/^(https?:|data:image)/.test(t)){{e.preventDefault();takeUrl(pasteCard,t);}}}});
// клик по фото — оригинальный размер, повторный клик — закрыть
const zoom=document.getElementById('zoom'),zimg=zoom.querySelector('img'),zcap=zoom.querySelector('div');
document.addEventListener('click',e=>{{const im=e.target.closest('.pair img, .gi img, .site img');if(!im)return;
  zcap.textContent='загрузка…';zimg.style.transform=im.style.transform||'';zimg.src=im.src;zoom.hidden=false;zoom.scrollTo(0,0);}});
zimg.addEventListener('load',()=>{{zcap.textContent=zimg.naturalWidth+'×'+zimg.naturalHeight+' px — клик, чтобы закрыть';}});
zoom.addEventListener('click',()=>{{zoom.hidden=true;zimg.removeAttribute('src');}});
document.addEventListener('keydown',e=>{{if(e.key!=='Escape')return;if(!zoom.hidden)zoom.click();document.querySelectorAll('.mlist').forEach(m=>m.hidden=true);}});
function download(url,name){{const a=document.createElement('a');a.href=url;a.download=name;document.body.appendChild(a);a.click();a.remove();}}
function setRot(c,r){{c.dataset.rot=((r%360)+360)%360;c.querySelector('.main').style.transform='rotate('+c.dataset.rot+'deg)';}}
function count(){{n.textContent=cards.filter(c=>c.classList.contains('on')).length;}}
function rec(c){{if(c.classList.contains('dcard'))return;st[c.dataset.id]={{on:c.classList.contains('on'),choice:c.dataset.choice,note:c.querySelector('.note').value,rot:+(c.dataset.rot||0),search:c.classList.contains('search')}};persist();count();}}
cards.forEach(c=>{{
  const cb=c.querySelector('.ok input'),alt=c.querySelector('.alt'),note=c.querySelector('.note'),s=st[c.dataset.id];
  if(s){{if(s.choice!==c.dataset.choice)swap(c);cb.checked=s.on;c.classList.toggle('on',s.on);note.value=s.note||'';setRot(c,s.rot||0);c.classList.toggle('search',!!s.search&&!s.on);}}
  c.querySelector('.fbtn').addEventListener('click',()=>{{const v=!c.classList.contains('search');c.classList.toggle('search',v);
    if(v){{cb.checked=false;c.classList.remove('on');}}rec(c);}});
  c.querySelectorAll('.rot button').forEach(b=>b.addEventListener('click',()=>{{setRot(c,+(c.dataset.rot||0)+(+b.dataset.r));rec(c);}}));
  cb.addEventListener('change',()=>{{if(cb.checked&&c.dataset.choice==='none'){{cb.checked=false;msg.textContent='ID '+c.dataset.id+': сначала добавьте фото — утверждать пока нечего';return;}}
    c.classList.toggle('on',cb.checked);if(cb.checked)c.classList.remove('search');rec(c);}});
  note.addEventListener('input',()=>rec(c));
  c.querySelectorAll('.dl').forEach(b=>b.addEventListener('click',()=>{{const id=c.dataset.id;
    if(b.dataset.what==='new')download(c.querySelector('.main').src.split('?')[0],id+'-'+c.dataset.choice+'.webp');
    else{{const s=c.querySelector('.pair img').src;download(s,id+'-original.'+s.split('.').pop());}}}}));
  const cutb=c.querySelector('.cutb');
  cutb.addEventListener('click',()=>{{if(cutb.disabled)return;cutb.disabled=true;
    fetch('/cut-bg',{{method:'POST',headers:{{'Content-Type':'application/json'}},body:JSON.stringify({{id:c.dataset.id,from:c.dataset.choice}})}})
     .then(x=>x.json().then(j=>{{if(!x.ok)throw j.error;cutWait.add(c.dataset.id);cutShow(j);
       msg.textContent='ID '+c.dataset.id+' — в очереди «Удалить фон» (фото идут по одному, ~30–60 с каждое)';}}))
     .catch(e=>{{cutb.disabled=false;msg.textContent='не удалось удалить фон: '+(e||'сервер не ответил');}});}});
  c.querySelector('.cropb').addEventListener('click',()=>{{crop.c=c;const np=c.classList.contains('nophoto');   // без фото на сайте кадрировать можно только новое
    document.querySelector('.ctop').querySelectorAll('button').forEach(b=>b.hidden=np&&b.dataset.f==='src');cropFrom(np?'new':'src');cropm.hidden=false;}});
  const ui=c.querySelector('.url input');
  c.querySelector('.ubtn').addEventListener('click',()=>{{if(ui.value.trim())takeUrl(c,ui.value.trim());}});
  ui.addEventListener('keydown',e=>{{if(e.key==='Enter'&&ui.value.trim())takeUrl(c,ui.value.trim());}});
  c.addEventListener('dragover',e=>{{e.preventDefault();c.classList.add('drop');}});
  c.addEventListener('dragleave',e=>{{if(!c.contains(e.relatedTarget))c.classList.remove('drop');}});
  c.addEventListener('drop',e=>{{e.preventDefault();c.classList.remove('drop');const dt=e.dataTransfer;
    if(dt.files&&dt.files.length&&dt.files[0].type.startsWith('image/'))return putFile(c,dt.files[0]);
    let u=(dt.getData('text/uri-list')||'').split(/\\r?\\n/).find(l=>l&&!l.startsWith('#'))||'';
    const h=dt.getData('text/html');if(h){{const m=h.match(/<img[^>]+src=["']([^"']+)/i);if(m)u=m[1].replace(/&amp;/g,'&');}}
    if(!u)u=(dt.getData('text/plain')||'').trim();
    if(u)takeUrl(c,u);else msg.textContent='не удалось понять, что перетащили — скопируйте адрес картинки и вставьте в поле';}});
  const gup=c.querySelector('.gup'),gfi=gup.querySelector('input');
  gfi.addEventListener('change',async()=>{{gup.classList.add('busy');for(const f of gfi.files)await putFile(c,f,true);gup.classList.remove('busy');gfi.value='';}});
  c.querySelector('.gis').addEventListener('click',e=>{{const b=e.target.closest('.gi b');if(!b)return;e.stopPropagation();const gi=b.parentNode;
    fetch('/gallery-delete',{{method:'POST',headers:{{'Content-Type':'application/json'}},body:JSON.stringify({{id:c.dataset.id,name:gi.dataset.name}})}})
     .then(x=>{{if(!x.ok)throw 0;gi.remove();rec(c);msg.textContent='фото убрано из галереи';}}).catch(()=>{{msg.textContent='не удалось убрать фото';}});}});
  const up=c.querySelector('.up:not(.gup)'),fi=up.querySelector('input');
  fi.addEventListener('change',()=>{{const f=fi.files[0];if(!f)return;up.classList.add('busy');
    fetch('/upload?id='+c.dataset.id,{{method:'POST',body:f}}).then(x=>x.json().then(j=>{{if(!x.ok)throw j.error;own(c,j.src);rec(c);
      msg.textContent='✓ своё фото добавлено для ID '+c.dataset.id+' ('+j.size.join('×')+')';}}))
     .catch(e=>{{msg.textContent='не удалось загрузить фото: '+(e||'сервер не ответил');}}).finally(()=>{{up.classList.remove('busy');fi.value='';}});}});
  if(alt)alt.addEventListener('click',()=>{{swap(c);rec(c);}});
}});count();
// очередь «Удалить фон»: сервер режет по одному фото, страница раз в 2 с спрашивает состояние, готовое ставит новым фото
const cutWait=new Set();let cutTimer=0;
function cutShow(st){{cards.forEach(c=>{{const id=c.dataset.id,b=c.querySelector('.cutb'),s=st[id];
    if(s&&(s.state==='queued'||s.state==='working')){{cutWait.add(id);b.disabled=true;b.textContent=s.state==='working'?'Удаляю фон…':'В очереди: '+s.pos;return;}}
    if(!cutWait.has(id))return;cutWait.delete(id);b.disabled=false;b.textContent='Удалить фон';
    if(s&&s.state==='done')ownDone(c,s,'фон удалён');else msg.textContent='ID '+id+': '+((s&&s.error)||'не удалось удалить фон');}});
  clearTimeout(cutTimer);if(cutWait.size)cutTimer=setTimeout(cutPoll,2000);}}
function cutPoll(){{fetch('/cut-status').then(x=>x.json()).then(cutShow).catch(()=>{{cutTimer=setTimeout(cutPoll,5000);}});}}
cutPoll();   // после перезагрузки страницы — показать, что ещё в очереди
// камера страницы: видоискатель с квадратной рамкой — снимок сразу квадратный (центр кадра камеры)
const cam=document.getElementById('cam'),cvid=cam.querySelector('video'),cfr=cam.querySelector('.cfr'),cpv=cam.querySelector('.cprev'),cmsg=cam.querySelector('.cmsg');
let camCard=null,camStream=null,camBlob=null;
function camFrame(){{const W=cam.clientWidth,H=cam.clientHeight,vw=cvid.videoWidth,vh=cvid.videoHeight;if(!vw)return;
  const k=Math.min(W/vw,H/vh),sz=Math.min(vw,vh)*k,st={{width:sz+'px',height:sz+'px',left:(W-sz)/2+'px',top:(H-sz)/2+'px'}};
  Object.assign(cfr.style,st);Object.assign(cpv.style,st);}}
cvid.addEventListener('loadedmetadata',camFrame);window.addEventListener('resize',()=>{{if(!cam.hidden)camFrame();}});
function camMode(shot){{cpv.hidden=!shot;cam.querySelector('.cx').hidden=shot;cam.querySelector('.csnap').hidden=shot;cam.querySelector('.cre').hidden=!shot;cam.querySelector('.cok').hidden=!shot;}}
async function camOpen(c){{camCard=c;
  if(!(navigator.mediaDevices&&navigator.mediaDevices.getUserMedia)){{c.querySelector('.camn input').click();return;}}   // нет камеры для страницы — родная
  cam.hidden=false;camMode(false);cmsg.textContent='включаю камеру…';
  try{{camStream=await navigator.mediaDevices.getUserMedia({{video:{{facingMode:{{ideal:'environment'}},width:{{ideal:1920}},height:{{ideal:1920}}}},audio:false}});
    cvid.srcObject=camStream;await cvid.play();cmsg.textContent='';camFrame();}}
  catch(e){{cmsg.textContent='Камера недоступна ('+(e.name||e)+'). Разрешите доступ к камере для этого сайта или выберите в меню «⋯» → «Родная камера».';}}}}
function camClose(){{cam.hidden=true;if(camStream)camStream.getTracks().forEach(t=>t.stop());camStream=null;cvid.srcObject=null;
  if(cpv.src.startsWith('blob:'))URL.revokeObjectURL(cpv.src);cpv.removeAttribute('src');}}
cam.querySelector('.csnap').addEventListener('click',()=>{{const vw=cvid.videoWidth,vh=cvid.videoHeight;if(!vw)return;
  const sz=Math.min(vw,vh),k=document.createElement('canvas');k.width=k.height=sz;
  k.getContext('2d').drawImage(cvid,(vw-sz)/2,(vh-sz)/2,sz,sz,0,0,sz,sz);
  k.toBlob(b=>{{camBlob=b;cpv.src=URL.createObjectURL(b);camMode(true);cmsg.textContent=sz+'×'+sz+' px';}},'image/jpeg',0.92);}});
cam.querySelector('.cre').addEventListener('click',()=>{{camMode(false);cmsg.textContent='';}});
cam.querySelector('.cx').addEventListener('click',camClose);
cam.querySelector('.cok').addEventListener('click',()=>{{const c=camCard,b=camBlob;camClose();
  putFile(c,new File([b],'camera.jpg',{{type:'image/jpeg'}}),c.classList.contains('dcard')||c.dataset.choice==='own');}});
document.addEventListener('click',e=>{{const b=e.target.closest('.shb');if(b)camOpen(b.closest('.card'));}});
document.addEventListener('keydown',e=>{{if(e.key==='Escape'&&!cam.hidden)camClose();}});
// родная камера телефона (меню «⋯»): снимок как есть, тем же порядком — главное, потом доп. фото
document.addEventListener('change',async e=>{{const fi=e.target.closest('.camn input');if(!fi||!fi.files.length)return;
  const c=fi.closest('.card'),lb=c.querySelector('.shb');lb.classList.add('busy');
  for(const f of fi.files)await putFile(c,f,c.classList.contains('dcard')||c.dataset.choice==='own');
  lb.classList.remove('busy');fi.value='';c.scrollIntoView({{block:'nearest'}});}});
function setAll(v){{cards.forEach(c=>{{if(v&&c.dataset.choice==='none')return;c.querySelector('.ok input').checked=v;c.classList.toggle('on',v);rec(c);}});}}
// «Уже на сайте»: только доп. фото — файлы, ссылка, перетаскивание, Ctrl+V; галочка = дозалить их на сайт
document.addEventListener('click',e=>{{const b=e.target.closest('.mbtn'),open=b&&b.nextElementSibling.hidden;
  // пункт меню (в т. ч. «Загрузить фото» — выбор файла) успевает сработать, меню закрывается следом
  setTimeout(()=>{{document.querySelectorAll('.mlist').forEach(m=>m.hidden=true);if(open)b.nextElementSibling.hidden=false;}},0);}});
document.addEventListener('click',e=>{{if(!e.target.closest('.smenu,.gi u'))document.querySelectorAll('.smenu').forEach(m=>m.remove());}});
// выбор «прежнее главное: убрать / оставить в доп. фото» — помнится в браузере до заливки
const RM='casa24-rmode-{batch}';let rmodes={{}};try{{rmodes=JSON.parse(localStorage.getItem(RM)||'{{}}')}}catch(e){{}}
function rmodeSave(c){{rmodes[c.dataset.id]=c.querySelector('.rmode select').value;try{{localStorage.setItem(RM,JSON.stringify(rmodes))}}catch(e){{}}}}
dcards.forEach(c=>{{const sel=c.querySelector('.rmode select');if(rmodes[c.dataset.id])sel.value=rmodes[c.dataset.id];sel.addEventListener('change',()=>{{rmodeSave(c);oldPrev(c);}});oldPrev(c);}});
function reChanged(c,src){{c.querySelector('.main').src=src;c.querySelector('.cap').textContent='изменённое главное фото — ещё НЕ на сайте';
  const r=c.querySelector('.rok');r.hidden=false;r.querySelector('input').checked=true;c.querySelector('.rmode').hidden=false;dOn(c);msg.textContent='✓ главное фото изменено для ID '+c.dataset.id+' — отметка «Заменить» поставлена';}}
function reEdit(c,deg){{return fetch('/re-edit',{{method:'POST',headers:{{'Content-Type':'application/json'}},body:JSON.stringify({{id:c.dataset.id,url:c.dataset.site,deg}})}})
  .then(x=>x.json().then(j=>{{if(!x.ok)throw j.error;return j.src;}}));}}
// превью: что станет с прежним главным при замене (swap — первым доп. фото, drop — уберётся)
function oldPrev(c){{const o=c.querySelector('.oldm');if(!o)return;const on=c.querySelector('.rok input').checked&&!c.querySelector('.rok').hidden;
  const sw=c.querySelector('.rmode select').value==='swap';o.hidden=!on;o.classList.toggle('drop',!sw);
  o.querySelector('em').textContent=sw?'← прежнее главное станет доп. фото №1':'прежнее главное уберётся с сайта';
  const site=c.querySelector('.site');if(on&&sw)site.insertBefore(o,site.children[1]);}}
function dOn(c){{oldPrev(c);c.classList.toggle('on',c.querySelector('.rok input').checked||c.querySelector('.gok input').checked);}}
dcards.forEach(c=>{{c.querySelectorAll('.rok input,.gok input').forEach(x=>x.addEventListener('change',()=>dOn(c)));
  c.querySelectorAll('.rer').forEach(b=>b.addEventListener('click',()=>reEdit(c,+b.dataset.r).then(src=>reChanged(c,src)).catch(e=>{{msg.textContent='не удалось повернуть: '+e;}})));
  c.querySelector('.recrop').addEventListener('click',()=>reEdit(c,0).then(src=>{{c.querySelector('.main').src=src;crop.c=c;cropFrom('new');
    document.querySelector('.ctop').querySelectorAll('button').forEach(b=>b.hidden=true);cropm.hidden=false;}}).catch(e=>{{msg.textContent='не удалось: '+e;}}));
  const rf=c.querySelector('.reup input');rf.addEventListener('change',()=>{{const f=rf.files[0];if(!f)return;msg.textContent='загружаю…';
    fetch('/upload?id='+c.dataset.id+'&to=re',{{method:'POST',body:f}}).then(x=>x.json().then(j=>{{if(!x.ok)throw j.error;reChanged(c,j.src);}}))
     .catch(e=>{{msg.textContent='не удалось: '+(e||'сервер не ответил');}}).finally(()=>{{rf.value='';}});}});
  const gup=c.querySelector('.gup'),gfi=gup.querySelector('input');
  gfi.addEventListener('change',async()=>{{gup.classList.add('busy');for(const f of gfi.files)await putFile(c,f,true);gup.classList.remove('busy');gfi.value='';}});
  c.querySelector('.gis').addEventListener('click',e=>{{const b=e.target.closest('.gi b');if(!b)return;const gi=b.parentNode;
    fetch('/gallery-delete',{{method:'POST',headers:{{'Content-Type':'application/json'}},body:JSON.stringify({{id:c.dataset.id,name:gi.dataset.name}})}})
     .then(x=>{{if(!x.ok)throw 0;gi.remove();msg.textContent='фото убрано';}}).catch(()=>{{msg.textContent='не удалось убрать фото';}});}});
  const ui=c.querySelector('.url input');
  c.querySelector('.ubtn').addEventListener('click',()=>{{if(ui.value.trim())takeUrl(c,ui.value.trim());}});
  ui.addEventListener('keydown',e=>{{if(e.key==='Enter'&&ui.value.trim())takeUrl(c,ui.value.trim());}});
  c.addEventListener('dragover',e=>{{e.preventDefault();c.classList.add('drop');}});
  c.addEventListener('dragleave',e=>{{if(!c.contains(e.relatedTarget))c.classList.remove('drop');}});
  c.addEventListener('drop',e=>{{e.preventDefault();c.classList.remove('drop');const dt=e.dataTransfer;
    if(dt.files&&dt.files.length)return[...dt.files].filter(f=>f.type.startsWith('image/')).reduce((pr,f)=>pr.then(()=>putFile(c,f,true)),Promise.resolve());
    let u=(dt.getData('text/uri-list')||'').split(/\\r?\\n/).find(l=>l&&!l.startsWith('#'))||'';
    const h=dt.getData('text/html');if(h){{const m=h.match(/<img[^>]+src=["']([^"']+)/i);if(m)u=m[1].replace(/&amp;/g,'&');}}
    if(!u)u=(dt.getData('text/plain')||'').trim();if(u)takeUrl(c,u);}});
}});
function save(){{const r={{}};cards.forEach(c=>{{const on=c.classList.contains('on');
    r[c.dataset.id]={{action:on?c.dataset.choice:(c.classList.contains('search')?'search':'keep'),variant:c.dataset.choice,comment:on?'':c.querySelector('.note').value.trim(),rotate:+(c.dataset.rot||0),gallery:[...c.querySelectorAll('.gi')].map(g=>g.dataset.name)}};}});
  let ga=0;dcards.forEach(c=>{{const g=c.querySelector('.gok input').checked?[...c.querySelectorAll('.gi')].map(x=>x.dataset.name):[];
    const rep=c.querySelector('.rok input').checked&&!c.querySelector('.rok').hidden;
    if(rep||g.length){{r[c.dataset.id]={{action:rep?'replace':'gallery',mode:c.querySelector('.rmode select').value,gallery:g,comment:'',rotate:0}};ga++;}}}});
  const on=Object.values(r).filter(v=>!['keep','search','gallery'].includes(v.action)).length, se=Object.values(r).filter(v=>v.action==='search').length;
  fetch('/save',{{method:'POST',headers:{{'Content-Type':'application/json'}},body:JSON.stringify(r)}})
   .then(x=>{{if(!x.ok)throw 0;msg.textContent='✓ сохранено: заливать '+on+', искать в Google '+se+', оставить старое '+(cards.length-on-se)+(ga?', изменить уже залитые: '+ga+' товар.':'')+' — Claude приступает';}})
   .catch(()=>{{const a=document.createElement('a');a.href=URL.createObjectURL(new Blob([JSON.stringify(r,null,1)],{{type:'application/json'}}));
     a.download='decisions-{batch}.json';a.click();msg.textContent='сервер не ответил — файл скачан: заливать '+on;}});}}
</script></body></html>'''
open(os.path.join(d, "index.html"), "w", encoding="utf-8").write(page)

# скрипт страницы — через node --check: одна синтаксическая ошибка выключает все кнопки
script = re.search(r"<script>(.*)</script>", page, re.S).group(1)
with tempfile.NamedTemporaryFile("w", suffix=".js", delete=False, encoding="utf-8") as f:
    f.write(script)
try:
    chk = subprocess.run(["node", "--check", f.name], capture_output=True, text=True)
    if chk.returncode:
        sys.exit("ОШИБКА в скрипте страницы:\n" + chk.stderr[:500])
except FileNotFoundError:
    print("node не найден — синтаксис скрипта не проверен")
finally:
    os.unlink(f.name)
print("ok", len(report))
