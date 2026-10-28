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

	// Новое дерево каталога (mu-plugin casa24-catalog-map) — мега-меню.
	if ( function_exists( 'casa24_catalog_is_ready' ) && casa24_catalog_is_ready() ) {
		glozin_child_catalog_mega_menu();
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
 * Иконки разделов каталога (контурные 24×24, как у блока «Категории» на главной).
 *
 * Ключ — ключ раздела в tools/catalog/catalog_tree.py (транслит RU-названия).
 *
 * @return string[]
 */
function glozin_child_catalog_section_icons() {
	return array(
		'stroymaterialy'            => '<rect x="3" y="4" width="18" height="16" rx="1"/><path d="M3 9.3h18M3 14.7h18M9 4v5.3M15 9.3v5.4M9 14.7V20"/>',
		'kraski-i-laki'             => '<rect x="3" y="3" width="15" height="6" rx="1"/><path d="M18 6h2a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-8v3"/><rect x="10" y="14" width="4" height="7" rx="1"/>',
		'otdelochnye-materialy'     => '<path d="M4 4h12a2 2 0 0 1 2 2v12H6a2 2 0 0 1-2-2z"/><path d="M18 8h2v12H8v-2"/><path d="M8 8h6M8 12h6"/>',
		'dveri-i-zamki'                   => '<path d="M6 21V3h12v18"/><path d="M3 21h18"/><path d="M14.5 12h.01"/>',
		'mebelnaya-furnitura'             => '<rect x="3" y="5" width="18" height="14" rx="1"/><path d="M12 5v14M3 12h18"/><path d="M8 8.5h1M15 8.5h1M8 15.5h1M15 15.5h1"/>',
		'ruchnoy-instrument'              => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
		'elektroinstrument'               => '<rect x="2" y="6" width="12" height="6" rx="1"/><path d="M14 8h4l3 1-3 1h-4"/><path d="M6 12v7a1 1 0 0 0 1 1h3a1 1 0 0 0 1-1v-7"/>',
		'rashodniki-k-instrumentu'        => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="2"/><path d="M12 3v4M12 17v4M3 12h4M17 12h4"/>',
		'malyarnyy-i-plitochnyy-instrument' => '<rect x="3" y="3" width="15" height="6" rx="1"/><path d="M18 6h2a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-8v3"/><rect x="10" y="14" width="4" height="7" rx="1"/>',
		'spetsodezhda-i-sredstva-zaschity' => '<path d="M12 3l8 3v5c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6z"/><path d="M9 12l2 2 4-4"/>',
		'krepezh'                         => '<path d="M8 3h8l1 4H7z"/><path d="M10 7v11l2 3 2-3V7"/><path d="M10 11l4-1.5M10 15l4-1.5"/>',
		'elektrika'                       => '<path d="M13 2L4 14h7l-1 8 9-12h-7z"/>',
		'osveschenie'                     => '<path d="M9 18h6M10 21h4"/><path d="M12 3a6 6 0 0 0-4 10.5c.7.7 1 1.5 1 2.5h6c0-1 .3-1.8 1-2.5A6 6 0 0 0 12 3z"/>',
		'santehnika-i-vannaya'            => '<path d="M12 2.7l5.66 5.66a8 8 0 1 1-11.31 0z"/>',
		'sad-i-ogorod'                    => '<path d="M12 21V11"/><path d="M12 11C12 7 9 4 4 4c0 4 3 7 8 7z"/><path d="M12 14c0-3.5 2.5-6 7-6 0 3.5-2.5 6-7 6z"/><path d="M7 21h10"/>',
		'uborka-i-bytovaya-himiya'        => '<path d="M14 3l-4 9"/><path d="M6 12h10l2 9H4z"/><path d="M8 16v5M12 16v5"/>',
		'kuhnya-i-posuda'                 => '<path d="M4 11h16v2a7 7 0 0 1-7 7h-2a7 7 0 0 1-7-7z"/><path d="M2 11h20"/><path d="M9 7c0-1.5 1-1.5 1-3M13 7c0-1.5 1-1.5 1-3"/>',
		'dom-i-interer'                   => '<path d="M3 11l9-8 9 8"/><path d="M5 9.5V21h14V9.5"/><path d="M10 21v-6h4v6"/>',
		'igrushki-i-detskie-tovary'       => '<circle cx="12" cy="12" r="9"/><path d="M5.6 5.6c3.5 3.5 3.5 9.3 0 12.8M18.4 5.6c-3.5 3.5-3.5 9.3 0 12.8"/>',
		'sport-otdyh-piknik'              => '<circle cx="12" cy="12" r="9"/><path d="M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18M3 12h18"/>',
		'kantstovary'                     => '<path d="M17 3l4 4L8 20H4v-4z"/><path d="M14 6l4 4"/>',
	);
}

/**
 * Мега-меню «Каталог» (обратная связь 28.10.2026, по образцу владельца):
 * слева разделы-«оглавление», при наведении справа — группы жирным и
 * подгруппы под ними, колонками. На телефоне — раскрывающийся список:
 * нажатие на раздел открывает его группы (assets/ui-interactions.js).
 *
 * Панель раздела лежит внутри его пункта списка: на компьютере она
 * позиционируется справа от колонки разделов, на телефоне — под пунктом.
 */
function glozin_child_catalog_mega_menu() {
	$sections = casa24_catalog_menu_tree();
	$icons    = glozin_child_catalog_section_icons();
	$shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );
	$chevron  = '<svg class="ip-mega__chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="9 6 15 12 9 18"></polyline></svg>';

	echo '<ul class="ip-mega" data-ip-mega>';

	foreach ( $sections as $i => $section ) {
		$panel_id = 'ip-mega-' . $section['key'];
		$icon     = $icons[ $section['key'] ] ?? '<circle cx="12" cy="12" r="8"/>';

		printf(
			'<li class="ip-mega__section%1$s" data-ip-mega-section><a class="ip-mega__section-link" href="%2$s" aria-controls="%3$s"><span class="ip-mega__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">%4$s</svg></span><span class="ip-mega__section-name">%5$s</span>%6$s</a>',
			0 === $i ? ' is-active' : '',
			esc_url( $section['url'] ),
			esc_attr( $panel_id ),
			$icon, // phpcs:ignore WordPress.Security.EscapeOutput -- статичная разметка иконки из массива выше.
			esc_html( $section['name'] ),
			$chevron // phpcs:ignore WordPress.Security.EscapeOutput -- статичная разметка.
		);

		printf( '<div class="ip-mega__panel" id="%s">', esc_attr( $panel_id ) );
		printf(
			'<a class="ip-mega__title" href="%1$s">%2$s</a><a class="ip-mega__all" href="%1$s">%3$s</a>',
			esc_url( $section['url'] ),
			esc_html( $section['name'] ),
			esc_html__( 'Все товары раздела', 'glozin-child' )
		);
		echo '<div class="ip-mega__groups">';

		foreach ( $section['children'] as $group ) {
			echo '<div class="ip-mega__group">';
			printf( '<a class="ip-mega__group-title" href="%s">%s</a>', esc_url( $group['url'] ), esc_html( $group['name'] ) );

			if ( $group['children'] ) {
				echo '<ul class="ip-mega__links">';
				foreach ( $group['children'] as $item ) {
					printf( '<li><a href="%s">%s</a></li>', esc_url( $item['url'] ), esc_html( $item['name'] ) );
				}
				echo '</ul>';
			}

			echo '</div>';
		}

		echo '</div></div></li>';
	}

	printf(
		'<li class="ip-mega__catalog-all"><a href="%s">%s</a></li>',
		esc_url( $shop_url ),
		esc_html__( 'Весь каталог', 'glozin-child' )
	);
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
	// Старая группа 1С → сразу её категория в новом каталоге, без 301 (28.10.2026).
	if ( function_exists( 'casa24_catalog_redirect_key' ) && casa24_catalog_is_ready() ) {
		$term = get_term( (int) $term_id, 'product_cat' );
		$key  = $term instanceof WP_Term ? casa24_catalog_redirect_key( $term ) : null;
		$ids  = casa24_catalog_term_ids( (string) apply_filters( 'wpml_current_language', casa24_catalog_default_lang() ) );
		if ( $key && ! empty( $ids[ $key ] ) ) {
			$term_id = (int) $ids[ $key ];
		}
	}

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

/**
 * Один h1 на главной (проверка прода 27.10.2026, М-2): Glozin выводил два —
 * скрытое название сайта у логотипа своей шапки («Casa24») и заголовок
 * страницы в page-header («Главная»). Оба скрыты стилями Industrial Pro,
 * но поисковики видят разметку. Логотип Glozin — всегда <p>, page-header
 * на главной Industrial Pro не выводится; осмысленный h1 — в самом
 * шаблоне главной (template-homepage-industrial-pro.php).
 *
 * @return string
 */
function glozin_child_site_title_tag() {
	return 'p';
}
add_filter( 'glozin_site_branding_title_tag', 'glozin_child_site_title_tag' );

/**
 * Без page-header Glozin («Главная» в h1) на главной Industrial Pro.
 *
 * @param array $items Элементы page-header.
 * @return array
 */
function glozin_child_ip_home_page_header( $items ) {
	return is_page_template( 'template-homepage-industrial-pro.php' ) ? array() : $items;
}
add_filter( 'glozin_get_page_header_elements', 'glozin_child_ip_home_page_header', 100 );

/**
 * Админ-панель WordPress на витрине (видна только вошедшим): пункты слева
 * не помещались в строку, и блок «Привет, admin» + поиск переносился вниз,
 * поверх кружков шапки (обратная связь 27.10.2026). Убираем то, что на витрине
 * не нужно: поиск WordPress (ищет записи и страницы, дублирует наш поиск
 * товаров) и «Настроить» (Customizer Glozin, дизайн задаётся не там).
 * Остальные пункты ужимаются до иконок в industrial-pro.css.
 *
 * @param WP_Admin_Bar $wp_admin_bar Админ-панель.
 */
function glozin_child_ip_trim_admin_bar( $wp_admin_bar ) {
	if ( is_admin() || ! glozin_child_ip_chrome_enabled() ) {
		return;
	}

	$wp_admin_bar->remove_node( 'search' );
	$wp_admin_bar->remove_node( 'customize' );
}
// После ядра: поиск WordPress добавляет на приоритете 9999.
add_action( 'admin_bar_menu', 'glozin_child_ip_trim_admin_bar', 10000 );
