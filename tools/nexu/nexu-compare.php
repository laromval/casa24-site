<?php
// wp eval-file nexu-compare.php — подобрать RU-товары с RO-переводом, перевести название+описание через NEXU (без записи), вывести JSON.
global $wpdb;
$want = array( 'Дверь межкомнатная Model R14', 'Дрель', 'Секатор', 'Ведро', 'Шланг', 'Ceresit', 'Ключ рожково', 'Краска', 'Штукатурка', 'Дверь металлическая Model KM03' );
$ids  = array();
foreach ( $want as $w ) {
	$id = $wpdb->get_var( $wpdb->prepare(
		"SELECT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->prefix}icl_translations t ON t.element_id=p.ID AND t.element_type='post_product' AND t.language_code='ru'
		 WHERE p.post_type='product' AND p.post_status='publish' AND p.post_title LIKE %s AND CHAR_LENGTH(p.post_content) > 60 ORDER BY p.ID DESC LIMIT 1",
		'%' . $wpdb->esc_like( $w ) . '%'
	) );
	if ( $id && apply_filters( 'wpml_object_id', $id, 'product', false, 'ro' ) ) {
		$ids[] = (int) $id;
	}
}
$ids = array_slice( array_unique( $ids ), 0, 8 );
$ai  = new Nexu_AI_Processor();
$tr  = static function ( $r ) { return is_array( $r ) ? ( isset( $r['translation'] ) ? $r['translation'] : wp_json_encode( $r, JSON_UNESCAPED_UNICODE ) ) : (string) $r; };
$out = array();
foreach ( $ids as $id ) {
	$ro  = apply_filters( 'wpml_object_id', $id, 'product', false, 'ro' );
	$ru_p = get_post( $id );
	$ro_p = get_post( $ro );
	$out[] = array(
		'id'        => $id,
		'ru_title'  => $ru_p->post_title,
		'ro_now'    => $ro_p->post_title,
		'ro_new'    => $tr( $ai->translate_text( $ru_p->post_title, 'ru', 'ro', null, null, 'title' ) ),
		'ru_desc'   => wp_strip_all_tags( $ru_p->post_content ),
		'ro_desc_now' => wp_strip_all_tags( $ro_p->post_content ),
		'ro_desc_new' => wp_strip_all_tags( $tr( $ai->translate_text( $ru_p->post_content, 'ru', 'ro', null, null, 'body' ) ) ),
	);
}
echo wp_json_encode( $out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
