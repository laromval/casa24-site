<?php
/**
 * Plugin Name: Casa24 — staging закрыт для всех, кроме вошедших в WordPress
 * Description: Только для staging.casa24.md. Без входа (пользователь с правом edit_posts) любая страница перенаправляет на форму входа WordPress; поисковикам — noindex. Заменяет пароль cPanel на папку («Конфиденциальность каталога»), из-за которого Chrome терял доступ к staging после AJAX-запросов (26.10.2026).
 *
 * Лежит в репозитории в staging/mu-plugins/ — отдельно от wp-content/,
 * чтобы не уехать на рабочий сайт вместе с остальными файлами. Даже если
 * окажется там, ничего не закроет: работает только на хосте staging.casa24.md.
 *
 * Убрать закрытие: удалить файл из wp-content/mu-plugins/ на staging.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Это staging?
 *
 * @return bool
 */
function casa24_staging_lock_is_staging() {
	$host = wp_parse_url( home_url(), PHP_URL_HOST );

	return 'staging.casa24.md' === $host;
}

/**
 * Пропустить запрос без входа: сама форма входа, сброс пароля, cron, wp-cli.
 *
 * @return bool
 */
function casa24_staging_lock_is_allowed_request() {
	if ( ( defined( 'WP_CLI' ) && WP_CLI ) || wp_doing_cron() ) {
		return true;
	}

	$script = isset( $_SERVER['SCRIPT_NAME'] ) ? basename( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- только сравнение с константой.

	return in_array( $script, array( 'wp-login.php', 'wp-cron.php' ), true );
}

/**
 * Закрыть staging: без входа — на форму входа (обычные страницы) или 401
 * (AJAX, REST, wc-ajax).
 */
function casa24_staging_lock() {
	if ( ! casa24_staging_lock_is_staging() || casa24_staging_lock_is_allowed_request() ) {
		return;
	}

	if ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) {
		return;
	}

	nocache_headers();
	header( 'X-Robots-Tag: noindex, nofollow', true );

	$is_ajax = wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || isset( $_GET['wc-ajax'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- только проверка наличия.
	if ( $is_ajax ) {
		status_header( 401 );
		exit;
	}

	$current = ( is_ssl() ? 'https://' : 'http://' ) . ( isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : 'staging.casa24.md' ) . ( isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/' );
	wp_safe_redirect( wp_login_url( $current ) );
	exit;
}
add_action( 'init', 'casa24_staging_lock', 0 );

/**
 * noindex для всех страниц staging (на случай, если закрытие когда-то снимут).
 */
add_filter(
	'wp_robots',
	function ( $robots ) {
		if ( casa24_staging_lock_is_staging() ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
		}
		return $robots;
	}
);
