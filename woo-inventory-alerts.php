<?php
/**
 * Plugin Name: Woo Inventory Alerts
 * Plugin URI: https://github.com/Open-WP-Club/woo-inventory-alerts
 * Description: Shows alerts for low stock and out of stock items directly on the WooCommerce order edit page.
 * Version: 1.0.0
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
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

/**
 * Add settings link to plugins page (works even without WooCommerce)
 */
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=wia-settings') . '">' . __('Settings', 'woo-inventory-alerts') . '</a>';
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
    load_plugin_textdomain('woo-inventory-alerts', false, dirname(plugin_basename(__FILE__)) . '/languages');

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
        // Admin settings
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_menu', array($this, 'add_settings_page'));

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
     * Register settings
     */
    public function register_settings() {
        register_setting('wia_settings', 'wia_stock_threshold', array(
            'type' => 'integer',
            'default' => 0,
            'sanitize_callback' => 'absint',
        ));

        add_settings_section(
            'wia_main_section',
            __('Alert Settings', 'woo-inventory-alerts'),
            array($this, 'settings_section_callback'),
            'wia-settings'
        );

        add_settings_field(
            'wia_stock_threshold',
            __('Stock Threshold', 'woo-inventory-alerts'),
            array($this, 'threshold_field_callback'),
            'wia-settings',
            'wia_main_section'
        );
    }

    /**
     * Settings section description
     */
    public function settings_section_callback() {
        echo '<p>' . esc_html__('Configure when to show low stock alerts on order pages.', 'woo-inventory-alerts') . '</p>';
    }

    /**
     * Threshold field
     */
    public function threshold_field_callback() {
        $value = $this->get_threshold();
        ?>
        <input type="number"
               name="wia_stock_threshold"
               value="<?php echo esc_attr($value); ?>"
               min="0"
               step="1"
               class="small-text">
        <p class="description">
            <?php esc_html_e('Show alert when product stock is at or below this number. Default: 0 (only out of stock).', 'woo-inventory-alerts'); ?>
        </p>
        <?php
    }

    /**
     * Add settings page
     */
    public function add_settings_page() {
        add_submenu_page(
            'woocommerce',
            __('Inventory Alerts', 'woo-inventory-alerts'),
            __('Inventory Alerts', 'woo-inventory-alerts'),
            'manage_woocommerce',
            'wia-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('wia_settings');
                do_settings_sections('wia-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
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

        wp_add_inline_style('woocommerce_admin_styles', $this->get_inline_css());
    }

    /**
     * Get inline CSS
     */
    private function get_inline_css() {
        return '
            .wia-stock-alert {
                display: inline-block;
                padding: 4px 8px;
                border-radius: 3px;
                font-size: 11px;
                font-weight: 600;
                line-height: 1.4;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .wia-stock-alert.wia-out-of-stock {
                background-color: #d63638;
                color: #fff;
            }
            .wia-stock-alert.wia-low-stock {
                background-color: #dba617;
                color: #fff;
            }
            .wia-stock-column {
                width: 120px;
                text-align: center;
            }
            .wia-meta-box-alert {
                padding: 10px;
                margin: 5px 0;
                border-left: 4px solid #d63638;
                background: #fcf0f0;
            }
            .wia-meta-box-alert.wia-warning {
                border-left-color: #dba617;
                background: #fef8e8;
            }
            .wia-meta-box-alert strong {
                color: #d63638;
            }
            .wia-meta-box-alert.wia-warning strong {
                color: #996800;
            }
            .wia-all-good {
                padding: 10px;
                color: #00a32a;
            }
        ';
    }

    /**
     * Add stock column header
     */
    public function add_stock_column_header($order) {
        echo '<th class="wia-stock-column">' . esc_html__('Stock Alert', 'woo-inventory-alerts') . '</th>';
    }

    /**
     * Add stock column value
     */
    public function add_stock_column_value($product, $item, $item_id) {
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

        foreach ($alerts as $alert) {
            $class = $alert['type'] === 'out_of_stock' ? '' : ' wia-warning';
            $label = $alert['type'] === 'out_of_stock'
                ? __('OUT OF STOCK', 'woo-inventory-alerts')
                : sprintf(__('LOW STOCK: %d left', 'woo-inventory-alerts'), $alert['stock']);

            printf(
                '<div class="wia-meta-box-alert%s"><strong>%s</strong><br>%s</div>',
                esc_attr($class),
                esc_html($label),
                esc_html($alert['name'])
            );
        }
    }
}
