<?php

declare(strict_types=1);

final class WC_Mm_Aggr_Blocks
{
    public static function register(): void
    {
        add_action('woocommerce_blocks_loaded', [self::class, 'loaded']);
    }

    public static function loaded(): void
    {
        if (!class_exists('\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
            return;
        }
        require_once WC_MM_AGGR_DIR.'includes/class-blocks-payment-method.php';
        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            static function ($registry): void {
                if (!is_object($registry) || !method_exists($registry, 'register')) {
                    return;
                }
                $registry->register(new WC_Mm_Aggr_Blocks_Payment_Method());
            },
        );
    }
}
