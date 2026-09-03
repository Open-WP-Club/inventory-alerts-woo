<?php
/**
 * WooCommerce settings integration.
 *
 * @package Inventory_Alerts_For_WooCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

function wia_add_inventory_alert_settings($settings, $current_section) {
    if ('inventory' !== $current_section) {
        return $settings;
    }

    $alert_settings = array(
        array('type' => 'sectionstart', 'id' => 'wia_inventory_alerts_options', 'title' => __('Inventory Alerts', 'inventory-alerts-for-woo')),
        array(
            'title' => __('Alert threshold', 'inventory-alerts-for-woo'),
            'desc' => __('Show alert when product stock is at or below this number. Set to 0 to only show out of stock alerts.', 'inventory-alerts-for-woo'),
            'desc_tip' => true,
            'id' => 'wia_stock_threshold',
            'type' => 'number',
            'default' => 0,
            'css' => 'width: 80px;',
            'custom_attributes' => array('min' => 0, 'step' => 1),
        ),
        array(
            'title' => __('Hide alerts', 'inventory-alerts-for-woo'),
            'desc' => __('Hide inventory alerts on the order edit page for all users', 'inventory-alerts-for-woo'),
            'id' => 'wia_hide_alerts',
            'type' => 'checkbox',
            'default' => 'no',
        ),
        array('type' => 'sectionend', 'id' => 'wia_inventory_alerts_options'),
    );

    $inserted = false;
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

    return $inserted ? $new_settings : array_merge($new_settings, $alert_settings);
}
