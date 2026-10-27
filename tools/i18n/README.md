# Переводы строк темы glozin-child (Industrial Pro) на RO

Строки шаблона главной и `inc/industrial-pro.php` пишутся по-русски с text domain
`glozin-child`. Румынский перевод лежит в `wp-content/themes/glozin-child/languages/`:

- `ro_RO.po` / `ro_RO.mo` — для редакторов переводов (Poedit и т.п.);
- `ro_RO.l10n.php` — то, что реально читает тема (`glozin_child_theme_mo_translate()`
  в `inc/industrial-pro.php`). WPML String Translation перехватывает загрузку .mo,
  а `MO::import_from_file()` на хостинге в веб-режиме файл не читал — поэтому PHP-массив.

Как обновить после изменения текстов в шаблоне (из корня репозитория):

```bash
python -I tools/i18n/extract_strings.py . strings.txt
python -I tools/i18n/build_ro.py wp-content/themes/glozin-child/languages strings.txt
```

`build_ro.py` остановится и перечислит строки, для которых нет перевода в словаре `RO`
внутри скрипта, — добавить их туда по `docs/glossary-ro-md.md` и запустить снова.
