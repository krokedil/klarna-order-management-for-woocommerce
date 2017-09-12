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
 *
 * @TODO: Move stuff here from Klarna Payments plugin.
 */
class WC_Klarna_Pending_Orders {

	/**
	 * Notification listener for Pending orders.
	 *
	 * @link https://developers.klarna.com/en/us/kco-v3/pending-orders
	 */
	public static function notification_listener() {
		if ( $_GET['order_id'] ) { // Input var okay.
			$order_id = (int) $_GET['order_id']; // Input var okay.
			$order = wc_get_order( $order_id );

			$post_body = file_get_contents( 'php://input' );
			$data = json_decode( $post_body, true );

			if ( 'FRAUD_RISK_ACCEPTED' === $data['event_type'] ) {
				$order->payment_complete( $data['order_id'] );
				$order->add_order_note( 'Payment via Klarna Payments.' );
			} elseif ( 'FRAUD_RISK_REJECTED' === $data['event_type'] || 'FRAUD_RISK_STOPPED' === $data['event_type'] ) {
				$request = new WC_Klarna_Order_Management_Request( array(
					'request' => 'retrieve',
					'order_id' => $order_id,
				) );
				$klarna_order = $request->response();

				// Set meta field so order cancellation doesn't trigger Klarna API requests.
				add_post_meta( $order_id, '_wc_klarna_pending_to_cancelled', true, true );
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

}
