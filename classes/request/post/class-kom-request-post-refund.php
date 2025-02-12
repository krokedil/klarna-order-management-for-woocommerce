<?php
/**
 * POST request class for order refund
 *
 * @package WC_Klarna_Order_Management/Classes/Requests
 */

defined( 'ABSPATH' ) || exit;

/**
 * POST request class for order refund
 */
class KOM_Request_Post_Refund extends KOM_Request_Post {


	/**
	 * The Refund Reason
	 *
	 * @var string
	 */
	protected $refund_reason;

	/**
	 * The Refund Amount
	 *
	 * @var integer
	 */
	protected $refund_amount;

	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );
		$this->log_title     = 'Refund Klarna order';
		$this->refund_reason = $arguments['refund_reason'];
		$this->refund_amount = $arguments['refund_amount'];
	}

	/**
	 * Get the request URL for this type of request.
	 *
	 * @return string
	 */
	protected function get_request_url() {
		return $this->get_api_url_base() . 'ordermanagement/v1/orders/' . $this->klarna_order_id . '/refunds';
	}

	/**
	 * Build the request body for this request.
	 *
	 * @return array
	 */
	protected function get_body() {
		$data = array(
			'refunded_amount' => round( $this->refund_amount * 100 ),
			'description'     => $this->refund_reason,
		);

		$refund_order_lines = $this->get_refund_order_lines();

		if ( isset( $refund_order_lines ) && ! empty( $refund_order_lines ) ) {
			$data['order_lines'] = $refund_order_lines;
		}

		return $data;
	}

	/**
	 * Returns the refund order lines.
	 * TODO: Move this functionality to WC_Klarna_Order_Management_Order_Lines class.
	 *
	 * @return array
	 */
	public function get_refund_order_lines() {
		$refund_id = $this->get_refunded_order_id( $this->order_id );

		if ( ! empty( $refund_id ) ) {
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
				/**
				 * Process order item products.
				 *
				 * @var WC_Order_Item_Product $item WooCommerce order item product.
				 */
				foreach ( $refunded_items as $item ) {
					$product = wc_get_product( $item->get_product_id() );

					/**
					 * Get the order line total from order for calculation.
					 *
					 * @var WC_Order_Item_Product $order_item WooCommerce order item product.
					 */
					foreach ( $order_items as $order_item ) {
						if ( $item->get_product_id() === $order_item->get_product_id() ) {
							$order_line_total    = round( ( $order->get_line_subtotal( $order_item, false ) * 100 ) );
							$order_line_tax      = round( ( $order->get_line_tax( $order_item ) * 100 ) );
							$tax_rates           = WC_Tax::get_base_tax_rates( $order_item->get_tax_class() );
							$first_tax_rate      = reset( $tax_rates );
							$order_line_tax_rate = ( 0 !== $order_line_tax && 0 !== $order_line_total ) ? ( $first_tax_rate['rate'] * 100 ?? round( ( $order_line_tax / $order_line_total ) * 100 * 100 ) ) : 0;
						}
					}

					 /**
					 *
					 *  If a product is not available inside of WC anymore wc_get_product() will return false
					 *  and the default check will fail resulting in an fatal error, creating the Refund with WC but not sending it to Klarna
					 *  This fallback allows DEVs to provide the product type which they saved before.
					 *
					 *  Alternatively KOM or KCO could save this information on order creation.
					 */

					if ( is_object( $product ) && method_exists( $product, 'is_downloadable' ) ) {
						  $type = $product->is_downloadable() || $product->is_virtual() ? 'digital' : 'physical';
					} else {
						  $type = apply_filters( 'kom_line_item_product_type', 'physical', $item );
					}

					$reference           = $order_lines_processor->get_item_reference( $item );
					$name                = $order_lines_processor->get_item_name( $item );
					$quantity            = abs( $order_lines_processor->get_item_quantity( $item ) );
					$refund_price_amount = round( abs( $refund_order->get_line_subtotal( $item, false ) ) * 100 );
					$total_discount      = $order_lines_processor->get_item_discount_amount( $item );
					$refund_tax_amount   = $separate_sales_tax ? 0 : abs( $order_lines_processor->get_item_tax_amount( $item ) );
					$unit_price          = round( ( $refund_price_amount + $refund_tax_amount ) / $quantity );
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

					$product_urls = kom_maybe_add_product_urls( $item );
					if ( ! empty( $product_urls ) ) {
						$item_data = array_merge( $item_data, kom_maybe_add_product_urls( $item ) );
					}

					// Do not add order lines if separate sales tax and no refund amount entered.
					if ( ! ( $separate_sales_tax && '0' == $refund_price_amount ) ) { // phpcs:ignore WordPress.PHP.StrictComparisons -- Can be float *or* integer, so non-strict is required.
						$data[] = $item_data;
					}
				}
			}
			// if shipping is refunded.
			if ( $refunded_shipping ) {
				/**
				 * Process Shipping
				 *
				 * @var WC_Order_Item_Shipping $shipping_item WooCommerce order item *shipping*.
				 */
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
					if ( ! ( $separate_sales_tax && '0' == $refund_price_amount ) ) { // phpcs:ignore WordPress.PHP.StrictComparisons -- Can be float *or* integer, so non-strict is required.
						$data[] = $shipping_data;
					}
				}
			}
			// If separate sales tax and if tax is being refunded.
			if ( $separate_sales_tax && '0' != $refund_order->get_total_tax() ) { // phpcs:ignore WordPress.PHP.StrictComparisons -- Can be float *or* integer, so non-strict is required.
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
	 * Returns the id of the refunded order.
	 *
	 * @param int $order_id The WooCommerce order id.
	 * @return string
	 */
	public function get_refunded_order_id( $order_id ) {
		$order = wc_get_order( $order_id );

		/* Always retrieve the most recent (current) refund (index 0). */
		return $order->get_refunds()[0]->get_id();
	}

}
