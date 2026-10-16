<?php

declare(strict_types=1);

final class WC_Mm_Aggr_Plugin
{
    public static function boot(): void
    {
        require_once WC_MM_AGGR_DIR.'includes/class-session-store.php';
        require_once WC_MM_AGGR_DIR.'includes/class-settings.php';
        require_once WC_MM_AGGR_DIR.'includes/class-order-sync.php';
        require_once WC_MM_AGGR_DIR.'includes/class-pending-orders.php';
        require_once WC_MM_AGGR_DIR.'includes/class-rest.php';
        require_once WC_MM_AGGR_DIR.'includes/class-assets.php';
        require_once WC_MM_AGGR_DIR.'includes/class-wc-gateway-mm-aggr.php';
        require_once WC_MM_AGGR_DIR.'includes/class-blocks.php';
        require_once WC_MM_AGGR_DIR.'includes/class-admin-api.php';
        require_once WC_MM_AGGR_DIR.'includes/class-admin-reports.php';
        require_once WC_MM_AGGR_DIR.'includes/class-admin-transactions.php';
        require_once WC_MM_AGGR_DIR.'includes/class-admin-wallets.php';
        require_once WC_MM_AGGR_DIR.'includes/class-admin-menu.php';

        add_filter('woocommerce_payment_gateways', [self::class, 'gateways']);
        WC_Mm_Aggr_Rest::register();
        WC_Mm_Aggr_Pending_Orders::register();
        WC_Mm_Aggr_Assets::register();
        if (is_admin()) {
            WC_Mm_Aggr_Admin_Menu::register();
        }
        WC_Mm_Aggr_Blocks::register();
    }

    /**
     * @param list<string> $gateways
     *
     * @return list<string>
     */
    public static function gateways(array $gateways): array
    {
        $gateways[] = WC_Gateway_Mm_Aggr::class;

        return $gateways;
    }
}
