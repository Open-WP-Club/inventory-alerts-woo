# Woo Inventory Alerts

A WooCommerce plugin that displays low stock and out of stock alerts on order edit pages.

## Overview

This plugin adds inventory alerts directly on WooCommerce order pages to help store managers quickly identify stock issues when processing orders.

## Features

- **Stock Alert Column**: Adds a column in the order items table showing stock status
- **Inventory Alerts Meta Box**: Sidebar widget summarizing all stock alerts for the order
- **Bullet Point Lists**: Groups out of stock and low stock items with product names in red
- **Configurable Threshold**: Set custom low stock threshold level
- **Hide Option**: Ability to hide alerts for all users

## File Structure

```
woo-inventory-alerts/
├── woo-inventory-alerts.php    # Main plugin file
├── uninstall.php               # Cleanup on plugin deletion
├── readme.txt                  # WordPress.org readme
├── README.md                   # GitHub readme
├── CLAUDE.md                   # This file
├── assets/
│   └── css/
│       └── admin.css           # Admin styles for order pages
└── languages/                  # Translation files (if any)
```

## Settings Location

Settings are integrated into WooCommerce's native settings:
**WooCommerce > Settings > Products > Inventory** (scroll to "Woo Inventory Alerts" section)

## Key Classes & Methods

### `WIA_Inventory_Alerts` (main class)

- `get_threshold()` - Returns the configured low stock threshold
- `is_alerts_hidden()` - Checks if alerts should be hidden
- `add_inventory_alert_settings()` - Adds settings to WooCommerce Products > Inventory
- `add_stock_column_header()` / `add_stock_column_value()` - Order items table column
- `add_stock_alert_meta_box()` / `render_stock_alert_meta_box()` - Sidebar meta box

## WooCommerce Compatibility

- Supports both legacy post-based orders and HPOS (High-Performance Order Storage)
- Tested with WooCommerce 5.0 - 8.0+

## Plugin Constants

- `WIA_VERSION` - Plugin version (for cache busting)
- `WIA_PLUGIN_FILE` - Path to main plugin file
- `WIA_PLUGIN_DIR` - Plugin directory path
- `WIA_PLUGIN_URL` - Plugin directory URL

## Development Notes

- Admin styles in `assets/css/admin.css`
- Uses WooCommerce Settings API for options
- Options stored: `wia_stock_threshold` (int), `wia_hide_alerts` ('yes'/'no')
