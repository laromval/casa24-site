"""Удалить фон по кнопке «Удалить фон» на странице проверки: вырезка BiRefNet и белый квадрат 1000×1000.

Запуск из окружения tools/media/.venv (его вызывает review_server.py):
  `python cut_bg.py <фото> <результат.webp>` — одно фото; печатает размер вырезанного товара «w h»,
      если модель товар не нашла — код выхода 2;
  `python cut_bg.py --queue` — очередь: из stdin строки «<фото>\t<результат.webp>», на каждую в stdout
      «ok w h» | «none» (товар не найден) | «err <текст>». Модель грузится один раз; конец stdin — выход.
Одна вырезка занимает до ~7 ГБ памяти, поэтому сервер гонит фото строго по одному.
"""
import sys

import numpy as np
import onnxruntime as ort
from PIL import Image, ImageOps
from rembg import new_session, remove

from whiten import MODEL, to_square


def session():
    opts = ort.SessionOptions()
    opts.enable_cpu_mem_arena = False      # с ареной onnxruntime падает «Failed to allocate memory» при свободных 8+ ГБ
    return new_session(MODEL, sess_opts=opts)


def cut(sess, src, dst):
    """Вырезать товар из src и сохранить белый квадрат в dst; размер вырезанного или None, если товара нет."""
    im = ImageOps.exif_transpose(Image.open(src)).convert("RGB")
    alpha = np.asarray(remove(im, session=sess, only_mask=True))
    ys, xs = np.nonzero(alpha > 10)
    if not len(xs):
        return None
    rgba = im.convert("RGBA")
    rgba.putalpha(Image.fromarray(alpha))
    rgba = rgba.crop((xs.min(), ys.min(), xs.max() + 1, ys.max() + 1))
    to_square(rgba).save(dst, quality=88, method=6)
    return rgba.size


def main():
    sys.stdout.reconfigure(encoding="utf-8")
    sys.stdin.reconfigure(encoding="utf-8")
    sess = session()
    if sys.argv[1] != "--queue":
        size = cut(sess, sys.argv[1], sys.argv[2])
        if not size:
            sys.exit(2)
        print(*size)
        return
    for line in sys.stdin:
        if not line.strip():
            continue
        try:
            src, dst = line.rstrip("\r\n").split("\t")
            size = cut(sess, src, dst)
            print(f"ok {size[0]} {size[1]}" if size else "none", flush=True)
        except Exception as e:  # noqa: BLE001 — одно битое фото не должно останавливать очередь
            print("err " + str(e).replace("\n", " ")[:300], flush=True)


if __name__ == "__main__":
    main()
