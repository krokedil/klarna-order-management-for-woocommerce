<?php
/**
 * Order scheduled actions display.
 *
 * Provides a way to display scheduled actions related to the order.
 *
 * @package WC_Klarna_Order_Management
 * @since   1.9.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Klarna_Order_Actions_Display class.
 *
 * Displays scheduled actions related to the order.
 */
class WC_Klarna_Order_Actions_Display {

	/**
	 * Retrieves and displays scheduled actions for the order.
	 *
	 * @param string $session_id The session ID.
	 * @return void
	 */
	public static function get_scheduled_actions( $session_id ) {
		$session_query_url = admin_url(
			'admin.php?page=wc-status&tab=action-scheduler&s=' . rawurlencode( $session_id ) . '&action=-1&paged=1&action2=-1'
		);

		$statuses      = array( 'complete', 'failed', 'pending' );
		$action_counts = array();

		foreach ( $statuses as $status ) {
			$action_counts[ $status ] = count(
				as_get_scheduled_actions(
					array(
						'search'   => $session_id,
						'status'   => array( $status ),
						'per_page' => -1,
					)
				)
			);
		}
		self::print_scheduled_actions( $session_query_url, $action_counts );
	}

	/**
	 * Print the scheduled actions.
	 *
	 * @param string $session_query_url The session query URL.
	 * @param array  $action_counts The action counts.
	 * @return void
	 */
	private static function print_scheduled_actions( $session_query_url, $action_counts ) {
		?>
		<strong>
			<?php esc_html_e( 'Scheduled actions ', 'klarna-order-management-for-woocommerce' ); ?>
			<span class="woocommerce-help-tip"
					data-tip="<?php esc_html_e( 'See all actions scheduled for this order.', 'klarna-order-management-for-woocommerce' ); ?>">
			</span>
		</strong>
		<br />
		<a target="_blank" href="<?php echo esc_url( $session_query_url ); ?>">
			<?php
			printf(
			// translators: %1$d: number of completed orders, %2$d: number of failed orders, %3$d: number of pending orders.
				esc_html__( '%1$d completed, %2$d failed, %3$d pending', 'klarna-order-management-for-woocommerce' ),
				esc_html( $action_counts['complete'] ),
				esc_html( $action_counts['failed'] ),
				esc_html( $action_counts['pending'] )
			);
			?>
		</a>
		<br />
		<?php
	}
}
new WC_Klarna_Order_Actions_Display();
