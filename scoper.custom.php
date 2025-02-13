<?php //phpcs:disable

function customize_php_scoper_config( array $config ): array {
    // Ignore the abspath constant when scoping.
	$config['exclude-constants'][] = 'ABSPATH';
	$config['exclude-constants'][] = 'WC_KLARNA_ORDER_MANAGEMENT_VERSION';
	$config['exclude-constants'][] = 'WC_KLARNA_ORDER_MANAGEMENT_MIN_PHP_VER';
	$config['exclude-constants'][] = 'WC_KLARNA_ORDER_MANAGEMENT_MIN_WC_VER';
	$config['exclude-constants'][] = 'WC_KLARNA_ORDER_MANAGEMENT_PLUGIN_PATH';
	$config['exclude-constants'][] = 'WC_KLARNA_ORDER_MANAGEMENT_CHECKOUT_URL';
	$config['exclude-classes'][] = 'WooCommerce';
	$config['exclude-classes'][] = 'WC_Product';
	$config['exclude-classes'][] = 'WP_Error';

	$functions = array();

	$config['exclude-functions'] = array_merge( $config['exclude-functions'] ?? array(), $functions );
	$config['exclude-namespaces'][] = 'Automattic';

	return $config;
}
