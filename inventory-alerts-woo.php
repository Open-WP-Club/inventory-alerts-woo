<?php
/**
 * Plugin Name: Inventory Alerts for WooCommerce
 * Plugin URI: https://github.com/Open-WP-Club/woo-inventory-alerts
 * Description: Shows alerts for low stock and out of stock items directly on the WooCommerce order edit page.
 * Version: 1.2.0
 * Author: Open WP Club
 * Author URI: https://github.com/Open-WP-Club
 * License: Apache-2.0
 * License URI: https://www.apache.org/licenses/LICENSE-2.0
 * Text Domain: inventory-alerts-for-woo
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 8.1
 * WC requires at least: 5.0
 * WC tested up to: 9.9
 * Tested up to: 7.1
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WIA_VERSION', '1.2.0');
define('WIA_PLUGIN_FILE', __FILE__);
define('WIA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WIA_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once WIA_PLUGIN_DIR . 'includes/stock-alerts.php';
require_once WIA_PLUGIN_DIR . 'includes/settings.php';
require_once WIA_PLUGIN_DIR . 'includes/order-screen.php';

function wia_check_woocommerce() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'wia_woocommerce_missing_notice');
        return false;
    }

    return true;
}

function wia_woocommerce_missing_notice() {
    echo '<div class="notice notice-error"><p>';
    esc_html_e('Inventory Alerts for WooCommerce requires WooCommerce to be installed and active.', 'inventory-alerts-for-woo');
    echo '</p></div>';
}

add_action('before_woocommerce_init', function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', WIA_PLUGIN_FILE, true);
    }
});

add_filter('plugin_action_links_' . plugin_basename(WIA_PLUGIN_FILE), function($links) {
    if (class_exists('WooCommerce')) {
        $url = admin_url('admin.php?page=wc-settings&tab=products&section=inventory');
        array_unshift($links, '<a href="' . esc_url($url) . '">' . esc_html__('Settings', 'inventory-alerts-for-woo') . '</a>');
    }

    return $links;
});

add_action('plugins_loaded', function() {
    if (!wia_check_woocommerce()) {
        return;
    }

    add_filter('woocommerce_get_settings_products', 'wia_add_inventory_alert_settings', 10, 2);

    if (is_admin()) {
        wia_register_order_screen_alerts();
    }
});
