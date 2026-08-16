<?php

declare(strict_types=1);

use MainMoney\WooCommercePlugin\CheckoutConfig;
use MainMoney\WooCommercePlugin\CheckoutSession;

final class WC_Mm_Aggr_Assets
{
    public static function enqueue(string $targetId, CheckoutSession $session): void
    {
        wp_enqueue_style('wc-mm-aggr-checkout', WC_MM_AGGR_URL.'assets/js/checkout.css', [], WC_MM_AGGR_VERSION);
        wp_enqueue_script('wc-mm-aggr-checkout', WC_MM_AGGR_URL.'assets/js/checkout.js', [], WC_MM_AGGR_VERSION, true);
        $config = CheckoutConfig::forSession(
            rest_url('wc-mm-aggr/v1'),
            rest_url('wc-mm-aggr/v1/status'),
            $session,
        );
        $config['targetId'] = $targetId;
        $config['logoUrl'] = WC_MM_AGGR_URL.'assets/js/main_money_square.png';
        wp_add_inline_script(
            'wc-mm-aggr-checkout',
            'window.mmAggrCheckouts = window.mmAggrCheckouts || []; window.mmAggrCheckouts.push('.wp_json_encode($config).');',
            'before',
        );
    }
}
