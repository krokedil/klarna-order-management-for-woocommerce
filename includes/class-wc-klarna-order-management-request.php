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
	 * Array of Klarna Checkout settings
	 *
	 * @var array
	 */
	var $klarna_checkout_settings;

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
	 * Refund amount.
	 *
	 * @var integer
	 */
	var $refund_amount;

	/**
	 * Refund reason.
	 *
	 * @var string
	 */
	var $refund_reason;

	/**
	 * WC_Klarna_Order_Management_Request constructor.
	 *
	 * @param array $args Klarna request arguments.
	 */
	public function __construct( $args = array() ) {
		$this->request            = $args['request'];
		$this->order_id           = $args['order_id'];
		$this->klarna_order       = array_key_exists( 'klarna_order', $args ) ? $args['klarna_order'] : false;
		$this->refund_amount      = array_key_exists( 'refund_amount', $args ) ? $args['refund_amount'] : 0;
		$this->refund_reason      = array_key_exists( 'refund_reason', $args ) ? $args['refund_reason'] : '';
		$this->klarna_order_id    = $this->get_klarna_order_id();
		$this->klarna_server_base = $this->get_server_base();

		$klarna_request_details            = $this->get_request_details();
		$this->klarna_request_url          = $this->klarna_server_base . $klarna_request_details['url'];
		$this->klarna_request_method       = $klarna_request_details['method'];
		$this->klarna_request_body         = array_key_exists( 'body', $klarna_request_details ) ? $klarna_request_details['body'] : false;
		$this->klarna_payments_settings    = get_option( 'woocommerce_klarna_payments_settings' );
		$this->klarna_checkout_settings    = get_option( 'woocommerce_kco_settings' );
		$this->klarna_authorization_header = $this->get_klarna_authorization_header();
		$this->klarna_merchant_id          = $this->get_merchant_id();
		$this->klarna_shared_secret        = $this->get_shared_secret();
	}

	/**
	 * Return processed Klarna order management request.
	 *
	 * @return array|mixed|object|WP_Error
	 */
	public function response() {
		if ( is_wp_error( $this->get_klarna_authorization_header() ) ) {
			return new WP_Error( 'missing_credentials', 'Klarna Payments credentials are missing' );
		}

		$request_args = array(
			'headers'    => array(
				'Authorization' => $this->get_klarna_authorization_header(),
				'Content-Type'  => 'application/json',
			),
			'user-agent' => apply_filters( 'http_headers_useragent', 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ) ) . ' - OM:' . WC_KLARNA_ORDER_MANAGEMENT_VERSION . ' - PHP Version: ' . phpversion() . ' - Krokedil',
			'method'     => $this->klarna_request_method,
		);

		if ( $this->klarna_request_body ) {
			if ( 'order_lines' === $this->klarna_request_body ) {
				$order_lines_processor = new WC_Klarna_Order_Management_Order_Lines( $this->order_id );
				$order_lines           = $order_lines_processor->order_lines();

				$request_args['body'] = wp_json_encode( array(
					'order_lines'      => $order_lines['order_lines'],
					'order_amount'     => $order_lines['order_amount'],
					'order_tax_amount' => $order_lines['order_tax_amount'],
				) );
			} elseif ( 'capture' === $this->klarna_request_body ) {
				$order                = wc_get_order( $this->order_id );
				$request_args['body'] = wp_json_encode( array(
					'captured_amount' => round( $order->get_total() * 100, 0 ),
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

		if ( is_wp_error( $response ) ) {
			WC_Klarna_Order_Management::log( var_export( $response, true ) );

			return new WP_Error( 'error', 'Klarna Payments API request could not be completed due to an error.' );
		}

		return $this->process_response( $response );
	}

	/**
	 * Get Klarna order management request authorization header for WooCommerce order.
	 *
	 * @return string|WP_Error
	 */
	public function get_klarna_authorization_header() {
		$order          = wc_get_order( $this->order_id );
		$payment_method = $order->get_payment_method();

		if ( 'klarna_payments' === $payment_method ) {
			$gateway_settings = get_option( 'woocommerce_klarna_payments_settings' );
			$gateway_title    = 'Klarna Payments';
		} elseif ( 'kco' === $payment_method ) {
			$gateway_settings = get_option( 'woocommerce_kco_settings' );
			$gateway_title    = 'Klarna Checkout';
		}

		if ( ! isset( $gateway_settings ) ) {
			return new WP_Error( 'wrong_gateway', 'This order was not create via Klarna Payments or Klarna Checkout for WooCommerce.' );
		}

		/*if ( 'yes' !== $gateway_settings['enabled'] ) {
			return new WP_Error( 'gateway_disabled', $gateway_title, ' gateway is currently disabled' );
		}*/

		if ( '' === $this->get_merchant_id() || '' === $this->get_shared_secret() ) {
			return new WP_Error( 'missing_credentials', $gateway_title . ' credentials are missing' );
		}

		return 'Basic ' . base64_encode( $this->get_merchant_id() . ':' . htmlspecialchars_decode( $this->get_shared_secret() ) );
	}

	/**
	 * Gets country used for Klarna purchase.
	 *
	 * @return mixed
	 */
	public function get_klarna_country() {
		$country = '';
		if ( get_post_meta( $this->order_id, '_wc_klarna_country', true ) ) {
			$country = get_post_meta( $this->order_id, '_wc_klarna_country', true );
		}

		return $country;
	}

	/**
	 * Gets environment (test/live) used for Klarna purchase.
	 *
	 * @return mixed
	 */
	public function get_klarna_environment() {
		$env   = 'test';
		$order = wc_get_order( $this->order_id );

		if ( $order ) {
			$order_payment_method    = $order->get_payment_method();
			$payment_method_settings = get_option( 'woocommerce_' . $order_payment_method . '_settings' );

			if ( 'yes' !== $payment_method_settings['testmode'] ) {
				$env = 'live';
			}
		}

		return $env;
	}

	/**
	 * Gets merchant ID from WooCommerce order, based on environment and country.
	 */
	public function get_merchant_id() {
		$order          = wc_get_order( $this->order_id );
		$payment_method = $order->get_payment_method();

		if ( 'klarna_payments' === $payment_method ) {
			$gateway_settings = $this->klarna_payments_settings;
		} elseif ( 'kco' === $payment_method ) {
			$gateway_settings = $this->klarna_checkout_settings;
		}

		$env     = $this->get_klarna_environment();
		$country = $this->get_klarna_country();

		if ( 'live' === $env ) {
			$env_string = '';
		} else {
			$env_string = 'test_';
		}

		if ( 'klarna_payments' === $payment_method ) {
			$country_string = strtolower( $country );
		} else {
			if ( 'US' === $country ) {
				$country_string = 'us';
			} else {
				$country_string = 'eu';
			}
		}

		$merchant_id = $gateway_settings[ $env_string . 'merchant_id_' . $country_string ];

		return $merchant_id;
	}

	/**
	 * Gets merchant ID from WooCommerce order, based on environment and country.
	 */
	public function get_shared_secret() {
		$order          = wc_get_order( $this->order_id );
		$payment_method = $order->get_payment_method();

		if ( 'klarna_payments' === $payment_method ) {
			$gateway_settings = $this->klarna_payments_settings;
		} elseif ( 'kco' === $payment_method ) {
			$gateway_settings = $this->klarna_checkout_settings;
		}

		$env     = $this->get_klarna_environment();
		$country = $this->get_klarna_country();

		if ( 'live' === $env ) {
			$env_string = '';
		} else {
			$env_string = 'test_';
		}

		if ( 'klarna_payments' === $payment_method ) {
			$country_string = strtolower( $country );
		} else {
			if ( 'US' === $country ) {
				$country_string = 'us';
			} else {
				$country_string = 'eu';
			}
		}

		$shared_secret = $gateway_settings[ $env_string . 'shared_secret_' . $country_string ];

		return utf8_encode( $shared_secret );
	}

	/**
	 * Gets Klarna server base WooCommerce order, based on environment and country.
	 */
	public function get_server_base() {
		if ( 'US' === $this->get_klarna_country() ) {
			if ( 'live' === $this->get_klarna_environment() ) {
				$server_base = 'https://api-na.klarna.com';
			} else {
				$server_base = 'https://api-na.playground.klarna.com';
			}
		} else {
			if ( 'live' === $this->get_klarna_environment() ) {
				$server_base = 'https://api.klarna.com';
			} else {
				$server_base = 'https://api.playground.klarna.com';
			}
		}

		return $server_base;
	}

	/**
	 * Gets Klarna order ID from WooCommerce order.
	 *
	 * @return mixed
	 */
	public function get_klarna_order_id() {
		if ( get_post_meta( $this->order_id, '_transaction_id', true ) ) {
			return get_post_meta( $this->order_id, '_transaction_id', true );
		}

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
			'cancel'             => array(
				'url'    => '/ordermanagement/v1/orders/' . $this->klarna_order_id . '/cancel',
				'method' => 'POST',
			),
			'retrieve'           => array(
				'url'    => '/ordermanagement/v1/orders/' . $this->klarna_order_id,
				'method' => 'GET',
			),
			'capture'            => array(
				'url'    => '/ordermanagement/v1/orders/' . $this->klarna_order_id . '/captures',
				'method' => 'POST',
				'body'   => 'capture',
			),
			'refund'             => array(
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
		$response_body = json_decode( wp_remote_retrieve_body( $response ) );
		$response_code = wp_remote_retrieve_response_code( $response );

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

					return sanitize_key( $klarna_capture_id );
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
				// Check if 2**.
				if ( 200 <= $response_code || 204 > $response_code ) {
					return true;
				} else {
					return new WP_Error( $response_body->error_code, $response_body->error_messages[0] );
				}
		} // End switch().

		return new WP_Error( 'invalid_request', 'Invalid request type.' );
	}
}
