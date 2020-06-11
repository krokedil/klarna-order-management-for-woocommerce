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
			if ( in_array( $order->get_payment_method(), array( 'kco', 'klarna_payments' ), true ) ) {
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
		$order_id = get_the_ID();
		$order    = wc_get_order( $order_id );
		// Check if the order has been paid.
		if ( empty( $order->get_date_paid() ) && ! in_array( $order->get_status(), array( 'on-hold' ), true ) ) {
			$this->print_error_content( __( 'The payment has not been finalized with Klarna.', 'klarna-order-management-for-woocommerce' ) );
			return;
		}
		// False if automatic settings are enabled, true if not. If true then show the option.
		if ( ! empty( get_post_meta( $order_id, '_transaction_id', true ) ) && ! empty( get_post_meta( $order_id, '_wc_klarna_order_id', true ) ) ) {

			$klarna_order = WC_Klarna_Order_Management::get_instance()->retrieve_klarna_order( $order_id );

			if ( is_wp_error( $klarna_order ) ) {
				$this->print_error_content( __( 'Failed to retrieve the order from Klarna.', 'klarna-order-management-for-woocommerce' ) );
				return;
			}
		}
		$this->print_standard_content( $klarna_order );
	}

	/**
	 * Prints the standard content for the OM Metabox
	 *
	 * @param object $klarna_order The Klarna order object.
	 * @return void
	 */
	public function print_standard_content( $klarna_order ) {
		$order_id      = get_the_ID();
		$settings      = get_option( 'kom_settings' );
		$capture_order = ( ! isset( $settings['kom_auto_capture'] ) || 'yes' === $settings['kom_auto_capture'] ) ? false : true;
		$cancel_order  = ( ! isset( $settings['kom_auto_cancel'] ) || 'yes' === $settings['kom_auto_cancel'] ) ? false : true;
		$sync_order    = ( ! isset( $settings['kom_auto_order_sync'] ) || 'yes' === $settings['kom_auto_order_sync'] ) ? false : true;
		$environment   = ! empty( get_post_meta( $order_id, '_wc_klarna_environment', true ) ) ? get_post_meta( $order_id, '_wc_klarna_environment', true ) : '';

		// Show klarna order information.
		?>
			<div class="kom-meta-box-content">
			<?php if ( $klarna_order ) { ?>
				<?php
				if ( '' !== $environment ) {
					$environment = 'live' === $environment ? 'Production' : 'Playground';
					?>
				<strong><?php esc_html_e( 'Klarna Environment: ', 'klarna-order-management-for-woocommerce' ); ?> </strong><?php echo esc_html( $environment ); ?><br/>
			<?php } ?> 
			<strong><?php esc_html_e( 'Klarna order status: ', 'klarna-order-management-for-woocommerce' ); ?> </strong> <?php echo esc_html( $klarna_order->status ); ?><br/>
			<strong><?php esc_html_e( 'Initial Payment method: ', 'klarna-order-management-for-woocommerce' ); ?> </strong> <?php echo esc_html( $klarna_order->initial_payment_method->description ); ?><br/>
			<?php } ?>
			<ul class="kom_order_actions_wrapper submitbox">
			<?php
			if ( $klarna_order ) {
				if ( $capture_order || $cancel_order || $sync_order ) {
					?>
				<li class="wide" id="kom-capture">
					<select class="kco_order_actions" name="kom_order_actions" id="kom_order_actions">
						<option value=""><?php echo esc_attr( __( 'Choose an action...', 'woocommerce' ) ); ?></option>
					<?php
				}
				// Check if the order can be captured.
				if ( $capture_order && empty( get_post_meta( $order_id, '_wc_klarna_capture_id', true ) ) && 'ACCEPTED' === $klarna_order->fraud_status && ! in_array( $klarna_order->status, array( 'CAPTURED', 'PART_CAPTURED', 'CANCELLED' ), true ) ) {
					?>
					<option value="kom_capture"><?php echo esc_attr( __( 'Capture order', 'klarna-order-management-for-woocommerce' ) ); ?></option>
					<?php
				}
				// Check if the order can be canceled.
				if ( $cancel_order && empty( get_post_meta( $order_id, '_wc_klarna_pending_to_cancelled', true ) ) && ! in_array( $klarna_order->status, array( 'CAPTURED', 'PART_CAPTURED' ), true ) ) {
					?>
					<option value="kom_cancel"><?php echo esc_attr( __( 'Cancel order', 'klarna-order-management-for-woocommerce' ) ); ?></option>
					<?php
				}
				if ( $sync_order ) {
					?>
				<option value="kom_sync"><?php echo esc_attr( __( 'Get customer', 'klarna-order-management-for-woocommerce' ) ); ?></option>
					<?php
				}
				if ( $capture_order || $cancel_order || $sync_order ) {
					?>
				</select>
				<button class="button wc-reload"><span><?php esc_html_e( 'Apply', 'woocommerce' ); ?></span></button>
				<span class="woocommerce-help-tip" data-tip="<?php esc_html_e( 'Capture order: Activates the order with Klarna.<br>Cancel order: Cancels the order with Klarna. <br>Get customer: Gets the customer data from Klarna and saves it to the WooCommerce order.', 'klarna-order-management-for-woocommerce' ); ?>"></span>
			</li>
					<?php
				}
			} else {
				?>
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
	 * Prints an error message for the OM Metabox
	 *
	 * @param string $message The error message.
	 * @return void
	 */
	public function print_error_content( $message ) {
		?>
		<div class="kom-meta-box-content">
			<p><?php echo esc_html( $message ); ?></p>
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
		$klarna_order_id = filter_input( INPUT_POST, 'klarna_order_id', FILTER_SANITIZE_STRING );
		$kom_action      = filter_input( INPUT_POST, 'kom_order_actions', FILTER_SANITIZE_STRING );
		$order           = wc_get_order( $post_id );
		// Bail if not a valid order.
		if ( ! $order ) {
			return;
		}
		if ( ! empty( $klarna_order_id ) ) {
			update_post_meta( $post_id, '_wc_klarna_order_id', $klarna_order_id );
			$order->set_transaction_id( $klarna_order_id );
			$order->save();
		}

		// If the KOM order actions is not set, or is empty bail.
		if ( empty( $kom_action ) ) {
			return;
		}

		// If we get here, process the action.
		// Capture order.
		if ( 'kom_capture' === $kom_action ) {
			WC_Klarna_Order_Management::get_instance()->capture_klarna_order( $post_id, true );
		}
		// Cancel order.
		if ( 'kom_cancel' === $kom_action ) {
			WC_Klarna_Order_Management::get_instance()->cancel_klarna_order( $post_id, true );
		}
		// Sync order.
		if ( 'kom_sync' === $kom_action ) {
			$klarna_order = WC_Klarna_Order_Management::get_instance()->retrieve_klarna_order( $post_id );
			WC_Klarna_Sellers_App::populate_klarna_order( $post_id, $klarna_order );
		}
	}
} new WC_Klarna_Meta_Box();
