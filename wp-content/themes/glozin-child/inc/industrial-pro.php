<?php
/**
 * Industrial Pro — редизайн casa24.md, Фаза 1 (docs/tz-redesign-phase1.md).
 *
 * Перенесено из черновика glozin-child-industrial-pro в рабочую тему
 * подрядчика (docs/merge-plan-glozin-child.md). Внешний вид меняется на
 * странице с шаблоном template-homepage-industrial-pro.php, а при включённой
 * опции casa24_ip_sitewide — шапка/футер Industrial Pro и перекраска Glozin
 * (assets/industrial-pro-site.css) на всех страницах. Логика подрядчика
 * (чекаут, maib, поиск по штрихкоду) не затрагивается.
 *
 * @package Glozin Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Страница собрана на шаблоне Industrial Pro?
 *
 * @return bool
 */
function glozin_child_is_industrial_pro_page() {
	return is_page_template( 'template-homepage-industrial-pro.php' );
}

/**
 * Industrial Pro на всём сайте (шапка, футер, перекраска страниц Glozin)?
 *
 * Включается опцией: wp option update casa24_ip_sitewide 1 (выключается — 0).
 * Сначала — только на staging (26.10.2026), на проде — после приёмки.
 *
 * @return bool
 */
function glozin_child_ip_sitewide() {
	return (bool) get_option( 'casa24_ip_sitewide' ) && ! is_admin();
}

/**
 * На странице выводятся шапка и футер Industrial Pro?
 *
 * @return bool
 */
function glozin_child_ip_chrome_enabled() {
	return glozin_child_is_industrial_pro_page() || glozin_child_ip_sitewide();
}

/**
 * Шапка Industrial Pro — сразу после <body>.
 */
function glozin_child_ip_render_header() {
	if ( glozin_child_ip_chrome_enabled() ) {
		get_template_part( 'template-parts/industrial-pro/header' );
	}
}
add_action( 'wp_body_open', 'glozin_child_ip_render_header', 5 );

/**
 * Футер Industrial Pro — в начале wp_footer, до скриптов и модальных окон Glozin.
 */
function glozin_child_ip_render_footer() {
	if ( glozin_child_ip_chrome_enabled() ) {
		get_template_part( 'template-parts/industrial-pro/footer' );
	}
}
add_action( 'wp_footer', 'glozin_child_ip_render_footer', 1 );

/**
 * Регистрация location 'primary' для главного меню шапки.
 *
 * template-homepage-industrial-pro.php вызывает wp_nav_menu( array( 'theme_location' => 'primary', ... ) ).
 * Если родительская тема Glozin уже регистрирует 'primary', WordPress просто
 * объединит массивы locations.
 */
function glozin_child_register_nav_menus() {
	register_nav_menus(
		array(
			'primary' => __( 'Главное меню (шапка)', 'glozin-child' ),
		)
	);
}
add_action( 'after_setup_theme', 'glozin_child_register_nav_menus' );

/**
 * Список «Каталог» в шапке: все разделы верхнего уровня WooCommerce.
 *
 * Раньше здесь было меню WordPress 'primary' — его на сайте так и не
 * назначили, и выводились 5 запасных пунктов (обратная связь 26.10.2026).
 * Теперь — категории товаров верхнего уровня на текущем языке WPML, по
 * алфавиту, с числом товаров (с учётом подкатегорий), без пустых и без
 * разделов, где все товары скрыты (нет в наличии). Первая
 * строка — весь каталог.
 */
function glozin_child_catalog_menu() {
	if ( ! taxonomy_exists( 'product_cat' ) ) {
		return;
	}

	$terms = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'parent'     => 0,
			'hide_empty' => true,
			'orderby'    => 'name',
			'order'      => 'ASC',
			'exclude'    => array( (int) get_option( 'default_product_cat', 0 ) ),
		)
	);

	if ( is_wp_error( $terms ) ) {
		return;
	}

	$shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );

	echo '<ul class="ip-catalog__list">';
	printf(
		'<li class="ip-catalog__item ip-catalog__item--all"><a href="%1$s">%2$s</a></li>',
		esc_url( $shop_url ),
		esc_html__( 'Все категории', 'glozin-child' )
	);

	foreach ( $terms as $term ) {
		$count = get_term_meta( $term->term_id, 'product_count_product_cat', true );

		// Пересчёт WooCommerce учитывает только видимые товары. 0 — все товары
		// раздела скрыты (обмен с 1С скрывает их при нулевом остатке), внутри
		// было бы пусто, как в «Спотах» (26.10.2026) — раздел не показываем.
		if ( '0' === (string) $count ) {
			continue;
		}

		$count = '' !== $count ? (int) $count : (int) $term->count;

		// Счётчик WooCommerce бывает 0 при товарах в подкатегориях — тогда без числа.
		printf(
			'<li class="ip-catalog__item"><a href="%1$s"><span class="ip-catalog__name">%2$s</span>%3$s</a></li>',
			esc_url( get_term_link( $term ) ),
			esc_html( $term->name ),
			$count > 0 ? '<span class="ip-catalog__count">' . esc_html( number_format_i18n( $count ) ) . '</span>' : ''
		);
	}

	echo '</ul>';
}

/**
 * Стили, шрифты и скрипты Industrial Pro — только на странице с шаблоном.
 *
 * Стиль родительской темы Glozin сюда не подключаем: родитель грузит его сам
 * (хэндл 'glozin-css'), повторное подключение дало бы двойную загрузку.
 * Раздел 4.2 ТЗ: шрифт заголовков (названия категорий, цены, кнопки, навигация)
 * — Roboto Condensed 600/700 вместо Barlow Condensed из ТЗ: в Barlow нет
 * кириллицы, русские заголовки выводились запасным шрифтом (решение
 * владельца 25.10.2026). Work Sans 400/500/600 — основной текст,
 * плюс 900 у обоих — акцентный текст на баннерах карусели.
 */
function glozin_child_industrial_pro_assets() {
	if ( ! glozin_child_ip_chrome_enabled() ) {
		return;
	}

	// Версия файлов — время изменения: после перезаливки браузер и кэш
	// LiteSpeed сразу берут новый файл (Version темы подрядчика всегда 1.0.0).
	wp_enqueue_style(
		'glozin-child-google-fonts',
		'https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@600;700;900&family=Work+Sans:wght@400;500;600;800;900&display=swap',
		array(),
		null
	);

	// После стиля дочерней темы подрядчика — чтобы наши правила имели приоритет.
	wp_enqueue_style(
		'glozin-child-industrial-pro',
		get_stylesheet_directory_uri() . '/assets/industrial-pro.css',
		array( 'glozin-child-style', 'glozin-child-google-fonts' ),
		filemtime( get_stylesheet_directory() . '/assets/industrial-pro.css' )
	);

	// Перекраска страниц Glozin (каталог, товар, корзина, чекаут, кабинет)
	// под Industrial Pro — переменные --gz-* и точечные правила.
	if ( glozin_child_ip_sitewide() ) {
		wp_enqueue_style(
			'glozin-child-industrial-pro-site',
			get_stylesheet_directory_uri() . '/assets/industrial-pro-site.css',
			array( 'glozin-child-industrial-pro' ),
			filemtime( get_stylesheet_directory() . '/assets/industrial-pro-site.css' )
		);
	}

	// Переключатель светлая/тёмная тема (обратная связь 19.09.2026) — чистый JS, без зависимостей.
	wp_enqueue_script(
		'glozin-child-theme-toggle',
		get_stylesheet_directory_uri() . '/assets/theme-toggle.js',
		array(),
		filemtime( get_stylesheet_directory() . '/assets/theme-toggle.js' ),
		true
	);

	// Кнопка "наверх страницы", аккордеон футера, карусель (обратная связь 19.09.2026).
	wp_enqueue_script(
		'glozin-child-ui-interactions',
		get_stylesheet_directory_uri() . '/assets/ui-interactions.js',
		array(),
		filemtime( get_stylesheet_directory() . '/assets/ui-interactions.js' ),
		true
	);
}
add_action( 'wp_enqueue_scripts', 'glozin_child_industrial_pro_assets', 30 );

/**
 * Классы body: ip-page — страница с шаблоном Industrial Pro (без заголовка
 * страницы Glozin, контент во всю ширину); ip-chrome — шапка и футер
 * Industrial Pro вместо Glozin; ip-site — перекраска страниц Glozin.
 *
 * @param string[] $classes Классы body.
 * @return string[]
 */
function glozin_child_industrial_pro_body_class( $classes ) {
	if ( glozin_child_is_industrial_pro_page() ) {
		$classes[] = 'ip-page';
	}

	if ( glozin_child_ip_chrome_enabled() ) {
		$classes[] = 'ip-chrome';
	}

	if ( glozin_child_ip_sitewide() ) {
		$classes[] = 'ip-site';
	}

	return $classes;
}
add_filter( 'body_class', 'glozin_child_industrial_pro_body_class' );

/**
 * Ссылка на категорию товаров по её ID — для кнопок на баннерах карусели.
 *
 * Через ID, а не жёстко прописанный адрес casa24.md: на staging ссылка
 * ведёт на staging, а на RO-версии WPML подставляет румынскую категорию.
 *
 * @param int $term_id ID категории product_cat (на RU-версии сайта).
 * @return string URL категории или '#', если категория не найдена.
 */
function glozin_child_category_url( $term_id ) {
	$term_id = (int) apply_filters( 'wpml_object_id', $term_id, 'product_cat', true );
	$link    = get_term_link( $term_id, 'product_cat' );

	return is_wp_error( $link ) ? '#' : $link;
}

/**
 * Ссылка на страницу избранного плагина WCBoost Wishlist (на текущем языке WPML).
 *
 * @return string
 */
function glozin_child_wishlist_url() {
	$page_id = (int) get_option( 'wcboost_wishlist_page_id' );

	if ( $page_id ) {
		$page_id = (int) apply_filters( 'wpml_object_id', $page_id, 'page', true );
		$link    = get_permalink( $page_id );

		if ( $link ) {
			return $link;
		}
	}

	return home_url( '/wishlist/' );
}

/**
 * Счётчик в кружке шапки (корзина / избранное).
 *
 * Классы — те же, что у шапки Glozin (header-counter header-cart__counter,
 * header-counter header-wishlist__counter): WooCommerce и WCBoost Wishlist
 * обновляют счётчики через AJAX-фрагменты именно по этим селекторам
 * ('span.header-cart__counter', 'span.header-wishlist__counter') и заменяют
 * span целиком — поэтому стили в industrial-pro.css тоже повешены на них.
 *
 * @param string $type  'cart' или 'wishlist'.
 * @param int    $count Количество.
 */
function glozin_child_header_counter( $type, $count ) {
	$classes = 'header-counter header-' . $type . '__counter';

	if ( ! $count ) {
		$classes .= ' empty-counter';
	}

	printf( '<span class="%1$s">%2$s</span>', esc_attr( $classes ), esc_html( $count ) );
}

/**
 * Количество товаров в корзине.
 *
 * @return int
 */
function glozin_child_cart_count() {
	return ( function_exists( 'WC' ) && WC()->cart ) ? (int) WC()->cart->get_cart_contents_count() : 0;
}

/**
 * Количество товаров в избранном (WCBoost Wishlist).
 *
 * @return int
 */
function glozin_child_wishlist_count() {
	if ( ! class_exists( '\WCBoost\Wishlist\Helper' ) ) {
		return 0;
	}

	$wishlist = \WCBoost\Wishlist\Helper::get_wishlist();

	return $wishlist ? (int) $wishlist->count_items() : 0;
}

/**
 * Кнопка «в корзину» в карточке товара.
 *
 * Простой товар в наличии — стандартная AJAX-кнопка WooCommerce
 * (add_to_cart_button ajax_add_to_cart): товар добавляется без перезагрузки,
 * Glozin после этого сам открывает боковую панель корзины. Вариативный или
 * отсутствующий товар — ссылка на карточку, там выбирают вариант.
 *
 * @param WC_Product $product Товар.
 */
function glozin_child_card_cart_button( $product ) {
	$ajax  = $product->is_type( 'simple' ) && $product->is_purchasable() && $product->is_in_stock();
	$title = $product->get_name();

	$classes = 'ip-product-card__cart-btn';
	if ( $ajax ) {
		$classes .= ' add_to_cart_button ajax_add_to_cart';
		/* translators: %s: название товара */
		$label = sprintf( __( 'Добавить «%s» в корзину', 'glozin-child' ), $title );
	} else {
		/* translators: %s: название товара */
		$label = sprintf( __( 'Выбрать «%s»', 'glozin-child' ), $title );
	}

	printf(
		'<a href="%1$s" class="%2$s" data-product_id="%3$d" data-product_sku="%4$s" data-quantity="1" aria-label="%5$s" rel="nofollow">',
		esc_url( $ajax ? $product->add_to_cart_url() : $product->get_permalink() ),
		esc_attr( $classes ),
		(int) $product->get_id(),
		esc_attr( $product->get_sku() ),
		esc_attr( $label )
	);
	echo '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>';
	echo '</a>';
}

/**
 * Переключатель языка RU/RO в верхней строке шапки (обратная связь 19.09.2026).
 * Использует WPML, если он активен на сайте; иначе — статический фолбэк.
 */
function glozin_child_language_switcher() {
	echo '<div class="ip-lang-switch">';

	if ( function_exists( 'icl_get_languages' ) ) {
		$languages = icl_get_languages( 'skip_missing=0' );
		foreach ( (array) $languages as $lang_code => $lang ) {
			printf(
				'<a href="%1$s" class="%2$s" lang="%3$s" hreflang="%3$s">%4$s</a>',
				esc_url( $lang['url'] ),
				! empty( $lang['active'] ) ? 'is-active' : '',
				esc_attr( $lang_code ),
				esc_html( strtoupper( $lang_code ) )
			);
		}
	} else {
		printf( '<a href="%1$s" class="is-active" lang="ru">%2$s</a>', esc_url( home_url( '/' ) ), 'RU' );
		printf( '<a href="%1$s" lang="ro">%2$s</a>', esc_url( home_url( '/ro/' ) ), 'RO' );
	}

	echo '</div>';
}

/**
 * preconnect на fonts.gstatic.com — файлы шрифтов отдаются оттуда.
 *
 * @param array  $urls          Список URL для resource hints.
 * @param string $relation_type Тип связи ('preconnect', 'dns-prefetch' и т.д.).
 * @return array
 */
function glozin_child_resource_hints( $urls, $relation_type ) {
	if ( 'preconnect' === $relation_type && glozin_child_is_industrial_pro_page() ) {
		$urls[] = array(
			'href' => 'https://fonts.gstatic.com',
			'crossorigin',
		);
	}

	return $urls;
}
add_filter( 'wp_resource_hints', 'glozin_child_resource_hints', 10, 2 );

/**
 * Text domain glozin-child для новых строк Industrial Pro (WPML String
 * Translation). Строки подрядчика остаются в домене 'glozin' — их не трогаем,
 * чтобы не потерять готовые переводы.
 */
function glozin_child_load_textdomain() {
	load_child_theme_textdomain(
		'glozin-child',
		get_stylesheet_directory() . '/languages'
	);
}
add_action( 'after_setup_theme', 'glozin_child_load_textdomain' );

/**
 * Перевод строк glozin-child из languages/{locale}.l10n.php самой темы.
 *
 * WPML String Translation перехватывает load_textdomain (override_load_textdomain)
 * и подставляет свои файлы — наш ro_RO.mo при этом не применялся, на /ro/
 * строки оставались русскими (staging, 25.10.2026). Фильтр берёт перевод из
 * файла темы, а если строки там нет — оставляет то, что дали WP/WPML
 * (например, перевод, добавленный позже в String Translation).
 *
 * @param string $translation Перевод, предложенный WordPress/WPML.
 * @param string $text        Исходная (русская) строка.
 * @return string
 */
function glozin_child_theme_mo_translate( $translation, $text ) {
	static $catalogs = array();

	$locale = determine_locale();
	if ( 0 === strpos( $locale, 'ru' ) ) {
		return $translation; // Исходные строки уже на русском.
	}

	if ( ! isset( $catalogs[ $locale ] ) ) {
		// PHP-файл перевода (формат WP 6.5+): MO::import_from_file() на
		// хостинге в веб-режиме .mo не читал (в wp-cli — читал).
		$file                = get_stylesheet_directory() . '/languages/' . $locale . '.l10n.php';
		$data                = is_readable( $file ) ? include $file : null;
		$catalogs[ $locale ] = ( is_array( $data ) && isset( $data['messages'] ) ) ? $data['messages'] : array();
	}

	return isset( $catalogs[ $locale ][ $text ] ) ? $catalogs[ $locale ][ $text ] : $translation;
}
add_filter( 'gettext_glozin-child', 'glozin_child_theme_mo_translate', 20, 2 );

/**
 * Пункт 10 раздела 5 ТЗ: отключить публичный wp-sitemap-users
 * (раскрывает логины пользователей через /wp-sitemap-users-1.xml).
 * Действует на весь сайт.
 *
 * @param WP_Sitemaps_Provider $provider Экземпляр провайдера карты сайта.
 * @param string               $name     Имя провайдера (например, 'users').
 * @return WP_Sitemaps_Provider|false
 */
function glozin_child_disable_users_sitemap( $provider, $name ) {
	if ( 'users' === $name ) {
		return false;
	}

	return $provider;
}
add_filter( 'wp_sitemaps_add_provider', 'glozin_child_disable_users_sitemap', 10, 2 );
