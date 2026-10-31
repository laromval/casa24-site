"""Быстрая партия плохих фото — без вырезки фона моделью: отобрать, скачать, показать на странице проверки.

Запуск: `python make_batch.py [папка=edited] [сколько=30]` — по умолчанию товары ДОБАВЛЯЮТСЯ в общую папку
tools/media/edited (одна страница проверки на всё: вверху на замену, внизу «Уже на сайте»), уже лежащие там не повторяются; — СНАЧАЛА товары в наличии вообще без фото (список прямо
с сайта по SSH; на витрине они скрыты mu-plugin'ом casa24-hide-no-photo.php, пока фото не появится) — у них нет src/ и full,
только карточка с пометкой «нет фото»; товары «Скрыть навсегда» (метка casa24_hidden на сайте) не берутся вовсе; затем из audit.json товары в наличии с небелым фоном,
которые ещё не залиты ни из одной партии (journal.jsonl в tools/media/*/), сначала самые «небелые»;
пишет <партия>/pick.json, src/<id>.<ext> (оригинал с сайта), out/<id>.full.webp (оригинал на белом квадрате)
и out/report.json (вариант «full»). Замену фото пользователь ищет сам («Найти в Google», «Загрузить фото»).
"""
import glob
import io
import json
import os
import subprocess
import sys
import time
import urllib.request

from PIL import Image

HERE = os.path.dirname(os.path.abspath(__file__))
UA = {"User-Agent": "casa24-photo-audit/1.0 (owner check)"}
SIZE, PAD = 1000, 0.05
SSH = ["ssh", "-i", os.path.expanduser("~/.ssh/casa24_tophost"), "-p", "49786", "-o", "BatchMode=yes", "casamd@195.178.106.95"]
# RU-товары в наличии без главного фото: id, артикул, название, ссылка, категории — тем же видом, что в audit.json
NO_PHOTO_PHP = r'''<?php
global $wpdb;
$ids = $wpdb->get_col("SELECT p.ID FROM {$wpdb->posts} p
  JOIN {$wpdb->prefix}icl_translations t ON t.element_id = p.ID AND t.element_type = 'post_product' AND t.language_code = 'ru'
  JOIN {$wpdb->postmeta} s ON s.post_id = p.ID AND s.meta_key = '_stock_status' AND s.meta_value = 'instock'
  LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_thumbnail_id'
  LEFT JOIN {$wpdb->postmeta} h ON h.post_id = p.ID AND h.meta_key = 'casa24_hidden'
  WHERE p.post_type = 'product' AND p.post_status = 'publish' AND (m.meta_value IS NULL OR m.meta_value IN ('', '0'))
    AND h.meta_id IS NULL
  ORDER BY p.ID");
$hidden = $wpdb->get_col("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'casa24_hidden' AND meta_value = '1'");
$out = array();
foreach ($ids as $id) {
  $cats = wp_get_post_terms($id, 'product_cat', array('fields' => 'names'));
  $out[] = array('id' => (int) $id, 'sku' => get_post_meta($id, '_sku', true), 'name' => get_the_title($id),
    'url' => get_permalink($id), 'in_stock' => true, 'cat' => implode(', ', is_array($cats) ? $cats : array()),
    'img_id' => 0, 'src' => '', 'thumb' => '', 'verdict' => 'нет фото', 'no_photo' => true);
}
echo wp_json_encode(array('no_photo' => $out, 'hidden' => array_map('intval', $hidden)));
'''


def no_photo_products():
    """(товары в наличии без фото, ID «скрыто навсегда» — их в партии не брать)."""
    r = subprocess.run(SSH + ["cd ~/public_html && wp eval-file -"], input=NO_PHOTO_PHP, capture_output=True,
                       text=True, encoding="utf-8", timeout=300)
    if r.returncode:
        sys.exit("не получил список товаров без фото: " + r.stderr[:300])
    d = json.loads(r.stdout)
    return d["no_photo"], {str(i) for i in d["hidden"]}


def to_square(im):
    im = im.convert("RGBA") if im.mode in ("RGBA", "LA", "P") else im.convert("RGB")
    if im.mode == "RGBA":
        bg = Image.new("RGBA", im.size, "white")
        im = Image.alpha_composite(bg, im)
    im = im.convert("RGB")
    k = round(SIZE * (1 - 2 * PAD)) / max(im.size)
    im = im.resize((max(1, round(im.width * k)), max(1, round(im.height * k))), Image.LANCZOS)
    c = Image.new("RGB", (SIZE, SIZE), "white")
    c.paste(im, ((SIZE - im.width) // 2, (SIZE - im.height) // 2))
    return c


def main():
    sys.stdout.reconfigure(encoding="utf-8")
    args = [a for a in sys.argv[1:]]
    name = args.pop(0) if args and not args[0].isdigit() else "edited"
    count = int(args[0]) if args else 30
    d = os.path.join(HERE, name)
    for sub in ("src", "out"):
        os.makedirs(os.path.join(d, sub), exist_ok=True)

    done = set()
    for j in glob.glob(os.path.join(HERE, "*", "journal.jsonl")):
        done |= {json.loads(l)["id"] for l in open(j, encoding="utf-8") if l.strip()}
    taken = set()                       # уже в какой-либо папке (в т. ч. в этой — ждут замены)
    for pj in glob.glob(os.path.join(HERE, "*", "pick.json")):
        taken |= {str(x["id"]) for x in json.load(open(pj, encoding="utf-8"))}

    pick, report = [], {}
    no_photo, hidden = no_photo_products()
    for x in no_photo:       # в первую очередь — в наличии, но без фото
        if len(pick) >= count:
            break
        pid = str(x["id"])
        if pid in done or pid in taken:
            continue
        pick.append(x)
        report[pid] = {"src": "", "choice": "none", "flags": ["нет фото"], "has_cut": False, "no_photo": True}
    print(f"без фото: {len(pick)}")

    ap = os.path.join(HERE, "audit.json")       # на новом компьютере его нет, пока не запущен photo_bg_audit.py
    audit = json.load(open(ap, encoding="utf-8")) if len(pick) < count and os.path.exists(ap) else []
    cand = [x for x in audit if x["verdict"] == "не белый" and x["in_stock"] and x.get("src")
            and str(x["id"]) not in done and str(x["id"]) not in taken and str(x["id"]) not in hidden]
    cand.sort(key=lambda x: sum(x.get("sides") or [1, 1, 1, 1]))      # меньше белого по краям — хуже
    for x in cand:
        if len(pick) >= count:
            break
        pid = str(x["id"])
        try:
            raw = urllib.request.urlopen(urllib.request.Request(x["src"], headers=UA), timeout=30).read()
            im = Image.open(io.BytesIO(raw))
            im.load()
        except Exception as e:  # noqa: BLE001
            print(f"{pid}: не скачалось ({e})")
            continue
        ext = (im.format or "jpg").lower().replace("jpeg", "jpg")
        open(os.path.join(d, "src", f"{pid}.{ext}"), "wb").write(raw)
        to_square(im).save(os.path.join(d, "out", f"{pid}.full.webp"), quality=88, method=6)
        pick.append(x)
        report[pid] = {"src": f"{pid}.{ext}", "choice": "full", "flags": [], "has_cut": False}
        if len(pick) % 20 == 0:
            print(f"{len(pick)}/{count}", flush=True)
        time.sleep(0.2)
    # дописать к тому, что уже лежит в папке (не затирать)
    pp, rp = os.path.join(d, "pick.json"), os.path.join(d, "out", "report.json")
    old_pick = json.load(open(pp, encoding="utf-8")) if os.path.exists(pp) else []
    old_report = json.load(open(rp, encoding="utf-8")) if os.path.exists(rp) else {}
    old_report.update(report)
    json.dump(old_pick + pick, open(pp, "w", encoding="utf-8"), ensure_ascii=False)
    json.dump(old_report, open(rp, "w", encoding="utf-8"), ensure_ascii=False, indent=1)
    print(f"{name}: добавлено {len(pick)} товаров, всего в папке {len(old_pick) + len(pick)}")


if __name__ == "__main__":
    main()
