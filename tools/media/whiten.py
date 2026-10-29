"""Замена фона фото товаров на белый: вырезка моделью BiRefNet (rembg, лицензия MIT) и квадрат 1000×1000.

Запуск (из окружения tools/media/.venv): `python whiten.py <папка с оригиналами> <папка для результата>`.
Для каждого фото готовит два варианта:
  <id>.cut.webp  — товар вырезан и поставлен на белый;
  <id>.full.webp — кадр целиком (без белых полей) на белом квадрате — для упаковки, книг, картин,
                   где товар и есть весь кадр и вырезка «съедает» рисунок с упаковки.
Какой вариант основной, решает choose() по форме маски; в report.json — выбор, метрики и флаги.
Маски модели кэшируются в <out>/mask/, поэтому пересчёт выбора не требует повторного прогона модели.
"""
import json
import os
import sys

import numpy as np
from PIL import Image
from scipy import ndimage
from scipy.spatial import ConvexHull

SIZE = 1000
PAD = 0.07          # поле вокруг товара, доля стороны
MODEL = "birefnet-general"


def to_square(im):
    """RGBA/RGB-картинку вписать в белый квадрат SIZE с полями PAD."""
    im = im.convert("RGBA")
    inner = round(SIZE * (1 - 2 * PAD))
    k = inner / max(im.size)
    im = im.resize((max(1, round(im.width * k)), max(1, round(im.height * k))), Image.LANCZOS)
    canvas = Image.new("RGBA", (SIZE, SIZE), (255, 255, 255, 255))
    canvas.alpha_composite(im, ((SIZE - im.width) // 2, (SIZE - im.height) // 2))
    return canvas.convert("RGB")


def content_box(rgb):
    """Рамка содержимого без однотонных белых полей по краям (у книг, картин, фото на белом)."""
    nonwhite = rgb.min(-1) < 235
    rows, cols = np.nonzero(nonwhite.mean(1) > 0.02)[0], np.nonzero(nonwhite.mean(0) > 0.02)[0]
    if len(rows) == 0 or len(cols) == 0:
        return 0, 0, rgb.shape[1], rgb.shape[0]
    return cols.min(), rows.min(), cols.max() + 1, rows.max() + 1


def metrics(rgb, alpha):
    h, w = alpha.shape
    fg = alpha > 127
    x0, y0, x1, y1 = content_box(rgb)
    content = max(1, (x1 - x0) * (y1 - y0))
    lab, n = ndimage.label(fg)
    sizes = ndimage.sum(fg, lab, range(1, n + 1)) if n else np.array([])
    comps = int((sizes > 0.01 * h * w).sum())
    solidity = 0.0
    if fg.sum() > 50:
        edge = fg & ~ndimage.binary_erosion(fg)
        pts = np.argwhere(edge)
        if len(pts) > 2:
            try:
                solidity = float(fg.sum() / ConvexHull(pts).volume)
            except Exception:  # noqa: BLE001 — вырожденная маска (линия)
                solidity = 0.0
    a = alpha.astype(float) / 255
    border = np.concatenate([rgb[0], rgb[-1], rgb[:, 0], rgb[:, -1]])
    return {
        "cover": round(float(fg.mean()), 3),
        "cover_in_content": round(float(fg.sum() / content), 3),
        "comps": comps,
        "solidity": round(min(solidity, 1.0), 3),
        "edge": round(float(np.concatenate([fg[0], fg[-1], fg[:, 0], fg[:, -1]]).mean()), 3),
        "soft": round(float(((a > 0.1) & (a < 0.9)).sum() / max(1, (a > 0.1).sum())), 3),
        "white_border": round(float(((border.min(-1) >= 235) & (np.ptp(border, -1) <= 18)).mean()), 3),
    }


def choose(m):
    """'cut' — вырезка, 'full' — кадр целиком; плюс флаги для просмотра глазами."""
    flags = []
    if m["cover"] < 0.04:
        return "full", ["модель не нашла товар"]
    if m["cover"] > 0.9:
        return "full", ["товар занимает весь кадр"]
    # низкая solidity сама по себе не признак брака: у ключей, плоскогубцев, пистолетов она 0.3–0.7;
    # упаковку выдаёт «несколько кусков рисунка» при малой доле кадра (пилот: брак 0.18–0.30, стамески 0.32)
    fragmented = m["comps"] >= 2
    if fragmented and m["cover_in_content"] < 0.3:
        return "full", ["вырезано кусками — похоже на упаковку"]
    if m["white_border"] > 0.5 and m["cover_in_content"] < 0.6:
        return "full", ["уже на белом, вырезана только часть"]
    if m["edge"] > 0.15:
        flags.append("товар обрезан краем кадра")
    if m["soft"] > 0.12:
        flags.append("много полупрозрачного (стекло/плёнка/ворс)")
    if fragmented:
        flags.append("маска из нескольких кусков")
    return "cut", flags


def main():
    sys.stdout.reconfigure(encoding="utf-8")
    src_dir, out_dir = sys.argv[1], sys.argv[2]
    mask_dir = os.path.join(out_dir, "mask")
    os.makedirs(mask_dir, exist_ok=True)
    session = None
    report = {}
    files = sorted(f for f in os.listdir(src_dir) if f.lower().rsplit(".", 1)[-1] in ("jpg", "jpeg", "png", "webp"))
    for i, f in enumerate(files, 1):
        pid = f.rsplit(".", 1)[0]
        im = Image.open(os.path.join(src_dir, f)).convert("RGB")
        mpath = os.path.join(mask_dir, pid + ".png")
        if os.path.exists(mpath):
            alpha = np.asarray(Image.open(mpath))
        else:
            if session is None:
                from rembg import new_session
                session = new_session(MODEL)
            from rembg import remove
            alpha = np.asarray(remove(im, session=session, only_mask=True))
            Image.fromarray(alpha).save(mpath)
        rgb = np.asarray(im)
        m = metrics(rgb, alpha)
        choice, flags = choose(m)

        ys, xs = np.nonzero(alpha > 10)
        if len(xs):
            cut = im.convert("RGBA")
            cut.putalpha(Image.fromarray(alpha))
            to_square(cut.crop((xs.min(), ys.min(), xs.max() + 1, ys.max() + 1))).save(
                os.path.join(out_dir, pid + ".cut.webp"), quality=88, method=6)
        elif choice == "cut":
            choice = "full"
        to_square(im.crop(content_box(rgb))).save(os.path.join(out_dir, pid + ".full.webp"), quality=88, method=6)

        report[pid] = {"src": f, "choice": choice, "flags": flags, "has_cut": bool(len(xs)), **m}
        print(f"{i}/{len(files)} {pid} {choice} {'; '.join(flags) or 'ок'}", flush=True)
    json.dump(report, open(os.path.join(out_dir, "report.json"), "w", encoding="utf-8"), ensure_ascii=False, indent=1)


if __name__ == "__main__":
    main()
