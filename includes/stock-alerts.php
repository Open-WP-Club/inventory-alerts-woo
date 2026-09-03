<?php
/**
 * Stock calculations shared by the order column and summary panel.
 *
 * @package Inventory_Alerts_For_WooCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

function wia_get_threshold() {
    return max(0, (float) get_option('wia_stock_threshold', 0));
}

function wia_alerts_are_hidden() {
    return 'yes' === get_option('wia_hide_alerts', 'no');
}

function wia_stock_managed_by_id($product) {
    if (!$product) {
        return 0;
    }

    return method_exists($product, 'get_stock_managed_by_id')
        ? (int) $product->get_stock_managed_by_id()
        : (int) $product->get_id();
}

/**
 * Return quantities that remain to be deducted for every stock record.
 */
function wia_pending_stock_quantities($order) {
    static $cache = array();
    $pending_quantities = array();

    if (!$order instanceof WC_Order) {
        return $pending_quantities;
    }

    // The order-item table invokes this once per row. An order does not change
    // during one PHP request, so reuse the result instead of rebuilding it.
    $cache_key = method_exists($order, 'get_id') && $order->get_id()
        ? (string) $order->get_id()
        : null;
    if ($cache_key !== null && isset($cache[$cache_key])) {
        return $cache[$cache_key];
    }

    foreach ($order->get_items('line_item') as $item) {
        $product = $item->get_product();
        if (!$product || !$product->managing_stock()) {
            continue;
        }

        $stock_id = wia_stock_managed_by_id($product);
        if (!$stock_id) {
            continue;
        }

        // WooCommerce records quantities it has already deducted per line item.
        $quantity         = (float) $item->get_quantity();
        $reduced_quantity = (float) $item->get_meta('_reduced_stock', true);
        $pending_quantity = max(0, $quantity - $reduced_quantity);

        if (!isset($pending_quantities[$stock_id])) {
            $pending_quantities[$stock_id] = 0;
        }
        $pending_quantities[$stock_id] += $pending_quantity;
    }

    if ($cache_key === null) {
        return $pending_quantities;
    }

    $cache[$cache_key] = $pending_quantities;

    return $cache[$cache_key];
}

/**
 * Return the stock expected after the order's outstanding quantity is deducted.
 */
function wia_projected_stock_quantity($product, $pending_quantities) {
    if (!$product || !$product->managing_stock()) {
        return null;
    }

    $quantity = $product->get_stock_quantity();
    if ($quantity === null) {
        return null;
    }

    $stock_id = wia_stock_managed_by_id($product);
    $pending  = isset($pending_quantities[$stock_id]) ? $pending_quantities[$stock_id] : 0;

    return (float) $quantity - $pending;
}

/**
 * Return out_of_stock, low_stock, or null.
 */
function wia_stock_alert_type($product, $threshold, $projected_quantity = null) {
    if (!$product) {
        return null;
    }

    if (!$product->managing_stock()) {
        return $product->is_in_stock() ? null : 'out_of_stock';
    }

    $quantity = $projected_quantity === null ? $product->get_stock_quantity() : $projected_quantity;
    if ($quantity === null) {
        return $product->is_in_stock() ? null : 'out_of_stock';
    }

    if ((float) $quantity <= 0) {
        return 'out_of_stock';
    }
    if ((float) $quantity <= max(0, (float) $threshold)) {
        return 'low_stock';
    }

    return null;
}

function wia_format_stock_quantity($quantity) {
    return function_exists('wc_format_localized_decimal')
        ? wc_format_localized_decimal($quantity)
        : (string) $quantity;
}
