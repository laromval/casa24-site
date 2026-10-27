<?php
/**
 * wp eval-file nexu-ro-create.php <RU-ID> [<RU-ID> ...]
 *
 * RO-перевод товара, у которого его нет: название/описание/краткое описание
 * переводит NEXU (настройки и инструкции плагина), запись создаётся
 * дубликатом WPML (цены, фото, разделы, атрибуты) и становится обычным
 * переводом-ЧЕРНОВИКОМ — покупатели не видят, пока не опубликуют.
 */
$ai = new Nexu_AI_Processor();
$tr = static function ( $r ) {
	if ( is_array( $r ) ) {
		return ! empty( $r['success'] ) || isset( $r['translation'] ) ? (string) ( $r['translation'] ?? '' ) : '';
	}
	return (string) $r;
};
$out = array();
foreach ( array_map( 'intval', $args ) as $id ) {
	$p = get_post( $id );
	if ( ! $p || 'product' !== $p->post_type ) {
		$out[] = array( 'ru' => $id, 'error' => 'not a product' );
		continue;
	}
	if ( apply_filters( 'wpml_object_id', $id, 'product', false, 'ro' ) ) {
		$out[] = array( 'ru' => $id, 'error' => 'RO already exists' );
		continue;
	}
	$title = $tr( $ai->translate_text( $p->post_title, 'ru', 'ro', null, null, 'title' ) );
	$body  = '' !== trim( $p->post_content ) ? $tr( $ai->translate_text( $p->post_content, 'ru', 'ro', null, null, 'body' ) ) : '';
	$exc   = '' !== trim( $p->post_excerpt ) ? $tr( $ai->translate_text( $p->post_excerpt, 'ru', 'ro', null, null, 'body' ) ) : '';
	if ( '' === trim( $title ) || ( '' !== trim( $p->post_content ) && '' === trim( $body ) ) ) {
		$out[] = array( 'ru' => $id, 'error' => 'translation failed', 'title' => $title );
		continue;
	}
	do_action( 'wpml_admin_make_post_duplicates', $id );
	$ro = (int) apply_filters( 'wpml_object_id', $id, 'product', false, 'ro' );
	if ( ! $ro || $ro === $id ) {
		$out[] = array( 'ru' => $id, 'error' => 'duplicate not created' );
		continue;
	}
	wp_update_post( array(
		'ID'           => $ro,
		'post_title'   => $title,
		'post_content' => $body,
		'post_excerpt' => $exc,
		'post_name'    => sanitize_title( $title ) . '-ro',
		'post_status'  => 'draft',
	) );
	delete_post_meta( $ro, '_icl_lang_duplicate_of' );
	$out[] = array(
		'ru'       => $id,
		'ro'       => $ro,
		'ru_title' => $p->post_title,
		'ro_title' => get_the_title( $ro ),
		'status'   => get_post_status( $ro ),
		'price'    => get_post_meta( $ro, '_price', true ) . ' / RU ' . get_post_meta( $id, '_price', true ),
		'sku'      => get_post_meta( $ro, '_sku', true ),
		'thumb'    => get_post_thumbnail_id( $ro ) . ' / RU ' . get_post_thumbnail_id( $id ),
		'cats'     => implode( ', ', wp_get_post_terms( $ro, 'product_cat', array( 'fields' => 'names' ) ) ),
		'ru_desc'  => wp_strip_all_tags( $p->post_content ),
		'ro_desc'  => wp_strip_all_tags( $body ),
	);
}
echo wp_json_encode( $out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
