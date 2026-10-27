<?php
// wp eval-file ro-export.php — RU-товары без RO-перевода (опубликованные) в JSON: id, title, content, excerpt, раздел.
global $wpdb;
$ids = $wpdb->get_col(
	"SELECT p.ID FROM {$wpdb->posts} p
	 JOIN {$wpdb->prefix}icl_translations t ON t.element_id = p.ID AND t.element_type = 'post_product' AND t.language_code = 'ru'
	 WHERE p.post_type = 'product' AND p.post_status = 'publish'
	   AND NOT EXISTS ( SELECT 1 FROM {$wpdb->prefix}icl_translations t2 WHERE t2.trid = t.trid AND t2.language_code = 'ro' )
	 ORDER BY p.ID"
);
$out = array();
foreach ( $ids as $id ) {
	$p     = get_post( $id );
	$out[] = array(
		'ru'      => (int) $id,
		'cat'     => implode( ' / ', wp_get_post_terms( $id, 'product_cat', array( 'fields' => 'names' ) ) ),
		'title'   => $p->post_title,
		'content' => $p->post_content,
		'excerpt' => $p->post_excerpt,
	);
}
echo wp_json_encode( $out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
