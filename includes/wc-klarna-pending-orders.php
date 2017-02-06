<?php
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
			$order_id = intval( $_GET['order_id'] ); // Input var okay.
			$order = wc_get_order( $order_id );

			$post_body = file_get_contents( 'php://input' );
			$data = json_decode( $post_body, true );

			if ( 'FRAUD_RISK_ACCEPTED' === $data['event_type'] ) {
				$order->payment_complete( $data['order_id'] );
				$order->add_order_note( 'Payment via Klarna Payments.' );
				add_post_meta( $order_id, '_wc_klarna_payments_order_id', $data['order_id'], true );
			} elseif ( 'FRAUD_RISK_REJECTED' === $data['event_type'] || 'FRAUD_RISK_STOPPED' === $data['event_type'] ) {
				// Set meta field so order cancellation doesn't trigger Klarna API requests.
				add_post_meta( $order_id, '_wc_klarna_pending_to_cancelled', true, true );
				$order->cancel_order( 'Klarna order rejected.' );
			}
		}
	}

}