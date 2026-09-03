<?php
/**
 * Lightweight tests that run without a WordPress or WooCommerce installation.
 *
 * Run: php tests/stock-alerts-test.php
 */

define('ABSPATH', dirname(__DIR__) . '/');

class WC_Order {
    private $items;

    public function __construct($items) {
        $this->items = $items;
    }

    public function get_items($type = '') {
        return $this->items;
    }
}

require_once dirname(__DIR__) . '/includes/stock-alerts.php';

function wia_test_assert_same($expected, $actual, $message) {
    if ($expected !== $actual) {
        fwrite(STDERR, 'Failed: ' . $message . "\n");
        exit(1);
    }
}

function wia_test_product($stock_id, $quantity, $managed = true, $in_stock = true, $product_id = null) {
    return new class($stock_id, $quantity, $managed, $in_stock, $product_id) {
        private $stock_id;
        private $quantity;
        private $managed;
        private $in_stock;
        private $product_id;

        public function __construct($stock_id, $quantity, $managed, $in_stock, $product_id) {
            $this->stock_id = $stock_id;
            $this->quantity = $quantity;
            $this->managed = $managed;
            $this->in_stock = $in_stock;
            $this->product_id = $product_id === null ? $stock_id : $product_id;
        }

        public function get_stock_managed_by_id() { return $this->stock_id; }
        public function get_id() { return $this->product_id; }
        public function get_stock_quantity() { return $this->quantity; }
        public function managing_stock() { return $this->managed; }
        public function is_in_stock() { return $this->in_stock; }
    };
}

function wia_test_item($product, $quantity, $reduced_quantity = 0) {
    return new class($product, $quantity, $reduced_quantity) {
        private $product;
        private $quantity;
        private $reduced_quantity;

        public function __construct($product, $quantity, $reduced_quantity) {
            $this->product = $product;
            $this->quantity = $quantity;
            $this->reduced_quantity = $reduced_quantity;
        }

        public function get_product() { return $this->product; }
        public function get_quantity() { return $this->quantity; }
        public function get_meta($key, $single = true) { return $this->reduced_quantity; }
    };
}

$product = wia_test_product(42, 1);
$order = new WC_Order(array(wia_test_item($product, 1)));
$pending = wia_pending_stock_quantities($order);
$projected = wia_projected_stock_quantity($product, $pending);
wia_test_assert_same(0.0, $projected, 'a scanned quantity must change 1 to projected 0');
wia_test_assert_same('out_of_stock', wia_stock_alert_type($product, 0, $projected), 'projected zero must be out of stock');

$order = new WC_Order(array(wia_test_item($product, 1, 1)));
$projected = wia_projected_stock_quantity($product, wia_pending_stock_quantities($order));
wia_test_assert_same(1.0, $projected, 'stock already reduced by WooCommerce must not be deducted twice');

$order = new WC_Order(array(wia_test_item($product, 1), wia_test_item($product, 1)));
$projected = wia_projected_stock_quantity($product, wia_pending_stock_quantities($order));
wia_test_assert_same(-1.0, $projected, 'all lines using the same stock record must be included');

$unmanaged_out_of_stock = wia_test_product(88, null, false, false);
wia_test_assert_same('out_of_stock', wia_stock_alert_type($unmanaged_out_of_stock, 0), 'manual out-of-stock products must alert');

$first_variation = wia_test_product(500, 3, true, true, 501);
$second_variation = wia_test_product(500, 3, true, true, 502);
$order = new WC_Order(array(wia_test_item($first_variation, 2), wia_test_item($second_variation, 2)));
$projected = wia_projected_stock_quantity($first_variation, wia_pending_stock_quantities($order));
wia_test_assert_same(-1.0, $projected, 'variations using parent-managed stock must share one projected quantity');

$threshold_product = wia_test_product(90, 2);
wia_test_assert_same('low_stock', wia_stock_alert_type($threshold_product, 2), 'quantity at the low-stock threshold must alert');
wia_test_assert_same(null, wia_stock_alert_type(wia_test_product(90, 2.5), 2), 'quantity above the low-stock threshold must not alert');
wia_test_assert_same('low_stock', wia_stock_alert_type(wia_test_product(90, 0.5), 0.5), 'decimal quantity at the threshold must alert');
wia_test_assert_same(null, wia_stock_alert_type(wia_test_product(90, 0.5), -1), 'negative thresholds must be treated as zero');
wia_test_assert_same('out_of_stock', wia_stock_alert_type(wia_test_product(90, 0), -1), 'zero stock must alert even with a negative threshold');

echo "All stock-alert tests passed.\n";
