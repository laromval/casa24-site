<?php
/**
 * Industrial Pro — редизайн casa24.md, Фаза 1 (docs/tz-redesign-phase1.md).
 *
 * Перенесено из черновика glozin-child-industrial-pro в рабочую тему
 * подрядчика (docs/merge-plan-glozin-child.md). Всё, что меняет внешний
 * вид, срабатывает только на странице с шаблоном
 * template-homepage-industrial-pro.php — остальные страницы сайта и
 * логика подрядчика (чекаут, maib, поиск по штрихкоду) не затрагиваются.
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
 * Фолбэк для wp_nav_menu( 'primary' ), пока в wp-admin (Внешний вид → Меню)
 * ещё не назначено реальное меню на location 'primary'. Показывает
 * статические пункты из раздела 4.4 ТЗ (Каталог, Двери, Электроинструмент,
 * Сад и огород, Статьи), переводимые через text domain glozin-child.
 */
function glozin_child_primary_nav_fallback() {
	$items = array(
		home_url( '/shop/' )               => __( 'Каталог', 'glozin-child' ),
		home_url( '/product-category/usi/' ) => __( 'Двери', 'glozin-child' ),
		home_url( '/product-category/electrounelte/' ) => __( 'Электроинструмент', 'glozin-child' ),
		home_url( '/product-category/gradina/' ) => __( 'Сад и огород', 'glozin-child' ),
		home_url( '/blog/' )               => __( 'Статьи', 'glozin-child' ),
	);

	echo '<ul id="primary-menu-fallback" class="ip-nav__fallback-list">';
	foreach ( $items as $url => $label ) {
		echo '<li><a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a></li>';
	}
	echo '</ul>';
}

/**
 * Стили, шрифты и скрипты Industrial Pro — только на странице с шаблоном.
 *
 * Стиль родительской темы Glozin сюда не подключаем: родитель грузит его сам
 * (хэндл 'glozin-css'), повторное подключение дало бы двойную загрузку.
 * Раздел 4.2 ТЗ: Barlow Condensed 600/700 (заголовки), Work Sans 400/500/600 (текст),
 * плюс 900 у обоих — акцентный текст на баннерах карусели.
 */
function glozin_child_industrial_pro_assets() {
	if ( ! glozin_child_is_industrial_pro_page() ) {
		return;
	}

	$version = wp_get_theme()->get( 'Version' );

	wp_enqueue_style(
		'glozin-child-google-fonts',
		'https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;900&family=Work+Sans:wght@400;500;600;800;900&display=swap',
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

	// Переключатель светлая/тёмная тема (обратная связь 19.09.2026) — чистый JS, без зависимостей.
	wp_enqueue_script(
		'glozin-child-theme-toggle',
		get_stylesheet_directory_uri() . '/assets/theme-toggle.js',
		array(),
		$version,
		true
	);

	// Кнопка "наверх страницы", аккордеон футера, карусель (обратная связь 19.09.2026).
	wp_enqueue_script(
		'glozin-child-ui-interactions',
		get_stylesheet_directory_uri() . '/assets/ui-interactions.js',
		array(),
		$version,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'glozin_child_industrial_pro_assets', 30 );

/**
 * Класс ip-page на body — по нему industrial-pro.css прячет шапку и футер
 * родительской темы на странице Industrial Pro.
 *
 * @param string[] $classes Классы body.
 * @return string[]
 */
function glozin_child_industrial_pro_body_class( $classes ) {
	if ( glozin_child_is_industrial_pro_page() ) {
		$classes[] = 'ip-page';
	}

	return $classes;
}
add_filter( 'body_class', 'glozin_child_industrial_pro_body_class' );

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
