<?php
/**
 * Согласие на обработку данных в формах Contact Form 7 (№40).
 * wp eval-file cf7-consent.php dry|apply|rollback
 *
 * - «Contact Us» RU 1000831 / RO 541 (страница «Контакты»): галочка была
 *   отмечена заранее (default:on) — теперь пустая, текст — о цели обработки.
 * - «Задать вопрос» RU 1001224 / RO 1071 (кнопка на карточке товара):
 *   согласия не было — добавлена галочка перед кнопкой.
 * Сообщение «не отмечена галочка» — на языке формы (у «Задать вопрос» было
 * английское).
 * Перед apply исходные разметка и сообщения формы сохраняются в мету _casa24_consent_backup.
 * ID — со staging; на проде сверить: wp post list --post_type=wpcf7_contact_form
 */
$mode = $args[0] ?? 'dry';

$forms = array(
	1000831 => array( 'lang' => 'ru', 'kind' => 'contact' ),
	541     => array( 'lang' => 'ro', 'kind' => 'contact' ),
	1001224 => array( 'lang' => 'ru', 'kind' => 'question' ),
	1071    => array( 'lang' => 'ro', 'kind' => 'question' ),
);

$texts = array(
	'ru' => array(
		'contact'  => 'Я согласен(на) на обработку моих данных для ответа на сообщение согласно %s.',
		'question' => 'Я согласен(на) на обработку моих данных, чтобы со мной связались по вопросу, согласно %s.',
		'link'     => 'Политике конфиденциальности',
		'error'    => 'Отметьте согласие на обработку данных, чтобы отправить сообщение.',
	),
	'ro' => array(
		'contact'  => 'Sunt de acord cu prelucrarea datelor mele pentru a primi răspuns la mesaj, conform %s.',
		'question' => 'Sunt de acord cu prelucrarea datelor mele pentru a fi contactat(ă) în legătură cu întrebarea, conform %s.',
		'link'     => 'Politicii de confidențialitate',
		'error'    => 'Bifați acordul pentru prelucrarea datelor ca să trimiteți mesajul.',
	),
);

/**
 * Блок галочки CF7 для формы.
 */
function casa24_cf7_acceptance( $lang, $kind, $texts ) {
	// Без смены языка WPML отдаёт адрес RU-версии и для RO-страницы.
	do_action( 'wpml_switch_language', $lang );
	$policy = apply_filters( 'wpml_object_id', 1080727, 'page', true, $lang );
	$url    = wp_make_link_relative( get_permalink( $policy ) );
	do_action( 'wpml_switch_language', null );
	$link   = '<a href="' . esc_url( $url ) . '" target="_blank">' . $texts[ $lang ]['link'] . '</a>';
	return '[acceptance privacy] ' . sprintf( $texts[ $lang ][ $kind ], $link ) . ' [/acceptance]';
}

foreach ( $forms as $id => $f ) {
	$post = get_post( $id );
	if ( ! $post || 'wpcf7_contact_form' !== $post->post_type ) {
		WP_CLI::warning( "$id: формы нет" );
		continue;
	}

	if ( 'rollback' === $mode ) {
		$b = get_post_meta( $id, '_casa24_consent_backup', true );
		if ( $b ) {
			update_post_meta( $id, '_form', wp_slash( $b['form'] ) );
			update_post_meta( $id, '_messages', wp_slash( $b['messages'] ) );
			delete_post_meta( $id, '_casa24_consent_backup' );
			WP_CLI::log( "$id: восстановлено" );
		}
		continue;
	}

	$form       = get_post_meta( $id, '_form', true );
	$acceptance = casa24_cf7_acceptance( $f['lang'], $f['kind'], $texts );

	if ( preg_match( '~\[acceptance\s+privacy[^\]]*\].*?\[/acceptance\]~s', $form ) ) {
		$new = preg_replace( '~\[acceptance\s+privacy[^\]]*\].*?\[/acceptance\]~s', $acceptance, $form );
	} else {
		// Своя строка перед строкой с кнопкой отправки.
		$new = preg_replace(
			'~(<div class="contact-form-row[^"]*">\s*<button[^>]*wpcf7-submit)~',
			'<div class="contact-form-row mb-20">' . "\n    " . $acceptance . "\n</div>\n" . '$1',
			$form,
			1
		);
	}

	$messages     = get_post_meta( $id, '_messages', true );
	$new_messages = is_array( $messages ) ? $messages : array();
	$new_messages['accept_terms'] = $texts[ $f['lang'] ]['error'];
	$changed      = $new !== $form || $new_messages !== $messages;

	WP_CLI::log( "== $id {$post->post_title} ({$f['lang']}): " . ( $changed ? 'меняется' : 'без изменений' ) );
	WP_CLI::log( '   ' . $acceptance );
	WP_CLI::log( '   сообщение без галочки: ' . ( $messages['accept_terms'] ?? '—' ) . ' → ' . $new_messages['accept_terms'] );

	if ( 'apply' === $mode && $changed ) {
		if ( ! get_post_meta( $id, '_casa24_consent_backup', true ) ) {
			update_post_meta( $id, '_casa24_consent_backup', wp_slash( array( 'form' => $form, 'messages' => $messages ) ) );
		}
		update_post_meta( $id, '_form', wp_slash( $new ) );
		update_post_meta( $id, '_messages', wp_slash( $new_messages ) );
		WP_CLI::success( "$id: записано" );
	}
}
