<?php

declare(strict_types=1);

use MainMoney\Aggregator\Exception\AggregatorException;
use MainMoney\WooCommercePlugin\CheckoutSession;
use MainMoney\WooCommercePlugin\ClientFactory;
use MainMoney\WooCommercePlugin\CheckoutConfig;
use MainMoney\WooCommercePlugin\OrderSyncPolicy;
use MainMoney\WooCommercePlugin\PaymentVerifier;
use MainMoney\WooCommercePlugin\ProxyCache;
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
        register_rest_route($namespace, '/session', [
            'methods' => 'POST',
            'callback' => [self::class, 'session'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route($namespace, '/orders/(?P<id>\d+)/status', [
            'methods' => 'GET',
            'callback' => [self::class, 'orderStatus'],
            'permission_callback' => '__return_true',
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

    /**
     * Creates (or reuses) a checkout session bound to the visitor's current cart total.
     * The amount comes from the server-side cart, never from the browser.
     */
    public static function session(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        if (function_exists('wc_load_cart')) {
            wc_load_cart();
        }
        $gateway = self::gateway();
        if ($gateway === null || !WC_Mm_Aggr_Settings::fromGateway($gateway)->isConfigured()) {
            return new \WP_Error('mm_aggr_unconfigured', 'MainMoney is not configured', ['status' => 503]);
        }
        $cart = WC()->cart;
        if ($cart !== null && $cart->is_empty() && method_exists($cart, 'get_cart_from_session')) {
            // REST runs after wp_loaded, when WooCommerce has already skipped restoring the cart from the session.
            $cart->get_cart_from_session();
        }
        if ($cart === null || $cart->is_empty()) {
            $hasCookie = false;
            foreach (array_keys($_COOKIE) as $name) {
                if (str_starts_with((string) $name, 'wp_woocommerce_session_')) {
                    $hasCookie = true;
                }
            }
            wc_get_logger()->warning(
                sprintf(
                    'MainMoney session: cart empty, WooCommerce session cookie %s, user %s (a logged-in user without X-WP-Nonce is dropped by WordPress)',
                    $hasCookie ? 'present' : 'missing',
                    is_user_logged_in() ? 'logged in' : 'guest',
                ),
                ['source' => 'mm-aggr'],
            );

            return new \WP_Error('mm_aggr_empty_cart', 'Your cart session could not be read. Please reload the page.', ['status' => 409]);
        }
        $cart->calculate_totals();
        $amount = (string) wc_format_decimal($cart->get_total('edit'), wc_get_price_decimals());
        $currency = get_woocommerce_currency();
        // Every call starts a fresh session: a refreshed page or a retry gets its own reference and
        // Idempotency-Key. A deposit that failed keeps its reference forever, so it can never be reused.
        if (WC()->session !== null) {
            WC()->session->set_customer_session_cookie(true);
        }
        $session = CheckoutSession::create(amount: $amount, currency: $currency, lockAmount: true);
        (new WC_Mm_Aggr_Session_Store())->save($session);

        $response = new \WP_REST_Response(CheckoutConfig::forSession(
            rest_url('wc-mm-aggr/v1'),
            rest_url('wc-mm-aggr/v1/status'),
            $session,
            str_starts_with(get_locale(), 'fr') ? 'fr' : 'en',
            self::customerEmail(),
        ), 200);
        $response->header('Cache-Control', 'no-store');

        return $response;
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
        $cache = new ProxyCache(new WC_Mm_Aggr_Cache_Store(), $settings->cacheScope(), WC_Mm_Aggr_Cache_Store::version());
        $result = (new ProxyService(ClientFactory::fromSettings($settings), $cache))->handle(
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
            wc_get_logger()->warning('MainMoney webhook rejected: '.$exception->getMessage(), ['source' => 'mm-aggr']);

            return new \WP_Error('mm_aggr_webhook', 'Invalid webhook', ['status' => 401]);
        }
        $reference = isset($payload['merchant_reference']) && is_string($payload['merchant_reference'])
            ? $payload['merchant_reference']
            : '';
        if ($reference !== '' && !isset($payload['refund']) && function_exists('wc_get_orders')) {
            $orders = wc_get_orders([
                'limit' => 1,
                'meta_key' => '_mm_aggr_reference',
                'meta_value' => $reference,
            ]);
            $order = $orders[0] ?? null;
            if ($order instanceof WC_Order) {
                $status = isset($payload['status']) && is_string($payload['status']) ? $payload['status'] : '';
                $order->add_order_note(sprintf('MainMoney webhook: %s', $status));
                WC_Mm_Aggr_Order_Sync::apply($order, $payload);
            } else {
                // The webhook beat order creation: keep it for process_payment.
                WC_Mm_Aggr_Order_Sync::stash($reference, $payload);
            }
        }

        return new \WP_REST_Response(['received' => true], 200);
    }

    /**
     * Polled by the thank-you page. Authorised by the order key, so guests can use it.
     */
    public static function orderStatus(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $order = wc_get_order((int) $request['id']);
        $key = (string) $request->get_param('key');
        if (!$order instanceof WC_Order || $order->get_payment_method() !== 'mm_aggr' || $key === '' || !hash_equals($order->get_order_key(), $key)) {
            return new \WP_Error('mm_aggr_order', 'Order not found', ['status' => 404]);
        }
        $outcome = PaymentVerifier::UNKNOWN;
        $gateway = self::gateway();
        if ($gateway !== null && $order->has_status('on-hold')) {
            $outcome = WC_Mm_Aggr_Order_Sync::refresh($order, $gateway);
            $order = wc_get_order($order->get_id());
        }
        if (!$order instanceof WC_Order) {
            return new \WP_Error('mm_aggr_order', 'Order not found', ['status' => 404]);
        }
        $response = new \WP_REST_Response([
            'state' => OrderSyncPolicy::publicState($order->get_status(), $order->is_paid(), $outcome),
            'retry_url' => $order->get_checkout_payment_url(),
        ], 200);
        $response->header('Cache-Control', 'no-store');

        return $response;
    }

    /**
     * Receipt address offered to the customer: the billing email WooCommerce already knows, else the account's.
     */
    private static function customerEmail(): string
    {
        $email = '';
        if (function_exists('WC') && WC()->customer !== null) {
            $email = (string) WC()->customer->get_billing_email();
        }
        if ($email === '' && is_user_logged_in()) {
            $email = (string) wp_get_current_user()->user_email;
        }

        return is_email($email) ? $email : '';
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
