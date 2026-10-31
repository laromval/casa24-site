<?php
/**
 * Plugin Name: Casa24 — наличные при получении только по Кишинёву
 * Description: На оформлении заказа способ «Оплата наличными» (cod) показывается только при доставке по Кишинёву (регион C) или при самовывозе. В другие районы и города — только оплата картой: заказ отправляем после полной оплаты.
 *
 * Условие владельца 30.10.2026, текст на странице «Оплата» (ID 1097054 / RO 1097056).
 *
 * Поля «Город» на оформлении нет (убрано в теме, functions.php) — адрес задают
 * регион (billing_state, коды WooCommerce для MD: C = Кишинёв) и улица. Смена
 * региона сама обновляет блок оплаты (update_order_review), а WooCommerce при
 * отправке заказа отклоняет способ, которого нет в списке доступных, — отдельная
 * проверка не нужна.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Убирает «наличными» из доступных способов оплаты, если доставка не по Кишинёву.
 *
 * @param array $gateways Доступные способы оплаты.
 * @return array
 */
function casa24_cod_only_chisinau( $gateways ) {
	if ( ! isset( $gateways['cod'] ) || ( is_admin() && ! wp_doing_ajax() ) || is_wc_endpoint_url( 'order-pay' ) ) {
		return $gateways;
	}
	if ( ! function_exists( 'WC' ) || ! WC()->customer || ! WC()->session ) {
		return $gateways;
	}

	// Самовывоз — платят в магазине, наличные остаются.
	foreach ( (array) WC()->session->get( 'chosen_shipping_methods', array() ) as $method ) {
		if ( 0 === strpos( (string) $method, 'local_pickup' ) ) {
			return $gateways;
		}
	}

	// Без «другого адреса доставки» WooCommerce копирует сюда регион из платёжного адреса.
	$country = WC()->customer->get_shipping_country();
	$state   = WC()->customer->get_shipping_state();
	if ( 'MD' !== $country || 'C' !== $state ) {
		unset( $gateways['cod'] );
	}

	return $gateways;
}
add_filter( 'woocommerce_available_payment_gateways', 'casa24_cod_only_chisinau', 20 );
