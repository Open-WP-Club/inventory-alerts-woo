=== Inventory Alerts for WooCommerce ===
Contributors: openwpclub
Tags: woocommerce, inventory, stock, alerts, orders
Requires at least: 5.8
Tested up to: 7.1
Stable tag: 1.2.0
Requires PHP: 8.1
License: Apache-2.0
License URI: https://www.apache.org/licenses/LICENSE-2.0

Display low stock and out of stock alerts on the WooCommerce order edit page.

== Description ==

Never miss that an item is running low - see inventory alerts right where you process orders.

**Features:**

* Stock Alert Column - Shows red badges for out of stock and orange badges for low stock items
* Side Panel Summary - Displays all inventory alerts at a glance in a meta box
* Configurable Threshold - Set your own stock threshold for low stock warnings
* Hide Option - Ability to hide alerts on the order page for all users
* HPOS Compatible - Fully compatible with WooCommerce High-Performance Order Storage
* Translation Ready - Includes text domain for translations

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/inventory-alerts-for-woo/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure at WooCommerce > Settings > Products > Inventory

== Frequently Asked Questions ==

= Where do I configure the plugin? =

Go to WooCommerce > Settings > Products > Inventory and scroll to the inventory alerts settings.

= What does the threshold setting do? =

Set to 0 (default) to only show out of stock alerts. Set to any number to show alerts when stock is at or below that number.

== Screenshots ==

1. Stock alert column in the order items table
2. Inventory alerts meta box in the order sidebar
3. Settings in WooCommerce > Settings > Products > Inventory

== Changelog ==

= 1.2.0 =
* Tested with WordPress 7.1.
* Show the projected stock remaining while an order is being edited, so an item scanned from 1 to 0 is immediately marked as out of stock.
* Account for several order lines that use the same managed stock record, including variations that use their parent's stock.
* Show alerts for products explicitly set to out of stock even when WooCommerce stock management is disabled.
* Preserve decimal stock quantities in low-stock alerts.
* Split stock calculations, settings, and order-screen rendering into focused files, with standalone stock-calculation tests.
* Require PHP 8.1 or newer.

= 1.1.3 =
* Fixed inventory alert settings not rendering as a proper separate section in WooCommerce > Settings > Products > Inventory
* Added missing sectionstart so the Inventory Alerts block has its own titled section header
* Updated WooCommerce tested up to 9.9

= 1.1.2 =
* Minor internal update

= 1.1.1 =
* Fixed stock status display: show empty cells for products with sufficient stock instead of incorrect status text

= 1.1.0 =
* Renamed plugin to "Inventory Alerts for WooCommerce" for WordPress.org compliance
* Changed text domain to "inventory-alerts-for-woo"
* Fixed plugin checker issues (strict comparisons, proper escaping)
* Added uninstall.php for cleanup on plugin deletion
* Added languages folder for translations
* Settings link only shows when WooCommerce is active

= 1.0.1 =
* Moved settings to WooCommerce Settings > Products > Inventory
* Added option to hide alerts for all users
* Improved UI with bullet point lists for multiple alerts
* Product names now displayed in red for better visibility
* Moved CSS to separate file for better caching
* Added plugin constants for better code organization
* Security improvements with proper escaping

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.2.0 =
Adds projected-stock alerts while editing an order and compatibility verification for WordPress 7.1.

= 1.1.3 =
Fixes inventory alert settings not appearing correctly in WooCommerce settings.
