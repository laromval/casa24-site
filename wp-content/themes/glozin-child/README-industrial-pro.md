# Industrial Pro внутри темы glozin-child

Эта папка — **рабочая дочерняя тема сайта casa24.md** (код подрядчика CLICK WEB
DESIGN, скачан с хостинга 25.10.2026) **плюс редизайн Фазы 1 «Industrial Pro»**
(`docs/tz-redesign-phase1.md`). Её можно класть на staging вместо текущей
`wp-content/themes/glozin-child/` — логика подрядчика сохранена.
Разбор кода подрядчика и решения по слиянию — `docs/merge-plan-glozin-child.md`.

## Что от подрядчика (не менять без причины)

`functions.php`, `inc/*` (кроме `inc/industrial-pro.php`), `woocommerce/*`,
`template-parts/*`, `style.css`, `wpml-config.xml`. Это чекаут под Молдову,
фикс maib, поиск по штрихкоду, карусель категорий, доработки WPML/TelSender.
Единственная правка — убрана отладочная запись `[BADGE-*]` в `debug.log`.

## Что добавлено редизайном

- `inc/industrial-pro.php` — подключается последней строкой `functions.php`:
  меню `primary`, переключатель языка, шрифты/стили/скрипты, класс `ip-page`
  на body, отключение `wp-sitemap-users` (п.10 ТЗ, действует на весь сайт).
- `template-homepage-industrial-pro.php` — шаблон главной.
- `assets/industrial-pro.css`, `assets/*.js`, логотип и баннеры карусели.

**Визуальное срабатывает на странице с шаблоном «Industrial Pro — Главная», а при
опции `casa24_ip_sitewide` (wp option update casa24_ip_sitewide 1) — на всём сайте:
шапка/футер из `template-parts/industrial-pro/`, перекраска Glozin из
`assets/industrial-pro-site.css`.** Выключить: `wp option update casa24_ip_sitewide 0`. Пока шаблон не выбран, сайт выглядит и работает
точно как раньше. На этой странице шапка и футер родительской темы скрыты
(`#topbar`, `#site-header`, `#site-footer`), вместо них — свои.

## Выкладка на staging

С 25.10.2026 — по SSH (ключ `~/.ssh/casa24_tophost`, порт 49786, см. docs/priorities.md №28):

```bash
scp -P 49786 -i ~/.ssh/casa24_tophost <файл> casamd@195.178.106.95:public_html/staging.casa24.md/wp-content/themes/glozin-child/<путь>
```

Только папка `staging.casa24.md`. Рабочий сайт — только с явного «да» владельца.
Страницы на staging: RU `/главная-industrial-pro/` (ID 1093428), RO
`/ro/pagina-principala-industrial-pro/` (ID 1093430). Перевод строк — `languages/`,
сборка — `tools/i18n/README.md`.

## Как включить на сайте

1. Залить папку поверх `wp-content/themes/glozin-child/` на staging
   (`public_html/staging.casa24.md`). Файлы `README-industrial-pro.md`,
   `TODO-industrial-pro.md` можно не заливать.
2. Страницы → «Главная» → Атрибуты страницы → Шаблон: «Industrial Pro — Главная».
3. Внешний вид → Меню: назначить меню на «Главное меню (шапка)».
4. WPML → String Translation: перевести строки домена `glozin-child` на RO.

## Что ещё не доделано

- Раздел «Категории»: сейчас случайные мелкие категории и иконки-заглушки.
- Мобильное меню новой шапки сверить с мобильной панелью Glozin.
- Остальные страницы (каталог, карточка товара, корзина, чекаут) пока в
  старом оформлении.
- Пункты ТЗ, которые не решаются кодом темы, — `TODO-industrial-pro.md`.
