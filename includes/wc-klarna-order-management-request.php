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

	/**
	 * Type of Klarna request to perform.
	 *
	 * @var string
	 */
	var $request;

	/**
	 * WooCommerce order ID.
	 *
	 * @var int
	 */
	var $order_id;

	/**
	 * Array of Klarna Payments settings
	 *
	 * @var array
	 */
	var $klarna_payments_settings;

	/**
	 * Klarna order object.
	 *
	 * @var object
	 */
	var $klarna_order;

	/**
	 * Klarna order ID.
	 *
	 * @var string
	 */
	var $klarna_order_id;

	/**
	 * Klarna merchant ID.
	 *
	 * @var string
	 */
	var $klarna_merchant_id;

	/**
	 * Klarna shared secret.
	 *
	 * @var string
	 */
	var $klarna_shared_secret;

	/**
	 * Klarna server base.
	 *
	 * @var string
	 */
	var $klarna_server_base;

	/**
	 * Klarna request URL.
	 *
	 * @var string
	 */
	var $klarna_request_url;

	/**
	 * Klarna request method (GET/POST/PATCH).
	 *
	 * @var string
	 */
	var $klarna_request_method;

	/**
	 * Klarna request body.
	 *
	 * @var string
	 */
	var $klarna_request_body;

	/**
	 * Klarna request authorization header.
	 *
	 * @var string
	 */
	var $klarna_authorization_header;

	/**
	 * WC_Klarna_Order_Management_Request constructor.
	 *
	 * @param array $args Klarna request arguments.
	 */
	public function __construct( $args = array() ) {
		$this->request                     = $args['request'];
		$this->order_id                    = $args['order_id'];
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
	 * Return processed Klarna order management request.
	 *
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
					'refunded_amount' => round( $this->refund_amount * 100 ),
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
	 * @return string|WP_Error
	 */
	public function get_klarna_authorization_header() {
		// @TODO: Once KCO is separate plugin, check which gateway was used to create the order
		if ( 'yes' !== $this->klarna_payments_settings['enabled'] ) {
			return new WP_Error( 'gateway_disabled', 'Klarna Payments gateway is currently disabled' );
		}

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
	 * @return mixed
	 */
	public function get_klarna_order_id() {
		return get_post_meta( $this->order_id, '_wc_klarna_order_id', true );
	}

	/**
	 * Gets Klarna API request details.
	 */
	public function get_request_details() {
		$requests = array(
			'update_order_lines' => array(
				'url'    => '/ordermanagement/v1/orders/' . $this->klarna_order_id . '/authorization',
				'method' => 'PATCH',
				'body'   => 'order_lines',
			),
			'cancel' => array(
				'url'    => '/ordermanagement/v1/orders/' . $this->klarna_order_id . '/cancel',
				'method' => 'POST',
			),
			'retrieve' => array(
				'url'    => '/ordermanagement/v1/orders/' . $this->klarna_order_id,
				'method' => 'GET',
			),
			'capture' => array(
				'url'    => '/ordermanagement/v1/orders/' . $this->klarna_order_id . '/captures',
				'method' => 'POST',
				'body'   => 'capture',
			),
			'refund' => array(
				'url'    => '/ordermanagement/v1/orders/' . $this->klarna_order_id . '/refunds',
				'method' => 'POST',
				'body'   => 'refund',
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
					$response_headers  = $response['headers']; // Capture ID is sent in headers.
					$klarna_capture_id = $response_headers['capture-id'];

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
					return new WP_Error( $response['response']['code'], $response['response']['message'] );
				}

			case 'refund':
				if ( 201 === $response_code ) {
					return true;
				} else {
					return new WP_Error( $response_body->error_code, $response_body->error_messages[0] );
				}
		}

		return new WP_Error( 'invalid_request', 'Invalid request type.' );
	}
}