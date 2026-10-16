<?php

declare(strict_types=1);

namespace MainMoney\WooCommercePlugin\Tests;

use MainMoney\WooCommercePlugin\OrderSyncPolicy;
use MainMoney\WooCommercePlugin\PaymentVerifier;
use PHPUnit\Framework\TestCase;

final class OrderSyncPolicyTest extends TestCase
{
    public function testSuccessCompletesAnOnHoldOrder(): void
    {
        self::assertSame(OrderSyncPolicy::COMPLETE, OrderSyncPolicy::decide('on-hold', false, PaymentVerifier::PAID));
    }

    public function testPendingDoesNothing(): void
    {
        self::assertSame(OrderSyncPolicy::NONE, OrderSyncPolicy::decide('on-hold', false, PaymentVerifier::PENDING));
    }

    public function testFailureFailsAnOnHoldOrder(): void
    {
        self::assertSame(OrderSyncPolicy::FAIL, OrderSyncPolicy::decide('on-hold', false, PaymentVerifier::FAILED));
    }

    public function testMismatchKeepsOrderForReview(): void
    {
        self::assertSame(OrderSyncPolicy::HOLD_MISMATCH, OrderSyncPolicy::decide('on-hold', false, PaymentVerifier::MISMATCH));
    }

    public function testDuplicateWebhookIsIdempotent(): void
    {
        self::assertSame(OrderSyncPolicy::NONE, OrderSyncPolicy::decide('processing', true, PaymentVerifier::PAID));
        self::assertSame(OrderSyncPolicy::NONE, OrderSyncPolicy::decide('on-hold', true, PaymentVerifier::PAID));
    }

    public function testLateFailureNeverRevivesOrRefailsClosedOrders(): void
    {
        self::assertSame(OrderSyncPolicy::NONE, OrderSyncPolicy::decide('processing', true, PaymentVerifier::FAILED));
        self::assertSame(OrderSyncPolicy::NONE, OrderSyncPolicy::decide('cancelled', false, PaymentVerifier::PAID));
    }

    public function testPublicState(): void
    {
        self::assertSame('paid', OrderSyncPolicy::publicState('processing', true, PaymentVerifier::UNKNOWN));
        self::assertSame('failed', OrderSyncPolicy::publicState('failed', false, PaymentVerifier::UNKNOWN));
        self::assertSame('pending', OrderSyncPolicy::publicState('on-hold', false, PaymentVerifier::UNKNOWN));
        self::assertSame('pending', OrderSyncPolicy::publicState('on-hold', false, PaymentVerifier::PENDING));
        self::assertSame('mismatch', OrderSyncPolicy::publicState('on-hold', false, PaymentVerifier::MISMATCH));
    }
}
