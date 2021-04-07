<?php
/**
 * Class for the order management requests.
 *
 * @package WC_Klarna_Order_Management
 */

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
	public $request;

	/**
	 * WooCommerce order ID.
	 *
	 * @var int
	 */
	public $order_id;

	/**
	 * Array of Klarna Payments settings
	 *
	 * @var array
	 */
	public $klarna_payments_settings;

	/**
	 * Array of Klarna Checkout settings
	 *
	 * @var array
	 */
	public $klarna_checkout_settings;

	/**
	 * Klarna order object.
	 *
	 * @var object
	 */
	public $klarna_order;

	/**
	 * Klarna order ID.
	 *
	 * @var string
	 */
	public $klarna_order_id;

	/**
	 * Klarna merchant ID.
	 *
	 * @var string
	 */
	public $klarna_merchant_id;

	/**
	 * Klarna shared secret.
	 *
	 * @var string
	 */
	public $klarna_shared_secret;

	/**
	 * Klarna server base.
	 *
	 * @var string
	 */
	public $klarna_server_base;

	/**
	 * Klarna request URL.
	 *
	 * @var string
	 */
	public $klarna_request_url;

	/**
	 * Klarna request method (GET/POST/PATCH).
	 *
	 * @var string
	 */
	public $klarna_request_method;

	/**
	 * Klarna request body.
	 *
	 * @var string
	 */
	public $klarna_request_body;

	/**
	 * Klarna request authorization header.
	 *
	 * @var string
	 */
	public $klarna_authorization_header;

	/**
	 * Refund amount.
	 *
	 * @var integer
	 */
	public $refund_amount;

	/**
	 * Refund reason.
	 *
	 * @var string
	 */
	public $refund_reason;

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
		$this->klarna_request_title        = $klarna_request_details['title'];
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
			'user-agent' => apply_filters( 'http_headers_useragent', 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ) ) . ' - WooCommerce: ' . WC()->version . ' - OM:' . WC_KLARNA_ORDER_MANAGEMENT_VERSION . ' - PHP Version: ' . phpversion() . ' - Krokedil',
			'method'     => $this->klarna_request_method,
		);
		if ( $this->klarna_request_body ) {
			if ( 'order_lines' === $this->klarna_request_body ) {
				$request_args['body'] = $this->get_order_lines();
			} elseif ( 'capture' === $this->klarna_request_body ) {
				$request_args['body'] = $this->order_capture();
			} elseif ( 'refund' === $this->klarna_request_body ) {
				$request_args['body'] = $this->order_refund();
			}
		}

		$response = wp_safe_remote_request(
			$this->klarna_request_url,
			$request_args
		);
		$code     = wp_remote_retrieve_response_code( $response );
		$log      = WC_Klarna_Logger::format_log( $this->klarna_order_id, $this->klarna_request_method, $this->klarna_request_title, $request_args, $response, $code );
		WC_Klarna_Logger::log( $log );
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'error', 'Klarna Payments API request could not be completed due to an error.' );
		}

		return $this->process_response( $response );
	}

	/**
	 * Returns the order lines for Klarna order management request.
	 *
	 * @return array
	 */
	public function get_order_lines() {
		$order_lines_processor = new WC_Klarna_Order_Management_Order_Lines( $this->order_id );
		$order_lines           = $order_lines_processor->order_lines();
		$encoded_data          = wp_json_encode(
			array(
				'order_lines'      => $order_lines['order_lines'],
				'order_amount'     => $order_lines['order_amount'],
				'order_tax_amount' => $order_lines['order_tax_amount'],
			)
		);
		return $encoded_data;
	}

	/**
	 * Returns the order lines needed for capturing an order.
	 *
	 * @return string
	 */
	public function order_capture() {
		// If force full capture is enabled, set to true.
		$settings                 = get_option( 'kom_settings' );
		$force_capture_full_order = ( isset( $settings['kom_force_full_capture'] ) && 'yes' === $settings['kom_force_full_capture'] ) ? true : false;
		$order                    = wc_get_order( $this->order_id );

		// If force capture is enabled, send the full remaining authorized amount.
		$data = array(
			'captured_amount' => ( $force_capture_full_order ) ? $this->klarna_order->remaining_authorized_amount : round( $order->get_total() * 100, 0 ),
		);

		$kss_shipment_data = $this->get_kss_shipment_data();
		if ( isset( $kss_shipment_data ) && ! empty( $kss_shipment_data ) ) {
			$kss_shipment_arr['shipping_info'] = array( $kss_shipment_data );
			$data                              = array_merge( $kss_shipment_arr, $data );
		}

		// Don't add order lines if we are forcing a full order capture.
		if ( ! $force_capture_full_order ) {
			$order_lines = $this->get_order_lines();

			if ( isset( $order_lines ) && ! empty( $order_lines ) ) {
				$data = array_merge( json_decode( $order_lines, true ), $data );
			}
		}
		$encoded_data = wp_json_encode( apply_filters( 'kom_order_capture_args', $data, $this->order_id ) );
		return $encoded_data;
	}

	/**
	 * Returns KSS shipment information.
	 *
	 * @return array
	 */
	public function get_kss_shipment_data() {
		$kss_shipment_data = array();

		$kco_kss_data     = json_decode( get_post_meta( $this->order_id, '_kco_kss_data', true ), true );
		$kss_tracking_id  = get_post_meta( $this->order_id, '_kss_tracking_id', true );
		$kss_tracking_url = get_post_meta( $this->order_id, '_kss_tracking_url', true );
		isset( $kco_kss_data['delivery_details']['carrier'] ) ? $kss_shipment_data['shipping_company'] = $kco_kss_data['delivery_details']['carrier'] : '';
		isset( $kco_kss_data['shipping_method'] ) ? $kss_shipment_data['shipping_method']              = $kco_kss_data['shipping_method'] : '';
		isset( $kss_tracking_id ) ? $kss_shipment_data['tracking_number']                              = $kss_tracking_id : '';
		isset( $kss_tracking_url ) ? $kss_shipment_data['tracking_uri']                                = $kss_tracking_url : '';

		return $kss_shipment_data;
	}

	/**
	 * Returns the id of the refunded order.
	 *
	 * @param int $order_id The WooCommerce order id.
	 * @return string
	 */
	public function get_refunded_order_id( $order_id ) {
		$query_args = array(
			'fields'         => 'id=>parent',
			'post_type'      => 'shop_order_refund',
			'post_status'    => 'any',
			'posts_per_page' => -1,
		);
		$refunds    = get_posts( $query_args );
		$refund_id  = array_search( $order_id, $refunds, true );
		if ( is_array( $refund_id ) ) {
			foreach ( $refund_id as $key => $value ) {
				$refund_id = $value;
				break;
			}
		}
		return $refund_id;
	}

	/**
	 * Returns the refund order lines.
	 *
	 * @return array
	 */
	public function get_refund_order_lines() {
		$refund_id = $this->get_refunded_order_id( $this->order_id );

		if ( null !== $refund_id ) {
			$refund_order            = wc_get_order( $refund_id );
			$order                   = wc_get_order( $this->order_id );
			$order_items             = $order->get_items();
			$refunded_items          = $refund_order->get_items();
			$refunded_shipping       = $refund_order->get_shipping_method();
			$refunded_shipping_items = $refund_order->get_items( 'shipping' );
			$order_lines_processor   = new WC_Klarna_Order_Management_Order_Lines( $refund_id );
			$separate_sales_tax      = $order_lines_processor->separate_sales_tax;
			$data                    = array();

			if ( $refunded_items ) {
				foreach ( $refunded_items as $item ) {
					$product = wc_get_product( $item->get_product_id() );

					// Gets the order line total from order for calculation.
					foreach ( $order_items as $order_item ) {
						if ( $item->get_product_id() === $order_item->get_product_id() ) {
							$order_line_total    = round( ( $order->get_line_subtotal( $order_item, false ) * 100 ) );
							$order_line_tax      = round( ( $order->get_line_tax( $order_item ) * 100 ) );
							$order_line_tax_rate = ( 0 != $order_line_tax && 0 != $order_line_total ) ? round( ( $order_line_tax / $order_line_total ) * 100 * 100 ) : 0; // phpcs:ignore WordPress.PHP.StrictComparisons -- Non strict is ok here.
						}
					}

					$type                = $product->is_downloadable() || $product->is_virtual() ? 'digital' : 'physical';
					$reference           = $order_lines_processor->get_item_reference( $item );
					$name                = $order_lines_processor->get_item_name( $item );
					$quantity            = abs( $order_lines_processor->get_item_quantity( $item ) );
					$refund_price_amount = round( abs( $refund_order->get_line_subtotal( $item, false ) ) * 100 );
					$total_discount      = $order_lines_processor->get_item_discount_amount( $item );
					$refund_tax_amount   = $separate_sales_tax ? 0 : abs( $order_lines_processor->get_item_tax_amount( $item ) );
					$unit_price          = round( $refund_price_amount + $refund_tax_amount );
					$total               = round( $quantity * $unit_price );
					$item_data           = array(
						'type'                  => $type,
						'reference'             => $reference,
						'name'                  => $name,
						'quantity'              => $quantity,
						'unit_price'            => $unit_price,
						'tax_rate'              => $order_line_tax_rate,
						'total_amount'          => $total,
						'total_discount_amount' => $total_discount,
						'total_tax_amount'      => $refund_tax_amount,
					);
					// Do not add order lines if separate sales tax and no refund amount entered.
					if ( ! ( $separate_sales_tax && '0' == $refund_price_amount ) ) { // phpcs:ignore WordPress.PHP.StrictComparisons -- Non strict is ok here.
						$data[] = $item_data;
					}
				}
			}
			// if shipping is refunded.
			if ( $refunded_shipping ) {
				foreach ( $refunded_shipping_items as $shipping_item ) {

					$order_shipping_total    = round( $order->get_shipping_total() * 100 );
					$order_shipping_tax      = round( $order->get_shipping_tax() * 100 );
					$order_shipping_tax_rate = round( ( $order_shipping_tax / $order_shipping_total ) * 100 * 100 );

					$type                = 'shipping_fee';
					$reference           = $shipping_item->get_method_id() . ':' . $shipping_item->get_instance_id();
					$name                = $shipping_item->get_name();
					$quantity            = 1;
					$total_discount      = $refund_order->get_total_discount( false );
					$refund_price_amount = round( abs( $shipping_item->get_total() ) * 100 );
					$refund_tax_amount   = $separate_sales_tax ? 0 : round( abs( $shipping_item->get_total_tax() ) * 100 );
					$unit_price          = round( $refund_price_amount + $refund_tax_amount );
					$total               = round( $quantity * $unit_price );
					$shipping_data       = array(
						'type'                  => $type,
						'reference'             => $reference,
						'name'                  => $name,
						'quantity'              => $quantity,
						'unit_price'            => $unit_price,
						'tax_rate'              => $order_shipping_tax_rate,
						'total_amount'          => $total,
						'total_discount_amount' => $total_discount,
						'total_tax_amount'      => $refund_tax_amount,
					);
					// Do not add order lines if separate sales tax and no refund amount entered.
					if ( ! ( $separate_sales_tax && '0' == $refund_price_amount ) ) { // phpcs:ignore WordPress.PHP.StrictComparisons -- Non strict is ok here.
						$data[] = $shipping_data;
					}
				}
			}
			// If separate sales tax and if tax is being refunded.
			if ( $separate_sales_tax && '0' != $refund_order->get_total_tax() ) { // phpcs:ignore WordPress.PHP.StrictComparisons -- Non strict is ok here.
				$sales_tax_amount = round( abs( $refund_order->get_total_tax() ) * 100 );

				// Add sales tax line item.
				$sales_tax = array(
					'type'                  => 'sales_tax',
					'reference'             => __( 'Sales Tax', 'klarna-payments-for-woocommerce' ),
					'name'                  => __( 'Sales Tax', 'klarna-payments-for-woocommerce' ),
					'quantity'              => 1,
					'unit_price'            => $sales_tax_amount,
					'tax_rate'              => 0,
					'total_amount'          => $sales_tax_amount,
					'total_discount_amount' => 0,
					'total_tax_amount'      => 0,
				);

				$data[] = $sales_tax;
			}
		}

		return apply_filters( 'kom_refund_order_args', $data, $this->order_id );
	}

	/**
	 * Returns the order lines needed for refunding an order.
	 *
	 * @return string
	 */
	public function order_refund() {

		$data = array(
			'refunded_amount' => round( $this->refund_amount * 100 ),
			'description'     => $this->refund_reason,
		);

		$refund_order_lines = $this->get_refund_order_lines();

		if ( isset( $refund_order_lines ) && ! empty( $refund_order_lines ) ) {
			$data['order_lines'] = $refund_order_lines;
		}
		$encoded_data = wp_json_encode( $data );

		return $encoded_data;
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

		if ( '' === $this->get_merchant_id() || '' === $this->get_shared_secret() ) {
			return new WP_Error( 'missing_credentials', $gateway_title . ' credentials are missing' );
		}

		return 'Basic ' . base64_encode( $this->get_merchant_id() . ':' . htmlspecialchars_decode( $this->get_shared_secret() ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- Base64 used to calculate auth headers.
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

		// If merchant id is stored in the order - use that.
		$merchant_id = get_post_meta( $this->order_id, '_wc_klarna_merchant_id', true );
		if ( ! empty( $merchant_id ) ) {
			return $merchant_id;
		}

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

		// If shared secret id is stored in the order - use that.
		$shared_secret = get_post_meta( $this->order_id, '_wc_klarna_shared_secret', true );
		if ( ! empty( $shared_secret ) ) {
			return utf8_encode( $shared_secret );
		}

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
		switch ( $this->get_klarna_country() ) {
			case 'US':
				$env_string = '-na';
				break;
			case 'CA':
				$env_string = '-na';
				break;
			case 'AU':
			case 'NZ':
				$env_string = '-oc';
				break;
			default:
				$env_string = '';
				break;
		}
		if ( 'live' === $this->get_klarna_environment() ) {
			$server_base = 'https://api' . $env_string . '.klarna.com/';
		} else {
			$server_base = 'https://api' . $env_string . '.playground.klarna.com/';
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
				'title'  => 'Update Klarna order lines',
			),
			'cancel'             => array(
				'url'    => '/ordermanagement/v1/orders/' . $this->klarna_order_id . '/cancel',
				'method' => 'POST',
				'title'  => 'Cancel Klarna order',
			),
			'retrieve'           => array(
				'url'    => '/ordermanagement/v1/orders/' . $this->klarna_order_id,
				'method' => 'GET',
				'title'  => 'Retrieve Klarna order',
			),
			'capture'            => array(
				'url'    => '/ordermanagement/v1/orders/' . $this->klarna_order_id . '/captures',
				'method' => 'POST',
				'body'   => 'capture',
				'title'  => 'Capture Klarna order',
			),
			'refund'             => array(
				'url'    => '/ordermanagement/v1/orders/' . $this->klarna_order_id . '/refunds',
				'method' => 'POST',
				'body'   => 'refund',
				'title'  => 'Refund Klarna order',
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
				// Check if 200 to 204.
				if ( 200 <= $response_code && 204 >= $response_code ) {
					return true;
				} else {
					return new WP_Error( $response_body->error_code, $response_body->error_messages[0] );
				}
		}

		return new WP_Error( 'invalid_request', 'Invalid request type.' );
	}
}
