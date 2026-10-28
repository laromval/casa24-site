"""Собрать wp-content/mu-plugins/casa24-catalog/map.php из catalog_tree.py.

Запуск из корня репозитория:  python tools/catalog/build_map.py
"""

import os
import re
import sys
import unicodedata

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from catalog_tree import TREE, SORT, UNSORTED, PRODUCT_OVERRIDES, REDIRECTS, NAME_RULES, LEGACY  # noqa: E402

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
OUT = os.path.join(ROOT, 'wp-content', 'mu-plugins', 'casa24-catalog', 'map.php')

# Группы-бренды: товары раскладываются по названию. Значение — набор правил
# в casa24_catalog_classify() и подгруппа по умолчанию (RU-название).
SORT_GROUPS = {}
for tid in (1172, 1178, 1179, 1439, 1185, 1187, 1188, 1189, 1190):
    SORT_GROUPS[tid] = ('paint', None)
for tid in (1175, 1176, 1177):
    SORT_GROUPS[tid] = ('paint_wall', 'Интерьерные краски')
for tid in (1182, 1183, 1184):
    SORT_GROUPS[tid] = ('paint_lac', 'Пропитки и антисептики для дерева')
for tid in (1220, 1252, 1257, 1254, 1222, 1255, 1221, 1251, 1256):
    SORT_GROUPS[tid] = ('clean', 'Средства для уборки')
for tid in (1223, 1224, 1253):
    SORT_GROUPS[tid] = ('clean', 'Средства для стирки')
for tid in (1273, 1274, 1275):
    SORT_GROUPS[tid] = ('clean', 'Мыло')
# Товары, лежащие прямо в группе «Электроинструмент» (не в подгруппе).
SORT_GROUPS[886] = ('power', 'Прочий электроинструмент')
# Большие группы-«мешки» 1С (второй этап, 28.10.2026): правила — в NAME_RULES.
for _rules, (_tids, _default, _) in NAME_RULES.items():
    for _tid in _tids:
        assert _tid not in SORT_GROUPS, ('группа в двух наборах правил', _tid)
        SORT_GROUPS[_tid] = (_rules, _default)

RU_LAT = dict(zip('абвгдеёжзийклмнопрстуфхцчшщъыьэюя',
                  ['a', 'b', 'v', 'g', 'd', 'e', 'e', 'zh', 'z', 'i', 'y', 'k', 'l', 'm', 'n', 'o', 'p', 'r', 's', 't',
                   'u', 'f', 'h', 'ts', 'ch', 'sh', 'sch', '', 'y', '', 'e', 'yu', 'ya']))


def slug_ru(name):
    s = ''.join(RU_LAT.get(ch, ch) for ch in name.lower())
    return re.sub(r'[^a-z0-9]+', '-', s).strip('-')


def slug_ro(name):
    s = unicodedata.normalize('NFKD', name.lower())
    s = ''.join(ch for ch in s if not unicodedata.combining(ch))
    return re.sub(r'[^a-z0-9]+', '-', s).strip('-')


nodes = []          # (key, parent_key, ru, ro, order)
groups = {}         # старый term_id -> key
by_name = {}        # RU-название -> key (для правил и переопределений)
seen = set()


def add(ru, ro, parent, order):
    key = slug_ru(ru)
    assert key not in seen, ('повтор ключа', key, ru)
    seen.add(key)
    nodes.append((key, parent, ru, ro, order))
    assert ru not in by_name, ('повтор названия', ru)
    by_name[ru] = key
    return key


def bind(olds, key):
    for o in olds:
        if o == SORT:
            continue
        assert o not in groups, ('старая группа дважды', o)
        groups[o] = key


for i, (sec_ru, sec_ro, grps) in enumerate(TREE):
    sec = add(sec_ru, sec_ro, '', i)
    for j, (g_ru, g_ro, subs) in enumerate(grps):
        g = add(g_ru, g_ro, sec, j)
        for k, (s_ru, s_ro, olds) in enumerate(subs):
            if s_ru is None:
                bind(olds, g)
            else:
                bind(olds, add(s_ru, s_ro, g, k))

unsorted = add(UNSORTED[0], UNSORTED[1], '', 99)

for tid in SORT_GROUPS:
    assert tid not in groups, ('группа и в разборе, и в переносе', tid)

for rules, (_, default, items) in NAME_RULES.items():
    for target in [default] + [t for t, _ in items]:
        assert target in by_name, ('правило ведёт в несуществующую категорию', rules, target)

ro_slugs = [slug_ro(n[3]) for n in nodes]
assert len(set(ro_slugs)) == len(ro_slugs), 'повтор RO-слага'


def php(v):
    if v is None:
        return 'null'
    if isinstance(v, int):
        return str(v)
    return "'" + str(v).replace('\\', '\\\\').replace("'", "\\'") + "'"


lines = [
    '<?php',
    '/**',
    ' * Новое дерево каталога и соответствие старым группам 1С.',
    ' * СГЕНЕРИРОВАНО tools/catalog/build_map.py из tools/catalog/catalog_tree.py — руками не править.',
    ' *',
    ' * @package Casa24',
    ' */',
    '',
    "if ( ! defined( 'ABSPATH' ) ) {",
    '\texit;',
    '}',
    '',
    'return array(',
    "\t'unsorted' => " + php(unsorted) + ',',
    "\t'tree'     => array(",
]
for key, parent, ru, ro, order in nodes:
    lines.append(f"\t\t{php(key)} => array( 'parent' => {php(parent)}, 'ru' => {php(ru)}, 'ro' => {php(ro)}, "
                 f"'slug_ro' => {php(slug_ro(ro))}, 'order' => {order} ),")
lines += ['\t),', "\t'groups'   => array("]
for tid in sorted(groups):
    lines.append(f'\t\t{tid} => {php(groups[tid])},')
lines += ['\t),', "\t'sort'     => array("]
for tid in sorted(SORT_GROUPS):
    rules, default = SORT_GROUPS[tid]
    lines.append(f"\t\t{tid} => array( {php(rules)}, {php(by_name[default] if default else None)} ),")
lines += ['\t),', "\t'rules'    => array("]
for rules, (_, _, items) in NAME_RULES.items():
    lines.append(f'\t\t{php(rules)} => array(')
    for target, needles in items:
        lines.append(f"\t\t\tarray( {php(target)}, array( {', '.join(php(n) for n in needles)} ) ),")
    lines.append('\t\t),')
lines += ['\t),', "\t'legacy'   => array("]
for slug in sorted(LEGACY):
    assert slug not in by_name.values(), ('старый ключ ещё в дереве', slug)
    lines.append(f'\t\t{php(slug)} => {php(by_name[LEGACY[slug]])},')
lines += ['\t),', "\t'redirects' => array("]
for tid in sorted(REDIRECTS):
    lines.append(f'\t\t{tid} => {php(by_name[REDIRECTS[tid]])},')
lines += ['\t),', "\t'names'    => array("]
for ru, key in by_name.items():
    lines.append(f'\t\t{php(ru)} => {php(key)},')
lines += ['\t),', "\t'products' => array("]
for pid in sorted(PRODUCT_OVERRIDES):
    target = PRODUCT_OVERRIDES[pid]
    lines.append(f'\t\t{pid} => {php(by_name[target] if target else unsorted)},')
lines += ['\t),', ');', '']

os.makedirs(os.path.dirname(OUT), exist_ok=True)
with open(OUT, 'w', encoding='utf-8', newline='\n') as f:
    f.write('\n'.join(lines))
print(f'{len(nodes)} категорий, {len(groups)} старых групп, {len(SORT_GROUPS)} групп на разбор -> {OUT}')
