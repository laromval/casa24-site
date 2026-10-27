<?php
/**
 * wp eval-file ro-rename-cats.php <file.tsv> — переименовать RO-разделы товаров
 * (TSV: term_id <TAB> новое название). Меняется только название (slug/адрес —
 * нет), напрямую в wp_terms: get_term()/wp_update_term() под WPML подменяют
 * ID на раздел текущего языка (в wp-cli — RU) и переименовали бы RU-раздел.
 * Язык проверяется по icl_translations; не RO — пропуск. Журнал — в stdout.
 */
global $wpdb;
foreach ( file( $args[0], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $line ) {
	list( $id, $name ) = array_map( 'trim', explode( "\t", $line, 2 ) );
	$id  = (int) $id;
	$row = $wpdb->get_row( $wpdb->prepare(
		"SELECT t.name, t.slug, tr.language_code FROM {$wpdb->terms} t
		 JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id AND tt.taxonomy = 'product_cat'
		 JOIN {$wpdb->prefix}icl_translations tr ON tr.element_id = tt.term_taxonomy_id AND tr.element_type = 'tax_product_cat'
		 WHERE t.term_id = %d",
		$id
	) );
	if ( ! $row || 'ro' !== $row->language_code ) {
		echo "$id\tSKIP (", $row ? $row->language_code : 'none', ")\n";
		continue;
	}
	if ( $row->name === $name ) {
		echo "$id\tSAME\t$name\n";
		continue;
	}
	$ok = $wpdb->update( $wpdb->terms, array( 'name' => $name ), array( 'term_id' => $id ) );
	clean_term_cache( $id, 'product_cat' );
	echo $id, "\t", false === $ok ? 'ERROR' : 'OK', "\t", $row->name, "\t→\t", $name, "\n";
}
