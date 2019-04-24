<?php
/**
 * Meta box
 *
 * Handles the functionality for the KOM meta box.
 *
 * @package WC_Klarna_Order_Management
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Klarna_Pending_Orders class.
 *
 * Handles the meta box for KOM
 */
class WC_Klarna_Meta_Box {
	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'kom_meta_box' ) );
		add_action( 'woocommerce_process_shop_order_meta', array( $this, 'process_kom_actions' ), 45, 2 );
	}

	/**
	 * Adds meta box to the side of a KCO or KP order.
	 *
	 * @param string $post_type The WordPress post type.
	 * @return void
	 */
	public function kom_meta_box( $post_type ) {
		if ( 'shop_order' === $post_type ) {
			$order_id = get_the_ID();
			$order    = wc_get_order( $order_id );
			if ( in_array( $order->get_payment_method(), array( 'kco', 'klarna_payments' ) ) ) {
				add_meta_box( 'kom_meta_box', __( 'Klarna Order Management', 'klarna-order-management-for-woocommerce' ), array( $this, 'kom_meta_box_content' ), 'shop_order', 'side', 'core' );
			}
		}
	}

	/**
	 * Adds content for the KOM meta box.
	 *
	 * @return void
	 */
	public function kom_meta_box_content() {
		$order_id     = get_the_ID();
		$klarna_order = null;
		if ( ! empty( get_post_meta( $order_id, '_transaction_id', true ) ) && ! empty( get_post_meta( $order_id, '_wc_klarna_order_id', true ) ) ) {
			$klarna_order = WC_Klarna_Order_Management::get_instance()->retrieve_klarna_order( $order_id );
		}
		// Show klarna order information.
		?>
		<div class="kom-meta-box-content">
		<?php if ( $klarna_order ) { ?>
		<strong><?php _e( 'Klarna order status: ', 'klarna-order-management-for-woocommerce' ); ?> </strong> <?php echo $klarna_order->status; ?><br/>
		<?php } ?>
		<ul class="kom_order_actions_wrapper submitbox">
		<?php if ( $klarna_order ) { ?>
		<li class="wide" id="kom-capture">
			<select class="kco_order_actions" name="kom_order_actions" id="kom_order_actions">
				<option value=""><?php echo esc_attr( __( 'Choose an action...', 'woocommerce' ) ); ?></option>
		<?php
		// Check if the order can be captured.
		if ( empty( get_post_meta( $order_id, '_wc_klarna_capture_id', true ) ) && 'ACCEPTED' == $klarna_order->fraud_status && ! in_array( $klarna_order->status, array( 'CAPTURED', 'PART_CAPTURED', 'CANCELLED' ), true ) ) {
			?>
				<option value="kom_capture"><?php echo esc_attr( __( 'Capture order', 'klarna-order-management-for-woocommerce' ) ); ?></option>
			<?php
		}
		// Check if the order can be canceled.
		if ( empty( get_post_meta( $order_id, '_wc_klarna_pending_to_cancelled', true ) ) && ! in_array( $klarna_order->status, array( 'CAPTURED', 'PART_CAPTURED' ), true ) ) {
			?>
				<option value="kom_cancel"><?php echo esc_attr( __( 'Cancel order', 'klarna-order-management-for-woocommerce' ) ); ?></option>
			<?php
		}
		?>
				<option value="kom_sync"><?php echo esc_attr( __( 'Sync order', 'klarna-order-management-for-woocommerce' ) ); ?></option>
			</select>
			<button class="button wc-reload"><span><?php esc_html_e( 'Apply', 'woocommerce' ); ?></span></button>
		</li>
		<?php } else { ?>
		<li class="wide" id="kom-capture">
			<input type="text" id="klarna_order_id" name="klarna_order_id" class="klarna_order_id" placeholder="Klarna order ID">
			<button class="button wc-reload"><span><?php esc_html_e( 'Apply', 'woocommerce' ); ?></span></button>
		</li>
		<?php } ?>
		</ul>
		</div>
		<?php
	}

	/**
	 * Handles KOM Actions
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post Post Object.
	 */
	public function process_kom_actions( $post_id, $post ) {
		$order = wc_get_order( $post_id );
		// Bail if not a valid order.
		if ( ! $order ) {
			return;
		}
		if ( isset( $_POST['klarna_order_id'] ) && ! empty( $_POST['klarna_order_id'] ) ) {
			update_post_meta( $post_id, '_wc_klarna_order_id', $_POST['klarna_order_id'] );
			$order->set_transaction_id( $_POST['klarna_order_id'] );
			$order->save();
		}

		// If the KOM order actions is not set, or is empty bail.
		if ( ! isset( $_POST['kom_order_actions'] ) && empty( $_POST['kom_order_actions'] ) ) {
			return;
		}

		// If we get here, process the action.
		// Capture order
		if ( 'kom_capture' === $_POST['kom_order_actions'] ) {
			WC_Klarna_Order_Management::get_instance()->capture_klarna_order( $post_id );
		}
		// Cancel order
		if ( 'kom_cancel' === $_POST['kom_order_actions'] ) {
			WC_Klarna_Order_Management::get_instance()->cancel_klarna_order( $post_id );
		}
		// Sync order
		if ( 'kom_sync' === $_POST['kom_order_actions'] ) {
			$klarna_order = WC_Klarna_Order_Management::get_instance()->retrieve_klarna_order( $post_id );
			WC_Klarna_Sellers_App::populate_klarna_order( $post_id, $klarna_order );
		}
	}
} new WC_Klarna_Meta_Box();
