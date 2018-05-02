=== Klarna Order Management for WooCommerce ===
Contributors: klarna, krokedil, automattic
Tags: woocommerce, klarna
Donate link: https://klarna.com
Requires at least: 4.0
Tested up to: 4.9.5
Requires PHP: 5.6
Stable tag: trunk
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Provides post-purchase order management for Klarna Payments for WooCommerce and Klarna Checkout for WooCommerce payment gateways.

== Installation ==
1. Upload plugin folder to to the "/wp-content/plugins/" directory.
2. Activate the plugin through the "Plugins" menu in WordPress.

== Changelog ==
= 2018.05.02  	- version 1.2.4 =
* Fix           - Added rounding to refund amount to prevent float value.


= 2018.04.27  	- version 1.2.3 =
* Tweak         - Adds support for making order management requests possible even if payment method is disabled in frontend.
* Tweak         - Added PHP version and Krokedil to useragent.

= 2018.01.25  	- version 1.2.2 =
* Fix           - Fixes WC 3.3 notices.

= 1.2.1 =
* Fixed compatibility with Klarna Checkout plugin.

= 1.1.1 =
* Initial release.
