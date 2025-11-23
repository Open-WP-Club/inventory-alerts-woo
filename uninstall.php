<?php
/**
 * Uninstall script for Inventory Alerts for WooCommerce
 *
 * This file runs when the plugin is deleted from the WordPress admin.
 * It removes all options created by the plugin.
 *
 * @package Inventory_Alerts_For_WooCommerce
 */

// Exit if not called by WordPress
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options
delete_option('wia_stock_threshold');
delete_option('wia_hide_alerts');
