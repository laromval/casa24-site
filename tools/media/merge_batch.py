"""Слить партию в общую папку отредактированных фото (по умолчанию tools/media/edited).

Запуск: `python merge_batch.py <папка партии> [<куда>]` — копирует src/, out/, own/, web/ (существующие файлы
не перезаписываются), объединяет pick.json, out/report.json и decisions.json (данные партии важнее),
дописывает журнал заливок без повторов. Исходная папка не меняется.
Страница проверки папки «edited» показывает в разделе «Уже на сайте» все отредактированные товары сразу.
"""
import json
import os
import shutil
import sys

HERE = os.path.dirname(os.path.abspath(__file__))


def load(path, default):
    return json.load(open(path, encoding="utf-8")) if os.path.exists(path) else default


def dump(obj, path):
    with open(path, "w", encoding="utf-8") as f:
        json.dump(obj, f, ensure_ascii=False, indent=1)


def main():
    sys.stdout.reconfigure(encoding="utf-8")
    src = os.path.abspath(sys.argv[1])
    dst = os.path.abspath(sys.argv[2] if len(sys.argv) > 2 else os.path.join(HERE, "edited"))
    os.makedirs(os.path.join(dst, "out"), exist_ok=True)

    copied = 0
    for sub in ("src", "out", "own", "web"):
        a = os.path.join(src, sub)
        if not os.path.isdir(a):
            continue
        for folder, _, files in os.walk(a):           # out/ содержит и подпапку mask/
            rel = os.path.relpath(folder, a)
            os.makedirs(os.path.join(dst, sub, rel), exist_ok=True)
            for name in files:
                if name == "report.json" and rel == ".":
                    continue
                target = os.path.join(dst, sub, rel, name)
                if not os.path.exists(target):
                    shutil.copy2(os.path.join(folder, name), target)
                    copied += 1

    pick = {str(x["id"]): x for x in load(os.path.join(dst, "pick.json"), [])}
    pick.update({str(x["id"]): x for x in load(os.path.join(src, "pick.json"), [])})
    with open(os.path.join(dst, "pick.json"), "w", encoding="utf-8") as f:
        json.dump(list(pick.values()), f, ensure_ascii=False)

    report = load(os.path.join(dst, "out", "report.json"), {})
    report.update(load(os.path.join(src, "out", "report.json"), {}))
    dump(report, os.path.join(dst, "out", "report.json"))

    decisions = load(os.path.join(dst, "decisions.json"), {})
    decisions.update(load(os.path.join(src, "decisions.json"), {}))
    dump(decisions, os.path.join(dst, "decisions.json"))

    jdst = os.path.join(dst, "journal.jsonl")
    have = {l.strip() for l in open(jdst, encoding="utf-8")} if os.path.exists(jdst) else set()
    added = 0
    with open(jdst, "a", encoding="utf-8") as f:
        for line in open(os.path.join(src, "journal.jsonl"), encoding="utf-8") if os.path.exists(os.path.join(src, "journal.jsonl")) else []:
            if line.strip() and line.strip() not in have:
                f.write(line if line.endswith("\n") else line + "\n")
                added += 1
    print(f"{os.path.basename(src)} → {os.path.basename(dst)}: файлов {copied}, товаров в pick {len(pick)}, записей журнала +{added}")


if __name__ == "__main__":
    main()
