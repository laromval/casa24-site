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
 * Регистрация location 'primary' для главного меню шапки.
 *
 * template-homepage-industrial-pro.php вызывает wp_nav_menu( array( 'theme_location' => 'primary', ... ) ).
 * Родительская тема Glozin, скорее всего, уже регистрирует свою собственную
 * location — эта регистрация безвредна и в этом случае (WordPress просто
 * объединяет массивы locations), а если родитель этого не делает, без неё
 * wp_nav_menu('primary') не покажет ничего, даже если меню назначено.
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
 * Сад и огород, Статьи), переводимые через text domain glozin-child, чтобы
 * шаблон не выглядел пустым до ручной настройки меню.
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
		'https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700&family=Work+Sans:wght@400;500;600;800&display=swap',
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

	// Переключатель светлая/тёмная тема (обратная связь 19.09.2026) — чистый JS, без зависимостей.
	wp_enqueue_script(
		'glozin-child-theme-toggle',
		get_stylesheet_directory_uri() . '/assets/theme-toggle.js',
		array(),
		wp_get_theme()->get( 'Version' ),
		true
	);

	// Кнопка "наверх страницы" в футере (обратная связь 19.09.2026).
	wp_enqueue_script(
		'glozin-child-ui-interactions',
		get_stylesheet_directory_uri() . '/assets/ui-interactions.js',
		array(),
		wp_get_theme()->get( 'Version' ),
		true
	);
}
add_action( 'wp_enqueue_scripts', 'glozin_child_enqueue_assets' );

/**
 * Переключатель языка RU/RO в верхней строке шапки (обратная связь 19.09.2026).
 * Использует WPML, если он активен на сайте; иначе — статический фолбэк
 * на два самых частых варианта адресов (см. TODO.md, пункт про структуру
 * языковых URL — реальные пути уточнить после доступа к настройкам WPML).
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
 * TODO — НЕ решается кодом дочерней темы, см. wp-content/themes/glozin-child-industrial-pro/TODO.md
 * -----------------------------------------------------------------------
 * Пункт 9 раздела 5 ТЗ (дубль "User-agent: *" в robots.txt) и пункт 11
 * (структура языковых URL RO/RU, конфигурация WPML) сознательно НЕ
 * реализованы здесь — требуют диагностики на живом сайте/доступа к
 * admin. Подробности и план действий — в TODO.md рядом с этим файлом.
 */
