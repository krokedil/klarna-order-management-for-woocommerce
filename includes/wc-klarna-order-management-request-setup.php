<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * WC_Klarna_Order_Management_Request_Setup class.
 *
 * Gets Klarna credentials based on order country.
 */
class WC_Klarna_Order_Management_Request_Setup {

	/**
	 * Get Klarna order management request authorization header for WooCommerce order.
	 *
	 * @param  integer $order_id WooCommerce order ID.
	 *
	 * @return string|WP_Error
	 */
	public static function get_authorization_header( $order_id ) {
		$order = wc_get_order( $order_id );
		$klarna_payments_settings = get_option( 'woocommerce_klarna_payments_settings' );

		// @TODO: Once KCO is separate plugin, check which gateway was used to create the order
		if ( 'yes' !== $klarna_payments_settings['enabled'] ) {
			return new WP_Error( 'gateway_disabled', 'Klarna Payments gateway is currently disabled' );
		}

		// @TODO: Based on order country and testmode get appropriate fields here
		if ( get_post_meta( $order_id, '_wc_klarna_payments_mode', true ) ) {
			if ( '' === $klarna_payments_settings['test_merchant_id'] || '' === $klarna_payments_settings['test_shared_secret'] ) {
				return new WP_Error( 'missing_credentials', 'Klarna Payments credentials are missing' );
			}

			$merchant_id = $klarna_payments_settings['test_merchant_id'];
			$shared_secret = $klarna_payments_settings['test_shared_secret'];
		} else {
			if ( '' === $klarna_payments_settings['merchant_id'] || '' === $klarna_payments_settings['shared_secret'] ) {
				return new WP_Error( 'missing_credentials', 'Klarna Payments credentials are missing' );
			}

			$merchant_id = $klarna_payments_settings['merchant_id'];
			$shared_secret = $klarna_payments_settings['shared_secret'];
		}

		return 'Basic ' . base64_encode( $merchant_id . ':' . $shared_secret );
	}

	/**
	 * Gets merchant ID from WooCommerce order, based on environment and country.
	 *
	 * @TODO: Consider other countries too, currently defaults to US.
	 */
	public static function get_merchant_id( $order_id ) {
		$order = wc_get_order( $order_id );
		$billing_address = $order->get_address( 'billing' );
		$billing_country = $billing_address['country'];

		$klarna_payments_settings = get_option( 'woocommerce_klarna_payments_settings' );
		return $klarna_payments_settings['merchant_id'];
	}

	/**
	 * Gets merchant ID from WooCommerce order, based on environment and country.
	 *
	 * @TODO: Consider other countries too, currently defaults to US.
	 */
	public static function get_shared_secret( $order_id ) {

	}

	/**
	 * Gets merchant ID from WooCommerce order, based on environment and country.
	 *
	 * @TODO: Consider other countries too, currently defaults to US.
	 */
	public static function get_server_base( $order_id ) {

	}

}