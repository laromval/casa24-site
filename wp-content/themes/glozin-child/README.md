# Glozin Child — Industrial Pro

Дочерняя тема casa24.md, Фаза 1 редизайна (`docs/tz-redesign-phase1.md`).
Разработана без доступа к staging/проду — только файлы темы, без БД.

## Что реализовано

- `style.css` — токены oklch (4.1), типографика Barlow Condensed/Work Sans (4.2),
  кнопки `.btn-accent`/`.btn-outline` (4.3), вёрстка главной с адаптивом 375/768px (4.4).
- `functions.php` — подключение стилей/шрифтов, `register_nav_menus('primary')`,
  `load_child_theme_textdomain()` для WPML, отключение `wp-sitemap-users` (п.10).
- `template-homepage-industrial-pro.php` — каркас главной на WP_Query/get_terms/
  wp_nav_menu, разные подборки «Топ продаж» и «Популярные товары» (п.3), alt на
  всех карточках товара (п.7), новый логотип в шапке (п.1).
- `assets/logo-mesterul-casei.svg` — функциональная заглушка логотипа.
- `TODO.md` — пункты 9 и 11, которые нельзя чинить без доступа к живому сайту.

## Что сделать руками в wp-admin, когда появится доступ к сайту

1. Активировать дочернюю тему (Внешний вид → Темы).
2. В редакторе страницы «Главная» выбрать шаблон «Industrial Pro — Главная».
3. Внешний вид → Меню: назначить меню на location «Главное меню (шапка)» (`primary`).
4. Настроить логотип/favicon через Customizer — заменить `logo-mesterul-casei.svg`
   на финальный логотип от дизайнера/media-agent.
5. Проверить WPML String Translation: все новые строки text domain `glozin-child`
   должны появиться в очереди на перевод RU/RO.
6. Проверить вывод на реальных товарах WooCommerce (фото, цены, total_sales,
   `_wc_average_rating`) — подборки не должны совпадать, alt не должен быть пустым.
7. Подключить реальные хуки корзины/избранного вместо разметки-заглушки (см.
   комментарии в шаблоне).

## Пункты раздела 5 ТЗ, НЕ закрытые этим кодом (нужен доступ к сайту/БД)

- №2 — цена «Model R14» и ревизия каталога дверей (content-agent).
- №5 — фото «Clasica KN SS 70 G» (media-agent/content-agent).
- №6 — перевод отзывов клиентов на RO (content-agent).
- №8 — опечатки в названиях товаров (content-agent).
- №9 — дубль `User-agent: *` в robots.txt (см. `TODO.md`, нужна диагностика).
- №11 — структура языковых URL RO/RU, конфигурация WPML (см. `TODO.md`).
