<?php
/**
 * Class for the KOM settings.
 *
 * @package WC_Klarna_Order_Management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class to add settings to the Klarna Add-ons page.
 */
class WC_Klarna_Order_Management_Settings {
	/**
	 * Class constructor.
	 */
	public function __construct() {
		// WP Settings API.
		add_action( 'admin_menu', array( $this, 'add_menu' ), 101 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// Klarna add-on.
		add_filter( 'klarna_addons_settings_pages', array( $this, 'register_settings_page' ), 1 );
		add_action( 'klarna_addons_settings_tab', array( $this, 'redirect_to_settings_page' ), 999999 );
	}

	/**
	 * Redirect to the settings page for KOM
	 *
	 * @return void
	 */
	public function redirect_to_settings_page() {
		global $wp;
		$query_args = array(
			'page' => 'kom-settings',
		);
		$url        = add_query_arg( $query_args, $wp->request );
		header( 'Location: ' . $url );
		wp_die();
	}

	/**
	 * Adds the submenue to the checkout addons page.
	 *
	 * @return void
	 */
	public function add_menu() {
		$submenu = add_submenu_page(
			'checkout-addons',
			__(
				'Klarna Order Management',
				'klarna-order-management-for-woocommerce'
			),
			__(
				'Klarna Order Management',
				'klarna-order-management-for-woocommerce'
			),
			'manage_woocommerce',
			'kom-settings',
			array( $this, 'settings_page' )
		);
	}

	/**
	 * Generates the HTML for the page.
	 *
	 * @return void
	 */
	public function settings_page() {
		$this->add_page_tabs();
		$this->get_settings_links();
		?>
		<form action="options.php" method="post">
			<?php settings_fields( 'kom-settings' ); ?>
			<?php do_settings_sections( 'kom-settings' ); ?>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * Registers settings for WordPress.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting( 'kom-settings', 'kom_settings' );

		add_settings_section(
			'kom_settings_section',
			'Klarna order management',
			array( $this, 'kom_settings_section_callback' ),
			'kom-settings'
		);

		add_settings_field(
			'kom_auto_capture',
			'On order completion',
			array( $this, 'field_auto_capture_render' ),
			'kom-settings',
			'kom_settings_section'
		);
		add_settings_field(
			'kom_auto_cancel',
			'On order cancel',
			array( $this, 'field_auto_cancel_render' ),
			'kom-settings',
			'kom_settings_section'
		);
		add_settings_field(
			'kom_auto_update',
			'On order update',
			array( $this, 'field_auto_update_render' ),
			'kom-settings',
			'kom_settings_section'
		);
		add_settings_field(
			'kom_auto_order_sync',
			'On order creation ( manual )',
			array( $this, 'field_order_sync_render' ),
			'kom-settings',
			'kom_settings_section'
		);
		add_settings_field(
			'kom_force_full_capture',
			'Force capture full order',
			array( $this, 'field_force_full_capture_render' ),
			'kom-settings',
			'kom_settings_section'
		);
		add_settings_field(
			'kom_debug_log',
			'Debug log',
			array( $this, 'field_debug_log_render' ),
			'kom-settings',
			'kom_settings_section'
		);
	}

	/**
	 * Empty function for now.
	 *
	 * @return void
	 */
	public function kom_settings_section_callback() {
		// Empty for now.
	}

	/**
	 * HTML For the input field.
	 *
	 * @return void
	 */
	public function field_auto_capture_render() {
		$options = get_option( 'kom_settings' );
		$val     = ( isset( $options['kom_auto_capture'] ) ) ? $options['kom_auto_capture'] : 'yes';
		?>
		<input type="hidden" name="kom_settings[kom_auto_capture]" value="no" />
		<label for="kom_settings[kom_auto_capture]" >
			<input type='checkbox' name='kom_settings[kom_auto_capture]' value='yes' <?php checked( $val, 'yes' ); ?>>
			<?php esc_html_e( 'Activate Klarna order automatically when WooCommerce order is marked complete.', 'klarna-order-management-for-woocommerce' ); ?>
		</label>
		<?php
	}

	/**
	 * HTML For the input field.
	 *
	 * @return void
	 */
	public function field_auto_cancel_render() {
		$options = get_option( 'kom_settings' );
		$val     = ( isset( $options['kom_auto_cancel'] ) ) ? $options['kom_auto_cancel'] : 'yes';
		?>
		<input type="hidden" name="kom_settings[kom_auto_cancel]" value="no" />
		<label for="kom_settings[kom_auto_cancel]" >
		<input type='checkbox' name='kom_settings[kom_auto_cancel]' value='yes' <?php checked( $val, 'yes' ); ?>>
		<?php esc_html_e( 'Cancel Klarna order automatically when WooCommerce order is marked canceled.', 'klarna-order-management-for-woocommerce' ); ?>
		</label>
		<?php
	}

	/**
	 * HTML For the input field.
	 *
	 * @return void
	 */
	public function field_auto_update_render() {
		$options = get_option( 'kom_settings' );
		$val     = ( isset( $options['kom_auto_update'] ) ) ? $options['kom_auto_update'] : 'yes';
		?>
		<input type="hidden" name="kom_settings[kom_auto_update]" value="no" />
		<label for="kom_settings[kom_auto_update]" >
		<input type='checkbox' name='kom_settings[kom_auto_update]' value='yes' <?php checked( $val, 'yes' ); ?>>
		<?php esc_html_e( 'Update Klarna order automatically when WooCommerce order is updated.', 'klarna-order-management-for-woocommerce' ); ?>
		</label>
		<?php
	}

	/**
	 * HTML For the input field.
	 *
	 * @return void
	 */
	public function field_order_sync_render() {
		$options = get_option( 'kom_settings' );
		$val     = ( isset( $options['kom_auto_order_sync'] ) ) ? $options['kom_auto_order_sync'] : 'yes';
		?>
		<input type="hidden" name="kom_settings[kom_auto_order_sync]" value="no" />
		<label for="kom_settings[kom_auto_order_sync]">
		<input type='checkbox' name='kom_settings[kom_auto_order_sync]' value='yes' <?php checked( $val, 'yes' ); ?>>
		<?php esc_html_e( 'Gets the customer information from Klarna when creating a manual admin order and adding a Klarna order id as a transaction id.', 'klarna-order-management-for-woocommerce' ); ?>
		</label>
		<?php
	}

	/**
	 * HTML For the input field.
	 *
	 * @return void
	 */
	public function field_debug_log_render() {
		$options = get_option( 'kom_settings' );
		$val     = ( isset( $options['kom_debug_log'] ) ) ? $options['kom_debug_log'] : 'yes';
		?>
		<input type="hidden" name="kom_settings[kom_debug_log]" value="no" />
		<label for="kom_settings[kom_debug_log]">
		<input type='checkbox' name='kom_settings[kom_debug_log]' value='yes' <?php checked( $val, 'yes' ); ?>>
		<?php esc_html_e( 'Enable the debug log.', 'klarna-order-management-for-woocommerce' ); ?>
		</label>
		<?php
	}

	/**
	 * HTML For the input field.
	 *
	 * @return void
	 */
	public function field_force_full_capture_render() {
		$options = get_option( 'kom_settings' );
		$val     = ( isset( $options['kom_force_full_capture'] ) ) ? $options['kom_force_full_capture'] : 'no';
		?>
		<input type="hidden" name="kom_settings[kom_force_full_capture]" value="no" />
		<label for="kom_settings[kom_force_full_capture]">
		<input type='checkbox' name='kom_settings[kom_force_full_capture]' value='yes' <?php checked( $val, 'yes' ); ?>>
		<?php esc_html_e( 'Force the capture of a full order. Usefull if the Klarna order has been updated by a ERP system.', 'klarna-order-management-for-woocommerce' ); ?>
		</label>
		<?php
	}

	/**
	 * Adds order management to the settings pages.
	 *
	 * @param array $pages List of the different pages.
	 * @return array
	 */
	public function register_settings_page( $pages ) {
		$pages['kom-settings'] = 'Klarna Order Management';
		return $pages;
	}


	/**
	 * Adds tabs to the Addons page.
	 *
	 * @param string $current Wich tab is to be selected.
	 * @return void
	 */
	public function add_page_tabs( $current = 'settings' ) {
		$tabs  = array(
			'addons'   => __( 'Klarna Add-ons', 'klarna-checkout-for-woocommerce' ),
			'settings' => __( 'Settings', 'klarna-checkout-for-woocommerce' ),
		);
		$pages = array(
			'addons'   => 'checkout-addons',
			'settings' => 'kom-settings',
		);
		?>
			<h2 class="nav-tab-wrapper">
			<?php
			foreach ( $tabs as $tab => $name ) {
				$class = ( $tab === $current ) ? 'nav-tab-active' : '';
				?>
					<a class="nav-tab <?php echo esc_html( $class ); ?>" href="?page=<?php echo esc_html( $pages[ $tab ] ); ?>"><?php echo esc_html( $name ); ?></a>
				<?php
			}
			?>
			</h2>
		<?php
	}

	/**
	 * Gets the links to the different settings pages.
	 *
	 * @return void
	 */
	public function get_settings_links() {
		global $wp;
		$pages = apply_filters( 'klarna_addons_settings_pages', array() );
		$i     = count( $pages );
		?>
		<p>
		<?php
		foreach ( $pages as $slug => $title ) {
			$query_args = array(
				'page' => $slug,
			);
			$i - 1;
			?>
				<a href="<?php echo esc_html( add_query_arg( $query_args, $wp->request ) ); ?>"><?php echo esc_html( $title ); ?></a>
			<?php
		}
		?>
		</p>
		<?php
	}
} new WC_Klarna_Order_Management_Settings();
