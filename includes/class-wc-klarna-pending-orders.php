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
	 *
	 * @link https://developers.klarna.com/en/us/kco-v3/pending-orders
	 */
	public static function notification_listener( $klarna_order_id = null, $data = null ) {
		$order_id = '';

		if ( isset( $_GET['order_id'] ) ) { // KP, Input var okay.
			$order_id = sanitize_key( $_GET['order_id'] ); // Input var okay.
		} else {
			if ( isset( $_GET['kco_wc_order_id'] ) ) { // KCO, Input var okay.
				$klarna_order_id = sanitize_key( $_GET['kco_wc_order_id'] ); // Input var okay.
				$order_id        = self::get_order_id_from_klarna_order_id( $klarna_order_id );
			} elseif ( $klarna_order_id ) {
				$order_id = self::get_order_id_from_klarna_order_id( $klarna_order_id );
			}
		}

		if ( '' !== $order_id ) {
			$order = wc_get_order( $order_id );

			// There's no incoming contents in punted notification, so we had to send it as argument.
			if ( ! $data ) {
				// In regular notification, grab the incoming data.
				$post_body = file_get_contents( 'php://input' );
				$data      = json_decode( $post_body, true );
			}

			$event_type = sanitize_text_field( $data['event_type'] );
			$order_id   = sanitize_key( $data['order_id'] );

			if ( 'FRAUD_RISK_ACCEPTED' === $event_type ) {
				$order->payment_complete( $order_id );
				$order->add_order_note( 'Payment via Klarna Payments.' );
			} elseif ( 'FRAUD_RISK_REJECTED' === $event_type || 'FRAUD_RISK_STOPPED' === $event_type ) {
				$request      = new WC_Klarna_Order_Management_Request( array(
					'request'  => 'retrieve',
					'order_id' => $order_id,
				) );
				$klarna_order = $request->response();

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
	}

	/**
	 * Gets WooCommerce order ID from Klarna order ID.
	 *
	 * @param $klarna_order_id
	 *
	 * @return $order_id
	 */
	private static function get_order_id_from_klarna_order_id( $klarna_order_id ) {
		$query_args = array(
			'post_type'   => wc_get_order_types(),
			'post_status' => array_keys( wc_get_order_statuses() ),
			'meta_key'    => '_wc_klarna_order_id',
			'meta_value'  => $klarna_order_id,
		);

		$orders = get_posts( $query_args );

		// If zero matching orders were found, return.
		if ( empty( $orders ) ) {
			return;
		}

		$order_id = $orders[0]->ID;

		return $order_id;
	}

}
