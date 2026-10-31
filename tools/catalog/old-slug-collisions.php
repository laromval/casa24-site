<?php
/**
 * wp eval-file old-slug-collisions.php [apply] — у старых групп 1С, чей slug
 * совпадает со slug новой категории (на любом языке), дописать «-1c».
 *
 * Иначе по адресу новой категории WordPress находит старую, а та
 * переадресуется на новую — то есть сама на себя (RO «usi-de-interior»,
 * 31.10.2026). Язык не важен: WPML ищет категорию по slug без учёта языка
 * и уводит на перевод найденной (RU-группа «usi-de-interior» → её RO-перевод).
 * Без «apply» — только список. Slug меняется напрямую в wp_terms:
 * wp_update_term() под WPML подменяет ID на раздел текущего языка
 * (см. tools/ro-translate/ro-rename-cats.php). Журнал — в stdout.
 */
global $wpdb;
$apply = isset( $args[0] ) && 'apply' === $args[0];

$rows = $wpdb->get_results(
	"SELECT DISTINCT o.term_id, o.slug, io.language_code AS lang, n.term_id AS new_id, i_n.language_code AS new_lang
	 FROM {$wpdb->terms} o
	 JOIN {$wpdb->term_taxonomy} tto ON tto.term_id = o.term_id AND tto.taxonomy = 'product_cat'
	 JOIN {$wpdb->prefix}icl_translations io ON io.element_id = tto.term_taxonomy_id AND io.element_type = 'tax_product_cat'
	 JOIN {$wpdb->terms} n ON n.slug = o.slug AND n.term_id <> o.term_id
	 JOIN {$wpdb->term_taxonomy} ttn ON ttn.term_id = n.term_id AND ttn.taxonomy = 'product_cat'
	 JOIN {$wpdb->prefix}icl_translations i_n ON i_n.element_id = ttn.term_taxonomy_id AND i_n.element_type = 'tax_product_cat'
	 JOIN {$wpdb->termmeta} mn ON mn.term_id = n.term_id AND mn.meta_key = '_casa24_catalog_key'
	 LEFT JOIN {$wpdb->termmeta} mo ON mo.term_id = o.term_id AND mo.meta_key = '_casa24_catalog_key'
	 WHERE mo.meta_id IS NULL
	 ORDER BY o.term_id"
);

foreach ( $rows as $row ) {
	$slug = $row->slug . '-1c';
	for ( $i = 2; $wpdb->get_var( $wpdb->prepare( "SELECT term_id FROM {$wpdb->terms} WHERE slug = %s", $slug ) ); $i++ ) {
		$slug = $row->slug . '-1c-' . $i;
	}
	if ( ! $apply ) {
		echo "{$row->term_id}\t[{$row->lang}]\t{$row->slug}\t→\t{$slug}\t(новая {$row->new_id} [{$row->new_lang}])\n";
		continue;
	}
	$ok = $wpdb->update( $wpdb->terms, array( 'slug' => $slug ), array( 'term_id' => (int) $row->term_id ) );
	clean_term_cache( (int) $row->term_id, 'product_cat' );
	echo $row->term_id, "\t[{$row->lang}]\t", false === $ok ? 'ERROR' : 'OK', "\t{$row->slug}\t→\t{$slug}\n";
}
echo count( $rows ), " совпадений\n";
