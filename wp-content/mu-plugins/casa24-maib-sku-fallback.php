<?php
/**
 * Plugin Name: Casa24 — maib: номер товара вместо пустого артикула
 * Description: Плагин maib передаёт артикул (SKU) позиции как orderInfo.items[].externalId, а банк требует непустую строку. У ~21% товаров casa24.md артикула нет — оплата картой падала с «Инициирование платежа… не удалось». Подставляем ID товара, только пока обрабатывается оплата через maib.
 *
 * Найдено тестовым заказом 25.10.2026 (заказ 1096561, лог wc-logs/maib_gateway-*):
 * RequestValidationException: Invalid 'orderInfo.items[0].externalId' parameter.
 *
 * Плагин maib (maib-checkout-payment-gateway-for-woocommerce-main 1.0.0) фильтров
 * для этого поля не даёт, поэтому подменяется результат WC_Product::get_sku() —
 * строго в запросе оформления/оплаты заказа с payment_method = maib. Артикулы в
 * каталоге, админке и обмене с 1С не меняются.
 *
 * Лежит в mu-plugins, чтобы не зависеть от темы и не потеряться при обновлении
 * плагина maib. Правильное долгосрочное решение — артикулы у всех товаров в 1С.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Идёт оплата через maib (оформление заказа или «оплатить заказ»)?
 *
 * @return bool
 */
function casa24_is_maib_payment_request() {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- только читаем выбранный способ оплаты; nonce проверяет WooCommerce.
	return isset( $_POST['payment_method'] ) && 'maib' === sanitize_key( wp_unslash( $_POST['payment_method'] ) );
}

/**
 * Пустой артикул → ID товара (для вариации — ID вариации).
 *
 * @param string     $sku     Артикул.
 * @param WC_Product $product Товар.
 * @return string
 */
function casa24_maib_sku_fallback( $sku, $product ) {
	if ( '' !== (string) $sku || ! casa24_is_maib_payment_request() ) {
		return $sku;
	}

	return (string) $product->get_id();
}
add_filter( 'woocommerce_product_get_sku', 'casa24_maib_sku_fallback', 20, 2 );
add_filter( 'woocommerce_product_variation_get_sku', 'casa24_maib_sku_fallback', 20, 2 );
