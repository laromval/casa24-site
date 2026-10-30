"""«Скрыть навсегда»: товары, которые владелец не хочет видеть на сайте.

Запуск: `python hide_forever.py <папка партии>` — берёт из <партия>/decisions.json товары с action "hide"
и одним SSH-подключением ставит метку casa24_hidden = 1 у RU-товара и всех его переводов (WPML).
mu-plugin casa24-hide-no-photo.php прячет такие товары с витрины (даже если у них есть фото),
make_batch.py больше не берёт их в партии. Товар не удаляется и не снимается с публикации.

Вернуть товар: `python hide_forever.py --undo <ID> [<ID> ...]` — снимает метку у товара и переводов.
Журнал — <партия>/hidden.jsonl.
"""
import json
import os
import subprocess
import sys
import time

SSH = ["ssh", "-i", os.path.expanduser("~/.ssh/casa24_tophost"), "-p", "49786", "-o", "BatchMode=yes", "casamd@195.178.106.95"]
PHP = r'''<?php
$ids = array_map('intval', explode(',', '%s'));
$set = %s;
$out = array();
foreach ($ids as $id) {
  $trid = apply_filters('wpml_element_trid', null, $id, 'post_product');
  $all = array($id);
  foreach ((array) apply_filters('wpml_get_element_translations', array(), $trid, 'post_product') as $t) {
    $all[] = (int) $t->element_id;
  }
  $all = array_values(array_unique($all));
  foreach ($all as $p) {
    if ($set) { update_post_meta($p, 'casa24_hidden', '1'); } else { delete_post_meta($p, 'casa24_hidden'); }
  }
  $out[$id] = $all;
}
delete_transient('casa24_no_photo_ids');
do_action('litespeed_purge_all');
echo wp_json_encode($out);
'''


def run(ids, hide):
    r = subprocess.run(SSH + ["cd ~/public_html && wp eval-file -"], input=PHP % (",".join(ids), "true" if hide else "false"),
                       capture_output=True, text=True, encoding="utf-8", timeout=300)
    if r.returncode:
        sys.exit("не получилось: " + r.stderr[:300])
    return json.loads(r.stdout)


def main():
    sys.stdout.reconfigure(encoding="utf-8")
    args = sys.argv[1:]
    if args and args[0] == "--undo":
        for pid, all_ids in run([a for a in args[1:] if a.isdigit()], False).items():
            print(f"{pid}: снова на сайте (если есть фото)  товары: {', '.join(map(str, all_ids))}")
        return
    d = args[0]
    decisions = json.load(open(os.path.join(d, "decisions.json"), encoding="utf-8"))
    jpath = os.path.join(d, "hidden.jsonl")
    done = {json.loads(l)["id"] for l in open(jpath, encoding="utf-8")} if os.path.exists(jpath) else set()
    todo = [k for k, v in decisions.items() if v.get("action") == "hide" and k not in done]
    print(f"скрыть навсегда: {len(todo)}")
    if not todo:
        return
    res = run(todo, True)
    with open(jpath, "a", encoding="utf-8") as f:
        for pid, all_ids in res.items():
            f.write(json.dumps({"id": pid, "ids": all_ids, "at": time.strftime("%Y-%m-%d %H:%M:%S")}, ensure_ascii=False) + "\n")
            print(f"{pid}: скрыт навсегда  товары: {', '.join(map(str, all_ids))}")


if __name__ == "__main__":
    main()
