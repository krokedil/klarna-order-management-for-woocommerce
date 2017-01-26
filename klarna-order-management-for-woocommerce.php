<?php
/*
 * Plugin Name: Klarna Payments for WooCommerce
 * Plugin URI: https://krokedil.se/
 * Description: Provides Klarna Payments as payment method to WooCommerce.
 * Author: Krokedil
 * Author URI: https://krokedil.se/
 * Version: 0.1-alpha
 * Text Domain: klarna-payments-for-woocommerce
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
		}

		/**
		 * Init the plugin at plugins_loaded.
		 */
		public function init() {
			include_once( dirname( __FILE__ ) . '/includes/class-wc-klarna-order-management-request-setup.php' );
			include_once( dirname( __FILE__ ) . '/includes/class-wc-klarna-order-management-pre-delivery.php' );
			include_once( dirname( __FILE__ ) . '/includes/class-wc-klarna-order-management-delivery.php' );
			include_once( dirname( __FILE__ ) . '/includes/class-wc-klarna-order-management-post-delivery.php' );
			include_once( dirname( __FILE__ ) . '/includes/class-wc-klarna-pending-orders.php' );
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
	}

	WC_Klarna_Order_Management::get_instance();

}