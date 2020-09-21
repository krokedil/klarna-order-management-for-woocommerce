=== Klarna Order Management for WooCommerce ===
Contributors: klarna, krokedil, NiklasHogefjord, automattic
Tags: woocommerce, klarna
Donate link: https://klarna.com
Requires at least: 4.0
Tested up to: 5.5
Requires PHP: 5.6
Stable tag: trunk
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Provides post-purchase order management for Klarna Payments for WooCommerce and Klarna Checkout for WooCommerce payment gateways.

== Installation ==
1. Upload plugin folder to to the "/wp-content/plugins/" directory.
2. Activate the plugin through the "Plugins" menu in WordPress.

== Changelog ==
= 2020.09.21    - version 1.6.0 =
* Feature       - Added ability to force a full capture of an order. Useful for merchants that are using an ERP system that could have updated the Klarna order without changing the WooCommerce order.
* Enhancement   - Improved logging to make debugging easier.
* Fix           - Fixed an issue were when adding products to the Klarna order, the tax rate sent to Klarna would be incorrect.

= 2020.09.08    - version 1.5.6 =
* Fix           - Fixed a division by zero issue that happened when you attempted to refund a product with 0 value.

= 2020.08.27    - version 1.5.5 =
* Fix           - Fix in http response code handling in refunds. Could cause denied refunds to appeared as approved in WooCommerce.
* Fix           - PHP error fix in pending orders class.

= 2020.07.08    - version 1.5.4 =
* Enhancement   - Update pending order status based on the fraud status in Klarnas system.

= 2020.07.03    - version 1.5.3 =
* Enhancement   - Add environment information to the Klarna order management metabox.
* Enhancement   - Add a filter to the order lines sent to Klarna. kom_wc_order_line_item.
* Fix           - Improvements to the notification listener. Prevents error notices.
* Fix           - Set order status to on-hold if the Klarna order id is missing during order completion.
* Fix           - Use Klarna order id for payment_complete to set the transaction id.

= 2020.05.28  	- version 1.5.2 =
* Enhancement   - Added debug log setting. You can now turn off logging of requests made from the plugin to Klarna.

= 2020.03.25  	- version 1.5.1 =
* Fix           - Prevent requests from being made from orders that have not been paid.

= 2020.01.22  	- version 1.5.0 =
* Feature       - Added support for oceania endpoints.
* Feature		- Added support for sending KSS data.
* Feature		- Added initial payment method in the Meta box.
* Fix			- Only show actions select field in the meta box if they are available
* Fix			- Fixed so canada uses the correct endpoint.

= 2019.10.08  	- version 1.4.0 =
* Feature       - Added order line data to be sent with capture and refund requests.

= 2019.09.24  	- version 1.3.1 =
* Fix           - Fixed callback array and priority in settings class. Caused PHP notice.
* Fix           - Fix for Klarna Sellers app integration. Fixes issue with KP.

= 2019.06.13  	- version 1.3.0 =
* Feature       - Added settings through the Klarna Add-ons page. Allows the merchant to select if they want to automatically process orders or do so manually through each order, or through the Klarna portal.
* Feature       - Added the option to get the customer address and save that to a WooCommerce order. Can be used with Klarna seller app orders to get the orders to WooCommerce ( You need to add the products manually ).
* Enhancement	- If the order is completed in Klarna before we try and activate the order, the order is no longer set to on-hold.

= 2019.05.07  	- version 1.2.5 =
* Enhancement   - Ser order status to on-hold if order activation fails.
* Fix           - Corrected text for refund error.

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
