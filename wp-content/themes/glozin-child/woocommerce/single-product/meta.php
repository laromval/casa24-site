<?php
/**
 * Single Product Meta
 *
 * @see         https://woocommerce.com/document/template-structure/
 * @package     WooCommerce\Templates
 * @version     9.7.0
 */

use Automattic\WooCommerce\Enums\ProductType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $product;
?>
<div class="product_meta">

	<?php do_action( 'woocommerce_product_meta_start' ); ?>

	<?php if ( \Glozin\Helper::get_option( 'product_sku' ) && wc_product_sku_enabled() && ( $product->get_sku() || $product->is_type( ProductType::VARIABLE ) ) ) : ?>

		<span class="sku_wrapper"><span class="meta__label"><?php esc_html_e( 'Sku:', 'glozin' ); ?></span>
			<span class="sku">
				<?php if ( $sku = $product->get_sku() ) {
					echo !empty( $sku ) ? $sku : '';
				} else {
					esc_html_e( 'N/A', 'woocommerce' );
				}   ?>
			</span>
		</span>

	<?php endif; ?>

	<?php if ( method_exists( $product, 'get_global_unique_id' ) && ( $barcode = trim( (string) $product->get_global_unique_id() ) ) !== '' ) : ?>
		<span class="barcode_wrapper"><span class="meta__label"><?php esc_html_e( 'Barcode:', 'glozin' ); ?></span>
			<span class="barcode"><?php echo esc_html( $barcode ); ?></span>
		</span>
	<?php endif; ?>

	<?php
		$availability = $product->get_availability();
		$stock = '<span class="stock">'.$availability['availability'].'</span>';
	?>

	<?php echo \Glozin\Helper::get_option( 'product_stock' ) ? '<span class="stock-status"><span class="meta__label">' . esc_html__( 'Available:', 'glozin' ) . '</span> ' . $stock . '</span>' : ''; ?>

	<?php echo \Glozin\Helper::get_option( 'product_categtories' ) ? wc_get_product_category_list( $product->get_id(), ', ', '<span class="posted_in"><span class="meta__label">' . _n( 'Category:', 'Categories:', count( $product->get_category_ids() ), 'woocommerce' ) . '</span> ', '</span>' ) : ''; ?>

	<?php echo \Glozin\Helper::get_option( 'product_tags' ) ? wc_get_product_tag_list( $product->get_id(), ', ', '<span class="posted_in"><span class="meta__label">' . _n( 'Tag:', 'Tags:', count( $product->get_tag_ids() ), 'woocommerce' ) . '</span> ', '</span>' ) : ''; ?>

	<?php do_action( 'woocommerce_product_meta_end' ); ?>

</div>
