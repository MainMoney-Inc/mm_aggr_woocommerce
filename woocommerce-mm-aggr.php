<?php
/**
 * Plugin Name: MainMoney for WooCommerce
 * Description: MainMoney aggregator gateway for WooCommerce.
 * Version: 0.1.0
 * Requires at least: 6.7
 * Requires PHP: 8.2
 * Requires Plugins: woocommerce
 * Author: MainMoney SARL
 * License: PolyForm-Noncommercial-1.0.0
 * Text Domain: woocommerce-mm-aggr
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('plugins_loaded', static function (): void {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }
    require_once __DIR__ . '/includes/class-wc-gateway-mm-aggr.php';
});
