<?php
/**
 * wp eval-file im_import.php <import.json>
 * Создаёт RU-товары по выгрузке поставщика (im_prepare.py): цена как у поставщика, в наличии, фото со склада
 * поставщика в медиатеку, раздел, «Производитель», метки поставщика. Уже загруженные (по коду у поставщика) пропускает.
 * RO-версии — навык perevod-ro (ro-create-from-json.php) — метки поставщика копируются в перевод там же ниже.
 */
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$items = json_decode( file_get_contents( $args[0] ), true );
foreach ( $items as $it ) {
	$exists = get_posts( array( 'post_type' => 'product', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'suppress_filters' => true,
		'meta_query' => array( array( 'key' => '_casa24_supplier_id', 'value' => $it['supplier_id'] ), array( 'key' => '_casa24_supplier', 'value' => $it['supplier'] ) ) ) );
	if ( $exists ) {
		echo $it['supplier_id'] . "\tSKIP exists " . $exists[0] . "\n";
		continue;
	}
	if ( wc_get_product_id_by_sku( $it['sku'] ) ) {
		echo $it['supplier_id'] . "\tSKIP sku busy\n";
		continue;
	}
	$p = new WC_Product_Simple();
	$p->set_name( $it['title'] );
	$p->set_status( 'publish' );
	$p->set_sku( $it['sku'] );
	$p->set_regular_price( (string) $it['price'] );
	$p->set_description( $it['content'] );
	$p->set_manage_stock( false );
	$p->set_stock_status( 'instock' );
	$p->set_category_ids( array( (int) $it['cat'] ) );
	$tax = 'pa_proizvoditel';
	$term = term_exists( $it['brand'], $tax ) ?: wp_insert_term( $it['brand'], $tax );
	$attr = new WC_Product_Attribute();
	$attr->set_id( wc_attribute_taxonomy_id_by_name( $tax ) );
	$attr->set_name( $tax );
	$attr->set_options( array( (int) ( is_array( $term ) ? $term['term_id'] : $term ) ) );
	$attr->set_visible( true );
	$p->set_attributes( array( $attr ) );
	$id = $p->save();

	$ids = array();
	foreach ( $it['images'] as $url ) {
		$aid = media_sideload_image( str_replace( ' ', '%20', $url ), $id, $it['title'], 'id' );
		if ( is_wp_error( $aid ) ) {
			echo "  img ERR " . $aid->get_error_message() . "\n";
			continue;
		}
		update_post_meta( $aid, '_wp_attachment_image_alt', $it['title'] );
		$ids[] = $aid;
	}
	if ( $ids ) {
		$p->set_image_id( array_shift( $ids ) );
		$p->set_gallery_image_ids( $ids );
		$p->save();
	}
	update_post_meta( $id, '_casa24_supplier', $it['supplier'] );
	update_post_meta( $id, '_casa24_supplier_url', $it['supplier_url'] );
	update_post_meta( $id, '_casa24_supplier_id', $it['supplier_id'] );
	update_post_meta( $id, '_casa24_supplier_synced', time() );
	echo $it['supplier_id'] . "\t$id\t" . $it['sku'] . "\t" . $it['price'] . "\t" . count( $it['images'] ) . " img\t" . apply_filters( 'wpml_element_language_code', null, array( 'element_id' => $id, 'element_type' => 'post_product' ) ) . "\n";
}
