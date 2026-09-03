<?php
/**
 * Order edit screen integration.
 *
 * @package Inventory_Alerts_For_WooCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

function wia_register_order_screen_alerts() {
    add_action('admin_enqueue_scripts', 'wia_enqueue_order_screen_styles');
    add_action('woocommerce_admin_order_item_headers', 'wia_add_stock_column_header');
    add_action('woocommerce_admin_order_item_values', 'wia_add_stock_column_value', 10, 3);
    add_action('add_meta_boxes', 'wia_add_stock_alert_meta_box');
}

function wia_enqueue_order_screen_styles() {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    $order_screen_id = function_exists('wc_get_page_screen_id') ? wc_get_page_screen_id('shop-order') : 'shop_order';

    if (!$screen || ($screen->id !== $order_screen_id && (!isset($screen->post_type) || $screen->post_type !== 'shop_order'))) {
        return;
    }

    wp_enqueue_style('wia-admin-styles', WIA_PLUGIN_URL . 'assets/css/admin.css', array(), WIA_VERSION);
}

function wia_add_stock_column_header($order) {
    if (!wia_alerts_are_hidden()) {
        echo '<th class="wia-stock-column">' . esc_html__('Stock Alert', 'inventory-alerts-for-woo') . '</th>';
    }
}

function wia_add_stock_column_value($product, $item, $item_id) {
    if (wia_alerts_are_hidden()) {
        return;
    }

    $order = method_exists($item, 'get_order') ? $item->get_order() : null;
    $pending_quantities = wia_pending_stock_quantities($order);
    $projected_quantity = wia_projected_stock_quantity($product, $pending_quantities);
    $alert_type = wia_stock_alert_type($product, wia_get_threshold(), $projected_quantity);

    echo '<td class="wia-stock-column">';
    if ('out_of_stock' === $alert_type) {
        printf('<span class="wia-stock-alert wia-out-of-stock">%s</span>', esc_html__('Out of Stock', 'inventory-alerts-for-woo'));
    } elseif ('low_stock' === $alert_type) {
        printf(
            '<span class="wia-stock-alert wia-low-stock">%s: %s</span>',
            esc_html__('Low Stock', 'inventory-alerts-for-woo'),
            esc_html(wia_format_stock_quantity($projected_quantity))
        );
    }
    echo '</td>';
}

function wia_add_stock_alert_meta_box() {
    if (wia_alerts_are_hidden()) {
        return;
    }

    $screen = function_exists('wc_get_page_screen_id') ? wc_get_page_screen_id('shop-order') : 'shop_order';
    add_meta_box('wia-stock-alerts', __('Inventory Alerts', 'inventory-alerts-for-woo'), 'wia_render_stock_alert_meta_box', $screen, 'side', 'high');
}

function wia_render_stock_alert_meta_box($post_or_order) {
    $order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order($post_or_order->ID);
    if (!$order) {
        echo '<p>' . esc_html__('Order not found.', 'inventory-alerts-for-woo') . '</p>';
        return;
    }

    $out_of_stock_alerts = array();
    $low_stock_alerts = array();
    $pending_quantities = wia_pending_stock_quantities($order);
    $seen_alerts = array();

    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        $projected_quantity = wia_projected_stock_quantity($product, $pending_quantities);
        $alert_type = wia_stock_alert_type($product, wia_get_threshold(), $projected_quantity);
        if (!$alert_type) {
            continue;
        }

        // Several lines (or variations) can use one stock record. Show the
        // stock warning once rather than repeating the same inventory issue.
        $stock_id = $product->managing_stock() ? wia_stock_managed_by_id($product) : $product->get_id();
        $alert_key = $alert_type . ':' . $stock_id;
        if (isset($seen_alerts[$alert_key])) {
            continue;
        }
        $seen_alerts[$alert_key] = true;

        $entry = array('name' => $product->get_name(), 'stock' => $projected_quantity);
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

    wia_render_stock_alert_list($out_of_stock_alerts, 'wia-meta-box-alert', __('OUT OF STOCK', 'inventory-alerts-for-woo'));
    wia_render_stock_alert_list($low_stock_alerts, 'wia-meta-box-alert wia-warning', __('LOW STOCK', 'inventory-alerts-for-woo'), true);
}

function wia_render_stock_alert_list($alerts, $classes, $heading, $show_quantity = false) {
    if (empty($alerts)) {
        return;
    }

    echo '<div class="' . esc_attr($classes) . '"><strong>' . esc_html($heading) . '</strong><ul class="wia-alert-list">';
    foreach ($alerts as $alert) {
        echo '<li><span class="wia-product-name">' . esc_html($alert['name']) . '</span>';
        if ($show_quantity) {
            echo ' <small>(' . esc_html(wia_format_stock_quantity($alert['stock'])) . ' ' . esc_html__('left', 'inventory-alerts-for-woo') . ')</small>';
        }
        echo '</li>';
    }
    echo '</ul></div>';
}
