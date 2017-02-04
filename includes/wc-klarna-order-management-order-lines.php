<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * WC_Klarna_Order_Management_Order_Lines class.
 *
 * Processes order lines from a WooCommerce order for Klarna order management requests.
 */
class WC_Klarna_Order_Management_Order_Lines {

	/**
	 * Klarna order order lines.
	 *
	 * @var $order_lines
	 */
	public $order_lines = array();

	/**
	 * Klarna order amount.
	 *
	 * @var $order_lines
	 */
	public $order_amount = 0;

	/**
	 * Klarna order tax amount.
	 *
	 * @var $order_tax_amount
	 */
	public $order_tax_amount = 0;

	/**
	 * WooCommerce order ID.
	 *
	 * @var $order_lines
	 */
	public $order_id;

	public $order;

	public $billing_country;

	/**
	 * WC_Klarna_Order_Management_Order_Lines constructor.
	 *
	 * @param int $order_id WooCommerce order ID.
	 */
	public function __construct( $order_id ) {
		$this->order_id = $order_id;
		$this->order = wc_get_order( $this->order_id );

		$billing_address = $this->order->get_address( 'billing' );
		$this->billing_country = $billing_address['country'];
	}

	/**
	 * Gets formatted order lines from WooCommerce order.
	 *
	 * @param int $order_id WooCommerce order ID.
	 *
	 * @return array
	 */
	public function order_lines() {
		// @TODO: Process fees.
		$this->process_order_line_items();
		$this->process_sales_tax();

		return array(
			'order_lines' => $this->order_lines,
			'order_amount' => $this->order_amount,
			'order_tax_amount' => $this->order_tax_amount,
		);
	}

	/**
	 * Process WooCommerce order items to Klarna Payments order lines.
	 *
	 * @param int $order_id WooCommerce order ID.
	 */
	public function process_order_line_items() {
		$order = wc_get_order( $this->order_id );

		// @TODO: Add coupons as separate items (smart coupons etc).
		foreach ( $order->get_items( array( 'line_item', 'shipping', 'fee' ) ) as $order_line_item_id => $order_line_item ) {
			$klarna_item = array(
				// 'type'
				'reference'             => $this->get_item_reference( $order_line_item ),
				'name'                  => $this->get_item_name( $order_line_item ),
				'quantity'              => $this->get_item_quantity( $order_line_item ),
				// 'quantity_unit'
				'unit_price'            => $this->get_item_unit_price( $order_line_item ),
				'tax_rate'              => $this->get_item_tax_rate( $order_line_item ),
				'total_amount'          => $this->get_item_total_amount( $order_line_item ),
				'total_discount_amount' => $this->get_item_discount_amount( $order_line_item ),
				'total_tax_amount'      => $this->get_item_tax_amount( $order_line_item ),
				// 'merchant_data'
				// 'product_url
				// 'image_url'
			);

			$this->order_lines[] = $klarna_item;
			$this->order_amount += $this->get_item_quantity( $order_line_item ) * $this->get_item_unit_price( $order_line_item );
		}
	}

	/**
	 * Process sales tax for US
	 *
	 * @param int $order_id WooCommerce order ID.
	 */
	public function process_sales_tax() {
		if ( 'US' === $this->billing_country ) {
			$sales_tax_amount = round( ( $this->order->get_cart_tax() + $this->order->get_shipping_tax() ) * 100 );

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

			$this->order_lines[] = $sales_tax;
			$this->order_amount += $sales_tax_amount;
		}
	}

	// Helpers.

	/**
	 * Get cart item reference.
	 *
	 * @return string $item_reference Cart item reference.
	 */
	public function get_item_reference( $order_line_item ) {
		if ( 'line_item' === $order_line_item['type'] ) {
			$product = $order_line_item['variation_id'] ? wc_get_product( $order_line_item['variation_id'] ) : wc_get_product( $order_line_item['product_id'] );

			if ( $product->get_sku() ) {
				$item_reference = $product->get_sku();
			} elseif ( $product->variation_id ) {
				$item_reference = $product->variation_id;
			} else {
				$item_reference = $product->id;
			}
		} elseif ( 'shipping' === $order_line_item['type'] ) {
			$item_reference = 'shipping';
		} else {
			$item_reference = $order_line_item['name'];
		}

		return strval( $item_reference );
	}

	/**
	 * Get cart item name.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param  array $order_line_item Order line item.
	 *
	 * @return string $item_name Cart item name.
	 */
	public function get_item_name( $order_line_item ) {
		$order_line_item_name = $order_line_item['name'];

		// Append item meta to the title, if it exists.
		if ( 'line_item' == $order_line_item['type'] ) {
			if ( isset( $order_line_item['item_meta'] ) ) {
				$item_meta = new WC_Order_Item_Meta( $order_line_item['item_meta'] );
				if ( $meta = $item_meta->display( true, true ) ) {
					$order_line_item_name .= ' [' . $meta . ']';
				}
			}
		}

		return strip_tags( $order_line_item_name );
	}

	/**
	 * Get cart item quantity.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param  array $order_line_item Order line item.
	 *
	 * @return integer $item_quantity Cart item quantity.
	 */
	public function get_item_quantity( $order_line_item ) {
		if ( $order_line_item['qty'] ) {
			return (int) $order_line_item['qty'];
		} else {
			return 1;
		}
	}

	/**
	 * Get cart item price.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param  array $order_line_item Order line item.
	 *
	 * @return integer $item_price Cart item price.
	 */
	public function get_item_unit_price( $order_line_item ) {
		if ( 'shipping' === $order_line_item['type'] ) {
			if ( 'US' === $this->billing_country ) {
				$item_price = $this->order->get_total_shipping();
			} else {
				$item_price = $this->order->get_total_shipping() + $this->order->order_shipping_tax;
			}

			$item_quantity = 1;
		} else {
			if ( 'US' === $this->billing_country ) {
				$item_price = $order_line_item['line_subtotal'];
			} else {
				$item_price = $order_line_item['line_subtotal'] + $order_line_item['line_subtotal_tax'];
			}

			$item_quantity = $order_line_item['qty'] ? $order_line_item['qty'] : 1;
		}

		$item_price = number_format( $item_price * 100, 0, '', '' ) / $item_quantity;

		return round( $item_price );
	}

	/**
	 * Calculate item tax percentage.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param  array  $order_line_item Order line item.
	 * @param  object $product Product object.
	 *
	 * @return integer $item_tax_rate Item tax percentage formatted for Klarna.
	 */
	public function get_item_tax_rate( $order_line_item ) {
		if ( $order_line_item['line_subtotal_tax'] > 0 ) {
			// Calculate tax rate.
			if ( 'US' === $this->billing_country ) {
				$item_tax_rate = 00;
			} else {
				$item_tax_rate = round( $order_line_item['line_subtotal_tax'] / $order_line_item['line_subtotal'] * 100 * 100 );
			}
		} else {
			$item_tax_rate = 00;
		}

		return intval( $item_tax_rate );
	}


	/**
	 * Get order line item total amount.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param array $order_line_item Order line item.
	 *
	 * @return integer $item_total_amount Cart item total amount.
	 */
	public function get_item_total_amount( $order_line_item ) {
		if ( 'US' === $this->billing_country ) {
			$item_total_amount = ( $order_line_item['line_total'] * 100 );
		} else {
			$item_total_amount = ( ( $order_line_item['line_total'] + $order_line_item['line_tax'] ) * 100 );
		}
		return round( $item_total_amount );
	}

	/**
	 * Calculate item tax percentage.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param  array $order_line_item Order line item.
	 *
	 * @return integer $item_tax_amount Item tax amount.
	 */
	public function get_item_tax_amount( $order_line_item ) {
		if ( 'US' === $this->billing_country ) {
			$item_tax_amount = 00;
		} else {
			$item_tax_amount = $order_line_item['line_tax'] * 100;
		}
		return round( $item_tax_amount );
	}

	/**
	 * Get cart item discount.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param array $order_line_item Order line item.
	 *
	 * @return integer $item_discount_amount Cart item discount.
	 */
	public function get_item_discount_amount( $order_line_item ) {
		if ( $order_line_item['line_subtotal'] > $order_line_item['line_total'] ) {
			$item_discount_amount = ( $order_line_item['line_subtotal'] + $order_line_item['line_subtotal_tax'] - $order_line_item['line_total'] - $order_line_item['line_tax'] ) * 100;
		} else {
			$item_discount_amount = 0;
		}

		return round( $item_discount_amount );
	}

}