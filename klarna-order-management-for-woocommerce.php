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
		 * 1. Update order amount
		 * 2. Retrieve an order
		 * 3. Update billing and/or shipping address
		 * 4. Update merchant references (update order ID, probably not needed)
		 *
		 *    Delivery
		 * 1. Capture full amount
		 * 2. Capture part of the order amount (can't do this in WooCommerce)
		 *
		 *    Post delivery
		 * 1. Retrieve a capture
		 * 2. Update billing address for a capture
		 * 3. Trigger a new send out of customer communication
		 * 4. Refund an amount of a captured order
		 * 5. Release the remaining authorization for an order
		 *
		 *    Pending orders
		 *  - notification_url is used for callbacks
		 *  - Orders should be set to on hold during checkout if fraud_status: PENDING
		 */


		/**
		 * @var Singleton The reference the *Singleton* instance of this class
		 */
		private static $instance;

		/**
		 * @var Reference to logging class.
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
			include_once( dirname( __FILE__ ) . '/includes/wc-klarna-order-management-request.php' );
			include_once( dirname( __FILE__ ) . '/includes/wc-klarna-order-management-order-lines.php' );
			// include_once( dirname( __FILE__ ) . '/includes/wc-klarna-pending-orders.php' );

			// Cancel order.
			add_action( 'woocommerce_order_status_cancelled', array( $this, 'cancel_klarna_order' ) );

			// Capture an order.
			add_action( 'woocommerce_order_status_completed', array( $this, 'capture_klarna_order' ) );

			// Update an order.
			add_action( 'woocommerce_before_save_order_items', array( $this, 'update_klarna_order'), 10, 2 );

			/*
			// Add order item.
			add_action( 'woocommerce_ajax_add_order_item_meta', array( $this, 'update_klarna_order_add_item' ), 10, 3 );

			// Remove order item.
			add_action( 'woocommerce_before_delete_order_item', array( $this, 'update_klarna_order_delete_item' ) );

			// Edit an order item and save.
			add_action( 'woocommerce_saved_order_items', array( $this, 'update_klarna_order_edit_item' ), 10, 2 );
			*/
		}

		public function test() {
			$request = new WC_Klarna_Order_Management_Request( 'capture', 260 );
			$response = $request->response();

			// $order = wc_get_order( 255 );
			// $order1 = '';

			// $order_lines_processor = new WC_Klarna_Order_Management_Order_Lines( 255 );
			// $order_lines = $order_lines_processor->order_lines();
			$a = 1;
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

			$request = new WC_Klarna_Order_Management_Request( 'retrieve', $order_id );
			$klarna_order = $request->response();

			if ( ! in_array( $klarna_order->status, array( 'CAPTURED', 'PART_CAPTURED', 'CANCELLED' ), true ) ) {
				$request = new WC_Klarna_Order_Management_Request( 'cancel', $order_id, $klarna_order );
				$response = $request->response();

				if ( ! is_wp_error( $response ) ) {
					$order->add_order_note( 'Klarna order cancelled.' );
				}
			}
		}

		/**
		 * Updates a Klarna order.
		 *
		 * @param int $order_id Order ID.
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
		 * Captures Klarna order.
		 *
		 * @param int $order_id Order ID.
		 */
		public function capture_klarna_order( $order_id ) {
			$order = wc_get_order( $order_id );

			// Not going to do this for non-KP orders.
			if ( 'klarna_payments' !== $order->payment_method ) {
				return;
			}

			// Retrieve Klarna order first.
			$request = new WC_Klarna_Order_Management_Request( 'retrieve', $order_id );
			$klarna_order = $request->response();

			if ( ! in_array( $klarna_order->status, array( 'CAPTURED', 'PART_CAPTURED', 'CANCELLED' ), true ) ) {
				$request = new WC_Klarna_Order_Management_Request( 'capture', $order_id, $klarna_order );
				$response = $request->response();

				if ( ! is_wp_error( $response ) ) {
					$order->add_order_note( 'Klarna order captured.' );
				}
			}
		}

	}

	WC_Klarna_Order_Management::get_instance();

}