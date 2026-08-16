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

define('WC_MM_AGGR_VERSION', '0.1.0');
define('WC_MM_AGGR_FILE', __FILE__);
define('WC_MM_AGGR_DIR', plugin_dir_path(__FILE__));
define('WC_MM_AGGR_URL', plugin_dir_url(__FILE__));

$wcMmAggrAutoload = WC_MM_AGGR_DIR.'vendor/autoload.php';
if (!is_file($wcMmAggrAutoload)) {
    add_action('admin_notices', static function (): void {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__('MainMoney for WooCommerce requires Composer dependencies. Run composer install in the plugin directory.', 'woocommerce-mm-aggr');
        echo '</p></div>';
    });

    return;
}

require_once $wcMmAggrAutoload;

add_action('plugins_loaded', static function (): void {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }
    load_plugin_textdomain('woocommerce-mm-aggr', false, dirname(plugin_basename(WC_MM_AGGR_FILE)).'/languages');
    require_once WC_MM_AGGR_DIR.'includes/class-plugin.php';
    WC_Mm_Aggr_Plugin::boot();
});
