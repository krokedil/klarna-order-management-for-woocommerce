<?php
/**
 * Pending orders
 *
 * Provides Klarna pending orders functionality.
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
class WC_Klarna_Pending_Orders {

	/**
	 * Notification listener for Pending orders.
	 *
	 * @param string $klarna_order_id Klarna order ID.
	 * @param array  $data The data for the order.
	 *
	 * @link https://developers.klarna.com/en/us/kco-v3/pending-orders
	 */
	public static function notification_listener( $klarna_order_id = null, $data = null ) {
		$order_id = filter_input( INPUT_GET, 'order_id', FILTER_SANITIZE_STRING );
		if ( empty( $klarna_order_id ) ) {
			$klarna_order_id = filter_input( INPUT_GET, 'kco_wc_order_id', FILTER_SANITIZE_STRING );
		}

		// Get order id from klarna order id.
		if ( empty( $order_id ) && ! empty( $klarna_order_id ) ) {
			$order_id = self::get_order_id_from_klarna_order_id( $klarna_order_id );
		}

		// Get klarna order id from order id.
		if ( empty( $klarna_order_id ) && ! empty( $order_id ) ) {
			$klarna_order_id = self::get_klarna_order_id_from_order_id( $order_id );
		}

		// Bail if we do not have the order id or Klarna order id.
		if ( empty( $order_id ) || empty( $klarna_order_id ) ) {
			return;
		}

		// Check the order status for the klarna order. Bail if it does not exist in order management.
		$klarna_order = WC_Klarna_Order_Management::get_instance()->retrieve_klarna_order( $order_id );
		if ( is_wp_error( $klarna_order ) ) {
			return;
		}

		$order = wc_get_order( $order_id );

		// Use the order from Klarna for the fraud status check.
		if ( 'ACCEPTED' === $klarna_order->fraud_status ) {
			$order->payment_complete( $klarna_order_id );
			$order->add_order_note( 'Payment with Klarna is accepted.' );
		} elseif ( 'REJECTED' === $klarna_order->fraud_status || 'STOPPED' === $klarna_order->fraud_status ) {
			// Set meta field so order cancellation doesn't trigger Klarna API requests.
			update_post_meta( $order_id, '_wc_klarna_pending_to_cancelled', true, true );
			$order->update_status( 'cancelled', 'Klarna order rejected.' );
			wc_mail(
				get_option( 'admin_email' ),
				'Klarna order rejected',
				sprintf(
					'Klarna has identified order %1$s, Klarna Reference %2$s as high risk and request that you do not ship this order. Please contact the Klarna Fraud Team to resolve.',
					$order->get_order_number(),
					$klarna_order->order_id
				)
			);
		}
	}

	/**
	 * Gets WooCommerce order ID from Klarna order ID.
	 *
	 * @param string $klarna_order_id The klarna order id.
	 * @return $order_id
	 */
	private static function get_order_id_from_klarna_order_id( $klarna_order_id ) {
		$query_args = array(
			'post_type'   => wc_get_order_types(),
			'post_status' => array_keys( wc_get_order_statuses() ),
			'meta_key'    => '_wc_klarna_order_id', // phpcs:ignore WordPress.DB.SlowDBQuery -- Slow DB Query is ok here, we need to limit to our meta key.
			'meta_value'  => $klarna_order_id, // phpcs:ignore WordPress.DB.SlowDBQuery -- Slow DB Query is ok here, we need to limit to our meta key.
		);

		$orders = get_posts( $query_args );

		// If zero matching orders were found, return.
		if ( empty( $orders ) ) {
			return;
		}

		$order_id = $orders[0]->ID;

		return $order_id;
	}

	/**
	 * Get Klarna order id from WooCommerce order id.
	 *
	 * @param int $order_id The WooCommerce order id.
	 * @return string|bool
	 */
	private static function get_klarna_order_id_from_order_id( $order_id ) {
		return get_post_meta( $order_id, '_wc_klarna_order_id', true );
	}
}
