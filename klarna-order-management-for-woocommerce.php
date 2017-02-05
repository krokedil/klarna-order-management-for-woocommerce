<?php
/*
 * Plugin Name: Klarna Order Management for WooCommerce
 * Plugin URI: https://krokedil.se/
 * Description: Provides order management for Klarna plugins.
 * Author: Krokedil
 * Author URI: https://krokedil.se/
 * Version: 0.1-alpha
 * Text Domain: klarna-order-management-for-woocommerce
 * Domain Path: /languages
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// @TODO: Make translateable

/**
 * Required minimums and constants
 */
define( 'WC_KLARNA_ORDER_MANAGEMENT_VERSION', '0.1-alpha' );
define( 'WC_KLARNA_ORDER_MANAGEMENT_MIN_PHP_VER', '5.3.0' );
define( 'WC_KLARNA_ORDER_MANAGEMENT_MIN_WC_VER', '2.5.0' );
define( 'WC_KLARNA_ORDER_MANAGEMENT_MAIN_FILE', __FILE__ );
define( 'WC_KLARNA_ORDER_MANAGEMENT_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );

if ( ! class_exists( 'WC_Klarna_Order_Management' ) ) {

	/**
	 * Class WC_Klarna_Order_Management
	 */
	class WC_Klarna_Order_Management {

		/**
		 * What this class needs to do:
		 *
		 *    Pre-delivery
		 *    ============
		 * 1. Update order amount
		 *    - Updated 'order_amount' must not be negative
		 *    - Updated 'order_amount' must not be less than current 'captured_amount'
		 * 2. Retrieve an order
		 * 3. Update billing and/or shipping address
		 *    - Fields can be updated independently. To clear a field, set its value to "" (empty string), mandatory fields can not be cleared
		 * 4. Update merchant references (update order ID, probably not needed)
		 *    - Update one or both merchant references. To clear a reference, set its value to "" (empty string)
		 *
		 *    Delivery
		 *    ========
		 * 1. (DONE) Capture full amount (and store capture ID as order meta field)
		 *    - 'captured_amount' must be equal to or less than the order's 'remaining_authorized_amount'
		 *    - Shipping address (for the capture) is inherited from the order
		 * 2. (NO) Capture part of the order amount (can't do this in WooCommerce)
		 *
		 *    Post delivery
		 *    =============
		 * 1. Retrieve a capture
		 * 2. (NO) Add new shipping information ('shipping_info', not address) to a capture (can't do this with WooCommerce alone)
		 * 3. Update billing address for a capture (do this when updating WC order after the capture)
		 *    - Fields can be updated independently. To clear a field, set its value to "" (empty string), mandatory fields can not be cleared
		 * 4. (NO) Trigger a new send out of customer communication (no need to do this right away)
		 * 5. Refund an amount of a captured order
		 *    - The refunded amount must not be higher than 'captured_amount'
		 *    - The refunded amount can optionally be accompanied by a descriptive text and order lines
		 * 6. Release the remaining authorization for an order (can't do this, because there's no partial captures)
		 *
		 *    Pending orders
		 *    ==============
		 *  - notification_url is used for callbacks
		 *  - Orders should be set to on hold during checkout if fraud_status: PENDING
		 */


		/**
		 * @var $instance reference the *Singleton* instance of this class
		 */
		private static $instance;

		/**
		 * @var Reference to logging class.
		 *
		 * @TODO: Add logging.
		 */
		private static $log;

		/**
		 * Returns the *Singleton* instance of this class.
		 *
		 * @return Singleton The *Singleton* instance.
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Private clone method to prevent cloning of the instance of the
		 * *Singleton* instance.
		 *
		 * @return void
		 */
		private function __clone() {}

		/**
		 * Private unserialize method to prevent unserializing of the *Singleton*
		 * instance.
		 *
		 * @return void
		 */
		private function __wakeup() {}

		/**
		 * Protected constructor to prevent creating a new instance of the
		 * *Singleton* via the `new` operator from outside of this class.
		 */
		protected function __construct() {
			add_action( 'plugins_loaded', array( $this, 'init' ) );

			// add_action( 'wp_head', array( $this, 'test' ) );
		}

		/**
		 * Init the plugin at plugins_loaded.
		 */
		public function init() {
			// Do nothing if Klarna Payments plugin is not active.
			if ( ! class_exists( 'WC_Klarna_Payments' ) ) {
				return;
			}

			include_once( dirname( __FILE__ ) . '/includes/wc-klarna-order-management-request.php' );
			include_once( dirname( __FILE__ ) . '/includes/wc-klarna-order-management-order-lines.php' );
			include_once( dirname( __FILE__ ) . '/includes/wc-klarna-pending-orders.php' );

			add_action( 'wc_klarna_payments_supports', array( $this, 'add_gateway_support' ) );

			// Cancel order.
			add_action( 'woocommerce_order_status_cancelled', array( $this, 'cancel_klarna_order' ) );

			// Capture an order.
			add_action( 'woocommerce_order_status_completed', array( $this, 'capture_klarna_order' ) );

			// Update an order.
			add_action( 'woocommerce_before_save_order_items', array( $this, 'update_klarna_order' ), 10, 2 );

			/*
			// Add order item.
			add_action( 'woocommerce_ajax_add_order_item_meta', array( $this, 'update_klarna_order_add_item' ), 10, 3 );

			// Remove order item.
			add_action( 'woocommerce_before_delete_order_item', array( $this, 'update_klarna_order_delete_item' ) );

			// Edit an order item and save.
			add_action( 'woocommerce_saved_order_items', array( $this, 'update_klarna_order_edit_item' ), 10, 2 );
			*/
		}

		/**
		 * Add refunds support to Klarna Payments gateway.
		 *
		 * @param array $features Supported features.
		 */
		public function add_gateway_support( $features ) {
			$features[] = 'refunds';
		}

		/**
		 * Instantiate WC_Logger class.
		 *
		 * @param string $message Log message.
		 */
		public static function log( $message ) {
			if ( empty( self::$log ) ) {
				self::$log = new WC_Logger();
			}
			self::$log->add( 'klarna-order-management-for-woocommerce', $message );
		}

		/**
		 * Cancels a Klarna order.
		 *
		 * @param int $order_id Order ID.
		 */
		public function cancel_klarna_order( $order_id ) {
			$order = wc_get_order( $order_id );

			// Not going to do this for non-KP orders.
			if ( 'klarna_payments' !== $order->payment_method ) {
				return;
			}

			// Retrieve Klarna order first.
			$request = new WC_Klarna_Order_Management_Request( 'retrieve', $order_id );
			$klarna_order = $request->response();

			if ( ! in_array( $klarna_order->status, array( 'CAPTURED', 'PART_CAPTURED', 'CANCELLED' ), true ) ) {
				$request = new WC_Klarna_Order_Management_Request( 'cancel', $order_id, $klarna_order );
				$response = $request->response();

				if ( ! is_wp_error( $response ) ) {
					$order->add_order_note( 'Klarna order cancelled.' );
					add_post_meta( $order_id, '_wc_klarna_cancelled', 'yes', true );
				} else {
					$order->add_order_note( 'Could not cancel Klarna order. ' . $response->get_error_message() );
				}
			}
		}

		/**
		 * Updates a Klarna order.
		 *
		 * @param int   $order_id Order ID.
		 * @param array $items Order items.
		 */
		public function update_klarna_order( $order_id, $items ) {
			$order = wc_get_order( $order_id );

			// Not going to do this for non-KP orders.
			if ( 'klarna_payments' !== $order->payment_method ) {
				return;
			}

			// Changes only possible if order is set to On Hold.
			if ( 'on-hold' !== $order->get_status() ) {
				return;
			}

			$request = new WC_Klarna_Order_Management_Request( 'retrieve', $order_id );
			$klarna_order = $request->response();

			if ( ! in_array( $klarna_order->status, array( 'CANCELLED' ), true ) && in_array( $klarna_order->status, array( 'CAPTURED', 'PART_CAPTURED' ), true ) ) {
				$request = new WC_Klarna_Order_Management_Request( 'update', $order_id, $klarna_order );
				$response = $request->response();

				if ( ! is_wp_error( $response ) ) {
					$order->add_order_note( 'Klarna order cancelled.' );
				}
			}
		}

		/**
		 * Captures a Klarna order.
		 *
		 * @param int $order_id Order ID.
		 */
		public function capture_klarna_order( $order_id ) {
			$order = wc_get_order( $order_id );

			// Not going to do this for non-KP orders.
			if ( 'klarna_payments' !== $order->payment_method ) {
				return;
			}

			// Do nothing if Klarna order was already captured.
			if ( get_post_meta( $order_id, '_wc_klarna_capture_id', true ) ) {
				return;
			}

			// Retrieve Klarna order first.
			$request = new WC_Klarna_Order_Management_Request( 'retrieve', $order_id );
			$klarna_order = $request->response();

			if ( ! in_array( $klarna_order->status, array( 'CAPTURED', 'PART_CAPTURED', 'CANCELLED' ), true ) ) {
				$request = new WC_Klarna_Order_Management_Request( 'capture', $order_id, $klarna_order );
				$response = $request->response();

				if ( ! is_wp_error( $response ) ) {
					$order->add_order_note( 'Klarna order captured. Capture ID: ' . $response );
					add_post_meta( $order_id, '_wc_klarna_capture_id', $response, true );
				} else {
					$order->add_order_note( 'Could not capture Klarna order. ' . $response->get_error_message() );
				}
			}
		}

	}

	WC_Klarna_Order_Management::get_instance();

}