<?php

declare(strict_types=1);

use MainMoney\Aggregator\Exception\AggregatorException;
use MainMoney\WooCommercePlugin\ClientFactory;
use MainMoney\WooCommercePlugin\PaymentVerifier;
use MainMoney\WooCommercePlugin\RefundService;

class WC_Gateway_Mm_Aggr extends WC_Payment_Gateway
{
    public function __construct()
    {
        $this->id = 'mm_aggr';
        $this->method_title = __('MainMoney', 'woocommerce-mm-aggr');
        $this->method_description = __('MainMoney aggregator payments', 'woocommerce-mm-aggr');
        $this->icon = WC_MM_AGGR_URL.'assets/js/main_money_square.png';
        $this->has_fields = true;
        $this->supports = [
            'products',
            'refunds',
        ];
        $this->init_form_fields();
        $this->init_settings();
        $this->title = $this->get_option('title', $this->method_title);
        $this->enabled = $this->get_option('enabled', 'yes');
        $this->method_description .= ' '.sprintf(
            /* translators: %s admin menu url */
            __('Full configuration and connection test: <a href="%s">MainMoney menu</a>.', 'woocommerce-mm-aggr'),
            esc_url(admin_url('admin.php?page=mm-aggr')),
        );
        add_action('woocommerce_update_options_payment_gateways_'.$this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_update_options_payment_gateways_'.$this->id, [WC_Mm_Aggr_Cache_Store::class, 'flush']);
    }

    public function init_form_fields(): void
    {
        $this->form_fields = [
            'enabled' => [
                'title' => __('Enable/Disable', 'woocommerce-mm-aggr'),
                'type' => 'checkbox',
                'label' => __('Enable MainMoney', 'woocommerce-mm-aggr'),
                'default' => 'yes',
            ],
            'title' => [
                'title' => __('Title', 'woocommerce-mm-aggr'),
                'type' => 'text',
                'default' => __('MainMoney', 'woocommerce-mm-aggr'),
            ],
            'client_id' => [
                'title' => __('Client ID', 'woocommerce-mm-aggr'),
                'type' => 'text',
                'default' => '',
            ],
            'secret' => [
                'title' => __('API secret', 'woocommerce-mm-aggr'),
                'type' => 'password',
                'default' => '',
            ],
            'test' => [
                'title' => __('Test mode', 'woocommerce-mm-aggr'),
                'type' => 'checkbox',
                'label' => __('Use testaggregator.mainmoney.net', 'woocommerce-mm-aggr'),
                'default' => 'no',
            ],
            'base_uri' => [
                'title' => __('Base URI override', 'woocommerce-mm-aggr'),
                'type' => 'text',
                'default' => '',
                'description' => __('Optional. Leave empty to use the PHP SDK hosts.', 'woocommerce-mm-aggr'),
            ],
            'webhook_secret' => [
                'title' => __('Webhook secret', 'woocommerce-mm-aggr'),
                'type' => 'password',
                'default' => '',
                'description' => sprintf(
                    /* translators: %s webhook URL */
                    __('Aggregator webhook URL: %s', 'woocommerce-mm-aggr'),
                    rest_url('wc-mm-aggr/v1/webhooks'),
                ),
            ],
        ];
    }

    public function is_available(): bool
    {
        if (!parent::is_available()) {
            return false;
        }

        return WC_Mm_Aggr_Settings::fromGateway($this)->isConfigured();
    }

    public function payment_fields(): void
    {
        // The checkout widget is mounted by assets/js/checkout.js, which requests a cart-bound session over REST.
        echo '<div class="mm-aggr-checkout" id="mm-aggr-checkout"></div>';
    }

    /**
     * @param mixed $order_id
     *
     * @return array<string, string>
     */
    public function process_payment($order_id): array
    {
        $order = wc_get_order($order_id);
        if (!$order instanceof WC_Order) {
            return ['result' => 'failure'];
        }
        $reference = function_exists('WC') && WC()->session !== null ? WC()->session->get('mm_aggr_reference') : null;
        if (!is_string($reference) || $reference === '') {
            wc_add_notice(__('Please complete the MainMoney payment before placing the order.', 'woocommerce-mm-aggr'), 'error');

            return ['result' => 'failure'];
        }
        $payload = $this->currentDepositStatus($reference);
        $outcome = PaymentVerifier::evaluate($payload, (string) $order->get_total('edit'), $order->get_currency());
        if ($outcome === PaymentVerifier::FAILED) {
            wc_add_notice(__('The MainMoney payment was not completed. Please complete the payment and try again.', 'woocommerce-mm-aggr'), 'error');

            return ['result' => 'failure'];
        }
        $order->update_meta_data('_mm_aggr_reference', $reference);
        if (isset($payload['provider_code']) && is_string($payload['provider_code'])) {
            $order->update_meta_data('_mm_aggr_provider', $payload['provider_code']);
        }
        $order->save();
        // Temporary status: the deposit may still be PENDING at the provider. The webhook, the thank-you page
        // poll and the reconciler move the order on as soon as the final result is known.
        $order->update_status('on-hold', __('Awaiting MainMoney payment confirmation.', 'woocommerce-mm-aggr'));
        $early = WC_Mm_Aggr_Order_Sync::takeStashed($reference);
        if ($early !== null) {
            WC_Mm_Aggr_Order_Sync::apply($order, $early);
        }
        if ($payload !== []) {
            WC_Mm_Aggr_Order_Sync::apply($order, $payload);
        }
        if (function_exists('WC') && WC()->session !== null) {
            WC()->session->set('mm_aggr_reference', null);
            WC()->session->set('mm_aggr_token', null);
        }
        if (function_exists('WC') && WC()->cart !== null) {
            WC()->cart->empty_cart();
        }

        return [
            'result' => 'success',
            'redirect' => $this->get_return_url($order),
        ];
    }

    /**
     * @param mixed $order_id
     * @param mixed $amount
     * @param mixed $reason
     */
    public function process_refund($order_id, $amount = null, $reason = '')
    {
        $order = wc_get_order($order_id);
        if (!$order instanceof WC_Order) {
            return false;
        }
        $original = (string) $order->get_meta('_mm_aggr_reference');
        if ($original === '') {
            return new \WP_Error('mm_aggr_refund', __('This order has no MainMoney payment reference.', 'woocommerce-mm-aggr'));
        }
        $settings = WC_Mm_Aggr_Settings::fromGateway($this);
        $client = ClientFactory::fromSettings($settings);
        $refundReference = 'WC-R-'.$order->get_id().'-'.bin2hex(random_bytes(4));
        $payload = [
            'reference' => $refundReference,
            'original_transaction_id' => $original,
            'amount' => is_scalar($amount) ? (string) $amount : $order->get_total(),
            'currency' => $order->get_currency(),
            'reason' => is_string($reason) ? $reason : '',
        ];
        $provider = (string) $order->get_meta('_mm_aggr_provider');
        if ($provider !== '') {
            $payload['provider_code'] = $provider;
        }
        try {
            (new RefundService())->create($client, $payload, $refundReference);
        } catch (AggregatorException $exception) {
            wc_get_logger()->warning('MainMoney refund failed: '.$exception->getMessage(), ['source' => 'mm-aggr']);

            return new \WP_Error('mm_aggr_refund', __('MainMoney could not process the refund. See WooCommerce logs for details.', 'woocommerce-mm-aggr'));
        }
        $order->add_order_note(sprintf('MainMoney refund requested: %s', $refundReference));

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function currentDepositStatus(string $reference): array
    {
        $settings = WC_Mm_Aggr_Settings::fromGateway($this);
        if (!$settings->isConfigured()) {
            return [];
        }
        try {
            $status = ClientFactory::fromSettings($settings)->status->check('deposit', $reference);
        } catch (AggregatorException $exception) {
            wc_get_logger()->warning('MainMoney status check failed: '.$exception->getMessage(), ['source' => 'mm-aggr']);

            return [];
        }

        return $status;
    }
}
