<?php

declare(strict_types=1);

use MainMoney\Aggregator\Exception\AggregatorException;
use MainMoney\WooCommercePlugin\CheckoutSession;
use MainMoney\WooCommercePlugin\ClientFactory;
use MainMoney\WooCommercePlugin\OrderStatusMapper;
use MainMoney\WooCommercePlugin\RefundService;

class WC_Gateway_Mm_Aggr extends WC_Payment_Gateway
{
    public function __construct()
    {
        $this->id = 'mm_aggr';
        $this->method_title = __('MainMoney', 'woocommerce-mm-aggr');
        $this->method_description = __('MainMoney aggregator payments', 'woocommerce-mm-aggr');
        $this->has_fields = true;
        $this->supports = [
            'products',
            'refunds',
        ];
        $this->init_form_fields();
        $this->init_settings();
        $this->title = $this->get_option('title', $this->method_title);
        $this->enabled = $this->get_option('enabled', 'yes');
        add_action('woocommerce_update_options_payment_gateways_'.$this->id, [$this, 'process_admin_options']);
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

    public function payment_fields(): void
    {
        $total = function_exists('WC') && WC()->cart !== null ? (string) WC()->cart->get_total('edit') : '';
        $currency = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : '';
        $existing = function_exists('WC') && WC()->session !== null ? WC()->session->get('mm_aggr_reference') : null;
        $session = CheckoutSession::create(
            amount: $total !== '' ? $total : null,
            currency: $currency !== '' ? $currency : null,
            reference: is_string($existing) && $existing !== '' ? $existing : null,
            lockAmount: true,
        );
        (new WC_Mm_Aggr_Session_Store())->save($session);
        $targetId = 'mm-aggr-checkout-'.(function_exists('wp_unique_id') ? wp_unique_id() : uniqid());
        WC_Mm_Aggr_Assets::enqueue($targetId, $session);
        echo '<div class="mm-aggr-checkout" id="'.esc_attr($targetId).'"></div>';
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
            $reference = 'WC-'.$order->get_id().'-'.bin2hex(random_bytes(4));
        }
        $order->update_meta_data('_mm_aggr_reference', $reference);
        $order->save();
        $statusCode = $this->currentDepositStatus($reference);
        if (OrderStatusMapper::isPaid($statusCode)) {
            $order->payment_complete();
            $order->add_order_note('MainMoney deposit SUCCESS');
        } else {
            $order->update_status('on-hold', __('Awaiting MainMoney payment.', 'woocommerce-mm-aggr'));
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
    public function process_refund($order_id, $amount = null, $reason = ''): bool
    {
        $order = wc_get_order($order_id);
        if (!$order instanceof WC_Order) {
            return false;
        }
        $original = (string) $order->get_meta('_mm_aggr_reference');
        if ($original === '') {
            throw new \RuntimeException('Missing MainMoney merchant reference');
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
            throw new \RuntimeException($exception->getMessage(), 0, $exception);
        }
        $order->add_order_note(sprintf('MainMoney refund requested: %s', $refundReference));

        return true;
    }

    private function currentDepositStatus(string $reference): string
    {
        $settings = WC_Mm_Aggr_Settings::fromGateway($this);
        if (!$settings->isConfigured()) {
            return '';
        }
        try {
            $status = ClientFactory::fromSettings($settings)->status->check('deposit', $reference);
        } catch (AggregatorException) {
            return '';
        }
        $code = $status['status'] ?? '';

        return is_string($code) ? $code : '';
    }
}
