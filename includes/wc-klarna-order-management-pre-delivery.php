<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * WC_Klarna_Order_Management_Pre_Delivery class.
 *
 * Gets Klarna credentials based on order country.
 */
class WC_Klarna_Order_Management_Pre_Delivery {

	/**
	 * Klarna retrieve order params.
	 *
	 * @var array
	 */
	public static $retrieve_order_params = array(
		'url'  => '/ordermanagement/v1/orders/{order_id}',
		'type' => 'GET',
	);

	/**
	 * Klarna update order amount URL.
	 *
	 * @var string
	 */
	public static $update_amount_params = array(
		'url'  => '/ordermanagement/v1/orders/{order_id}/authorization-adjustments',
		'type' => 'POST',
	);

	/**
	 * Klarna update order address URL.
	 *
	 * @var string
	 */
	public static $update_address = array(
		'url'  => '/ordermanagement/v1/orders/{order_id}/customer-details',
		'type' => 'PATCH',
	);

	/**
	 * Klarna update merchant references URL.
	 *
	 * @var string
	 */
	public static $update_merchant_reference = array(
		'url'  => '/ordermanagement/v1/orders/{order_id}/merchant-references',
		'type' => 'PATCH',
	);

	/**
	 * Retrieves a Klarna order based on WooCommerce order ID.
	 *
	 * @param int $order_id WooCommerce order ID.
	 *
	 * @return array|WP_Error
	 */
	public static function retrieve_klarna_order( $order_id ) {
		$server_base = WC_Klarna_Order_Management_Request_Setup::get_server_base( $order_id );
		$klarna_order_id = get_post_meta( $order_id, 'wc_klarna_payments_order_id', true );

		$request_url  = $server_base . 'ordermanagement/v1/orders/' . $klarna_order_id;
		$request_args = array(
			'headers' => array(
				'Authorization' => WC_Klarna_Order_Management_Request_Setup::get_authorization_header( $order_id ),
				'Content-Type'  => 'application/json',
			),
		);

		$response = wp_safe_remote_get( $request_url, $request_args );

		$response_body    = json_decode( wp_remote_retrieve_body( $response ) );
		$response_code    = wp_remote_retrieve_response_code( $response );

		if ( 200 === $response_code ) {
			return $response_body;
		} else {
			return new WP_Error( $response_body->error_code, $response_body->error_messages[0] );
		}
	}

}