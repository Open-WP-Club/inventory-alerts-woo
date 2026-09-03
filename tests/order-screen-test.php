<?php
/**
 * Component tests for settings and order-screen output, without WordPress.
 *
 * Run: php tests/order-screen-test.php
 */

define('ABSPATH', dirname(__DIR__) . '/');

$wia_test_actions = array();
$wia_test_meta_boxes = array();
$wia_test_screen = 'shop_order';
$wia_test_orders = array();
function add_action($hook, $callback, $priority = 10, $arguments = 1) {
    global $wia_test_actions;
    $wia_test_actions[$hook] = $callback;
}
function get_option($name, $default = false) { return $default; }
function __($text, $domain = '') { return $text; }
function esc_html__($text, $domain = '') { return $text; }
function esc_html($text) { return (string) $text; }
function esc_attr($text) { return (string) $text; }
function wc_format_localized_decimal($quantity) { return (string) $quantity; }
function wc_get_page_screen_id($screen) { global $wia_test_screen; return $wia_test_screen; }
function wc_get_order($order_id) { global $wia_test_orders; return isset($wia_test_orders[$order_id]) ? $wia_test_orders[$order_id] : false; }
function add_meta_box($id, $title, $callback, $screen, $context, $priority) {
    global $wia_test_meta_boxes;
    $wia_test_meta_boxes[] = compact('id', 'callback', 'screen', 'context', 'priority');
}

class WC_Order {
    private $id;
    private $items;

    public function __construct($id, $items) {
        $this->id = $id;
        $this->items = $items;
    }

    public function get_id() { return $this->id; }
    public function get_items($type = '') { return $this->items; }
}

require_once dirname(__DIR__) . '/includes/stock-alerts.php';
require_once dirname(__DIR__) . '/includes/settings.php';
require_once dirname(__DIR__) . '/includes/order-screen.php';

function wia_component_assert($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, 'Failed: ' . $message . "\n");
        exit(1);
    }
}

function wia_component_product($id, $quantity, $name = 'Product') {
    return new class($id, $quantity, $name) {
        private $id;
        private $quantity;
        private $name;

        public function __construct($id, $quantity, $name) {
            $this->id = $id;
            $this->quantity = $quantity;
            $this->name = $name;
        }

        public function get_id() { return $this->id; }
        public function get_stock_managed_by_id() { return $this->id; }
        public function get_stock_quantity() { return $this->quantity; }
        public function managing_stock() { return true; }
        public function is_in_stock() { return true; }
        public function get_name() { return $this->name; }
    };
}

function wia_component_item($product, $quantity) {
    return new class($product, $quantity) {
        private $product;
        private $quantity;

        public function __construct($product, $quantity) {
            $this->product = $product;
            $this->quantity = $quantity;
        }

        public function get_product() { return $this->product; }
        public function get_quantity() { return $this->quantity; }
        public function get_meta($key, $single = true) { return 0; }
    };
}

$settings = array(
    array('type' => 'sectionend', 'id' => 'product_inventory_options'),
    array('type' => 'sectionend', 'id' => 'other_options'),
);
$settings = wia_add_inventory_alert_settings($settings, 'inventory');
wia_component_assert('wia_inventory_alerts_options' === $settings[1]['id'], 'settings must follow the native inventory section');
wia_component_assert('sectionstart' === $settings[1]['type'], 'alert settings must start a separate section');

wia_register_order_screen_alerts();
wia_component_assert(isset($wia_test_actions['woocommerce_admin_order_item_values']), 'order-item value hook must be registered');
wia_component_assert(isset($wia_test_actions['add_meta_boxes']), 'meta-box hook must be registered');

$product = wia_component_product(71, 1, 'Shared stock product');
$order = new WC_Order(501, array(wia_component_item($product, 1), wia_component_item($product, 1)));
ob_start();
wia_render_stock_alert_meta_box($order);
$html = ob_get_clean();
wia_component_assert(1 === substr_count($html, 'Shared stock product'), 'summary must not repeat one shared-stock alert');
wia_component_assert(false !== strpos($html, 'OUT OF STOCK'), 'projected zero must render as out of stock');

// HPOS passes a WC_Order object to the meta-box callback.
$wia_test_screen = 'woocommerce_page_wc-orders';
wia_add_stock_alert_meta_box();
wia_component_assert('woocommerce_page_wc-orders' === $wia_test_meta_boxes[0]['screen'], 'HPOS meta box must target the WooCommerce orders screen');

// Legacy orders pass a post object; the callback must retrieve the WC order.
$wia_test_screen = 'shop_order';
wia_add_stock_alert_meta_box();
wia_component_assert('shop_order' === $wia_test_meta_boxes[1]['screen'], 'legacy meta box must target the shop_order screen');
$wia_test_orders[501] = $order;
ob_start();
wia_render_stock_alert_meta_box((object) array('ID' => 501));
$legacy_html = ob_get_clean();
wia_component_assert(false !== strpos($legacy_html, 'OUT OF STOCK'), 'legacy meta box must render the retrieved order');

echo "All order-screen tests passed.\n";
