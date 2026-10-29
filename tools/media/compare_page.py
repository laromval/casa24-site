"""Страница проверки партии глазами: оригинал, выбранный вариант и запасной, галочка «заливать на сайт».

Запуск: `python compare_page.py <папка партии>` — в папке ждёт pick.json, src/ и out/ (с report.json);
пишет index.html рядом. На сайт уходят только фото с галочкой, у остальных остаётся старое фото.
Клик по маленькому варианту — поменять местами. Кнопка «Сохранить решения» пишет через review_server.py
<папка партии>/decisions.json {id: {action: "cut" | "full" | "keep", variant, comment}} — по нему идёт
загрузка на сайт, а comment объясняет, почему фото не утверждено
(без сервера файл просто скачивается браузером).
Отметки держатся в localStorage браузера, чтобы не потерять их при перезагрузке страницы.
"""
import html
import json
import os
import sys

d = sys.argv[1]
batch = os.path.basename(os.path.abspath(d))
pick = {str(x["id"]): x for x in json.load(open(os.path.join(d, "pick.json"), encoding="utf-8"))}
report = json.load(open(os.path.join(d, "out", "report.json"), encoding="utf-8"))
order = sorted(report, key=lambda k: (not report[k]["flags"], report[k]["choice"], k))

cards = []
for pid in order:
    r, p = report[pid], pick.get(pid, {})
    fl = "".join(f'<span class="fl">{html.escape(f)}</span>' for f in r["flags"])
    alt = "full" if r["choice"] == "cut" else "cut"
    alt_img = f'<img class="alt" src="out/{pid}.{alt}.webp" title="запасной вариант — клик, чтобы выбрать">' \
        if (alt == "full" or r["has_cut"]) else ""
    cards.append(f'''<div class="card" data-id="{pid}" data-choice="{r["choice"]}" data-default="{r["choice"]}">
<div class="pair"><figure><img src="src/{html.escape(r["src"])}" loading="lazy"><figcaption>сейчас на сайте</figcaption></figure>
<figure><img class="main" src="out/{pid}.{r["choice"]}.webp" loading="lazy"><figcaption>новое</figcaption></figure></div>
<label class="ok"><input type="checkbox"> Утвердить — заливать на сайт</label>
<textarea class="note" rows="2" placeholder="Почему не утверждено? (например: обрезана ручка, лучше кадр целиком, нужен другой ракурс)"></textarea>
<div class="meta">{alt_img}<a href="{html.escape(p.get("url", ""))}" target="_blank">{html.escape(p.get("name", pid))}</a>
<div class="cat">{html.escape((p.get("cat") or "").split(", ")[0])} · ID {pid} · вариант <b class="ch">{r["choice"]}</b></div>{fl}</div></div>''')

page = f'''<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Проверка: белый фон</title><style>
body{{font:14px system-ui,sans-serif;margin:16px;background:#eceff1;color:#222}}
h1{{font-size:20px}} .grid{{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:12px}}
.card{{background:#fff;border-radius:8px;padding:8px;border:3px solid transparent}}
.card.on{{border-color:#2e7d32;background:#f1f8e9}}
.pair{{display:grid;grid-template-columns:1fr 1fr;gap:6px}} figure{{margin:0}}
.pair img{{width:100%;aspect-ratio:1;object-fit:contain;background:#fafafa;border:1px solid #ddd}}
figcaption{{font-size:11px;color:#888;text-align:center}}
.ok{{display:flex;align-items:center;gap:8px;margin:8px 0 4px;padding:8px;background:#eceff1;border-radius:6px;font-weight:600;cursor:pointer;user-select:none}}
.ok input{{width:22px;height:22px;accent-color:#2e7d32}} .card.on .ok{{background:#c8e6c9;color:#1b5e20}}
.note{{width:100%;box-sizing:border-box;font:inherit;font-size:13px;padding:6px;border:1px solid #cfd8dc;border-radius:6px;margin-bottom:6px;resize:vertical}}
.card.on .note{{display:none}}
.meta{{overflow:hidden}} .alt{{float:right;width:64px;height:64px;object-fit:contain;border:2px dashed #90a4ae;margin-left:6px;cursor:pointer}}
.alt:hover{{border-color:#f28c28}} .cat{{color:#777;font-size:12px}}
.fl{{display:inline-block;background:#fff3e0;color:#e65100;font-size:12px;border-radius:4px;padding:1px 6px;margin:4px 4px 0 0}}
.bar{{position:sticky;bottom:0;background:#263238;color:#fff;padding:10px;margin-top:16px;border-radius:8px;display:flex;flex-wrap:wrap;gap:8px;align-items:center}}
button{{font:inherit;padding:6px 12px;cursor:pointer}} .save{{background:#f28c28;color:#fff;border:0;border-radius:4px;font-weight:600}}
</style></head><body>
<h1>Проверка: замена фона на белый — {len(report)} фото</h1>
<p>Слева — как на сайте сейчас, справа — новое фото. <b>Галочка «Утвердить»</b> — это фото будет залито на сайт.
Без галочки у товара останется старое фото — напишите в поле под галочкой, что не так, чтобы это учесть в доработке. <b>cut</b> — товар вырезан, <b>full</b> — кадр целиком на белом;
клик по маленькой картинке справа — выбрать её вместо основной. Когда закончите — «Сохранить решения» и пришлите файл (или скажите, что сохранили).</p>
<div class="grid">{"".join(cards)}</div>
<div class="bar">Утверждено: <b id="n">0</b> из {len(report)}
<button onclick="setAll(true)">Утвердить все</button><button onclick="setAll(false)">Снять все</button>
<button class="save" onclick="save()">Сохранить решения</button> <span id="msg"></span></div>
<script>
const KEY='casa24-photo-review-{batch}';
let st={{}};try{{st=JSON.parse(localStorage.getItem(KEY)||'{{}}')}}catch(e){{}}
function persist(){{try{{localStorage.setItem(KEY,JSON.stringify(st))}}catch(e){{}}}}
const cards=[...document.querySelectorAll('.card')];
function swap(c){{const main=c.querySelector('.main'),alt=c.querySelector('.alt');if(!alt)return;
  const t=main.src;main.src=alt.src;alt.src=t;c.dataset.choice=c.dataset.choice==='cut'?'full':'cut';c.querySelector('.ch').textContent=c.dataset.choice;}}
function count(){{n.textContent=cards.filter(c=>c.classList.contains('on')).length;}}
function rec(c){{st[c.dataset.id]={{on:c.classList.contains('on'),choice:c.dataset.choice,note:c.querySelector('.note').value}};persist();count();}}
cards.forEach(c=>{{
  const cb=c.querySelector('input'),alt=c.querySelector('.alt'),note=c.querySelector('.note'),s=st[c.dataset.id];
  if(s){{if(s.choice!==c.dataset.choice)swap(c);cb.checked=s.on;c.classList.toggle('on',s.on);note.value=s.note||'';}}
  cb.addEventListener('change',()=>{{c.classList.toggle('on',cb.checked);rec(c);}});
  note.addEventListener('input',()=>rec(c));
  if(alt)alt.addEventListener('click',()=>{{swap(c);rec(c);}});
}});count();
function setAll(v){{cards.forEach(c=>{{c.querySelector('input').checked=v;c.classList.toggle('on',v);rec(c);}});}}
function save(){{const r={{}};cards.forEach(c=>{{const on=c.classList.contains('on');
    r[c.dataset.id]={{action:on?c.dataset.choice:'keep',variant:c.dataset.choice,comment:on?'':c.querySelector('.note').value.trim()}};}});
  const on=Object.values(r).filter(v=>v.action!=='keep').length;
  fetch('/save',{{method:'POST',headers:{{'Content-Type':'application/json'}},body:JSON.stringify(r)}})
   .then(x=>{{if(!x.ok)throw 0;msg.textContent='✓ сохранено: заливать '+on+', оставить старое '+(cards.length-on);}})
   .catch(()=>{{const a=document.createElement('a');a.href=URL.createObjectURL(new Blob([JSON.stringify(r,null,1)],{{type:'application/json'}}));
     a.download='decisions-{batch}.json';a.click();msg.textContent='сервер не ответил — файл скачан: заливать '+on;}});}}
</script></body></html>'''
open(os.path.join(d, "index.html"), "w", encoding="utf-8").write(page)
print("ok", len(report))
