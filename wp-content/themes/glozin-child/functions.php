<?php
/**
 * Glozin Child — Industrial Pro
 * functions.php
 *
 * Скелет дочерней темы для casa24.md (docs/tz-redesign-phase1.md).
 * Не подключается к боевой БД/хостингу — только файлы темы.
 *
 * @package Glozin_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Прямой доступ к файлу запрещён.
}

/**
 * Подключение родительских и дочерних стилей + шрифтов Google Fonts.
 *
 * Раздел 4.2 ТЗ: Barlow Condensed 600/700 (заголовки), Work Sans 400/500/600 (текст).
 * preconnect на fonts.gstatic.com — для ускорения загрузки шрифтов (см. dev-agent.md,
 * зона ответственности "производительность").
 */
function glozin_child_enqueue_assets() {

	// Стиль родительской темы Glozin.
	wp_enqueue_style(
		'glozin-style',
		get_template_directory_uri() . '/style.css',
		array(),
		wp_get_theme( 'glozin' )->get( 'Version' )
	);

	// Google Fonts: Barlow Condensed + Work Sans (раздел 4.2 ТЗ).
	wp_enqueue_style(
		'glozin-child-google-fonts',
		'https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700&family=Work+Sans:wght@400;500;600&display=swap',
		array(),
		null
	);

	// Стиль дочерней темы — подключается последним, зависит от родительского.
	wp_enqueue_style(
		'glozin-child-style',
		get_stylesheet_directory_uri() . '/style.css',
		array( 'glozin-style', 'glozin-child-google-fonts' ),
		wp_get_theme()->get( 'Version' )
	);
}
add_action( 'wp_enqueue_scripts', 'glozin_child_enqueue_assets' );

/**
 * preconnect на fonts.gstatic.com для ускорения загрузки шрифтов
 * (fonts.googleapis.com обрабатывается автоматически WordPress core
 * resource hints, но статический файл шрифтов отдаётся с gstatic.com,
 * поэтому preconnect добавляем вручную).
 *
 * @param array  $urls          Список URL для resource hints.
 * @param string $relation_type Тип связи ('preconnect', 'dns-prefetch' и т.д.).
 * @return array
 */
function glozin_child_resource_hints( $urls, $relation_type ) {
	if ( 'preconnect' === $relation_type ) {
		$urls[] = array(
			'href' => 'https://fonts.gstatic.com',
			'crossorigin',
		);
	}

	return $urls;
}
add_filter( 'wp_resource_hints', 'glozin_child_resource_hints', 10, 2 );

/**
 * Text domain дочерней темы — чтобы все __()/_e() в новых PHP-шаблонах
 * можно было переводить (в т.ч. через WPML String Translation) без
 * потери мультиязычности RU/RO (ТЗ раздел 3: "Мультиязычность (WPML,
 * RU/RO) должна продолжать работать... после редизайна").
 */
function glozin_child_load_textdomain() {
	load_child_theme_textdomain(
		'glozin-child',
		get_stylesheet_directory() . '/languages'
	);
}
add_action( 'after_setup_theme', 'glozin_child_load_textdomain' );

/**
 * Фикс пункта 10 раздела 5 ТЗ: отключить публичный wp-sitemap-users
 * (раскрывает логины пользователей через /wp-sitemap-users-1.xml).
 * Стандартный сниппет через фильтр wp_sitemaps_add_provider — не зависит
 * от специфики сайта, безопасен для коммита "как есть".
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

/*
 * -----------------------------------------------------------------------
 * TODO — НЕ решается кодом дочерней темы, см. wp-content/themes/glozin-child/TODO.md
 * -----------------------------------------------------------------------
 * Пункт 9 раздела 5 ТЗ (дубль "User-agent: *" в robots.txt) и пункт 11
 * (структура языковых URL RO/RU, конфигурация WPML) сознательно НЕ
 * реализованы здесь — требуют диагностики на живом сайте/доступа к
 * admin. Подробности и план действий — в TODO.md рядом с этим файлом.
 */
