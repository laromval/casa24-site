<?php
/**
 * Шапка Industrial Pro: верхняя строка (логотип, телефон, кнопки, язык)
 * и закреплённая строка «Каталог + поиск».
 *
 * Выводится на wp_body_open (inc/industrial-pro.php) — на странице с шаблоном
 * «Industrial Pro — Главная» и, если включено (опция casa24_ip_sitewide), на
 * всех страницах сайта вместо шапки Glozin. Обёртка .industrial-pro нужна
 * стилям (селекторы «.industrial-pro …»); display: contents у .ip-site-header —
 * чтобы position: sticky у .ip-navrow работал на всю высоту страницы.
 *
 * @package Glozin Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="industrial-pro ip-site-header">
	<!-- =================================================================
	     1. Верхняя строка: логотип, телефон/часы, избранное/корзина,
	     язык, переключатель светлая/тёмная тема.

	     ВАЖНО для деплоя (см. TODO.md): в реальной теме сайта эта
	     разметка должна заменить существующий header.php/inc/header,
	     а не просто вывестись НИЖЕ него — иначе на сайте будут два
	     заголовка друг под другом. get_header() ниже по коду вызывает
	     заголовок ТЕКУЩЕЙ активной темы, который на сайте уже содержит
	     свою шапку — при переносе на прод это надо свести в одно место.
	     ================================================================= -->
	<div class="ip-topbar">
		<div class="container">
			<?php
			/*
			 * Логотип и кнопки — во вложенном .ip-topbar__row, ЖЁСТКО одной строкой
			 * (flex-wrap: nowrap), телефон/часы — отдельным блоком под ней (не в
			 * общем flex-ряду с ними). Раньше все три блока были прямыми children
			 * одного flex-контейнера с wrap — при сужении окна порядок переноса не
			 * контролировался явно, и было видно, как кнопки "прыгают" между
			 * строкой логотипа и отдельной строкой на разных промежуточных ширинах
			 * (обратная связь 22.09.2026).
			 */
			?>
			<div class="ip-topbar__row">
				<a class="ip-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php esc_attr_e( 'Casa24.md — на главную', 'glozin-child' ); ?>">
				<?php
				/*
				 * Логотип-заглушка (не финальный дизайн, см. README.md). Заменяет прежний
				 * логотип "Larom Val" — пункт 1 раздела 5 ТЗ. Здесь, в отличие от футера,
				 * SVG встроен прямо в разметку (не через <img>), чтобы строка "MEȘTERUL
				 * CASEI" могла перекрашиваться через CSS-токен --topbar-text в зависимости
				 * от темы (обратная связь 19.09.2026: белая шапка -> тёмный текст цвета
				 * второй строки, тёмная шапка -> светлый текст).
				 */
				?>
				<svg width="220" height="47" viewBox="0 0 340 72" xmlns="http://www.w3.org/2000/svg" role="img" aria-hidden="true">
					<rect x="0" y="4" width="64" height="64" rx="12" fill="oklch(0.27 0.02 255)"/>
					<g transform="translate(12,16)" fill="none" stroke="oklch(0.70 0.19 45)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
						<path d="M2 20 L20 4 L38 20"/>
						<path d="M7 17 V38 H33 V17"/>
						<rect x="17" y="25" width="6" height="13" fill="oklch(0.27 0.02 255)" stroke="none"/>
					</g>
					<text class="ip-logo__line1" x="80" y="34" font-family="'Roboto Condensed', 'Arial Narrow', Arial, sans-serif" font-weight="700" font-size="24" letter-spacing="1.2">MEȘTERUL CASEI</text>
					<text x="80" y="56" font-family="'Roboto Condensed', 'Arial Narrow', Arial, sans-serif" font-weight="600" font-size="16" letter-spacing="2.8" fill="oklch(0.70 0.19 45)">CASA24.MD</text>
				</svg>
			</a>

			<?php /* Телефон — в одной строке с логотипом; часы работы убраны, чтобы шапка была на строку ниже (обратная связь 25.10.2026). */ ?>
			<a class="ip-topbar__phone" href="tel:+37368338813" aria-label="<?php esc_attr_e( 'Позвонить: +373 68 338 813', 'glozin-child' ); ?>">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
				<span class="ip-topbar__phone-number"><?php esc_html_e( '+373 68 338 813', 'glozin-child' ); ?></span>
			</a>

			<div class="ip-topbar__actions">
				<?php
				/*
				 * Порядок в этом блоке — по обратной связи 19.09.2026:
				 * Зарегистрироваться → Избранное → переключатель темы → Корзина → RU/RO.
				 */
				?>
				<?php /* Не вошёл — окно входа Glozin (#login-modal), вошёл — страница личного кабинета. */ ?>
				<a href="<?php echo esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/my-account/' ) ); ?>" class="ip-icon-btn"<?php echo is_user_logged_in() ? '' : ' data-toggle="modal" data-target="login-modal"'; ?> aria-label="<?php esc_attr_e( 'Зарегистрироваться / Личный кабинет', 'glozin-child' ); ?>">
					<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
						<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
						<circle cx="12" cy="7" r="4"></circle>
					</svg>
				</a>

				<?php /* Избранное — плагин WCBoost Wishlist, счётчик обновляется его AJAX-фрагментами. */ ?>
				<a href="<?php echo esc_url( glozin_child_wishlist_url() ); ?>" class="ip-icon-btn" aria-label="<?php esc_attr_e( 'Избранное', 'glozin-child' ); ?>">
					<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
						<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z"></path>
					</svg>
					<?php glozin_child_header_counter( 'wishlist', glozin_child_wishlist_count() ); ?>
				</a>

				<button type="button" class="ip-theme-toggle" data-ip-theme-toggle aria-label="<?php esc_attr_e( 'Переключить светлую/тёмную тему сайта', 'glozin-child' ); ?>">
					<svg class="ip-theme-toggle__icon ip-theme-toggle__icon--sun" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="4"></circle><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"></path></svg>
					<svg class="ip-theme-toggle__icon ip-theme-toggle__icon--moon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
				</button>

				<?php /* Корзина — открывает боковую панель корзины Glozin (#cart-panel); без JS ведёт на страницу корзины. */ ?>
				<a href="<?php echo function_exists( 'wc_get_cart_url' ) ? esc_url( wc_get_cart_url() ) : '#'; ?>" class="ip-icon-btn" data-toggle="off-canvas" data-target="cart-panel" aria-label="<?php esc_attr_e( 'Корзина', 'glozin-child' ); ?>">
					<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
						<circle cx="9" cy="21" r="1"></circle>
						<circle cx="20" cy="21" r="1"></circle>
						<path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
					</svg>
					<?php glozin_child_header_counter( 'cart', glozin_child_cart_count() ); ?>
				</a>

				<?php glozin_child_language_switcher(); ?>
			</div>
			</div>
		</div>
	</div>

	<!-- =================================================================
	     2. Вторая строка: «Каталог» (подкатегории — выпадающий список)
	     слева, поиск по товарам по центру.
	     ================================================================= -->
	<div class="ip-navrow">
		<div class="container">
			<div class="ip-catalog">
				<button type="button" class="ip-catalog__toggle" aria-expanded="false" aria-haspopup="true">
					<?php esc_html_e( 'Каталог', 'glozin-child' ); ?>
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="6 9 12 15 18 9"></polyline></svg>
				</button>
				<nav class="ip-catalog__menu" aria-label="<?php esc_attr_e( 'Категории каталога', 'glozin-child' ); ?>">
					<?php
					wp_nav_menu(
						array(
							'theme_location' => 'primary',
							'container'      => false,
							'items_wrap'     => '<ul id="%1$s" class="%2$s">%3$s</ul>',
							'fallback_cb'    => 'glozin_child_primary_nav_fallback',
						)
					);
					?>
				</nav>
			</div>

			<?php
			/*
			 * Классы header-search__form / gz-instant-search__form и data-toggle —
			 * как у формы поиска в шапке Glozin: скрипт glozin-addons подхватывает
			 * её, показывает живые подсказки в окне #search-modal, а поиск по
			 * штрихкоду подрядчика (inc/plugins/barcode-search.php) работает и тут.
			 */
			?>
			<form class="ip-search header-search__form gz-instant-search__form" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>" data-toggle="modal" data-target="search-modal">
				<input type="text" name="s" class="ip-search__input header-search__field gz-instant-search__field" placeholder="<?php esc_attr_e( 'Искать товары…', 'glozin-child' ); ?>" autocomplete="off">
				<input type="hidden" name="post_type" class="header-search__post-type" value="product">
				<button type="submit" class="ip-search__submit" aria-label="<?php esc_attr_e( 'Найти', 'glozin-child' ); ?>">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
				</button>
			</form>
		</div>
	</div>
</div>
