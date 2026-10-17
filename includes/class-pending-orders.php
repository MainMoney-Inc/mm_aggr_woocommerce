<?php

declare(strict_types=1);

use MainMoney\WooCommercePlugin\PaymentVerifier;

/**
 * Handles orders that are on hold while the provider confirms the deposit:
 * the waiting UI on the thank-you page and the background reconciler.
 */
final class WC_Mm_Aggr_Pending_Orders
{
    public const CRON_HOOK = 'wc_mm_aggr_reconcile';
    private const MIN_AGE = 120;
    private const MAX_AGE = 172800;
    public const REFETCH_ACTION = 'mm_aggr_refetch_status';

    public static function register(): void
    {
        add_action('woocommerce_thankyou_mm_aggr', [self::class, 'thankYou']);
        add_filter('cron_schedules', [self::class, 'schedules']);
        add_action(self::CRON_HOOK, [self::class, 'reconcile']);
        add_action('init', [self::class, 'schedule']);
        add_filter('woocommerce_order_actions', [self::class, 'orderActions'], 10, 2);
        add_action('woocommerce_order_action_'.self::REFETCH_ACTION, [self::class, 'refetchStatus']);
    }

    /**
     * "Refetch status from MainMoney" for orders still waiting on the provider.
     *
     * @param array<string, string> $actions
     *
     * @return array<string, string>
     */
    public static function orderActions(array $actions, mixed $order = null): array
    {
        if (!$order instanceof WC_Order) {
            $order = wc_get_order(isset($GLOBALS['theorder']) && $GLOBALS['theorder'] instanceof WC_Order ? $GLOBALS['theorder']->get_id() : 0);
        }
        if ($order instanceof WC_Order
            && $order->get_payment_method() === 'mm_aggr'
            && $order->has_status(['on-hold', 'pending'])
            && $order->get_meta('_mm_aggr_reference') !== '') {
            $actions[self::REFETCH_ACTION] = __('Refetch status from MainMoney', 'woocommerce-mm-aggr');
        }

        return $actions;
    }

    public static function refetchStatus(WC_Order $order): void
    {
        $gateway = WC()->payment_gateways()->payment_gateways()['mm_aggr'] ?? null;
        if (!$gateway instanceof WC_Gateway_Mm_Aggr) {
            return;
        }
        $outcome = WC_Mm_Aggr_Order_Sync::refresh($order, $gateway, 0);
        $order = wc_get_order($order->get_id()) ?: $order;
        $labels = [
            PaymentVerifier::PAID => __('payment confirmed', 'woocommerce-mm-aggr'),
            PaymentVerifier::PENDING => __('still pending at the provider', 'woocommerce-mm-aggr'),
            PaymentVerifier::FAILED => __('payment failed', 'woocommerce-mm-aggr'),
            PaymentVerifier::MISMATCH => __('payment does not match the order, review manually', 'woocommerce-mm-aggr'),
        ];
        $order->add_order_note(sprintf(
            /* translators: %s outcome of the manual status check */
            __('MainMoney status refetched manually: %s.', 'woocommerce-mm-aggr'),
            $labels[$outcome] ?? __('MainMoney could not be reached or returned no status', 'woocommerce-mm-aggr'),
        ));
    }

    /**
     * @param array<string, array<string, mixed>> $schedules
     *
     * @return array<string, array<string, mixed>>
     */
    public static function schedules(array $schedules): array
    {
        $schedules['wc_mm_aggr_five_minutes'] = [
            'interval' => 300,
            'display' => __('Every five minutes', 'woocommerce-mm-aggr'),
        ];

        return $schedules;
    }

    public static function schedule(): void
    {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 60, 'wc_mm_aggr_five_minutes', self::CRON_HOOK);
        }
    }

    public static function thankYou(mixed $orderId): void
    {
        $order = wc_get_order(is_numeric($orderId) ? (int) $orderId : 0);
        if (!$order instanceof WC_Order || !$order->has_status('on-hold')) {
            return;
        }
        wp_enqueue_script('wc-mm-aggr-order-status', WC_MM_AGGR_URL.'assets/js/order-status.js', [], WC_MM_AGGR_VERSION, true);
        wp_enqueue_style('wc-mm-aggr-checkout', WC_MM_AGGR_URL.'assets/js/checkout.css', [], WC_MM_AGGR_VERSION);
        $data = [
            'url' => rest_url('wc-mm-aggr/v1/orders/'.$order->get_id().'/status'),
            'key' => $order->get_order_key(),
            'logo' => WC_MM_AGGR_URL.'assets/js/main_money_square.png',
            'waiting' => __('Waiting for MainMoney to confirm your payment…', 'woocommerce-mm-aggr'),
            'slow' => __('Your payment request has been sent. We are waiting for the provider to confirm success or failure. You can leave this page: we will email you once it is confirmed.', 'woocommerce-mm-aggr'),
            'failed' => __('The payment failed or was cancelled.', 'woocommerce-mm-aggr'),
            'retry' => __('Try paying again', 'woocommerce-mm-aggr'),
            'mismatch' => __('We received a payment that does not match this order. The shop will review it manually.', 'woocommerce-mm-aggr'),
        ];
        echo '<div class="mm-aggr-order-status mm-checkout"';
        foreach ($data as $name => $value) {
            echo ' data-'.esc_attr($name).'="'.esc_attr($value).'"';
        }
        echo '></div>';
    }

    public static function reconcile(): void
    {
        if (!function_exists('wc_get_orders') || !function_exists('WC') || WC()->payment_gateways() === null) {
            return;
        }
        $gateway = WC()->payment_gateways()->payment_gateways()['mm_aggr'] ?? null;
        if (!$gateway instanceof WC_Gateway_Mm_Aggr) {
            return;
        }
        $orders = wc_get_orders([
            'status' => 'on-hold',
            'payment_method' => 'mm_aggr',
            'limit' => 50,
            'orderby' => 'date',
            'order' => 'ASC',
        ]);
        foreach ($orders as $order) {
            if (!$order instanceof WC_Order || $order->get_meta('_mm_aggr_reference') === '') {
                continue;
            }
            $created = $order->get_date_created();
            $age = $created !== null ? time() - $created->getTimestamp() : 0;
            if ($age < self::MIN_AGE) {
                continue;
            }
            if ($age > self::MAX_AGE) {
                if ($order->get_meta('_mm_aggr_stale') !== 'yes') {
                    $order->update_meta_data('_mm_aggr_stale', 'yes');
                    $order->add_order_note(__('MainMoney payment is still unconfirmed after 48 hours. Check it in the MainMoney dashboard.', 'woocommerce-mm-aggr'));
                    $order->save();
                }
                continue;
            }
            WC_Mm_Aggr_Order_Sync::refresh($order, $gateway, 0);
        }
    }
}
