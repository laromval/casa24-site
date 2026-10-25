<?php
/**
 * Hide Maib "Pay"/"Cancel" action buttons for orders paid via Maib Checkout.
 * Fixes race condition where user lands on order-received before the
 * Maib callback updates the order status from "pending" to "completed".
 */
add_filter( 'woocommerce_my_account_my_orders_actions', 'glozin_child_hide_maib_actions', 10, 2 );
function glozin_child_hide_maib_actions( $actions, $order ) {
	if ( ! $order || ! $order->get_meta( '_checkout_id' ) ) {
		return $actions;
	}
	unset( $actions['pay'] );
	unset( $actions['cancel'] );
	return $actions;
}
