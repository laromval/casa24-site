<?php
/**
 * Plugin Name: Casa24 — галочки в списке заказов
 * Description: В WordPress 7.1 галочка строки списка выводится в <td class="check-column">, а скрипт WooCommerce 10.7 (wc-orders.js) открывает заказ по клику на любой td строки — поставить галочку нельзя, сразу открывается карточка. Помечаем колонку классом no-link: такие клики WooCommerce пропускает. 30.10.2026.
 *
 * @package Casa24
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'admin_footer',
	function () {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->id, array( 'woocommerce_page_wc-orders', 'edit-shop_order' ), true ) ) {
			return;
		}
		?>
		<script>
		jQuery( function( $ ) {
			$( '.wp-list-table tbody td.check-column' ).addClass( 'no-link' );
		} );
		</script>
		<?php
	}
);
