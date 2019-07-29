<?php
/**
 * Sellers App
 *
 * Provides support for Klarna sellers app.
 *
 * @package WC_Klarna_Order_Management
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Klarna_Pending_Orders class.
 *
 * Handles pending orders.
 */
class WC_Klarna_Sellers_App {
	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'wp_insert_post', array( $this, 'process_order_creation' ), 9999, 3 );
	}

	/**
	 * Handles the wp_insert_post hook.
	 *
	 * @param string  $post_id WordPress post id.
	 * @param WP_Post $post The post object.
	 * @param bool    $update If this was an update.
	 * @return void
	 */
	public function process_order_creation( $post_id, $post, $update ) {
		// If this is not an admin page bail.
		if ( ! is_admin() ) {
			return;
		}

		// If post status is not draft bail.
		if ( 'draft' !== $post->post_status ) {
			return;
		}

		// If post type is not shop_order bail.
		if ( 'shop_order' !== $post->post_type ) {
			return;
		}

		// Check that this is an update, and that we have a transaction number, and that the payment method is set to KCO or KP.
		if ( $update && ! empty( get_post_meta( $post_id, '_transaction_id', true ) ) && in_array( get_post_meta( $post_id, '_payment_method', true ), array( 'kco', 'klarna_payments' ) ) ) {
			$order = wc_get_order( $post_id );
			// Set post metas
			update_post_meta( $post_id, '_wc_klarna_order_id', $order->get_transaction_id() );
			update_post_meta( $post_id, '_wc_klarna_country', wc_get_base_location()['country'] );
			update_post_meta( $post_id, '_wc_klarna_enviroment', self::get_klarna_environment( get_post_meta( $post_id, '_payment_method', true ) ) );

			$klarna_order = WC_Klarna_Order_Management::get_instance()->retrieve_klarna_order( $post_id );

			self::populate_klarna_order( $post_id, $klarna_order );
		}
	}

	/**
	 * Populates the new order with customer data.
	 *
	 * @param string $post_id WordPress post id.
	 * @param object $klarna_order The klarna order.
	 * @return void
	 */
	public static function populate_klarna_order( $post_id, $klarna_order ) {
		$order = wc_get_order( $post_id );

		// Set billing address.
		$order->set_billing_first_name( sanitize_text_field( $klarna_order->billing_address->given_name ) );
		$order->set_billing_last_name( sanitize_text_field( $klarna_order->billing_address->family_name ) );
		$order->set_billing_address_1( sanitize_text_field( $klarna_order->billing_address->street_address ) );
		$order->set_billing_address_2( sanitize_text_field( $klarna_order->billing_address->street_address2 ) );
		$order->set_billing_city( sanitize_text_field( $klarna_order->billing_address->city ) );
		$order->set_billing_state( sanitize_text_field( $klarna_order->billing_address->region ) );
		$order->set_billing_postcode( sanitize_text_field( $klarna_order->billing_address->postal_code ) );
		$order->set_billing_email( sanitize_text_field( $klarna_order->billing_address->email ) );
		$order->set_billing_phone( sanitize_text_field( $klarna_order->billing_address->phone ) );

		// Set shipping address.
		$order->set_shipping_first_name( sanitize_text_field( $klarna_order->shipping_address->given_name ) );
		$order->set_shipping_last_name( sanitize_text_field( $klarna_order->shipping_address->family_name ) );
		$order->set_shipping_address_1( sanitize_text_field( $klarna_order->shipping_address->street_address ) );
		$order->set_shipping_address_2( sanitize_text_field( $klarna_order->shipping_address->street_address2 ) );
		$order->set_shipping_city( sanitize_text_field( $klarna_order->shipping_address->city ) );
		$order->set_shipping_state( sanitize_text_field( $klarna_order->shipping_address->region ) );
		$order->set_shipping_postcode( sanitize_text_field( $klarna_order->shipping_address->postal_code ) );

		$order->save();

		$order->add_order_note( __( 'Order address updated by Klarna Order management.', 'klarna-order-management-for-woocommerce' ) );
	}

	/**
	 * Gets environment (test/live) used for Klarna purchase.
	 *
	 * @return mixed
	 */
	public static function get_klarna_environment( $payment_method ) {
		$env                     = 'test';
		$payment_method_settings = get_option( 'woocommerce_' . $payment_method . '_settings' );

		if ( 'yes' !== $payment_method_settings['testmode'] ) {
			$env = 'live';
		}

		return $env;
	}
}
new WC_Klarna_Sellers_App();
