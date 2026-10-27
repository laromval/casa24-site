<?php
/**
 * wp eval-file ro-create-from-json.php <file.json> [draft]
 *
 * RO-переводы товаров по готовому переводу (без ИИ-сервисов): JSON —
 * [{"ru": ID, "title": "...", "content": "...", "excerpt": "..."}].
 * Запись создаётся дубликатом WPML (цены, артикул, фото, разделы,
 * атрибуты), затем становится обычным переводом и публикуется
 * (с аргументом draft — черновиком). Товары, у которых RO уже есть, пропускаются.
 */
$items  = json_decode( file_get_contents( $args[0] ), true );
$status = ( isset( $args[1] ) && 'draft' === $args[1] ) ? 'draft' : 'publish';
$done   = 0;
foreach ( $items as $it ) {
	$id = (int) $it['ru'];
	if ( 'product' !== get_post_type( $id ) ) {
		echo "$id\tSKIP not a product\n";
		continue;
	}
	if ( apply_filters( 'wpml_object_id', $id, 'product', false, 'ro' ) ) {
		echo "$id\tSKIP RO exists\n";
		continue;
	}
	do_action( 'wpml_admin_make_post_duplicates', $id );
	$ro = (int) apply_filters( 'wpml_object_id', $id, 'product', false, 'ro' );
	if ( ! $ro || $ro === $id ) {
		echo "$id\tERROR duplicate not created\n";
		continue;
	}
	wp_update_post( array(
		'ID'           => $ro,
		'post_title'   => $it['title'],
		'post_content' => isset( $it['content'] ) ? $it['content'] : '',
		'post_excerpt' => isset( $it['excerpt'] ) ? $it['excerpt'] : '',
		'post_name'    => sanitize_title( $it['title'] ) . '-ro',
		'post_status'  => $status,
	) );
	delete_post_meta( $ro, '_icl_lang_duplicate_of' );
	++$done;
	echo "$id\t$ro\t", get_post_status( $ro ), "\t", get_post_meta( $ro, '_price', true ), "\t", get_the_title( $ro ), "\n";
}
echo "DONE $done\n";
