<?php
/**
 * Plugin Name: Woo Inventory Alerts
 * Plugin URI: https://github.com/Open-WP-Club/woo-inventory-alerts
 * Description: Shows alerts for low stock and out of stock items directly on the WooCommerce order edit page.
 * Version: 1.0.1
 * Author: Open WP Club
 * Author URI: https://github.com/Open-WP-Club
 * License: Apache-2.0
 * License URI: https://www.apache.org/licenses/LICENSE-2.0
 * Text Domain: woo-inventory-alerts
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('WIA_VERSION', '1.0.1');
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
        <p><?php esc_html_e('Woo Inventory Alerts requires WooCommerce to be installed and active.', 'woo-inventory-alerts'); ?></p>
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
 * Add settings link to plugins page (works even without WooCommerce)
 */
add_filter('plugin_action_links_' . plugin_basename(WIA_PLUGIN_FILE), function($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=wc-settings&tab=products&section=inventory') . '">' . __('Settings', 'woo-inventory-alerts') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
});

/**
 * Initialize the plugin
 */
add_action('plugins_loaded', function() {
    if (!wia_check_woocommerce()) {
        return;
    }

    // Load text domain
    load_plugin_textdomain('woo-inventory-alerts', false, dirname(plugin_basename(WIA_PLUGIN_FILE)) . '/languages');

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
     * Add inventory alert settings to WooCommerce Products > Inventory section
     */
    public function add_inventory_alert_settings($settings, $current_section) {
        if ('inventory' !== $current_section) {
            return $settings;
        }

        // Find the position to insert our settings (before the section end)
        $new_settings = array();
        foreach ($settings as $setting) {
            // Insert our settings before the section end
            if (isset($setting['type']) && 'sectionend' === $setting['type'] && isset($setting['id']) && 'product_inventory_options' === $setting['id']) {
                // Add our settings
                $new_settings[] = array(
                    'title' => __('Woo Inventory Alerts Plugin', 'woo-inventory-alerts'),
                    'type'  => 'title',
                    'desc'  => '<em>' . __('The following settings are provided by the Woo Inventory Alerts plugin.', 'woo-inventory-alerts') . '</em>',
                    'id'    => 'wia_inventory_alerts_options',
                );

                $new_settings[] = array(
                    'title'             => __('Alert threshold', 'woo-inventory-alerts'),
                    'desc'              => __('Show alert when product stock is at or below this number. Set to 0 to only show out of stock alerts.', 'woo-inventory-alerts'),
                    'id'                => 'wia_stock_threshold',
                    'type'              => 'number',
                    'default'           => 0,
                    'css'               => 'width: 80px;',
                    'custom_attributes' => array(
                        'min'  => 0,
                        'step' => 1,
                    ),
                );

                $new_settings[] = array(
                    'title'         => __('Hide alerts', 'woo-inventory-alerts'),
                    'desc'          => __('Hide inventory alerts on the order edit page for all users', 'woo-inventory-alerts'),
                    'id'            => 'wia_hide_alerts',
                    'type'          => 'checkbox',
                    'default'       => 'no',
                    'checkboxgroup' => 'start',
                );

                $new_settings[] = array(
                    'type' => 'sectionend',
                    'id'   => 'wia_inventory_alerts_options',
                );
            }

            $new_settings[] = $setting;
        }

        return $new_settings;
    }

    /**
     * Enqueue admin styles
     */
    public function enqueue_admin_styles($hook) {
        global $post_type;

        // Only on order edit pages
        if (!in_array($hook, array('post.php', 'woocommerce_page_wc-orders')) ||
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
        echo '<th class="wia-stock-column">' . esc_html__('Stock Alert', 'woo-inventory-alerts') . '</th>';
    }

    /**
     * Add stock column value
     */
    public function add_stock_column_value($product, $item, $item_id) {
        if ($this->is_alerts_hidden()) {
            return;
        }

        echo '<td class="wia-stock-column">';

        if (!$product) {
            echo '&mdash;';
            echo '</td>';
            return;
        }

        // Check if product manages stock
        if (!$product->managing_stock()) {
            echo '&mdash;';
            echo '</td>';
            return;
        }

        $stock_qty = $product->get_stock_quantity();
        $threshold = $this->get_threshold();

        if ($stock_qty !== null && $stock_qty <= 0) {
            printf(
                '<span class="wia-stock-alert wia-out-of-stock">%s</span>',
                esc_html__('Out of Stock', 'woo-inventory-alerts')
            );
        } elseif ($stock_qty !== null && $stock_qty <= $threshold) {
            printf(
                '<span class="wia-stock-alert wia-low-stock">%s: %d</span>',
                esc_html__('Low Stock', 'woo-inventory-alerts'),
                $stock_qty
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

        // Determine screen based on HPOS status (with fallback for older WooCommerce)
        $screen = 'shop_order';
        if (class_exists('\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController')) {
            $controller = wc_get_container()->get(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class);
            if ($controller->custom_orders_table_usage_is_enabled()) {
                $screen = wc_get_page_screen_id('shop-order');
            }
        }

        add_meta_box(
            'wia-stock-alerts',
            __('Inventory Alerts', 'woo-inventory-alerts'),
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
            echo '<p>' . esc_html__('Order not found.', 'woo-inventory-alerts') . '</p>';
            return;
        }

        $threshold = $this->get_threshold();
        $alerts = array();

        foreach ($order->get_items() as $item) {
            $product = $item->get_product();

            if (!$product || !$product->managing_stock()) {
                continue;
            }

            $stock_qty = $product->get_stock_quantity();
            $product_name = $product->get_name();

            if ($stock_qty !== null && $stock_qty <= 0) {
                $alerts[] = array(
                    'type' => 'out_of_stock',
                    'name' => $product_name,
                    'stock' => $stock_qty,
                );
            } elseif ($stock_qty !== null && $stock_qty <= $threshold) {
                $alerts[] = array(
                    'type' => 'low_stock',
                    'name' => $product_name,
                    'stock' => $stock_qty,
                );
            }
        }

        if (empty($alerts)) {
            echo '<p class="wia-all-good">' . esc_html__('All items have sufficient stock.', 'woo-inventory-alerts') . '</p>';
            return;
        }

        // Separate alerts by type
        $out_of_stock_alerts = array_filter($alerts, function($alert) {
            return $alert['type'] === 'out_of_stock';
        });
        $low_stock_alerts = array_filter($alerts, function($alert) {
            return $alert['type'] === 'low_stock';
        });

        // Display out of stock alerts
        if (!empty($out_of_stock_alerts)) {
            echo '<div class="wia-meta-box-alert">';
            echo '<strong>' . esc_html__('OUT OF STOCK', 'woo-inventory-alerts') . '</strong>';
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
            echo '<strong>' . esc_html__('LOW STOCK', 'woo-inventory-alerts') . '</strong>';
            echo '<ul class="wia-alert-list">';
            foreach ($low_stock_alerts as $alert) {
                printf(
                    '<li><span class="wia-product-name">%s</span> <small>(%d %s)</small></li>',
                    esc_html($alert['name']),
                    $alert['stock'],
                    esc_html__('left', 'woo-inventory-alerts')
                );
            }
            echo '</ul>';
            echo '</div>';
        }
    }
}
