"""Сложные фото товаров через Nano Banana (Google Gemini, генерация по фото): блик, перспектива, дорисовать края.

Запуск: `python nano_banana.py <папка партии> <prompts.json>` — prompts.json {id: "запрос на английском"};
ключ API — переменная окружения GEMINI_API_KEY или GOOGLE_API_KEY. Берёт src/<id>.*, пишет out/<id>.ai.webp
(белый квадрат 1000×1000) и добавляет вариант «ai» в out/report.json — на странице проверки он основной,
запасной — прежний вариант. Модель может исказить текст на упаковке — результат только через проверку глазами.
"""
import base64
import io
import json
import os
import sys
import urllib.error
import urllib.request

from PIL import Image

MODELS = ["gemini-3-pro-image-preview", "gemini-2.5-flash-image"]   # Nano Banana Pro, затем обычная
SIZE = 1000
PAD = 0.05


def generate(key, model, prompt, img_bytes, mime):
    body = {"contents": [{"parts": [{"text": prompt},
                                    {"inline_data": {"mime_type": mime, "data": base64.b64encode(img_bytes).decode()}}]}],
            "generationConfig": {"responseModalities": ["IMAGE", "TEXT"]}}
    req = urllib.request.Request(
        f"https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent",
        data=json.dumps(body).encode(), method="POST",
        headers={"Content-Type": "application/json", "x-goog-api-key": key})
    with urllib.request.urlopen(req, timeout=300) as r:
        res = json.load(r)
    for cand in res.get("candidates", []):
        for part in cand.get("content", {}).get("parts", []):
            data = part.get("inline_data") or part.get("inlineData")
            if data:
                return base64.b64decode(data["data"])
    raise RuntimeError("нет картинки в ответе: " + json.dumps(res, ensure_ascii=False)[:300])


def to_square(im):
    im = im.convert("RGB")
    inner = round(SIZE * (1 - 2 * PAD))
    k = inner / max(im.size)
    im = im.resize((round(im.width * k), round(im.height * k)), Image.LANCZOS)
    canvas = Image.new("RGB", (SIZE, SIZE), "white")
    canvas.paste(im, ((SIZE - im.width) // 2, (SIZE - im.height) // 2))
    return canvas


def main():
    sys.stdout.reconfigure(encoding="utf-8")
    d, prompts_path = sys.argv[1], sys.argv[2]
    key = os.environ.get("GEMINI_API_KEY") or os.environ.get("GOOGLE_API_KEY")
    if not key:
        sys.exit("нет GEMINI_API_KEY / GOOGLE_API_KEY")
    prompts = json.load(open(prompts_path, encoding="utf-8"))
    rpath = os.path.join(d, "out", "report.json")
    report = json.load(open(rpath, encoding="utf-8"))
    for pid, prompt in prompts.items():
        src = next(f for f in os.listdir(os.path.join(d, "src")) if f.rsplit(".", 1)[0] == pid)
        raw = open(os.path.join(d, "src", src), "rb").read()
        mime = "image/png" if src.lower().endswith(".png") else "image/webp" if src.lower().endswith(".webp") else "image/jpeg"
        out = None
        for model in MODELS:
            try:
                out = generate(key, model, prompt, raw, mime)
                break
            except urllib.error.HTTPError as e:
                print(f"{pid}: {model} → HTTP {e.code} {e.read()[:200]!r}", flush=True)
        if out is None:
            print(f"{pid}: не получилось", flush=True)
            continue
        to_square(Image.open(io.BytesIO(out))).save(os.path.join(d, "out", f"{pid}.ai.webp"), quality=88, method=6)
        r = report[pid]
        if r["choice"] != "ai":
            r["alt"] = r["choice"]
        r.update(choice="ai", ai_model=model, flags=[f"Nano Banana ({model}) — сверить текст и форму с оригиналом"])
        json.dump(report, open(rpath, "w", encoding="utf-8"), ensure_ascii=False, indent=1)
        print(f"{pid}: готово ({model})", flush=True)


if __name__ == "__main__":
    main()
