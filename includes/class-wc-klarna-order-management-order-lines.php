<?php
/**
 * Order lines formatter
 *
 * Formats WooCommerce cart items for Klarna API.
 *
 * @package WC_Klarna_Order_Management
 * @since   1.0.0
 */

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
	 * @var $order_id
	 */
	public $order_id;

	/**
	 * WooCommerce order.
	 *
	 * @var $order
	 */
	public $order;

	/**
	 * Klarna country used for creating this order.
	 *
	 * @var string
	 */
	public $klarna_country = 'US';

	/**
	 * Send sales tax as separate item (US merchants).
	 *
	 * @var bool
	 */
	public $separate_sales_tax = false;

	/**
	 * WC_Klarna_Order_Management_Order_Lines constructor.
	 *
	 * @param int $order_id WooCommerce order ID.
	 */
	public function __construct( $order_id ) {
		$this->order_id = $order_id;
		$this->order    = wc_get_order( $this->order_id );

		$base_location = wc_get_base_location();
		$shop_country  = $base_location['country'];

		if ( 'US' === $shop_country ) {
			$this->separate_sales_tax = true;
		}

		$this->klarna_country = strtoupper( get_post_meta( $order_id, '_wc_klarna_country', true ) );
	}

	/**
	 * Gets formatted order lines from WooCommerce order and returns them, with order amount and order tax amount.
	 *
	 * @return array
	 */
	public function order_lines() {
		// @TODO: Process fees.
		$this->process_order_line_items();
		$this->process_sales_tax();

		return array(
			'order_lines'      => $this->order_lines,
			'order_amount'     => $this->order_amount,
			'order_tax_amount' => $this->order_tax_amount,
		);
	}

	/**
	 * Process WooCommerce order items to Klarna Payments order lines.
	 */
	public function process_order_line_items() {
		$order = wc_get_order( $this->order_id );

		// @TODO: Add coupons as separate items (smart coupons etc).
		foreach (
			$order->get_items( array(
				'line_item',
				'shipping',
				'coupon',
				'fee',
			) ) as $order_line_item_id => $order_line_item
		) {
			$klarna_item = array(
				'reference'             => $this->get_item_reference( $order_line_item ),
				'name'                  => $this->get_item_name( $order_line_item ),
				'quantity'              => $this->get_item_quantity( $order_line_item ),
				'unit_price'            => $this->get_item_unit_price( $order_line_item ),
				'tax_rate'              => $this->get_item_tax_rate( $order_line_item ),
				'total_amount'          => $this->get_item_total_amount( $order_line_item ),
				'total_discount_amount' => $this->get_item_discount_amount( $order_line_item ),
				'total_tax_amount'      => $this->get_item_tax_amount( $order_line_item ),
			);

			if ( 'line_item' === $order_line_item['type'] ) {
				$order_payment_method    = $order->get_payment_method();
				$payment_method_settings = get_option( 'woocommerce_' . $order_payment_method . '_settings' );
				if ( 'yes' === $payment_method_settings['send_product_urls'] ) {
					$product                    = $order_line_item['variation_id'] ? wc_get_product( $order_line_item['variation_id'] ) : wc_get_product( $order_line_item['product_id'] );
					$klarna_item['product_url'] = $product->get_permalink();
					if ( $product->get_image_id() > 0 ) {
						$image_id                 = $product->get_image_id();
						$klarna_item['image_url'] = wp_get_attachment_image_url( $image_id, 'shop_thumbnail' );
					}
				}
			}

			if ( 'shipping' === $order_line_item['type'] ) {
				$klarna_item['type'] = 'shipping_fee';
			}

			if ( 'fee' === $order_line_item['type'] ) {
				$klarna_item['type'] = 'surcharge';
			}

			if ( 'coupon' === $order_line_item['type'] ) {
				$coupon = new WC_Coupon( $order_line_item['name'] );

				// @TODO: For now, only send smart coupons as separate items, needs to include all coupons for US
				if ( 'smart_coupon' === $coupon->get_discount_type() ) {
					$coupon_amount     = - $order_line_item['discount_amount'] * 100;
					$coupon_tax_amount = - $order_line_item['discount_amount_tax'] * 100;
					$coupon_reference  = 'Discount';
				} else {
					if ( 'US' === $this->klarna_country ) {
						$coupon_amount     = 0;
						$coupon_tax_amount = 0;

						if ( $coupon->is_type( 'fixed_cart' ) || $coupon->is_type( 'percent' ) ) {
							$coupon_type = 'Cart discount';
						} elseif ( $coupon->is_type( 'fixed_product' ) || $coupon->is_type( 'percent_product' ) ) {
							$coupon_type = 'Product discount';
						} else {
							$coupon_type = 'Discount';
						}

						$coupon_reference = $coupon_type . ' (amount: ' . $order_line_item['discount_amount'] . ', tax amount: ' . $order_line_item['discount_amount_tax'] . ')';
					}
				}

				// Add discount line item, only if it's a smart coupon or purchase country was US.
				if ( 'smart_coupon' === $coupon->get_discount_type() || 'US' === $this->klarna_country ) {
					$klarna_item['type']                  = 'discount';
					$klarna_item['reference']             = $coupon_reference;
					$klarna_item['total_discount_amount'] = 0;
					$klarna_item['unit_price']            = $coupon_amount;
					$klarna_item['total_amount']          = $coupon_amount;
					$klarna_item['total_tax_amount']      = $coupon_tax_amount;

					$this->order_lines[]    = $klarna_item;
					$this->order_amount     += $coupon_amount;
					$this->order_tax_amount += $coupon_tax_amount;
				}
			} else {
				$this->order_lines[] = $klarna_item;
				$this->order_amount  += $this->get_item_total_amount( $order_line_item );
			} // End if().
		} // End foreach().
	}

	/**
	 * Process sales tax for US
	 */
	public function process_sales_tax() {
		if ( $this->separate_sales_tax ) {
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

			$this->order_lines[]    = $sales_tax;
			$this->order_amount     += $sales_tax_amount;
			$this->order_tax_amount = $sales_tax_amount;
		}
	}

	/**
	 * Get cart item reference.
	 *
	 * @param array $order_line_item WooCommerce order line item.
	 *
	 * @return string $item_reference Cart item reference.
	 */
	public function get_item_reference( $order_line_item ) {
		if ( 'line_item' === $order_line_item['type'] ) {
			$product = $order_line_item['variation_id'] ? wc_get_product( $order_line_item['variation_id'] ) : wc_get_product( $order_line_item['product_id'] );

			if ( $product->get_sku() ) {
				$item_reference = $product->get_sku();
			} else {
				$item_reference = $product->get_id();
			}
		} elseif ( 'shipping' === $order_line_item['type'] ) {
			$item_reference = $order_line_item['method_id'];
		} elseif ( 'coupon' === $order_line_item['type'] ) {
			$item_reference = 'Discount';
		} elseif ( 'fee' === $order_line_item['type'] ) {
			$item_reference = 'Fee';
		} else {
			$item_reference = $order_line_item['name'];
		}

		return substr( (string) $item_reference, 0, 64 );
	}

	/**
	 * Get cart item name.
	 *
	 * @param  array $order_line_item Order line item.
	 *
	 * @return string $order_line_item_name Order line item name.
	 */
	public function get_item_name( $order_line_item ) {
		$order_line_item_name = $order_line_item['name'];

		// Append item meta to the title, if it exists.
		if ( 'line_item' === $order_line_item['type'] ) {
			if ( isset( $order_line_item['item_meta'] ) ) {
				$item_meta = new WC_Order_Item_Meta( $order_line_item['item_meta'] );
				if ( $item_meta->display( true, true ) ) {
					$meta                 = $item_meta->display( true, true );
					$order_line_item_name .= ' [' . $meta . ']';
				}
			}
		}

		return (string) strip_tags( $order_line_item_name );
	}

	/**
	 * Get cart item quantity.
	 *
	 * @param array $order_line_item Order line item.
	 *
	 * @return integer Cart item quantity.
	 */
	public function get_item_quantity( $order_line_item ) {
		if ( $order_line_item['qty'] ) {
			return $order_line_item['qty'];
		} else {
			return 1;
		}
	}

	/**
	 * Get cart item price.
	 *
	 * @param  array $order_line_item Order line item.
	 *
	 * @return integer $item_price Cart item price.
	 */
	public function get_item_unit_price( $order_line_item ) {
		if ( 'shipping' === $order_line_item['type'] ) {
			if ( $this->separate_sales_tax ) {
				$item_price = $this->order->get_shipping_total();
			} else {
				$item_price = $this->order->get_shipping_total() + $this->order->get_shipping_tax();
			}

			$item_quantity = 1;
		} elseif ( 'fee' === $order_line_item['type'] ) {
			$item_price    = $order_line_item['total'];
			$item_quantity = 1;
		} else {
			if ( $this->separate_sales_tax ) {
				$item_price = $order_line_item['subtotal'];
			} else {
				$item_price = $order_line_item['subtotal'] + $order_line_item['subtotal_tax'];
			}

			$item_quantity = $order_line_item['qty'] ? $order_line_item['qty'] : 1;
		}

		$item_price = number_format( $item_price * 100, 0, '', '' ) / $item_quantity;

		return round( $item_price );
	}

	/**
	 * Calculate item tax percentage.
	 *
	 * @param array $order_line_item Order line item.
	 *
	 * @return integer $item_tax_rate Item tax percentage formatted for Klarna.
	 */
	public function get_item_tax_rate( $order_line_item ) {
		if ( $order_line_item['total_tax'] > 0 ) {
			// Calculate tax rate.
			if ( $this->separate_sales_tax ) {
				$item_tax_rate = 00;
			} else {
				$item_tax_rate = round( $order_line_item['total_tax'] / $order_line_item['total'] * 100 * 100 );
			}
		} else {
			$item_tax_rate = 00;
		}

		return round( $item_tax_rate );
	}


	/**
	 * Get order line item total amount.
	 *
	 * @param array $order_line_item Order line item.
	 *
	 * @return integer $item_total_amount Cart item total amount.
	 */
	public function get_item_total_amount( $order_line_item ) {
		if ( 'shipping' === $order_line_item['type'] ) {
			if ( $this->separate_sales_tax ) {
				$item_total_amount = $this->order->get_shipping_total();
			} else {
				$item_total_amount = $this->order->get_shipping_total() + (float) $this->order->get_shipping_tax();
			}
		} elseif ( 'fee' === $order_line_item['type'] ) {
			$item_total_amount = $order_line_item['total'];
		} else {
			if ( $this->separate_sales_tax ) {
				$item_total_amount = $order_line_item['subtotal'];
			} else {
				$item_total_amount = $order_line_item['total'] + $order_line_item['total_tax'];
			}
		}

		$item_total_amount = $item_total_amount * 100;

		return round( $item_total_amount );
	}

	/**
	 * Calculate item tax percentage.
	 *
	 * @param  array $order_line_item Order line item.
	 *
	 * @return integer $item_tax_amount Item tax amount.
	 */
	public function get_item_tax_amount( $order_line_item ) {
		if ( $this->separate_sales_tax ) {
			$item_tax_amount = 00;
		} else {
			if ( 'line_item' === $order_line_item['type'] ) {
				$item_tax_amount = $order_line_item['total_tax'] * 100;
			} elseif ( 'shipping' === $order_line_item['type'] ) {
				$item_tax_amount = $order_line_item['total_tax'] * 100;
			} else {
				$item_tax_amount = 00;
			}
		}

		return round( $item_tax_amount );
	}

	/**
	 * Get cart item discount.
	 *
	 * @param array $order_line_item Order line item.
	 *
	 * @return integer $item_discount_amount Cart item discount.
	 */
	public function get_item_discount_amount( $order_line_item ) {
		if ( $order_line_item['subtotal'] > $order_line_item['total'] ) {
			if ( $this->separate_sales_tax ) {
				$item_discount_amount = ( $order_line_item['subtotal'] - $order_line_item['total'] ) * 100;
			} else {
				$item_discount_amount = ( $order_line_item['subtotal'] + $order_line_item['subtotal_tax'] - $order_line_item['total'] - $order_line_item['total_tax'] ) * 100;
			}
		} else {
			$item_discount_amount = 0;
		}

		return round( $item_discount_amount );
	}

}
