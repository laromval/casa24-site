<?php
// wp eval-file ro-missing.php [limit] — опубликованные RU-товары без RO-перевода, новые первыми.
global $wpdb;
$limit = isset( $args[0] ) ? (int) $args[0] : 20;
$rows  = $wpdb->get_results(
	"SELECT p.ID, p.post_title, p.post_date, CHAR_LENGTH(p.post_content) clen, CHAR_LENGTH(p.post_excerpt) elen
	 FROM {$wpdb->posts} p
	 JOIN {$wpdb->prefix}icl_translations t ON t.element_id = p.ID AND t.element_type = 'post_product' AND t.language_code = 'ru'
	 WHERE p.post_type = 'product' AND p.post_status = 'publish'
	   AND NOT EXISTS ( SELECT 1 FROM {$wpdb->prefix}icl_translations t2 WHERE t2.trid = t.trid AND t2.language_code = 'ro' )
	 ORDER BY p.ID DESC"
);
echo 'total: ', count( $rows ), "\n";
foreach ( array_slice( $rows, 0, $limit ) as $r ) {
	$stock = get_post_meta( $r->ID, '_stock_status', true );
	echo "{$r->ID}\t{$r->post_date}\t{$stock}\tdesc={$r->clen}\texc={$r->elen}\t{$r->post_title}\n";
}
