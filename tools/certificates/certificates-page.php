<?php
/**
 * Страница «Сертификаты» RU / «Certificate» RO с документами о качестве товаров.
 * wp eval-file certificates-page.php dry|apply <путь-к-pdf>
 *
 * - PDF загружается в медиатеку (если файла с таким именем там ещё нет).
 * - RU-страница со слагом sertifikaty, RO — certificate, связаны в WPML.
 *   Футер (template-parts/industrial-pro/footer.php) находит страницу по слагу.
 * - Повторный запуск не создаёт дублей: страницы ищутся по мете _casa24_certificates.
 *
 * Первый документ (28.10.2026): санитарное заключение ANSP № P-23485/2026 на
 * канистры и бидоны из пищевого полиэтилена (бочки), PRIMA PLASTIC S.R.L.
 */
$mode = $args[0] ?? 'dry';
$pdf  = $args[1] ?? '';

if ( ! in_array( $mode, array( 'dry', 'apply' ), true ) || ! is_readable( $pdf ) ) {
	WP_CLI::error( 'Использование: wp eval-file certificates-page.php dry|apply <путь-к-pdf>' );
}

/* Вложение: ищем по имени файла, чтобы не загружать второй раз. */
$pdf_name   = basename( $pdf );
$attachment = get_posts(
	array(
		'post_type'        => 'attachment',
		'post_status'      => 'inherit',
		'posts_per_page'   => 1,
		'fields'           => 'ids',
		'suppress_filters' => true,
		'meta_query'       => array(
			array(
				'key'     => '_wp_attached_file',
				'value'   => pathinfo( $pdf_name, PATHINFO_FILENAME ),
				'compare' => 'LIKE',
			),
		),
	)
);
$attachment_id = $attachment ? (int) $attachment[0] : 0;

if ( ! $attachment_id ) {
	WP_CLI::log( "PDF: будет загружен {$pdf_name}" );
	if ( 'apply' === $mode ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$tmp = wp_tempnam( $pdf_name );
		copy( $pdf, $tmp );
		$attachment_id = media_handle_sideload(
			array(
				'name'     => $pdf_name,
				'tmp_name' => $tmp,
			),
			0,
			'Санитарное заключение P-23485/2026 — канистры и бидоны из полиэтилена'
		);
		if ( is_wp_error( $attachment_id ) ) {
			WP_CLI::error( $attachment_id->get_error_message() );
		}
	}
} else {
	WP_CLI::log( "PDF: уже в медиатеке, вложение {$attachment_id}" );
}
$pdf_url = $attachment_id ? wp_get_attachment_url( $attachment_id ) : '(после загрузки)';

$file_block = static function ( $url, $title, $button ) use ( $attachment_id ) {
	return '<!-- wp:file {"id":' . (int) $attachment_id . ',"href":"' . esc_url( $url ) . '","displayPreview":false} -->' . "\n"
		. '<div class="wp-block-file"><a href="' . esc_url( $url ) . '" target="_blank" rel="noreferrer noopener">' . esc_html( $title ) . '</a>'
		. '<a href="' . esc_url( $url ) . '" class="wp-block-file__button wp-element-button" download>' . esc_html( $button ) . '</a></div>' . "\n"
		. '<!-- /wp:file -->';
};

$p = static function ( $text ) {
	return "<!-- wp:paragraph -->\n<p>{$text}</p>\n<!-- /wp:paragraph -->";
};

$h = static function ( $text ) {
	return "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">{$text}</h2>\n<!-- /wp:heading -->";
};

$pages = array(
	'ru' => array(
		'title'   => 'Сертификаты',
		'slug'    => 'sertifikaty',
		'content' => implode(
			"\n\n",
			array(
				$p( 'Документы, которые подтверждают качество и безопасность товаров Casa24. Файлы открываются в формате PDF.' ),
				$h( 'Бочки, бидоны и канистры из пищевого полиэтилена' ),
				$p( 'Санитарное заключение № P-23485/2026 от 07.04.2026, выдано Национальным агентством общественного здоровья Республики Молдова. Изделия из полиэтилена для упаковки, перевозки и хранения пищевых жидкостей (канистры, бидоны) соответствуют HG 278/2013 — санитарному регламенту о пластиковых материалах, контактирующих с пищевыми продуктами, — и HG 308/2011. Получатель заключения — PRIMA PLASTIC S.R.L., Кишинёв. Действительно до 17.03.2029.' ),
				$file_block( $pdf_url, 'Санитарное заключение P-23485/2026 (PDF)', 'Скачать' ),
			)
		),
	),
	'ro' => array(
		'title'   => 'Certificate',
		'slug'    => 'certificate',
		'content' => implode(
			"\n\n",
			array(
				$p( 'Documentele care confirmă calitatea și siguranța mărfurilor Casa24. Fișierele se deschid în format PDF.' ),
				$h( 'Butoaie, bidoane și canistre din polietilenă alimentară' ),
				$p( 'Aviz sanitar nr. P-23485/2026 din 07.04.2026, eliberat de Agenția Națională pentru Sănătate Publică a Republicii Moldova. Articolele din polietilenă pentru ambalarea, transportarea și păstrarea lichidelor alimentare (canistre, bidoane) sunt conforme HG 278/2013 — Regulamentul sanitar privind materialele și obiectele din plastic destinate să vină în contact cu produsele alimentare — și HG 308/2011. Destinatarul avizului — PRIMA PLASTIC S.R.L., Chișinău. Valabil până la 17.03.2029.' ),
				$file_block( $pdf_url, 'Aviz sanitar P-23485/2026 (PDF)', 'Descarcă' ),
			)
		),
	),
);

$ids = array();
foreach ( $pages as $lang => $page ) {
	$found = get_posts(
		array(
			'post_type'        => 'page',
			'post_status'      => 'any',
			'posts_per_page'   => 1,
			'fields'           => 'ids',
			'suppress_filters' => true,
			'meta_key'         => '_casa24_certificates',
			'meta_value'       => $lang,
		)
	);
	$id = $found ? (int) $found[0] : 0;
	WP_CLI::log( sprintf( '%s: %s «%s» /%s/', strtoupper( $lang ), $id ? "обновить {$id}" : 'создать', $page['title'], $page['slug'] ) );

	if ( 'apply' !== $mode ) {
		continue;
	}
	$data = array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => $page['title'],
		'post_name'    => $page['slug'],
		'post_content' => $page['content'],
	);
	if ( $id ) {
		$data['ID'] = $id;
		wp_update_post( $data );
	} else {
		$id = wp_insert_post( $data, true );
		if ( is_wp_error( $id ) ) {
			WP_CLI::error( $id->get_error_message() );
		}
		update_post_meta( $id, '_casa24_certificates', $lang );
	}
	$ids[ $lang ] = $id;
}

if ( 'apply' === $mode ) {
	/* Связка RU ↔ RO в WPML: RO-страница становится переводом RU. */
	do_action(
		'wpml_set_element_language_details',
		array(
			'element_id'    => $ids['ru'],
			'element_type'  => 'post_page',
			'trid'          => false,
			'language_code' => 'ru',
		)
	);
	$trid = apply_filters( 'wpml_element_trid', null, $ids['ru'], 'post_page' );
	do_action(
		'wpml_set_element_language_details',
		array(
			'element_id'           => $ids['ro'],
			'element_type'         => 'post_page',
			'trid'                 => $trid,
			'language_code'        => 'ro',
			'source_language_code' => 'ru',
		)
	);
	/* Слаг RO мог получить суффикс, пока страница была «русской» — выставить заново. */
	wp_update_post(
		array(
			'ID'        => $ids['ro'],
			'post_name' => $pages['ro']['slug'],
		)
	);
	foreach ( $ids as $lang => $id ) {
		WP_CLI::success( strtoupper( $lang ) . ": {$id} " . get_permalink( $id ) );
	}
}
