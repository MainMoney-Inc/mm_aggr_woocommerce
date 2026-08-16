<?php

declare(strict_types=1);

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use MainMoney\WooCommercePlugin\CheckoutSession;

final class WC_Mm_Aggr_Blocks_Payment_Method extends AbstractPaymentMethodType
{
    protected $name = 'mm_aggr';

    public function initialize(): void
    {
        $this->settings = get_option('woocommerce_mm_aggr_settings', []);
        if (!is_array($this->settings)) {
            $this->settings = [];
        }
    }

    public function is_active(): bool
    {
        return ($this->settings['enabled'] ?? 'yes') === 'yes';
    }

    /**
     * @return list<string>
     */
    public function get_payment_method_script_handles(): array
    {
        $total = function_exists('WC') && WC()->cart !== null ? (string) WC()->cart->get_total('edit') : '';
        $currency = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : '';
        $session = CheckoutSession::create(
            amount: $total !== '' ? $total : null,
            currency: $currency !== '' ? $currency : null,
            lockAmount: true,
        );
        (new WC_Mm_Aggr_Session_Store())->save($session);
        WC_Mm_Aggr_Assets::enqueue('mm-aggr-blocks-checkout', $session);
        wp_enqueue_script(
            'wc-mm-aggr-blocks',
            WC_MM_AGGR_URL.'assets/js/blocks.js',
            ['wc-blocks-registry', 'wp-element', 'wc-mm-aggr-checkout'],
            WC_MM_AGGR_VERSION,
            true,
        );

        return ['wc-mm-aggr-blocks'];
    }

    /**
     * @return array<string, mixed>
     */
    public function get_payment_method_data(): array
    {
        return [
            'title' => $this->settings['title'] ?? 'MainMoney',
            'supports' => ['products', 'refunds'],
        ];
    }
}
