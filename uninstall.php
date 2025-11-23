<?php
/**
 * Uninstall script for Woo Inventory Alerts
 *
 * This file runs when the plugin is deleted from the WordPress admin.
 * It removes all options created by the plugin.
 *
 * @package Woo_Inventory_Alerts
 */

// Exit if not called by WordPress
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options
delete_option('wia_stock_threshold');
delete_option('wia_hide_alerts');
