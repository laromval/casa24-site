import collections
import csv
import json
import os

D = os.path.dirname(os.path.abspath(__file__))
ru = json.load(open(os.path.join(D, "ru.json"), encoding="utf-8"))
ro = json.load(open(os.path.join(D, "ro.json"), encoding="utf-8"))

by_img = collections.defaultdict(list)
by_sku = collections.defaultdict(list)
for p in ro:
    if p["img"]:
        by_img[p["img"]].append(p)
    if p["sku"]:
        by_sku[p["sku"]].append(p)

pairs, unmatched, ambiguous = [], [], 0
for r in ru:
    cand = by_img.get(r["img"], []) if r["img"] else []
    how = "img"
    if len(cand) != 1:
        c2 = by_sku.get(r["sku"], []) if r["sku"] else []
        if len(c2) == 1:
            cand, how = c2, "sku"
        elif len(cand) > 1 and r["sku"]:
            c3 = [c for c in cand if c["sku"] == r["sku"]]
            cand, how = (c3, "img+sku") if len(c3) == 1 else (cand, how)
    if len(cand) == 1:
        pairs.append((r, cand[0], how))
    elif len(cand) > 1:
        ambiguous += 1
    else:
        unmatched.append(r)

diff = [(r, o, h) for r, o, h in pairs if r["price"] is not None and o["price"] is not None and abs(r["price"] - o["price"]) > 0.001]
nulls = [(r, o, h) for r, o, h in pairs if r["price"] is None or o["price"] is None]
sku_diff = [(r, o) for r, o, h in pairs if r["sku"] != o["sku"]]

diff.sort(key=lambda x: -abs(x[0]["price"] - x[1]["price"]) / max(x[0]["price"], 0.01))
with open(os.path.join(D, "price-diff-ru-ro.csv"), "w", encoding="utf-8-sig", newline="") as f:
    w = csv.writer(f, delimiter=";")
    w.writerow(["RU id", "RO id", "Артикул RU", "Артикул RO", "Название RU", "Цена RU", "Цена RO", "Разница RO-RU", "% от RU", "Связь", "RU ссылка", "RO ссылка"])
    for r, o, h in diff:
        d = o["price"] - r["price"]
        w.writerow([r["id"], o["id"], r["sku"], o["sku"], r["name"], f'{r["price"]:.2f}'.replace(".", ","), f'{o["price"]:.2f}'.replace(".", ","),
                    f"{d:.2f}".replace(".", ","), f'{100 * d / r["price"]:.0f}' if r["price"] else "", h, r["url"], o["url"]])

up = sum(1 for r, o, h in diff if o["price"] > r["price"])
print(json.dumps({
    "ru_listed": len(ru), "ro_total": len(ro), "pairs": len(pairs), "by": collections.Counter(h for *_, h in pairs),
    "ambiguous": ambiguous, "unmatched": len(unmatched), "price_null": len(nulls),
    "price_diff": len(diff), "ro_higher": up, "ro_lower": len(diff) - up, "sku_diff": len(sku_diff),
    "ro_price_le_1": sum(1 for r, o, h in diff if o["price"] <= 1),
    "big_diff_over_50pct": sum(1 for r, o, h in diff if abs(o["price"] - r["price"]) / max(r["price"], .01) > .5),
}, ensure_ascii=False, indent=1, default=dict))
for r, o, h in diff[:25]:
    print(f'{r["price"]:>10.2f} | {o["price"]:>10.2f} | {r["name"][:60]}')
print("unmatched sample:", [u["name"][:40] for u in unmatched[:8]])
