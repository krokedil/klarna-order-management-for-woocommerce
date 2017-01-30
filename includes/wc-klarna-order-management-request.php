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
	var $capture_id;
	var $klarna_order_id;
	var $klarna_merchant_id;
	var $klarna_shared_secret;
	var $klarna_server_base;
	var $klarna_request_url;
	var $klarna_request_method;
	var $klarna_authorization_header;

	/**
	 * WC_Klarna_Order_Management_Request constructor.
	 *
	 * @param string      $request    Klarna request.
	 * @param int         $order_id   WooCommerce order ID.
	 * @param string|bool $capture_id Klarna capture ID.
	 */
	public function __construct( $request, $order_id, $capture_id = false ) {
		$this->request                     = $request;
		$this->order_id                    = $order_id;
		$this->capture_id                  = $capture_id;
		$this->klarna_order_id             = $this->get_klarna_order_id( $this->order_id );
		$this->klarna_server_base          = $this->get_server_base( $this->order_id );
		$klarna_request_details            = $this->get_request_details( $this->request, $this->klarna_order_id, $this->capture_id );
		$this->klarna_request_url          = $klarna_request_details['url'];
		$this->klarna_request_method       = $klarna_request_details['method'];
		$this->klarna_authorization_header = $this->get_klarna_authorization_header();
		$this->klarna_payments_settings    = get_option( 'woocommerce_klarna_payments_settings' );
		$this->merchant_id                 = $this->get_merchant_id( $this->order_id );
		$this->shared_secret               = $this->get_shared_secret( $this->order_id );
	}

	public function response() {
		$request_args = array(
			'headers' => array(
				'Authorization' => $this->get_authorization_header( $this->order_id ),
				'Content-Type'  => 'application/json',
			),
			'method' => $this->klarna_request_method,
		);

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
		if ( '' === $this->merchant_id || '' === $this->shared_secret ) {
			return new WP_Error( 'missing_credentials', 'Klarna Payments credentials are missing' );
		}

		return 'Basic ' . base64_encode( $this->merchant_id . ':' . $this->shared_secret );
	}

	/**
	 * Gets merchant ID from WooCommerce order, based on environment and country.
	 *
	 * @TODO: Consider other countries too, currently defaults to US.
	 */
	public function get_merchant_id( $order_id ) {
		$order = wc_get_order( $order_id );
		$billing_address = $order->get_address( 'billing' );
		$billing_country = $billing_address['country'];

		if ( get_post_meta( $order_id, '_wc_klarna_payments_mode', true ) ) {
			return $this->klarna_payments_settings['test_merchant_id'];
		} else {
			return $this->klarna_payments_settings['merchant_id'];
		}
	}

	/**
	 * Gets merchant ID from WooCommerce order, based on environment and country.
	 *
	 * @TODO: Consider other countries too, currently defaults to US.
	 */
	public function get_shared_secret( $order_id ) {
		$order = wc_get_order( $order_id );
		$billing_address = $order->get_address( 'billing' );
		$billing_country = $billing_address['country'];

		$klarna_payments_settings = get_option( 'woocommerce_klarna_payments_settings' );

		if ( get_post_meta( $order_id, '_wc_klarna_payments_mode', true ) ) {
			return $klarna_payments_settings['test_shared_secret'];
		} else {
			return $klarna_payments_settings['shared_secret'];
		}
	}

	/**
	 * Gets Klarna server base WooCommerce order, based on environment and country.
	 *
	 * @TODO: Consider other countries too, currently defaults to US.
	 */
	public function get_server_base( $order_id ) {
		if ( get_post_meta( $order_id, '_wc_klarna_payments_mode', true ) ) {
			return 'https://api-na.playground.klarna.com';
		} else {
			return 'https://api-na.klarna.com';
		}
	}

	/**
	 * Gets Klarna order ID from WooCommerce order.
	 *
	 * @param int $order_id WooCommerce order ID.
	 *
	 * @return mixed
	 */
	public function get_klarna_order_id( $order_id ) {
		return get_post_meta( $order_id, 'wc_klarna_payments_order_id', true );
	}

	/**
	 * @param $request
	 * @param $klarna_order_id
	 * @param $klarna_capture_id
	 */
	public function get_request_details( $request, $klarna_order_id, $klarna_capture_id = null ) {
		$requests = array(
			'update_amount' => array(
				'url' => '/ordermanagement/v1/orders/ ' . $klarna_order_id . '/authorization-adjustments',
				'method' => 'POST',
			),
			'cancel' => array(
				'url' => '/ordermanagement/v1/orders/' . $klarna_order_id . '/cancel',
				'method' => 'POST',
			),
			'retrieve' => array(
				'url' => '/ordermanagement/v1/orders/' . $klarna_order_id,
				'method' => 'GET',
			),
			'update_address' => array(
				'url' => '/ordermanagement/v1/orders/' . $klarna_order_id . '/customer-details',
				'method' => 'PATCH',
			),
			'update_merchant_reference' => array(
				'url' => '/ordermanagement/v1/orders/' . $klarna_order_id . '/merchant-references',
				'method' => 'PATCH',
			),
			'capture' => array(
				'url' => '/ordermanagement/v1/orders/' . $klarna_order_id . '/captures',
				'method' => 'POST',
			),
			'retrieve_captures' => array(
				'url' => '/ordermanagement/v1/orders/' . $klarna_order_id . '/captures',
				'method' => 'GET',
			),
			'retrieve_capture' => array(
				'url' => '/ordermanagement/v1/orders/' . $klarna_order_id . '/captures/' . $klarna_capture_id,
				'method' => 'GET',
			),
			'update_capture_billing_address' => array(
				'url' => '',
				'method' => '',
			),
			'add_shipping_info_to_capture' => array(
				'url' => '/ordermanagement/v1/orders/' . $klarna_order_id . ' /captures/' . $klarna_capture_id . '/shipping-info',
				'method' => 'POST',
			),
			'trigger_communication' => array(
				'url' => '/ordermanagement/v1/orders/' . $klarna_order_id . '/captures/' . $klarna_capture_id . '/trigger-send-out',
				'method' => 'POST',
			),
			'refund' => array(
				'url' => '/ordermanagement/v1/orders/' . $klarna_order_id . '/refunds',
				'method' => 'POST',
			),
			'release' => array(
				'url' => '',
				'method' => '',
			),
		);

		return $requests[ $request ];
	}


	/**
	 * Process response from Klarna.
	 *
	 * @param array $response HTTP request response.
	 *
	 * @return array|mixed|object|WP_Error
	 */
	private static function process_response( $response ) {
		$response_body    = json_decode( wp_remote_retrieve_body( $response ) );
		$response_code    = wp_remote_retrieve_response_code( $response );

		if ( 200 === $response_code ) {
			return $response_body;
		} else {
			return new WP_Error( $response_body->error_code, $response_body->error_messages[0] );
		}
	}
}