<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * WC_Klarna_Order_Management_Request class.
 *
 * Gets Klarna credentials based on order country.
 */
class WC_Klarna_Order_Management_Request {

	var $request;
	var $order_id;
	var $klarna_payments_settings;
	var $klarna_capture_id;
	var $klarna_order;
	var $klarna_order_id;
	var $klarna_merchant_id;
	var $klarna_shared_secret;
	var $klarna_server_base;
	var $klarna_request_url;
	var $klarna_request_method;
	var $klarna_request_body;
	var $klarna_authorization_header;

	/**
	 * WC_Klarna_Order_Management_Request constructor.
	 *
	 * @param array $args Klarna request arguments.
	 */
	public function __construct( $args = array() ) {
		$this->request                     = $args['request'];
		$this->order_id                    = $args['order_id'];
		$this->klarna_capture_id           = array_key_exists( 'klarna_capture_id', $args ) ? $args['klarna_capture_id'] : false;
		$this->klarna_order                = array_key_exists( 'klarna_order', $args ) ? $args['klarna_order'] : false;
		$this->refund_amount               = array_key_exists( 'refund_amount', $args ) ? $args['refund_amount'] : 0;
		$this->refund_reason               = array_key_exists( 'refund_reason', $args ) ? $args['refund_reason'] : '';

		$this->klarna_order_id             = $this->get_klarna_order_id();
		$this->klarna_server_base          = $this->get_server_base();
		$klarna_request_details            = $this->get_request_details();
		$this->klarna_request_url          = $this->klarna_server_base . $klarna_request_details['url'];
		$this->klarna_request_method       = $klarna_request_details['method'];
		$this->klarna_request_body         = array_key_exists( 'body', $klarna_request_details ) ? $klarna_request_details['body'] : false;
		$this->klarna_authorization_header = $this->get_klarna_authorization_header();
		$this->klarna_payments_settings    = get_option( 'woocommerce_klarna_payments_settings' );
		$this->klarna_merchant_id          = $this->get_merchant_id();
		$this->klarna_shared_secret        = $this->get_shared_secret();
	}

	/**
	 * @return array|mixed|object|WP_Error
	 */
	public function response() {
		$request_args = array(
			'headers' => array(
				'Authorization' => $this->get_klarna_authorization_header(),
				'Content-Type'  => 'application/json',
			),
			'method' => $this->klarna_request_method,
		);

		if ( $this->klarna_request_body ) {
			if ( 'order_lines' === $this->klarna_request_body ) {
				$order_lines_processor = new WC_Klarna_Order_Management_Order_Lines( $this->order_id );
				$order_lines = $order_lines_processor->order_lines();

				$request_args['body'] = wp_json_encode( array(
					'order_lines'      => $order_lines['order_lines'],
					'order_amount'     => $order_lines['order_amount'],
					'order_tax_amount' => $order_lines['order_tax_amount'],
				) );
			} elseif ( 'capture' === $this->klarna_request_body ) {
				$order = wc_get_order( $this->order_id );
				$request_args['body'] = wp_json_encode( array(
					'captured_amount' => $order->get_total() * 100,
				) );
			} elseif ( 'refund' === $this->klarna_request_body ) {
				// @TODO: Send order lines as well. Not always possible, but should be done when it is.
				$request_args['body'] = wp_json_encode( array(
					'refunded_amount' => (int) $this->refund_amount * 100,
					'description'     => $this->refund_reason,
				) );
			}
		}

		$response = wp_safe_remote_request(
			$this->klarna_request_url,
			$request_args
		);

		return $this->process_response( $response );
	}

	/**
	 * Get Klarna order management request authorization header for WooCommerce order.
	 *
	 * @param  integer $order_id WooCommerce order ID.
	 *
	 * @return string|WP_Error
	 */
	public function get_klarna_authorization_header() {
		$order = wc_get_order( $this->order_id );

		// @TODO: Once KCO is separate plugin, check which gateway was used to create the order
		if ( 'yes' !== $this->klarna_payments_settings['enabled'] ) {
			return new WP_Error( 'gateway_disabled', 'Klarna Payments gateway is currently disabled' );
		}

		// @TODO: Based on order country and testmode get appropriate fields here
		if ( '' === $this->klarna_merchant_id || '' === $this->klarna_shared_secret ) {
			return new WP_Error( 'missing_credentials', 'Klarna Payments credentials are missing' );
		}

		return 'Basic ' . base64_encode( $this->klarna_merchant_id . ':' . $this->klarna_shared_secret );
	}

	/**
	 * Gets merchant ID from WooCommerce order, based on environment and country.
	 *
	 * @TODO: Consider other countries too, currently defaults to US.
	 */
	public function get_merchant_id() {
		$order = wc_get_order( $this->order_id );

		switch ( get_post_meta( $this->order_id, '_wc_klarna_environment', true ) ) {
			case 'us-test':
				$merchant_id = $this->klarna_payments_settings['test_merchant_id_us'];
				break;
			case 'us-live':
				$merchant_id = $this->klarna_payments_settings['merchant_id'];
				break;
			case 'eu-test':
				$merchant_id = $this->klarna_payments_settings['test_merchant_id_eu'];
				break;
			case 'eu-live':
				$merchant_id = $this->klarna_payments_settings['merchant_id_eu'];
				break;
			default:
				$merchant_id = '';
		}

		return $merchant_id;
	}

	/**
	 * Gets merchant ID from WooCommerce order, based on environment and country.
	 *
	 * @TODO: Consider other countries too, currently defaults to US.
	 */
	public function get_shared_secret() {
		$order = wc_get_order( $this->order_id );

		switch ( get_post_meta( $this->order_id, '_wc_klarna_environment', true ) ) {
			case 'us-test':
				$shared_secret = $this->klarna_payments_settings['test_shared_secret_us'];
				break;
			case 'us-live':
				$shared_secret = $this->klarna_payments_settings['shared_secret_us'];
				break;
			case 'eu-test':
				$shared_secret = $this->klarna_payments_settings['test_shared_secret_eu'];
				break;
			case 'eu-live':
				$shared_secret = $this->klarna_payments_settings['shared_secret_eu'];
				break;
			default:
				$shared_secret = '';
		}

		return $shared_secret;
	}

	/**
	 * Gets Klarna server base WooCommerce order, based on environment and country.
	 *
	 * @TODO: Consider other countries too, currently defaults to US.
	 */
	public function get_server_base() {
		switch ( get_post_meta( $this->order_id, '_wc_klarna_environment', true ) ) {
			case 'us-test':
				$server_base = 'https://api-na.playground.klarna.com';
				break;
			case 'us-live':
				$server_base = 'https://api-na.klarna.com';
				break;
			case 'eu-test':
				$server_base = 'https://api.playground.klarna.com';
				break;
			case 'eu-live':
				$server_base = 'https://api.klarna.com';
				break;
			default:
				$server_base = '';
		}

		return $server_base;
	}

	/**
	 * Gets Klarna order ID from WooCommerce order.
	 *
	 * @param int $order_id WooCommerce order ID.
	 *
	 * @return mixed
	 */
	public function get_klarna_order_id() {
		return get_post_meta( $this->order_id, '_wc_klarna__order_id', true );
	}

	/**
	 * Gets Klarna API request details.
	 */
	public function get_request_details() {
		$requests = array(
			'update_order_lines' => array(
				'url' => '/ordermanagement/v1/orders/' . $this->klarna_order_id . '/authorization',
				'method' => 'PATCH',
				'body' => 'order_lines',
			),
			'cancel' => array(
				'url' => '/ordermanagement/v1/orders/' . $this->klarna_order_id . '/cancel',
				'method' => 'POST',
			),
			'retrieve' => array(
				'url' => '/ordermanagement/v1/orders/' . $this->klarna_order_id,
				'method' => 'GET',
			),
			'update_address' => array(
				'url' => '/ordermanagement/v1/orders/' . $this->klarna_order_id . '/customer-details',
				'method' => 'PATCH',
				'body' => 'addresses',
			),
			'update_merchant_reference' => array(
				'url' => '/ordermanagement/v1/orders/' . $this->klarna_order_id . '/merchant-references',
				'method' => 'PATCH',
				'body' => 'merchant_reference',
			),
			'capture' => array(
				'url' => '/ordermanagement/v1/orders/' . $this->klarna_order_id . '/captures',
				'method' => 'POST',
				'body' => 'capture',
			),
			'retrieve_captures' => array(
				'url' => '/ordermanagement/v1/orders/' . $this->klarna_order_id . '/captures',
				'method' => 'GET',
			),
			'retrieve_capture' => array(
				'url' => '/ordermanagement/v1/orders/' . $this->klarna_order_id . '/captures/' . $this->klarna_capture_id,
				'method' => 'GET',
			),
			'update_capture_billing_address' => array(
				'url' => '',
				'method' => '',
			),
			'add_shipping_info_to_capture' => array(
				'url' => '/ordermanagement/v1/orders/' . $this->klarna_order_id . ' /captures/' . $this->klarna_capture_id . '/shipping-info',
				'method' => 'POST',
				'body' => 'shipping_info',
			),
			'trigger_communication' => array(
				'url' => '/ordermanagement/v1/orders/' . $this->klarna_order_id . '/captures/' . $this->klarna_capture_id . '/trigger-send-out',
				'method' => 'POST',
			),
			'refund' => array(
				'url' => '/ordermanagement/v1/orders/' . $this->klarna_order_id . '/refunds',
				'method' => 'POST',
				'body' => 'refund',
			),
			'release' => array(
				'url' => '',
				'method' => '',
			),
		);

		return $requests[ $this->request ];
	}


	/**
	 * Process response from Klarna.
	 *
	 * @param array $response HTTP request response.
	 *
	 * @return array|mixed|object|WP_Error
	 */
	private function process_response( $response ) {
		$response_body    = json_decode( wp_remote_retrieve_body( $response ) );
		$response_code    = wp_remote_retrieve_response_code( $response );

		switch ( $this->request ) {
			case 'retrieve':
				if ( 200 === $response_code ) {
					// Return entire Klarna order object.
					return $response_body;
				} else {
					return new WP_Error( $response_body->error_code, $response_body->error_messages[0] );
				}

			case 'capture':
				if ( 201 === $response_code ) {
					$response_headers = $response['headers']; // Captured ID is sent in headers.
					$klarna_capture_id       = $response_headers['capture-id'];

					return $klarna_capture_id;
				} else {
					return new WP_Error( $response_body->error_code, $response_body->error_messages[0] );
				}

			case 'cancel':
				if ( 204 === $response_code ) {
					return true;
				} else {
					return new WP_Error( $response_body->error_code, $response_body->error_messages[0] );
				}

			case 'update_order_lines':
				if ( 204 === $response_code ) {
					return true;
				} else {
					return new WP_Error( $response_body->error_code, $response_body->error_messages[0] );
				}

			case 'refund':
				if ( 201 === $response_code ) {
					return true;
				} else {
					return new WP_Error( $response_body->error_code, $response_body->error_messages[0] );
				}
		}
	}
}