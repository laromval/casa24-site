<?php
/**
 * Согласия посетителя — Закон РМ № 195/2024 (docs/legal/personal-data-requirements.md).
 *
 * №38 — cookie-баннер вместо плагина NB Cookie Consent (там была одна кнопка
 *       «Я согласен»): «Принять все» / «Только необходимые» / «Настроить»,
 *       категории «Необходимые» (всегда), «Аналитика», «Маркетинг»; выбор
 *       хранится в cookie casa24_consent, изменить — ссылка «Настройки cookie»
 *       в футере. Каждый выбор пишется в журнал (таблица casa24_consent_log)
 *       без IP и браузера — только случайный ID из cookie посетителя.
 * №41 — тег Google (GA4 через Site Kit) и cookie WooCommerce «источник
 *       заказа» (sbjs_*) — только после согласия на аналитику; тег грузит
 *       assets/cookie-consent.js, Site Kit свой тег больше не выводит.
 * №27, №40 — чекаут: обязательная неотмеченная галочка «Принимаю условия
 *       Публичной оферты» (решение владельца 27.10.2026), отметка о согласии
 *       сохраняется в заказ; тексты о данных на чекауте и при регистрации.
 *       Формы Contact Form 7 — tools/privacy/cf7-consent.php.
 *
 * Всё на клиенте (баннер, загрузка тега) — чтобы работало и с кэшем страниц.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Версия текста согласия. Сменить дату — баннер снова покажется всем
 * (например, после правки политики или добавления новой категории).
 */
const CASA24_CONSENT_VERSION = '2026-10-27';

/** Срок хранения выбора посетителя в cookie, дней. */
const CASA24_CONSENT_DAYS = 180;

/**
 * Ссылка на страницу текущего языка WPML (RU-ID → перевод).
 *
 * @param int $ru_id ID страницы на русском.
 * @return string
 */
function glozin_child_consent_page_url( $ru_id ) {
	$id = (int) apply_filters( 'wpml_object_id', $ru_id, 'page', true );
	return get_permalink( $id );
}

/**
 * Измеритель GA4 из настроек Site Kit — тот же, что Site Kit выводил сам.
 * Пусто, если модуль не подключён или Site Kit не считает этого
 * посетителя (настройка «не отслеживать вошедших пользователей»).
 *
 * @return array{tag:string,measurement:string}|null
 */
function glozin_child_consent_ga4() {
	$s = get_option( 'googlesitekit_analytics-4_settings' );
	if ( ! is_array( $s ) || empty( $s['measurementID'] ) || empty( $s['useSnippet'] ) ) {
		return null;
	}

	$disabled = isset( $s['trackingDisabled'] ) ? (array) $s['trackingDisabled'] : array();
	if ( is_user_logged_in() && ( in_array( 'loggedinUsers', $disabled, true )
		|| ( in_array( 'contentCreators', $disabled, true ) && current_user_can( 'edit_posts' ) ) ) ) {
		return null;
	}

	return array(
		'tag'         => ! empty( $s['googleTagID'] ) ? $s['googleTagID'] : $s['measurementID'],
		'measurement' => $s['measurementID'],
	);
}

// Site Kit больше не вставляет тег сам — его грузит cookie-consent.js после согласия.
add_filter( 'googlesitekit_analytics-4_tag_blocked', '__return_true' );

// WooCommerce «источник заказа» (cookie sbjs_*): выключено, пока нет согласия
// на аналитику; cookie-consent.js включает через wc_order_attribution.setOrderTracking().
add_filter( 'wc_order_attribution_allow_tracking', '__return_false' );

/**
 * Скрипт и стиль баннера — на всех страницах сайта, независимо от
 * шапки Industrial Pro (баннер нужен и на страницах Glozin).
 */
function glozin_child_consent_assets() {
	$dir = get_stylesheet_directory();
	$uri = get_stylesheet_directory_uri();

	wp_enqueue_style( 'glozin-child-cookie-consent', $uri . '/assets/cookie-consent.css', array(), filemtime( $dir . '/assets/cookie-consent.css' ) );
	wp_enqueue_script( 'glozin-child-cookie-consent', $uri . '/assets/cookie-consent.js', array(), filemtime( $dir . '/assets/cookie-consent.js' ), true );
	wp_localize_script(
		'glozin-child-cookie-consent',
		'casa24Consent',
		array(
			'version' => CASA24_CONSENT_VERSION,
			'days'    => CASA24_CONSENT_DAYS,
			'logUrl'  => admin_url( 'admin-ajax.php?action=casa24_consent_log' ),
			'lang'    => apply_filters( 'wpml_current_language', 'ru' ),
			'ga4'     => glozin_child_consent_ga4(),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'glozin_child_consent_assets', 40 );

/**
 * Разметка баннера и окна настроек. Скрыта, пока скрипт не решит её показать
 * (нет выбора или сменилась версия) — без JS баннера нет, но и ничего
 * необязательного без JS не грузится.
 */
function glozin_child_consent_markup() {
	$policy = glozin_child_consent_page_url( 1080727 );
	?>
	<div class="c24-consent" id="c24-consent" role="dialog" aria-modal="false" aria-labelledby="c24-consent-title" aria-describedby="c24-consent-text" hidden>
		<div class="c24-consent__box">
			<p class="c24-consent__title" id="c24-consent-title"><?php esc_html_e( 'Мы используем cookie', 'glozin-child' ); ?></p>
			<p class="c24-consent__text" id="c24-consent-text">
				<?php esc_html_e( 'Необходимые cookie нужны, чтобы работали корзина, вход и выбор языка. Аналитику и маркетинг включаем только с вашего согласия — выбор можно изменить в любой момент по ссылке «Настройки cookie» внизу сайта.', 'glozin-child' ); ?>
				<a href="<?php echo esc_url( $policy ); ?>"><?php esc_html_e( 'Политика конфиденциальности', 'glozin-child' ); ?></a>
			</p>

			<form class="c24-consent__prefs" data-c24-prefs hidden>
				<label class="c24-consent__cat">
					<input type="checkbox" checked disabled>
					<span>
						<strong><?php esc_html_e( 'Необходимые', 'glozin-child' ); ?></strong>
						<?php esc_html_e( 'Корзина, вход в кабинет, язык, защита сайта. Без них магазин не работает, поэтому они включены всегда.', 'glozin-child' ); ?>
					</span>
				</label>
				<label class="c24-consent__cat">
					<input type="checkbox" name="analytics" value="1">
					<span>
						<strong><?php esc_html_e( 'Аналитика', 'glozin-child' ); ?></strong>
						<?php esc_html_e( 'Google Analytics и источник перехода в заказе: какие страницы смотрят и откуда приходят. Помогает улучшать сайт.', 'glozin-child' ); ?>
					</span>
				</label>
				<label class="c24-consent__cat">
					<input type="checkbox" name="marketing" value="1">
					<span>
						<strong><?php esc_html_e( 'Маркетинг', 'glozin-child' ); ?></strong>
						<?php esc_html_e( 'Персональная реклама в Google и соцсетях. Сейчас не используется — включится только с вашего согласия.', 'glozin-child' ); ?>
					</span>
				</label>
			</form>

			<div class="c24-consent__actions">
				<button type="button" class="c24-consent__btn" data-c24-consent="necessary"><?php esc_html_e( 'Только необходимые', 'glozin-child' ); ?></button>
				<button type="button" class="c24-consent__btn" data-c24-consent="customize"><?php esc_html_e( 'Настроить', 'glozin-child' ); ?></button>
				<button type="button" class="c24-consent__btn" data-c24-consent="save" hidden><?php esc_html_e( 'Сохранить выбор', 'glozin-child' ); ?></button>
				<button type="button" class="c24-consent__btn c24-consent__btn--primary" data-c24-consent="all"><?php esc_html_e( 'Принять все', 'glozin-child' ); ?></button>
			</div>
		</div>
	</div>
	<?php
}
add_action( 'wp_footer', 'glozin_child_consent_markup', 5 );

/* ---------------------------------------------------------------------
 * Журнал согласий (ст. 7: согласие должно быть доказуемым).
 * Хранится: случайный ID из cookie посетителя, время, язык, версия
 * текста, выбранные категории. IP и браузер не храним.
 * ------------------------------------------------------------------- */

/** Имя таблицы журнала. */
function glozin_child_consent_table() {
	global $wpdb;
	return $wpdb->prefix . 'casa24_consent_log';
}

/** Создаёт таблицу журнала при первом запуске (и при смене схемы). */
function glozin_child_consent_install() {
	if ( '1' === get_option( 'casa24_consent_db' ) ) {
		return;
	}
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta(
		'CREATE TABLE ' . glozin_child_consent_table() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			consent_id char(36) NOT NULL,
			created_at datetime NOT NULL,
			lang varchar(5) NOT NULL DEFAULT '',
			version varchar(20) NOT NULL DEFAULT '',
			analytics tinyint(1) NOT NULL DEFAULT 0,
			marketing tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY consent_id (consent_id)
		) " . $wpdb->get_charset_collate() . ';'
	);
	update_option( 'casa24_consent_db', '1', false );
}
add_action( 'init', 'glozin_child_consent_install' );

/**
 * Запись выбора посетителя. Без nonce — страницы могут отдаваться из кэша;
 * принимаются только строго проверенные значения.
 */
function glozin_child_consent_log() {
	$id   = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
	$ver  = isset( $_POST['v'] ) ? sanitize_text_field( wp_unslash( $_POST['v'] ) ) : '';
	$lang = isset( $_POST['lang'] ) ? sanitize_key( wp_unslash( $_POST['lang'] ) ) : '';

	if ( ! preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $id ) || CASA24_CONSENT_VERSION !== $ver ) {
		wp_send_json_error( null, 400 );
	}

	global $wpdb;
	$wpdb->insert(
		glozin_child_consent_table(),
		array(
			'consent_id' => $id,
			'created_at' => current_time( 'mysql', true ),
			'lang'       => in_array( $lang, array( 'ru', 'ro' ), true ) ? $lang : '',
			'version'    => $ver,
			'analytics'  => empty( $_POST['a'] ) ? 0 : 1,
			'marketing'  => empty( $_POST['m'] ) ? 0 : 1,
		),
		array( '%s', '%s', '%s', '%s', '%d', '%d' )
	);
	wp_send_json_success();
}
add_action( 'wp_ajax_casa24_consent_log', 'glozin_child_consent_log' );
add_action( 'wp_ajax_nopriv_casa24_consent_log', 'glozin_child_consent_log' );

/* ---------------------------------------------------------------------
 * Чекаут и регистрация (№27, №40).
 * Страница условий — «Публичная оферта» (RU 1085732, RO 1085734):
 * опция woocommerce_terms_page_id задаётся при выкладке, см. TODO-industrial-pro.md.
 * ------------------------------------------------------------------- */

// Галочка оферты заранее не отмечена: скрипт подрядчика, который ставил её
// сам, убран из functions.php. Фильтр woocommerce_terms_is_checked_default
// не трогаем — он сохраняет галочку, уже поставленную покупателем, когда
// WooCommerce перерисовывает блок оплаты.

/*
 * Ссылки собираем сами: WooCommerce в [terms] / [privacy_policy] подставляет
 * «terms and conditions» / «политика конфиденциальности» в именительном
 * падеже. Классы ссылок — как у WooCommerce (по клику на оферту он
 * раскрывает её текст прямо на чекауте).
 */

/**
 * Текст галочки оферты на текущем языке.
 *
 * @return string
 */
function glozin_child_terms_checkbox_text() {
	$page_id = (int) apply_filters( 'wpml_object_id', wc_terms_and_conditions_page_id(), 'page', true );
	$label   = __( 'Публичной оферты', 'glozin-child' );
	$link    = $page_id
		? '<a href="' . esc_url( get_permalink( $page_id ) ) . '" class="woocommerce-terms-and-conditions-link" target="_blank">' . esc_html( $label ) . '</a>'
		: esc_html( $label );

	/* translators: %s — ссылка «Публичной оферты». */
	return sprintf( __( 'Я прочитал(а) и принимаю условия %s', 'glozin-child' ), $link );
}
add_filter( 'woocommerce_get_terms_and_conditions_checkbox_text', 'glozin_child_terms_checkbox_text' );

/**
 * Текст о данных под формой: на чекауте — про заказ, при регистрации — про кабинет.
 *
 * @param string $text Текст из настроек WooCommerce.
 * @param string $type checkout|registration.
 * @return string
 */
function glozin_child_privacy_policy_text( $text, $type ) {
	$page_id = (int) apply_filters( 'wpml_object_id', wc_privacy_policy_page_id(), 'page', true );
	$label   = __( 'Политике конфиденциальности', 'glozin-child' );
	$link    = $page_id
		? '<a href="' . esc_url( get_permalink( $page_id ) ) . '" class="woocommerce-privacy-policy-link" target="_blank">' . esc_html( $label ) . '</a>'
		: esc_html( $label );

	if ( 'registration' === $type ) {
		/* translators: %s — ссылка «Политике конфиденциальности». */
		return sprintf( __( 'Данные аккаунта используются для входа, истории заказов и сохранения адресов. Подробнее — в %s.', 'glozin-child' ), $link );
	}
	/* translators: %s — ссылка «Политике конфиденциальности». */
	return sprintf( __( 'Ваши данные используются для оформления, оплаты и доставки заказа. Подробнее — в %s.', 'glozin-child' ), $link );
}
add_filter( 'woocommerce_get_privacy_policy_text', 'glozin_child_privacy_policy_text', 10, 2 );

/**
 * Отметка о принятии оферты — в заказ: когда, какая редакция страницы
 * (дата её последнего изменения) и на каком языке.
 *
 * @param WC_Order $order Создаваемый заказ.
 */
function glozin_child_save_terms_acceptance( $order ) {
	if ( empty( $_POST['terms'] ) || ! wc_terms_and_conditions_page_id() ) { // phpcs:ignore WordPress.Security.NonceVerification -- проверено WooCommerce при оформлении.
		return;
	}
	$page = get_post( wc_terms_and_conditions_page_id() );
	$order->update_meta_data( '_casa24_terms_accepted', gmdate( 'c' ) );
	$order->update_meta_data( '_casa24_terms_version', $page ? $page->post_modified_gmt : '' );
	$order->update_meta_data( '_casa24_terms_lang', apply_filters( 'wpml_current_language', '' ) );
}
add_action( 'woocommerce_checkout_create_order', 'glozin_child_save_terms_acceptance' );
