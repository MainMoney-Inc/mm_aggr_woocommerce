<?php

declare(strict_types=1);

use MainMoney\Aggregator\Exception\AggregatorException;
use MainMoney\WooCommercePlugin\CheckoutSession;
use MainMoney\WooCommercePlugin\ClientFactory;
use MainMoney\WooCommercePlugin\OrderStatusMapper;
use MainMoney\WooCommercePlugin\ProxyService;
use MainMoney\WooCommercePlugin\WebhookService;

final class WC_Mm_Aggr_Rest
{
    public static function register(): void
    {
        add_action('rest_api_init', [self::class, 'routes']);
    }

    public static function routes(): void
    {
        $namespace = 'wc-mm-aggr/v1';
        foreach (['countries', 'providers', 'match-provider', 'amount-limits', 'checkout-preferences', 'status'] as $route) {
            register_rest_route($namespace, '/'.$route, [
                'methods' => 'GET',
                'callback' => [self::class, 'proxy'],
                'permission_callback' => [self::class, 'requireSession'],
            ]);
        }
        register_rest_route($namespace, '/fees/simulate', [
            'methods' => 'POST',
            'callback' => [self::class, 'proxy'],
            'permission_callback' => [self::class, 'requireSession'],
        ]);
        register_rest_route($namespace, '/deposits', [
            'methods' => 'POST',
            'callback' => [self::class, 'proxy'],
            'permission_callback' => [self::class, 'requireSession'],
        ]);
        register_rest_route($namespace, '/webhooks', [
            'methods' => 'POST',
            'callback' => [self::class, 'webhook'],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function requireSession(\WP_REST_Request $request): bool|\WP_Error
    {
        $session = self::sessionFromRequest($request);

        return $session instanceof \WP_Error ? $session : true;
    }

    public static function proxy(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $session = self::sessionFromRequest($request);
        if ($session instanceof \WP_Error) {
            return $session;
        }
        $gateway = self::gateway();
        if ($gateway === null) {
            return new \WP_Error('mm_aggr_unconfigured', 'MainMoney gateway is not available', ['status' => 503]);
        }
        $settings = WC_Mm_Aggr_Settings::fromGateway($gateway);
        if (!$settings->isConfigured()) {
            return new \WP_Error('mm_aggr_unconfigured', 'MainMoney is not configured', ['status' => 503]);
        }
        $route = trim(str_replace('/wc-mm-aggr/v1/', '', $request->get_route()), '/');
        $result = (new ProxyService(ClientFactory::fromSettings($settings)))->handle(
            $request->get_method(),
            $route,
            $request->get_query_params(),
            is_array($request->get_json_params()) ? $request->get_json_params() : [],
            $session,
        );

        return new \WP_REST_Response($result['body'], $result['status']);
    }

    public static function webhook(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $gateway = self::gateway();
        if ($gateway === null) {
            return new \WP_Error('mm_aggr_unconfigured', 'MainMoney gateway is not available', ['status' => 503]);
        }
        $settings = WC_Mm_Aggr_Settings::fromGateway($gateway);
        if ($settings->webhookSecret === '') {
            return new \WP_Error('mm_aggr_unconfigured', 'Webhook secret is not configured', ['status' => 503]);
        }
        try {
            $payload = (new WebhookService())->verifyAndDecode(
                ClientFactory::fromSettings($settings),
                $request->get_body(),
                (string) $request->get_header('x-webhook-signature'),
                $settings->webhookSecret,
            );
        } catch (AggregatorException $exception) {
            return new \WP_Error('mm_aggr_webhook', $exception->getMessage(), ['status' => 401]);
        }
        $reference = isset($payload['merchant_reference']) && is_string($payload['merchant_reference'])
            ? $payload['merchant_reference']
            : '';
        $status = isset($payload['status']) && is_string($payload['status']) ? $payload['status'] : '';
        if ($reference !== '' && function_exists('wc_get_orders')) {
            $orders = wc_get_orders([
                'limit' => 1,
                'meta_key' => '_mm_aggr_reference',
                'meta_value' => $reference,
            ]);
            $order = $orders[0] ?? null;
            if ($order instanceof WC_Order) {
                $order->add_order_note(sprintf('MainMoney webhook: %s', $status));
                if (OrderStatusMapper::isPaid($status)) {
                    $order->payment_complete(isset($payload['transaction_id']) && is_string($payload['transaction_id']) ? $payload['transaction_id'] : '');
                } elseif (OrderStatusMapper::isFailed($status)) {
                    $order->update_status('failed', 'MainMoney deposit '.$status);
                }
            }
        }

        return new \WP_REST_Response(['received' => true], 200);
    }

    private static function sessionFromRequest(\WP_REST_Request $request): CheckoutSession|\WP_Error
    {
        $header = (string) $request->get_header('authorization');
        if (!preg_match('/Bearer\s+(\S+)/i', $header, $matches)) {
            return new \WP_Error('mm_aggr_unauthorized', 'Missing checkout session token', ['status' => 401]);
        }
        $session = (new WC_Mm_Aggr_Session_Store())->find($matches[1]);
        if ($session === null) {
            return new \WP_Error('mm_aggr_unauthorized', 'Invalid checkout session', ['status' => 401]);
        }

        return $session;
    }

    private static function gateway(): ?WC_Gateway_Mm_Aggr
    {
        if (!function_exists('WC') || WC()->payment_gateways() === null) {
            return null;
        }
        $gateways = WC()->payment_gateways()->payment_gateways();
        $gateway = $gateways['mm_aggr'] ?? null;

        return $gateway instanceof WC_Gateway_Mm_Aggr ? $gateway : null;
    }
}
