<?php

declare(strict_types=1);

namespace MainMoney\WooCommercePlugin\Tests;

use MainMoney\Aggregator\Http\HttpResponse;
use MainMoney\WooCommercePlugin\CheckoutSession;
use MainMoney\WooCommercePlugin\CheckoutConfig;
use MainMoney\WooCommercePlugin\ClientFactory;
use MainMoney\WooCommercePlugin\InMemoryCacheStore;
use MainMoney\WooCommercePlugin\OrderStatusMapper;
use MainMoney\WooCommercePlugin\PaymentVerifier;
use MainMoney\WooCommercePlugin\ProxyCache;
use MainMoney\WooCommercePlugin\ProxyService;
use MainMoney\WooCommercePlugin\RefundService;
use MainMoney\WooCommercePlugin\Settings;
use MainMoney\WooCommercePlugin\SettingsSanitizer;
use PHPUnit\Framework\TestCase;

final class GatewayServicesTest extends TestCase
{
    public function testLockedDepositUsesOrderTotal(): void
    {
        $mock = new MockHttpClient();
        $client = ClientFactory::fromSettings($this->settings(), $mock);
        $mock->enqueue(
            $this->tokenResponse(),
            $this->envelope(['status' => 'PENDING', 'merchant_reference' => 'WC-1']),
        );
        $session = new CheckoutSession('tok', 'WC-1', '40.00', 'USD', true, time() + 60);
        $result = (new ProxyService($client))->handle('POST', 'deposits', [], [
            'amount' => '1.00',
            'currency' => 'USD',
            'provider_code' => 'VODACOM_MPESA_COD',
            'customer_phone' => '243820000000',
        ], $session);
        self::assertSame(200, $result['status']);
        self::assertSame('40.00', $mock->history[1]['options']['json']['amount'] ?? null);
        self::assertSame('WC-1', $mock->history[1]['options']['json']['reference'] ?? null);
    }

    public function testRefundCallsPhpSdk(): void
    {
        $mock = new MockHttpClient();
        $client = ClientFactory::fromSettings($this->settings(), $mock);
        $mock->enqueue($this->tokenResponse(), $this->envelope(['status' => 'PENDING']));
        $payload = [
            'reference' => 'WC-R-1',
            'original_transaction_id' => 'WC-1',
            'amount' => '10.00',
            'currency' => 'USD',
        ];
        (new RefundService())->create($client, $payload, 'WC-R-1');
        self::assertStringContainsString('/transactions/refunds/', $mock->history[1]['uri']);
        self::assertSame('WC-R-1', $mock->history[1]['options']['headers']['Idempotency-Key'] ?? null);
    }

    public function testOrderStatusMapping(): void
    {
        self::assertTrue(OrderStatusMapper::isPaid('SUCCESS'));
        self::assertTrue(OrderStatusMapper::isFailed('EXPIRED'));
        self::assertSame('processing', OrderStatusMapper::wcStatus('SUCCESS'));
        self::assertSame('failed', OrderStatusMapper::wcStatus('FAILED'));
        self::assertSame('on-hold', OrderStatusMapper::wcStatus('PENDING'));
    }

    public function testPaymentVerifier(): void
    {
        $paid = ['status' => 'SUCCESS', 'amount' => '40.00', 'currency' => 'USD'];
        self::assertSame(PaymentVerifier::PAID, PaymentVerifier::evaluate($paid, '40.00', 'USD'));
        self::assertSame(PaymentVerifier::PAID, PaymentVerifier::evaluate($paid, '40', 'usd'));
        self::assertSame(PaymentVerifier::MISMATCH, PaymentVerifier::evaluate($paid, '41.00', 'USD'));
        self::assertSame(PaymentVerifier::MISMATCH, PaymentVerifier::evaluate($paid, '40.00', 'CDF'));
        self::assertSame(PaymentVerifier::MISMATCH, PaymentVerifier::evaluate(['status' => 'SUCCESS'], '40.00', 'USD'));
        self::assertSame(PaymentVerifier::PENDING, PaymentVerifier::evaluate(['status' => 'PENDING'], '40.00', 'USD'));
        self::assertSame(PaymentVerifier::FAILED, PaymentVerifier::evaluate(['status' => 'FAILED'], '40.00', 'USD'));
        self::assertSame(PaymentVerifier::UNKNOWN, PaymentVerifier::evaluate([], '40.00', 'USD'));
    }

    public function testPaymentVerifierComparesTheOrderAmountOfAConvertedDeposit(): void
    {
        $converted = [
            'status' => 'SUCCESS',
            'amount' => '30000.00',
            'currency' => 'CDF',
            'original_amount' => '10.00',
            'original_currency' => 'EUR',
        ];
        self::assertSame(PaymentVerifier::PAID, PaymentVerifier::evaluate($converted, '10.00', 'EUR'));
        self::assertSame(PaymentVerifier::MISMATCH, PaymentVerifier::evaluate($converted, '30000.00', 'CDF'));
    }

    public function testDepositStatusIsCheckedByReference(): void
    {
        $mock = new MockHttpClient();
        $client = ClientFactory::fromSettings($this->settings(), $mock);
        $mock->enqueue($this->tokenResponse(), $this->envelope(['status' => 'SUCCESS', 'amount' => '5.00', 'currency' => 'USD']));
        $session = new CheckoutSession('tok', 'WC-9', '5.00', 'USD', true, time() + 60);
        $result = (new ProxyService($client))->handle('GET', 'status', [], [], $session);
        self::assertSame(200, $result['status']);
        self::assertStringContainsString('/transactions/status/check/deposit/', $mock->history[1]['uri']);
        self::assertSame('WC-9', $mock->history[1]['options']['json']['reference'] ?? null);
    }

    public function testBrowserCannotSetFees(): void
    {
        $mock = new MockHttpClient();
        $client = ClientFactory::fromSettings($this->settings(), $mock);
        $mock->enqueue($this->tokenResponse(), $this->envelope(['status' => 'PENDING']));
        $session = new CheckoutSession('tok', 'WC-2', '10.00', 'USD', true, time() + 60);
        (new ProxyService($client))->handle('POST', 'deposits', [], ['fees' => '0', 'fee_bearer' => 'customer'], $session);
        self::assertArrayNotHasKey('fees', $mock->history[1]['options']['json']);
        self::assertArrayNotHasKey('fee_bearer', $mock->history[1]['options']['json']);
    }

    public function testIdempotencyKeyFollowsTheSessionNotTheReference(): void
    {
        $first = new CheckoutSession('tok-a', 'WC-1', '40.00', 'USD', true, time() + 60);
        $second = new CheckoutSession('tok-b', 'WC-1', '40.00', 'USD', true, time() + 60);
        self::assertSame(ProxyService::idempotencyKey($first), ProxyService::idempotencyKey($first));
        self::assertNotSame(ProxyService::idempotencyKey($first), ProxyService::idempotencyKey($second));
        self::assertNotSame($first->reference, ProxyService::idempotencyKey($first));

        $mock = new MockHttpClient();
        $client = ClientFactory::fromSettings($this->settings(), $mock);
        $mock->enqueue($this->tokenResponse(), $this->envelope(['status' => 'PENDING']));
        (new ProxyService($client))->handle('POST', 'deposits', [], ['customer_phone' => '243820000000'], $first);
        self::assertSame(
            ProxyService::idempotencyKey($first),
            $mock->history[1]['options']['headers']['Idempotency-Key'] ?? null,
        );
    }

    public function testCartCurrencyIsPinnedButPaymentCurrencyPasses(): void
    {
        $mock = new MockHttpClient();
        $client = ClientFactory::fromSettings($this->settings(), $mock);
        $mock->enqueue($this->tokenResponse(), $this->envelope(['status' => 'PENDING']));
        $session = new CheckoutSession('tok', 'WC-3', '40.00', 'EUR', true, time() + 60);
        (new ProxyService($client))->handle('POST', 'deposits', [], ['currency' => 'CDF', 'payment_currency' => 'CDF'], $session);
        self::assertSame('EUR', $mock->history[1]['options']['json']['currency'] ?? null);
        self::assertSame('CDF', $mock->history[1]['options']['json']['payment_currency'] ?? null);
    }

    public function testStaticLookupsAreCachedUntilTheVersionChanges(): void
    {
        $store = new InMemoryCacheStore();
        $mock = new MockHttpClient();
        $client = ClientFactory::fromSettings($this->settings(), $mock);
        $mock->enqueue($this->tokenResponse(), $this->envelope([['code' => 'CD']]), $this->envelope([['code' => 'KE']]));
        $session = new CheckoutSession('tok', 'WC-4', '1.00', 'USD', true, time() + 60);
        $scope = $this->settings()->cacheScope();

        $proxy = new ProxyService($client, new ProxyCache($store, $scope, 1));
        $first = $proxy->handle('GET', 'countries', [], [], $session);
        $again = $proxy->handle('GET', 'countries', [], [], $session);
        self::assertSame($first['body'], $again['body']);
        self::assertCount(2, $mock->history, 'token + one countries call only');

        $bumped = (new ProxyService($client, new ProxyCache($store, $scope, 2)))->handle('GET', 'countries', [], [], $session);
        self::assertSame([['code' => 'KE']], $bumped['body']);
        self::assertCount(3, $mock->history);
    }

    public function testLookupWithAccountHolderNameIsNeverCached(): void
    {
        $store = new InMemoryCacheStore();
        $mock = new MockHttpClient();
        $client = ClientFactory::fromSettings($this->settings(), $mock);
        $mock->enqueue($this->tokenResponse(), $this->envelope(['entity' => 'A']), $this->envelope(['entity' => 'A']));
        $session = new CheckoutSession('tok', 'WC-5', '1.00', 'USD', true, time() + 60);
        $proxy = new ProxyService($client, new ProxyCache($store, 'scope', 1));
        $query = ['account_number' => '243820000000', 'get_lookup' => 'true'];
        $proxy->handle('GET', 'match-provider', $query, [], $session);
        $proxy->handle('GET', 'match-provider', $query, [], $session);
        self::assertCount(3, $mock->history);
    }

    public function testFeeSimulationAndStatusAreNeverCached(): void
    {
        $store = new InMemoryCacheStore();
        $mock = new MockHttpClient();
        $client = ClientFactory::fromSettings($this->settings(), $mock);
        $mock->enqueue($this->tokenResponse(), $this->envelope(['status' => 'PENDING']), $this->envelope(['status' => 'SUCCESS']));
        $session = new CheckoutSession('tok', 'WC-6', '1.00', 'USD', true, time() + 60);
        $proxy = new ProxyService($client, new ProxyCache($store, 'scope', 1));
        $proxy->handle('GET', 'status', [], [], $session);
        $proxy->handle('GET', 'status', [], [], $session);
        self::assertCount(3, $mock->history);
    }

    public function testCheckoutConfigCarriesCartCurrencyAndEmail(): void
    {
        $session = new CheckoutSession('tok', 'WC-7', '40.00', 'EUR', true, time() + 60);
        $config = CheckoutConfig::forSession('https://shop/rest', 'https://shop/status', $session, 'en', 'buyer@example.com');
        self::assertSame('EUR', $config['currency']);
        self::assertSame('buyer@example.com', $config['customerEmail']);
        self::assertSame(
            ['merchantBackendUrl', 'clientToken', 'pollUrl', 'pollHeaders', 'locale', 'amount', 'lockAmount', 'reference', 'currency', 'customerEmail'],
            array_keys($config),
        );
    }

    public function testSettingsSanitizerKeepsBlankSecrets(): void
    {
        $out = SettingsSanitizer::sanitize(
            ['title' => ' <b>Pay</b> ', 'client_id' => 'cid', 'secret' => '', 'webhook_secret' => 'new', 'test' => '1', 'base_uri' => 'nope'],
            ['secret' => 'kept', 'webhook_secret' => 'old'],
        );
        self::assertSame('Pay', $out['title']);
        self::assertSame('kept', $out['secret']);
        self::assertSame('new', $out['webhook_secret']);
        self::assertSame('yes', $out['test']);
        self::assertSame('no', $out['enabled']);
        self::assertSame('', $out['base_uri']);
    }

    private function settings(): Settings
    {
        return new Settings('client-id', 'secret', true, 'https://example.test/api/v1/', 'whsec');
    }

    private function tokenResponse(): HttpResponse
    {
        $expiresAt = (new \DateTimeImmutable('+1 hour'))->format(\DateTimeInterface::ATOM);

        return new HttpResponse(200, json_encode([
            'access_token' => 'tok_1',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'expires_at' => $expiresAt,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string, mixed>|list<mixed> $data
     */
    private function envelope(array $data): HttpResponse
    {
        return new HttpResponse(200, json_encode([
            'success' => true,
            'response_code' => 200,
            'response_data' => $data,
            'message' => 'ok',
        ], JSON_THROW_ON_ERROR));
    }
}
