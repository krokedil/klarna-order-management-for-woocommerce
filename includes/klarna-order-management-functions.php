<?php
/**
 * Utility functions.
 *
 * @package WC_Klarna_Order_Management/Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get the product and its image URLs.
 *
 * @param WC_Order_Item_Product The order item.
 * @return array The product and image URL if available, otherwise an empty array.
 */
function kom_maybe_add_product_urls( $item ) {
	$product_data = array();
	$settings     = get_option( 'woocommerce_kco_settings', array() );
	if ( isset( $settings['send_product_urls'] ) && 'yes' === $settings['send_product_urls'] ) {
		$product = wc_get_product( $item->get_product_id() );

		if ( $product instanceof WC_Product ) {
			if ( $product->get_image_id() > 0 ) {
				$image_id                  = $product->get_image_id();
				$image_url                 = wp_get_attachment_image_url( $image_id, 'shop_single', false );
				$product_data['image_url'] = $image_url;
			}
		}

		$product_data['product_url'] = $product->get_permalink();
	}
	return $product_data;
}
