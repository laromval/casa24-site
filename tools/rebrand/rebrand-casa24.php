<?php
/**
 * Замена "Meșterul Casei" -> "Casa24" в Elementor-данных и post_content.
 * wp eval-file rebrand-casa24.php dry|apply|rollback <ID...>
 * Перед apply сохраняет исходные значения в мету _casa24_rebrand_backup.
 */
$mode = $args[0] ?? 'dry';
$ids  = array_map( 'intval', array_slice( $args, 1 ) );

function casa24_rebrand_str( $s ) {
	// "S.R.L. MEȘTERUL CASEI" -> "Casa24" (юрлицо всё равно не это).
	$s = preg_replace( '/S\.?R\.?L\.?\s+M[EeЕе][ȘșSs]TERUL\s+CASEI/iu', 'CASA24', $s );
	return preg_replace_callback(
		'/M[e][șs]terul\s+Casei/iu',
		function ( $m ) {
			return ( mb_strtoupper( $m[0] ) === $m[0] ) ? 'CASA24' : 'Casa24';
		},
		$s
	);
}

function casa24_rebrand_walk( &$node, &$log ) {
	if ( is_array( $node ) ) {
		foreach ( $node as &$v ) {
			casa24_rebrand_walk( $v, $log );
		}
	} elseif ( is_string( $node ) ) {
		$new = casa24_rebrand_str( $node );
		if ( $new !== $node ) {
			preg_match_all( '/.{0,40}(?:M[e][șs]terul\s+Casei|CASA24|Casa24).{0,40}/iu', wp_strip_all_tags( $new ), $mm );
			$log[] = implode( ' | ', $mm[0] );
			$node  = $new;
		}
	}
}

foreach ( $ids as $id ) {
	if ( 'rollback' === $mode ) {
		$b = get_post_meta( $id, '_casa24_rebrand_backup', true );
		if ( $b ) {
			update_post_meta( $id, '_elementor_data', wp_slash( $b['elementor'] ) );
			wp_update_post( array( 'ID' => $id, 'post_content' => $b['content'] ) );
			WP_CLI::log( "$id: восстановлено" );
		}
		continue;
	}
	$log   = array();
	$raw   = get_post_meta( $id, '_elementor_data', true );
	$data  = $raw ? json_decode( $raw, true ) : null;
	if ( $data ) {
		casa24_rebrand_walk( $data, $log );
	}
	$post    = get_post( $id );
	$content = casa24_rebrand_str( $post->post_content );
	WP_CLI::log( "== $id {$post->post_title}: " . count( $log ) . ' строк в Elementor, content ' . ( $content !== $post->post_content ? 'меняется' : 'без изменений' ) );
	foreach ( $log as $l ) {
		WP_CLI::log( '   ' . $l );
	}
	if ( 'apply' === $mode && ( $log || $content !== $post->post_content ) ) {
		if ( ! get_post_meta( $id, '_casa24_rebrand_backup', true ) ) {
			add_post_meta( $id, '_casa24_rebrand_backup', array( 'elementor' => $raw, 'content' => $post->post_content ) );
		}
		if ( $log ) {
			update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
		}
		wp_update_post( array( 'ID' => $id, 'post_content' => $content ) );
		WP_CLI::log( '   применено' );
	}
}
if ( 'apply' === $mode || 'rollback' === $mode ) {
	if ( class_exists( '\Elementor\Plugin' ) ) {
		\Elementor\Plugin::$instance->files_manager->clear_cache();
	}
}
