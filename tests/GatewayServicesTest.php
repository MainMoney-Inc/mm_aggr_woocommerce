<?php

declare(strict_types=1);

namespace MainMoney\WooCommercePlugin\Tests;

use MainMoney\Aggregator\Http\HttpResponse;
use MainMoney\WooCommercePlugin\CheckoutSession;
use MainMoney\WooCommercePlugin\ClientFactory;
use MainMoney\WooCommercePlugin\OrderStatusMapper;
use MainMoney\WooCommercePlugin\ProxyService;
use MainMoney\WooCommercePlugin\RefundService;
use MainMoney\WooCommercePlugin\Settings;
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
