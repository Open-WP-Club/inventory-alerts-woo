<?php
/**
 * Plugin Name: Inventory Alerts for WooCommerce
 * Plugin URI: https://github.com/Open-WP-Club/woo-inventory-alerts
 * Description: Shows alerts for low stock and out of stock items directly on the WooCommerce order edit page.
 * Version: 1.1.3
 * Author: Open WP Club
 * Author URI: https://github.com/Open-WP-Club
 * License: Apache-2.0
 * License URI: https://www.apache.org/licenses/LICENSE-2.0
 * Text Domain: inventory-alerts-for-woo
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 9.9
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('WIA_VERSION', '1.1.3');
define('WIA_PLUGIN_FILE', __FILE__);
define('WIA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WIA_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Check if WooCommerce is active
 */
function wia_check_woocommerce() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'wia_woocommerce_missing_notice');
        return false;
    }
    return true;
}

/**
 * Admin notice for missing WooCommerce
 */
function wia_woocommerce_missing_notice() {
    ?>
    <div class="notice notice-error">
        <p><?php esc_html_e('Inventory Alerts for WooCommerce requires WooCommerce to be installed and active.', 'inventory-alerts-for-woo'); ?></p>
    </div>
    <?php
}

/**
 * Declare HPOS compatibility
 */
add_action('before_woocommerce_init', function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', WIA_PLUGIN_FILE, true);
    }
});

/**
 * Add settings link to plugins page
 */
add_filter('plugin_action_links_' . plugin_basename(WIA_PLUGIN_FILE), function($links) {
    if (class_exists('WooCommerce')) {
        $settings_link = '<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=products&section=inventory')) . '">' . esc_html__('Settings', 'inventory-alerts-for-woo') . '</a>';
        array_unshift($links, $settings_link);
    }
    return $links;
});

/**
 * Initialize the plugin
 */
add_action('plugins_loaded', function() {
    if (!wia_check_woocommerce()) {
        return;
    }

    // Initialize main class
    WIA_Inventory_Alerts::get_instance();
});

/**
 * Main plugin class
 */
class WIA_Inventory_Alerts {

    /**
     * Single instance
     */
    private static $instance = null;

    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // WooCommerce settings integration
        add_filter('woocommerce_get_settings_products', array($this, 'add_inventory_alert_settings'), 10, 2);

        if (!is_admin()) {
            return;
        }

        // Order page alerts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
        add_action('woocommerce_admin_order_item_headers', array($this, 'add_stock_column_header'));
        add_action('woocommerce_admin_order_item_values', array($this, 'add_stock_column_value'), 10, 3);

        // Add meta box for summary
        add_action('add_meta_boxes', array($this, 'add_stock_alert_meta_box'));
    }

    /**
     * Get stock threshold option
     */
    public function get_threshold() {
        return (int) get_option('wia_stock_threshold', 0);
    }

    /**
     * Check if alerts are hidden
     */
    public function is_alerts_hidden() {
        return 'yes' === get_option('wia_hide_alerts', 'no');
    }

    /**
     * Returns 'out_of_stock', 'low_stock', or null for a product and threshold.
     */
    private function get_stock_alert_type($product, $threshold) {
        if (!$product || !$product->managing_stock()) {
            return null;
        }
        $qty = $product->get_stock_quantity();
        if ($qty === null) {
            return null;
        }
        if ($qty <= 0) {
            return 'out_of_stock';
        }
        if ($qty <= $threshold) {
            return 'low_stock';
        }
        return null;
    }

    /**
     * Add inventory alert settings to WooCommerce Products > Inventory section
     */
    public function add_inventory_alert_settings($settings, $current_section) {
        if ('inventory' !== $current_section) {
            return $settings;
        }

        $alert_settings = array(
            array(
                'type'  => 'sectionstart',
                'id'    => 'wia_inventory_alerts_options',
                'title' => __('Inventory Alerts', 'inventory-alerts-for-woo'),
            ),
            array(
                'title'             => __('Alert threshold', 'inventory-alerts-for-woo'),
                'desc'              => __('Show alert when product stock is at or below this number. Set to 0 to only show out of stock alerts.', 'inventory-alerts-for-woo'),
                'desc_tip'          => true,
                'id'                => 'wia_stock_threshold',
                'type'              => 'number',
                'default'           => 0,
                'css'               => 'width: 80px;',
                'custom_attributes' => array(
                    'min'  => 0,
                    'step' => 1,
                ),
            ),
            array(
                'title'   => __('Hide alerts', 'inventory-alerts-for-woo'),
                'desc'    => __('Hide inventory alerts on the order edit page for all users', 'inventory-alerts-for-woo'),
                'id'      => 'wia_hide_alerts',
                'type'    => 'checkbox',
                'default' => 'no',
            ),
            array(
                'type' => 'sectionend',
                'id'   => 'wia_inventory_alerts_options',
            ),
        );

        $inserted     = false;
        $new_settings = array();
        foreach ($settings as $setting) {
            $new_settings[] = $setting;
            if (!$inserted && isset($setting['type'], $setting['id']) && 'sectionend' === $setting['type'] && 'product_inventory_options' === $setting['id']) {
                foreach ($alert_settings as $alert_setting) {
                    $new_settings[] = $alert_setting;
                }
                $inserted = true;
            }
        }

        if (!$inserted) {
            foreach ($alert_settings as $alert_setting) {
                $new_settings[] = $alert_setting;
            }
        }

        return $new_settings;
    }

    /**
     * Enqueue admin styles
     */
    public function enqueue_admin_styles($hook) {
        global $post_type;

        // Only on order edit pages
        if (!in_array($hook, array('post.php', 'woocommerce_page_wc-orders'), true) ||
            ($hook === 'post.php' && $post_type !== 'shop_order')) {
            return;
        }

        wp_enqueue_style(
            'wia-admin-styles',
            WIA_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WIA_VERSION
        );
    }

    /**
     * Add stock column header
     */
    public function add_stock_column_header($order) {
        if ($this->is_alerts_hidden()) {
            return;
        }
        echo '<th class="wia-stock-column">' . esc_html__('Stock Alert', 'inventory-alerts-for-woo') . '</th>';
    }

    /**
     * Add stock column value
     */
    public function add_stock_column_value($product, $item, $item_id) {
        if ($this->is_alerts_hidden()) {
            return;
        }

        echo '<td class="wia-stock-column">';

        $alert_type = $this->get_stock_alert_type($product, $this->get_threshold());

        if ('out_of_stock' === $alert_type) {
            printf(
                '<span class="wia-stock-alert wia-out-of-stock">%s</span>',
                esc_html__('Out of Stock', 'inventory-alerts-for-woo')
            );
        } elseif ('low_stock' === $alert_type) {
            printf(
                '<span class="wia-stock-alert wia-low-stock">%s: %d</span>',
                esc_html__('Low Stock', 'inventory-alerts-for-woo'),
                (int) $product->get_stock_quantity()
            );
        }

        echo '</td>';
    }

    /**
     * Add meta box for stock alerts summary
     */
    public function add_stock_alert_meta_box() {
        if ($this->is_alerts_hidden()) {
            return;
        }

        $screen = function_exists('wc_get_page_screen_id') ? wc_get_page_screen_id('shop-order') : 'shop_order';

        add_meta_box(
            'wia-stock-alerts',
            __('Inventory Alerts', 'inventory-alerts-for-woo'),
            array($this, 'render_stock_alert_meta_box'),
            $screen,
            'side',
            'high'
        );
    }

    /**
     * Render meta box content
     */
    public function render_stock_alert_meta_box($post_or_order) {
        // Handle both HPOS and legacy
        if ($post_or_order instanceof WC_Order) {
            $order = $post_or_order;
        } else {
            $order = wc_get_order($post_or_order->ID);
        }

        if (!$order) {
            echo '<p>' . esc_html__('Order not found.', 'inventory-alerts-for-woo') . '</p>';
            return;
        }

        $threshold          = $this->get_threshold();
        $out_of_stock_alerts = array();
        $low_stock_alerts    = array();

        foreach ($order->get_items() as $item) {
            $product    = $item->get_product();
            $alert_type = $this->get_stock_alert_type($product, $threshold);

            if (!$alert_type) {
                continue;
            }

            $entry = array('name' => $product->get_name(), 'stock' => $product->get_stock_quantity());

            if ('out_of_stock' === $alert_type) {
                $out_of_stock_alerts[] = $entry;
            } else {
                $low_stock_alerts[] = $entry;
            }
        }

        if (empty($out_of_stock_alerts) && empty($low_stock_alerts)) {
            echo '<p class="wia-all-good">' . esc_html__('All items have sufficient stock.', 'inventory-alerts-for-woo') . '</p>';
            return;
        }

        // Display out of stock alerts
        if (!empty($out_of_stock_alerts)) {
            echo '<div class="wia-meta-box-alert">';
            echo '<strong>' . esc_html__('OUT OF STOCK', 'inventory-alerts-for-woo') . '</strong>';
            echo '<ul class="wia-alert-list">';
            foreach ($out_of_stock_alerts as $alert) {
                printf(
                    '<li><span class="wia-product-name">%s</span></li>',
                    esc_html($alert['name'])
                );
            }
            echo '</ul>';
            echo '</div>';
        }

        // Display low stock alerts
        if (!empty($low_stock_alerts)) {
            echo '<div class="wia-meta-box-alert wia-warning">';
            echo '<strong>' . esc_html__('LOW STOCK', 'inventory-alerts-for-woo') . '</strong>';
            echo '<ul class="wia-alert-list">';
            foreach ($low_stock_alerts as $alert) {
                printf(
                    '<li><span class="wia-product-name">%s</span> <small>(%d %s)</small></li>',
                    esc_html($alert['name']),
                    (int) $alert['stock'],
                    esc_html__('left', 'inventory-alerts-for-woo')
                );
            }
            echo '</ul>';
            echo '</div>';
        }
    }
}
