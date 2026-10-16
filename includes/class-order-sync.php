<?php

declare(strict_types=1);

use MainMoney\Aggregator\Exception\AggregatorException;
use MainMoney\WooCommercePlugin\ClientFactory;
use MainMoney\WooCommercePlugin\OrderSyncPolicy;
use MainMoney\WooCommercePlugin\PaymentVerifier;

/**
 * Single, idempotent place where a deposit result is applied to an order.
 * Called by the webhook, the thank-you page poll, the reconciler and process_payment.
 */
final class WC_Mm_Aggr_Order_Sync
{
    private const EVENT_PREFIX = 'wc_mm_aggr_evt_';
    private const THROTTLE_PREFIX = 'wc_mm_aggr_chk_';

    /**
     * @param array<string, mixed> $payload status or webhook payload
     *
     * @return string PaymentVerifier outcome
     */
    public static function apply(WC_Order $order, array $payload): string
    {
        $status = isset($payload['status']) && is_string($payload['status']) ? $payload['status'] : '';
        $outcome = PaymentVerifier::evaluate($payload, (string) $order->get_total('edit'), $order->get_currency());
        $action = OrderSyncPolicy::decide($order->get_status(), $order->is_paid(), $outcome);
        if ($action === OrderSyncPolicy::COMPLETE) {
            $order->payment_complete(self::transactionId($payload));
            $order->add_order_note(__('MainMoney deposit SUCCESS.', 'woocommerce-mm-aggr'));
        } elseif ($action === OrderSyncPolicy::FAIL) {
            $order->update_status('failed', sprintf('MainMoney deposit %s', $status));
        } elseif ($action === OrderSyncPolicy::HOLD_MISMATCH && $order->get_meta('_mm_aggr_mismatch') !== 'yes') {
            $order->update_meta_data('_mm_aggr_mismatch', 'yes');
            $order->save();
            $order->update_status('on-hold', __('MainMoney reported a payment whose amount or currency does not match this order. Check it manually.', 'woocommerce-mm-aggr'));
        }

        return $outcome;
    }

    /** Keeps a webhook that beat order creation so process_payment can apply it. */
    public static function stash(string $reference, array $payload): void
    {
        set_transient(self::EVENT_PREFIX.md5($reference), $payload, HOUR_IN_SECONDS);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function takeStashed(string $reference): ?array
    {
        $payload = get_transient(self::EVENT_PREFIX.md5($reference));
        if (!is_array($payload)) {
            return null;
        }
        delete_transient(self::EVENT_PREFIX.md5($reference));

        return $payload;
    }

    /**
     * Asks the aggregator for the deposit status and applies it. Throttled so 1s polling stays cheap.
     *
     * @return string PaymentVerifier outcome (UNKNOWN when throttled or the API is unreachable)
     */
    public static function refresh(WC_Order $order, WC_Gateway_Mm_Aggr $gateway, int $throttleSeconds = 2): string
    {
        $reference = (string) $order->get_meta('_mm_aggr_reference');
        if ($reference === '') {
            return PaymentVerifier::UNKNOWN;
        }
        $key = self::THROTTLE_PREFIX.$order->get_id();
        if ($throttleSeconds > 0 && get_transient($key) !== false) {
            return PaymentVerifier::UNKNOWN;
        }
        $settings = WC_Mm_Aggr_Settings::fromGateway($gateway);
        if (!$settings->isConfigured()) {
            return PaymentVerifier::UNKNOWN;
        }
        if ($throttleSeconds > 0) {
            set_transient($key, 1, $throttleSeconds);
        }
        try {
            $payload = ClientFactory::fromSettings($settings)->status->check('deposit', $reference);
        } catch (AggregatorException $exception) {
            wc_get_logger()->warning('MainMoney status check failed: '.$exception->getMessage(), ['source' => 'mm-aggr']);

            return PaymentVerifier::UNKNOWN;
        }

        return self::apply($order, $payload);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function transactionId(array $payload): string
    {
        foreach (['internal_reference', 'transaction_id'] as $key) {
            if (isset($payload[$key]) && is_string($payload[$key])) {
                return $payload[$key];
            }
        }

        return '';
    }
}
