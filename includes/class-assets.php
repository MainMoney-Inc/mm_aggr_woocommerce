<?php

declare(strict_types=1);

final class WC_Mm_Aggr_Assets
{
    public static function register(): void
    {
        add_action('wp_enqueue_scripts', [self::class, 'maybeEnqueue']);
    }

    public static function maybeEnqueue(): void
    {
        if (function_exists('is_checkout') && is_checkout() && !is_wc_endpoint_url('order-received')) {
            self::enqueue();
        }
    }

    public static function enqueue(): void
    {
        wp_enqueue_style('wc-mm-aggr-checkout', WC_MM_AGGR_URL.'assets/js/checkout.css', [], WC_MM_AGGR_VERSION);
        wp_enqueue_script('wc-mm-aggr-checkout', WC_MM_AGGR_URL.'assets/js/checkout.js', [], WC_MM_AGGR_VERSION, true);
        $config = [
            'sessionUrl' => rest_url('wc-mm-aggr/v1/session'),
            'logoUrl' => WC_MM_AGGR_URL.'assets/js/main_money_square.png',
        ];
        wp_add_inline_script(
            'wc-mm-aggr-checkout',
            'window.mmAggrConfig = '.wp_json_encode($config).';',
            'before',
        );
    }
}
