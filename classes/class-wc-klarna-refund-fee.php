<?php
/**
 * Refund fee class.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class WC_Klarna_Refund_Fee {



	/**
	 * Constructor.
	 *
	 * @param string $id          The fee ID.
	 * @param float  $amount      The fee amount.
	 * @param string $description The fee description.
	 */
	public function __construct() {
		// Return fee
		add_action( 'woocommerce_admin_order_items_after_shipping', array( $this, 'add_return_fee_order_lines_html' ), PHP_INT_MAX );
		add_action( 'woocommerce_after_order_refund_item_name', array( $this, 'show_return_fee_info' ) );
	}

	/**
	 * Add the return fee order line.
	 *
	 * @param int $order_id The WooCommerce order.
	 *
	 * @return void
	 */
	public function add_return_fee_order_lines_html( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! in_array( $order->get_payment_method(), array( 'klarna_payments', 'kco' ), true ) ) {
			return;
		}

		if ( ! $order->get_meta( '_wc_klarna_capture_id' ) ) {
			return;
		}

		?>
		</tbody>
		<tbody id="klarna_return_fee" data-klarna-hide="yes" style="display: none;">
			<tr class="klarna-return-fee" data-order_item_id="klarna_return_fee">
				<td class="thumb"><div></div></td>
				<td class="name" >
					<div class="view">
						<?php esc_html_e( 'Klarna return fee', 'klarna-order-management-for-woocommerce' ); ?>
					</div>
				</td>
				<td class="item_cost" width="1%">&nbsp;</td>
				<td class="quantity" width="1%">&nbsp;</td>
				<td class="line_cost" width="1%">
					<div class="refund" style="display: none;">
						<input type="text" name="klarna_return_fee_amount" placeholder="0" class="refund_line_total wc_input_price" />
					</div>
				</td>
				<?php foreach ( $order->get_taxes() as $tax ) : ?>
					<?php if ( empty( $tax->get_rate_percent() ) ) : ?>
						<td class="line_tax" width="1%">&nbsp;</td>
					<?php else : ?>
						<td class="line_tax" width="1%">
							<div class="refund" style="display: none;">
								<input
									type="text"
									name="klarna_return_fee_tax_amount[<?php echo esc_attr( $tax->get_rate_id() ); ?>]"
									placeholder="0"
									class="refund_line_tax wc_input_price"
									data-tax_id="<?php echo esc_attr( $tax->get_rate_id() ); ?>"
								/>
							</div>
						</td>
						<?php break; ?>
				<?php endif; ?>
			<?php endforeach; ?>
				<td class="wc-order-edit-line-item">&nbsp;</td>
			</tr>
		<?php
	}

	/**
	 * Show the return fee info in the refund order.
	 *
	 * @param WC_Order $refund_order The refund order..
	 */
	public function show_return_fee_info( $refund_order ) {
		$return_fee = $refund_order->get_meta( '_klarna_return_fees' );

		// If its empty, just return.
		if ( empty( $return_fee ) ) {
			return;
		}

		$amount     = floatval( $return_fee['amount'] ) ?? 0;
		$tax_amount = floatval( $return_fee['tax_amount'] ) ?? 0;
		$total      = $amount + $tax_amount;

		// If the total is 0, just return.
		if ( $total <= 0 ) {
			return;
		}

		?>
		<span class="klarna-return-fee-info display_meta" style="display: block; margin-top: 10px; color: #888; font-size: .92em!important;">
			<span style="font-weight: bold;"><?php esc_html_e( 'Klarna return fee: ' ); ?></span>
			<?php echo wp_kses_post( wc_price( $total, array( 'currency' => $refund_order->get_currency() ) ) . '. ' ); ?>
			<span style="font-weight: bold;"><?php esc_html_e( 'Refunded to customer: ' ); ?></span>
			<?php echo wp_kses_post( wc_price( self::get_refunded_total_to_customer( $total, $refund_order ), array( 'currency' => $refund_order->get_currency() ) ) ); ?>
		</span>
		<?php
	}

	/**
	 * Get the refunded total to customer.
	 *
	 * @param float    $total_return_fees Total return fees.
	 * @param WC_Order $refund_order The refund order.
	 *
	 * @return float
	 */
	public static function get_refunded_total_to_customer( $total_return_fees, $refund_order ) {
		$refunded_total = abs( $refund_order->get_total() ) - $total_return_fees;

		// If the refunded total is less than 0, set it to 0.
		if ( $refunded_total < 0 ) {
			$refunded_total = 0;
		}

		return $refunded_total;
	}
}
// Initialize the class.
new WC_Klarna_Refund_Fee();