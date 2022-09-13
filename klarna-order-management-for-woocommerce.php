<?php // phpcs:ignore
/**
 * Plugin Name: Klarna Order Management for WooCommerce
 * Plugin URI: https://krokedil.se/klarna/
 * Description: Provides order management for Klarna Payments and Klarna Checkout gateways.
 * Author: klarna, krokedil
 * Author URI: https://krokedil.se/
 * Version: 1.6.10
 * Text Domain: klarna-order-management-for-woocommerce
 * Domain Path: /languages
 *
 * WC requires at least: 3.4.0
 * WC tested up to: 6.4.0
 *
 * Copyright (c) 2018-2022 Krokedil
 *
 * @package WC_Klarna_Order_Management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Required minimums and constants
 */
define( 'WC_KLARNA_ORDER_MANAGEMENT_VERSION', '1.6.10' );
define( 'WC_KLARNA_ORDER_MANAGEMENT_MIN_PHP_VER', '5.3.0' );
define( 'WC_KLARNA_ORDER_MANAGEMENT_MIN_WC_VER', '3.3.0' );
define( 'WC_KLARNA_ORDER_MANAGEMENT_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );

if ( ! class_exists( 'WC_Klarna_Order_Management' ) ) {

	/**
	 * Class WC_Klarna_Order_Management
	 */
	class WC_Klarna_Order_Management {

		/**
		 * *Singleton* instance of this class
		 *
		 * @var $instance
		 */
		private static $instance;

		/**
		 * Returns the *Singleton* instance of this class.
		 *
		 * @return self The *Singleton* instance.
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
		private function __clone() {
		}

		/**
		 * Private unserialize method to prevent unserializing of the *Singleton*
		 * instance.
		 *
		 * @return void
		 */
		public function __wakeup() {
		}

		/**
		 * Protected constructor to prevent creating a new instance of the
		 * *Singleton* via the `new` operator from outside of this class.
		 */
		protected function __construct() {
			add_action( 'plugins_loaded', array( $this, 'init' ) );

			// Add action links.
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
		}

		/**
		 * Init the plugin at plugins_loaded.
		 */
		public function init() {
			// Check if we have KP settings, so we can retrieve credentials.
			if ( ! get_option( 'woocommerce_klarna_payments_settings' ) && ! get_option( 'woocommerce_kco_settings' ) ) {
				return;
			}

			if ( ! is_array( get_option( 'woocommerce_klarna_payments_settings' ) ) && ! is_array( get_option( 'woocommerce_kco_settings' ) ) ) {
				return;
			}

			include_once WC_KLARNA_ORDER_MANAGEMENT_PLUGIN_PATH . '/includes/class-wc-klarna-order-management-request.php';
			include_once WC_KLARNA_ORDER_MANAGEMENT_PLUGIN_PATH . '/includes/class-wc-klarna-order-management-order-lines.php';
			include_once WC_KLARNA_ORDER_MANAGEMENT_PLUGIN_PATH . '/includes/class-wc-klarna-pending-orders.php';
			include_once WC_KLARNA_ORDER_MANAGEMENT_PLUGIN_PATH . '/includes/class-wc-klarna-sellers-app.php';
			include_once WC_KLARNA_ORDER_MANAGEMENT_PLUGIN_PATH . '/includes/class-wc-klarna-meta-box.php';
			include_once WC_KLARNA_ORDER_MANAGEMENT_PLUGIN_PATH . '/includes/class-wc-klarna-order-management-settings.php';
			include_once WC_KLARNA_ORDER_MANAGEMENT_PLUGIN_PATH . '/includes/class-wc-klarna-logger.php';

			// Add refunds support to Klarna Payments and Klarna Checkout gateways.
			add_action( 'wc_klarna_payments_supports', array( $this, 'add_gateway_support' ) );
			add_action( 'kco_wc_supports', array( $this, 'add_gateway_support' ) );

			// Cancel order.
			add_action( 'woocommerce_order_status_cancelled', array( $this, 'cancel_klarna_order' ) );

			// Capture an order.
			add_action( 'woocommerce_order_status_completed', array( $this, 'capture_klarna_order' ) );

			// Update an order.
			add_action( 'woocommerce_saved_order_items', array( $this, 'update_klarna_order_items' ), 10, 2 );

			// Refund an order.
			add_filter( 'wc_klarna_payments_process_refund', array( $this, 'refund_klarna_order' ), 10, 4 );
			add_filter( 'wc_klarna_checkout_process_refund', array( $this, 'refund_klarna_order' ), 10, 4 );

			// Pending orders.
			add_action(
				'wc_klarna_notification_listener',
				array(
					'WC_Klarna_Pending_Orders',
					'notification_listener',
				),
				10,
				2
			);
		}

		/**
		 * Adds plugin action link to Krokedil documentation for KOM.
		 *
		 * @param array $links Plugin action link before filtering.
		 *
		 * @return array Filtered links.
		 */
		public function plugin_action_links( $links ) {
			$plugin_links = array(
				'<a target="_blank" href="https://docs.krokedil.com/article/149-klarna-order-management">Docs</a>',
			);

			return array_merge( $plugin_links, $links );
		}

		/**
		 * Add refunds support to Klarna Payments gateway.
		 *
		 * @param array $features Supported features.
		 *
		 * @return array $features Supported features.
		 */
		public function add_gateway_support( $features ) {
			$features[] = 'refunds';

			return $features;
		}

		/**
		 * Cancels a Klarna order.
		 *
		 * @param int  $order_id Order ID.
		 * @param bool $action If this was triggered through an action or not.
		 */
		public function cancel_klarna_order( $order_id, $action = false ) {
			$options = get_option( 'kom_settings' );
			if ( ! isset( $options['kom_auto_cancel'] ) || 'yes' === $options['kom_auto_cancel'] || $action ) {
				$order = wc_get_order( $order_id );

				// Check if the order has been paid.
				if ( empty( $order->get_date_paid() ) ) {
					return;
				}

				// Not going to do this for non-KP and non-KCO orders.
				if ( ! in_array(
					$order->get_payment_method(),
					array(
						'klarna_payments',
						'kco',
					),
					true
				) ) {
					return;
				}

				// Don't do this if the order is being rejected in pending flow.
				if ( get_post_meta( $order_id, '_wc_klarna_pending_to_cancelled', true ) ) {
					return;
				}

				// Retrieve Klarna order first.
				$klarna_order = $this->retrieve_klarna_order( $order_id );

				if ( is_wp_error( $klarna_order ) ) {
					$order->add_order_note( 'Klarna order could not be cancelled due to an error.' );

					return;
				}

				// Captured, part-captured and cancelled orders cannot be cancelled.
				if ( in_array( $klarna_order->status, array( 'CAPTURED', 'PART_CAPTURED' ), true ) ) {
					$order->add_order_note( 'The Klarna order cannot be cancelled due to it already being captured.' );
				} elseif ( 'CANCELLED' === $klarna_order->status ) {
					$order->add_order_note( 'Klarna order has already been cancelled.' );
				} else {
					$request  = new WC_Klarna_Order_Management_Request(
						array(
							'request'      => 'cancel',
							'order_id'     => $order_id,
							'klarna_order' => $klarna_order,
						)
					);
					$response = $request->response();

					if ( ! is_wp_error( $response ) ) {
						$order->add_order_note( 'Klarna order cancelled.' );
						update_post_meta( $order_id, '_wc_klarna_cancelled', 'yes', true );
					} else {
						$order->add_order_note( 'Could not cancel Klarna order. ' . $response->get_error_message() . '.' );
					}
				}
			}
		}

		/**
		 * Updates Klarna order items.
		 *
		 * @param int   $order_id Order ID.
		 * @param array $items Order items.
		 * @param bool  $action If this was triggered by an action.
		 */
		public function update_klarna_order_items( $order_id, $items, $action = false ) {
			$options = get_option( 'kom_settings' );
			if ( ! isset( $options['kom_auto_update'] ) || 'yes' === $options['kom_auto_update'] || $action ) {

				$order = wc_get_order( $order_id );

					// Check if the order has been paid.
				if ( empty( $order->get_date_paid() ) ) {
					return;
				}

				// Not going to do this for non-KP and non-KCO orders.
				if ( ! in_array(
					$order->get_payment_method(),
					array(
						'klarna_payments',
						'kco',
					),
					true
				) ) {
					return;
				}

				// Changes are only possible if order is an allowed order status.
				if ( ! in_array( $order->get_status(), apply_filters( 'kom_allowed_update_statuses', array( 'on-hold' ) ), true ) ) {
					return;
				}

				// Retrieve Klarna order first.
				$klarna_order = $this->retrieve_klarna_order( $order_id );

				if ( is_wp_error( $klarna_order ) ) {
					$order->add_order_note( 'Klarna order could not be updated due to an error.' );

					return;
				}

				if ( ! in_array( $klarna_order->status, array( 'CANCELLED', 'CAPTURED', 'PART_CAPTURED' ), true ) ) {
					$request  = new WC_Klarna_Order_Management_Request(
						array(
							'request'      => 'update_order_lines',
							'order_id'     => $order_id,
							'klarna_order' => $klarna_order,
						)
					);
					$response = $request->response();
					if ( ! is_wp_error( $response ) ) {
						$order->add_order_note( 'Klarna order updated.' );
					} else {
						$order_note = 'Could not update Klarna order lines.';
						if ( '' !== $response->get_error_message() ) {
							$order_note .= ' ' . $response->get_error_message() . '.';
						}
						$order->add_order_note( $order_note );
					}
				}
			}
		}

		/**
		 * Captures a Klarna order.
		 *
		 * @param int  $order_id Order ID.
		 * @param bool $action If this was triggered by an action.
		 */
		public function capture_klarna_order( $order_id, $action = false ) {
			$options = get_option( 'kom_settings' );
			if ( ! isset( $options['kom_auto_capture'] ) || 'yes' === $options['kom_auto_capture'] || $action ) {
				$order = wc_get_order( $order_id );

					// Check if the order has been paid.
				if ( empty( $order->get_date_paid() ) ) {
					return;
				}

				// Not going to do this for non-KP and non-KCO orders.
				if ( ! in_array(
					$order->get_payment_method(),
					array(
						'klarna_payments',
						'kco',
					),
					true
				) ) {
					return;
				}
				// Do nothing if Klarna order was already captured.
				if ( get_post_meta( $order_id, '_wc_klarna_capture_id', true ) ) {
					$order->add_order_note( 'Klarna order has already been captured.' );

					return;
				}
				// Do nothing if we don't have Klarna order ID.
				if ( ! get_post_meta( $order_id, '_wc_klarna_order_id', true ) && ! get_post_meta( $order_id, '_transaction_id', true ) ) {
					$order->add_order_note( 'Klarna order ID is missing, Klarna order could not be captured at this time.' );
					$order->set_status( 'on-hold' );
					$order->save();
					return;
				}
				// Retrieve Klarna order.
				$klarna_order = $this->retrieve_klarna_order( $order_id );

				if ( is_wp_error( $klarna_order ) ) {
					$order->add_order_note( 'Klarna order could not be captured due to an error.' );
					$order->set_status( 'on-hold' );
					$order->save();
					return;
				}
				// Check if order is pending review.
				if ( 'PENDING' === $klarna_order->fraud_status ) {
					$order->add_order_note( 'Klarna order is pending review and could not be captured at this time.' );
					$order->set_status( 'on-hold' );
					$order->save();
					return;
				}
				// Check if Klarna order has already been captured.
				if ( in_array( $klarna_order->status, array( 'CAPTURED' ), true ) ) {
					$order->add_order_note( 'Klarna order has already been captured on ' . $klarna_order->captures[0]->captured_at );
					update_post_meta( $order_id, '_wc_klarna_capture_id', $klarna_order->captures[0]->capture_id );
					return;
				}
				// Check if Klarna order has already been canceled.
				if ( 'CANCELLED' === $klarna_order->status ) {
					$order->add_order_note( 'Klarna order failed to capture, the order has already been canceled' );

					return;
				}
				// Only send capture request if Klarna order fraud status is accepted.
				if ( 'ACCEPTED' !== $klarna_order->fraud_status ) {
					$order->add_order_note( 'Klarna order could not be captured at this time.' );

					return;
				} else {
					$request  = new WC_Klarna_Order_Management_Request(
						array(
							'request'      => 'capture',
							'order_id'     => $order_id,
							'klarna_order' => $klarna_order,
						)
					);
					$response = $request->response();

					if ( ! is_wp_error( $response ) ) {
						$order->add_order_note( 'Klarna order captured. Capture amount: ' . $order->get_formatted_order_total( '', false ) . '. Capture ID: ' . $response );
						update_post_meta( $order_id, '_wc_klarna_capture_id', $response, true );
					} else {

						/* The suggested approach by Klarna is to try again after some time. If that still fails, the merchant should inform the customer, and ask them to either "create a new subscription or add funds to their payment method if they wish to continue." */
						if ( isset( $response->get_error_data()['code'] ) && 403 === $response->get_error_data()['code'] && 'PAYMENT_METHOD_FAILED' === $response->get_error_code() ) {
							$order = wc_get_order( $order_id );
							$order->add_order_note( __( 'Klarna could not charge the customer. Please try again later. If that still fails, the customer may have to create a new subscription or add funds to their payment method if they wish to continue.', 'klarna-order-management-for-woocommerce' ) );
						} else {
							// translators: %s: Error message from Klarna.
							$order->add_order_note( __( sprintf( 'Could not capture Klarna order. %s', $response->get_error_message() ), 'klarna-order-management-for-woocommerce' ) );
						}

						$order->set_status( 'on-hold' );
						$order->save();
					}
				}
			}
		}

		/**
		 * Refund a Klarna order.
		 *
		 * @param bool        $result Refund attempt result.
		 * @param int         $order_id WooCommerce order ID.
		 * @param null|string $amount Refund amount, full order amount if null.
		 * @param string      $reason Refund reason.
		 *
		 * @return bool $result Refund attempt result.
		 */
		public function refund_klarna_order( $result, $order_id, $amount = null, $reason = '' ) {
			$order = wc_get_order( $order_id );

			// Not going to do this for non-KP and non-KCO orders.
			if ( ! in_array(
				$order->get_payment_method(),
				array(
					'klarna_payments',
					'kco',
				),
				true
			) ) {
				return false;
			}

			// Do nothing if Klarna order was already captured.
			if ( ! get_post_meta( $order_id, '_wc_klarna_capture_id', true ) ) {
				$order->add_order_note( 'Klarna order has not been captured and cannot be refunded.' );

				return false;
			}

			// Retrieve Klarna order first.
			$klarna_order = $this->retrieve_klarna_order( $order_id );

			if ( is_wp_error( $klarna_order ) ) {
				$order->add_order_note( 'Could not capture Klarna order. ' . $klarna_order->get_error_message() . '.' );

				return false;
			}

			if ( in_array( $klarna_order->status, array( 'CAPTURED', 'PART_CAPTURED' ), true ) ) {
				$request  = new WC_Klarna_Order_Management_Request(
					array(
						'request'       => 'refund',
						'order_id'      => $order_id,
						'klarna_order'  => $klarna_order,
						'refund_amount' => $amount,
						'refund_reason' => $reason,
					)
				);
				$response = $request->response();

				if ( ! is_wp_error( $response ) ) {
					$order->add_order_note( wc_price( $amount, array( 'currency' => get_post_meta( $order_id, '_order_currency', true ) ) ) . ' refunded via Klarna.' );
					update_post_meta( $order_id, '_wc_klarna_capture_id', $response, true );

					return true;
				} else {
					$order->add_order_note( 'Could not capture Klarna order. ' . $response->get_error_message() . '.' );
				}
			}

			return false;
		}

		/**
		 * Retrieve a Klarna order.
		 *
		 * @param int $order_id WooCommerce order ID.
		 *
		 * @return object $klarna_order Klarna Order.
		 */
		public function retrieve_klarna_order( $order_id ) {
			$request      = new WC_Klarna_Order_Management_Request(
				array(
					'request'  => 'retrieve',
					'order_id' => $order_id,
				)
			);
			$klarna_order = $request->response();

			return $klarna_order;
		}
	}

	WC_Klarna_Order_Management::get_instance();

}
