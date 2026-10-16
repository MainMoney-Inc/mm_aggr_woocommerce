=== MainMoney for WooCommerce ===
Contributors: mainmoney
Tags: payments, mobile money, woocommerce, africa
Requires at least: 6.7
Tested up to: 6.8
Requires PHP: 8.2
Stable tag: 0.1.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept mobile money and bank payments in WooCommerce through the MainMoney aggregator.

== Description ==

Customers pay with the MainMoney checkout widget on the classic and Blocks checkout. The order is completed only after the payment is confirmed by the aggregator (checkout verification and signed webhook).

== Installation ==

1. Upload the zip in Plugins > Add New > Upload Plugin and activate it.
2. WooCommerce > Settings > Payments > MainMoney: enter Client ID, API secret and webhook secret.
3. Set the webhook URL in your aggregator merchant settings to https://your-site.example/wp-json/wc-mm-aggr/v1/webhooks

== Changelog ==

= 0.1.2 =
* Payment verification, amount checks, Blocks support, safer webhooks.
