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

	public static $retrieve_order_url = '/ordermanagement/v1/orders/{order_id}'; // GET
	public static $update_amount_url = '/ordermanagement/v1/orders/{order_id}/authorization-adjustments'; // POST
	public static $update_address = '/ordermanagement/v1/orders/{order_id}/customer-details'; // PATCH
	public static $update_merchant_reference = '/ordermanagement/v1/orders/{order_id}/merchant-references'; // PATCH


	public static function retrieve_klarna_order( $order_id ) {
		$server_base = '';
		$merchant_id = '';
		$shared_secret = '';

		$request_url  = $server_base . 'ordermanagement/v1/orders/';
		$request_args = array(
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $merchant_id . ':' . $shared_secret ),
				'Content-Type'  => 'application/json',
			)
		);

		$response = wp_safe_remote_get( $request_url, $request_args );

		return $response;
	}

}