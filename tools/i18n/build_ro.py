"""Build languages/ro_RO.po and ro_RO.mo for the glozin-child text domain."""
import struct
import sys

out_dir = sys.argv[1]

RO = {
    "Фото товара Casa24": "Fotografia mărfii Casa24",
    "Casa24.md — на главную": "Casa24.md — pagina principală",
    "Позвонить: +373 68 338 813": "Sunați: +373 68 338 813",
    "+373 68 338 813": "+373 68 338 813",
    "Зарегистрироваться / Личный кабинет": "Înregistrare / Cabinet personal",
    "Избранное": "Favorite",
    "Переключить светлую/тёмную тему сайта": "Comută tema deschisă/întunecată a site-ului",
    "Корзина": "Coș",
    "Каталог": "Catalog",
    "Категории каталога": "Categoriile catalogului",
    "Искать товары…": "Caută mărfuri…",
    "Найти": "Caută",
    "Завершение дачного сезона": "Încheierea sezonului la vilă",
    "Всё для уборки": "Totul pentru curățenie",
    "Инвентарь": "Inventar",
    "Тачки": "Roabe",
    "Мешки": "Saci",
    "Вёдра": "Găleți",
    "Сохрани вкус лета": "Păstrează gustul verii",
    "Банки": "Borcane",
    "Пищевые вёдра": "Găleți alimentare",
    "Бочки пищевые": "Butoaie alimentare",
    "Дисконтная карта «Meșterul Casei» — максимальная скидка по карте на кассе": "Card-discount „Meșterul Casei” — reducere maximă cu cardul la casă",
    "Акции и предложения": "Promoții și oferte",
    "Выбор слайда": "Alege slide-ul",
    "Слайд %d": "Slide-ul %d",
    "Бесплатная доставка": "Livrare gratuită",
    "При заказе от 1000 лей.": "La comanda de la 1000 lei.",
    "Гарантия качества": "Garanția calității",
    "Высокое качество всех товаров.": "Calitate înaltă a tuturor mărfurilor.",
    "Удобная оплата": "Achitare comodă",
    "Любым удобным способом.": "Prin orice metodă comodă.",
    "Мы на связи": "Suntem la telefon",
    "Поддержка в один клик.": "Suport dintr-un clic.",
    "Категории": "Categorii",
    "Все категории": "Toate categoriile",
    "Товаров: %s": "Mărfuri: %s",
    "Категории товаров появятся здесь после подключения каталога WooCommerce.": "Categoriile de mărfuri vor apărea aici după conectarea catalogului WooCommerce.",
    "Топ продаж": "Top vânzări",
    "Смотреть всё": "Vezi tot",
    "Товары появятся здесь после подключения каталога WooCommerce.": "Mărfurile vor apărea aici după conectarea catalogului WooCommerce.",
    "Товары на скидках": "Mărfuri la reducere",
    "Сейчас нет товаров со скидкой — блок появится, как только в WooCommerce будет хотя бы один товар с указанной акционной ценой.": "Acum nu sunt mărfuri la reducere — blocul va apărea imediat ce în WooCommerce va exista cel puțin o marfă cu preț promoțional.",
    'WooCommerce не активен — блоки "Топ продаж" и "Товары на скидках" появятся после активации плагина.': "WooCommerce nu este activ — blocurile „Top vânzări” și „Mărfuri la reducere” vor apărea după activarea modulului.",
    "Meșterul Casei — Casa24.md": "Meșterul Casei — Casa24.md",
    "Полезная информация": "Informații utile",
    "Будьте на связи": "Rămâneți în legătură",
    "Интернет-магазин": "Magazin online",
    "Доставка и оплата": "Livrare și achitare",
    "Публичная оферта": "Ofertă publică",
    "Обмен и возврат товара": "Schimbul și returnarea mărfii",
    "Политика конфиденциальности": "Politica de confidențialitate",
    "Контакты": "Contacte",
    "Двери": "Uși",
    "Электроинструмент": "Instrumente electrice",
    "Сад и огород": "Grădină",
    "Хозтовары": "Mărfuri de uz casnic",
    "О компании": "Despre companie",
    "О нас": "Despre noi",
    "Статьи": "Articole",
    "Как нас найти": "Cum ne găsiți",
    "Все права защищены.": "Toate drepturile rezervate.",
    "Наверх страницы": "Sus",
    "Главное меню (шапка)": "Meniul principal (antet)",
    "Добавить «%s» в корзину": "Adaugă „%s” în coș",
    "Выбрать «%s»": "Alege „%s”",
}

strings = open(sys.argv[2], encoding="utf-8").read().splitlines()
missing = [s for s in strings if s not in RO]
if missing:
    sys.exit("Нет перевода для: " + repr(missing))

header = (
    "Project-Id-Version: glozin-child Industrial Pro\n"
    "Language: ro_RO\n"
    "MIME-Version: 1.0\n"
    "Content-Type: text/plain; charset=UTF-8\n"
    "Content-Transfer-Encoding: 8bit\n"
    "Plural-Forms: nplurals=3; plural=(n==1 ? 0 : (n==0 || (n%100 > 0 && n%100 < 20)) ? 1 : 2);\n"
)


def po_escape(s):
    return s.replace("\\", "\\\\").replace('"', '\\"').replace("\n", "\\n")


with open(out_dir + "/ro_RO.po", "w", encoding="utf-8", newline="\n") as po:
    po.write("# Румынский (Молдова) для строк Industrial Pro, text domain glozin-child.\n")
    po.write("# Лексика — по docs/glossary-ro-md.md. Источник для ro_RO.mo (собирается\n")
    po.write("# скриптом, при правке перевода пересобрать .mo).\n")
    po.write('msgid ""\nmsgstr ""\n')
    for line in header.splitlines(keepends=True):
        po.write('"' + po_escape(line) + '"\n')
    for s in strings:
        po.write('\nmsgid "%s"\nmsgstr "%s"\n' % (po_escape(s), po_escape(RO[s])))

# .mo (GNU gettext, little-endian)
entries = {"": header}
entries.update({s: RO[s] for s in strings})
keys = sorted(entries.keys(), key=lambda k: k.encode("utf-8"))
ids = b""
strs = b""
offsets = []
for k in keys:
    kb, vb = k.encode("utf-8"), entries[k].encode("utf-8")
    offsets.append((len(ids), len(kb), len(strs), len(vb)))
    ids += kb + b"\0"
    strs += vb + b"\0"
n = len(keys)
keystart = 7 * 4 + 16 * n
valuestart = keystart + len(ids)
koffsets, voffsets = [], []
for o1, l1, o2, l2 in offsets:
    koffsets += [l1, o1 + keystart]
    voffsets += [l2, o2 + valuestart]
data = struct.pack("Iiiiiii", 0x950412DE, 0, n, 7 * 4, 7 * 4 + n * 8, 0, 0)
data += struct.pack("%di" % len(koffsets), *koffsets)
data += struct.pack("%di" % len(voffsets), *voffsets)
data += ids + strs
open(out_dir + "/ro_RO.mo", "wb").write(data)
print("ok", n - 1, "strings")


# PHP-файл перевода (формат WordPress 6.5+, .l10n.php) — его читает
# glozin_child_theme_mo_translate(); .po/.mo остаются для редакторов переводов.
def php_str(s):
    return "'" + s.replace("\\", "\\\\").replace("'", "\'") + "'"


with open(out_dir + "/ro_RO.l10n.php", "w", encoding="utf-8", newline="\n") as php:
    php.write("<?php\n// Сгенерировано из ro_RO.po (glossary-ro-md.md). Не править вручную — правьте .po и пересоберите.\n")
    php.write("return array(\n\t'domain' => 'glozin-child',\n\t'language' => 'ro_RO',\n\t'messages' => array(\n")
    for s in strings:
        php.write("\t\t%s => %s,\n" % (php_str(s), php_str(RO[s])))
    php.write("\t),\n);\n")
print("php ok")
