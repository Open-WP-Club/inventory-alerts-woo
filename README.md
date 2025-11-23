# Woo Inventory Alerts

A WooCommerce plugin that displays low stock and out of stock alerts directly on the order edit page. Never miss that an item is running low - see it right where you process orders.

## Features

- **Stock Alert Column** - Adds a dedicated "Stock Alert" column to the order items table showing red badges for out of stock and orange badges for low stock items
- **Side Panel Summary** - Displays a summary meta box on the order page with all inventory alerts at a glance
- **Configurable Threshold** - Set your own stock threshold for when to show low stock warnings (default: 0 = only out of stock)
- **Hide Option** - Ability to hide alerts on the order page for all users
- **HPOS Compatible** - Fully compatible with WooCommerce High-Performance Order Storage
- **Translation Ready** - Includes text domain for translations

## Screenshots

When viewing an order, you'll see:
- A red **"OUT OF STOCK"** badge next to items with 0 stock
- An orange **"LOW STOCK: X"** badge next to items below your threshold
- A side panel summary showing all alerts for quick review

## Installation

1. Download the plugin files
2. Upload to `/wp-content/plugins/woo-inventory-alerts/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Configure at **WooCommerce > Settings > Products > Inventory**

## Configuration

Navigate to **WooCommerce > Settings > Products > Inventory** and scroll to the "Woo Inventory Alerts Plugin" section to set:

- **Stock Threshold** - Show alerts when stock is at or below this number
  - `0` (default): Only show alerts for out of stock items
  - `1`: Alert when only 1 item remains
  - `5`: Alert when 5 or fewer items remain

## Requirements

- WordPress 5.8+
- WooCommerce 5.0+
- PHP 7.4+

## License

Apache License 2.0 - see [LICENSE](LICENSE) for details.
