<?php

declare(strict_types=1);

namespace MainMoney\WooCommercePlugin;

/**
 * Decides what a deposit status payload means for a WooCommerce order.
 * Pure (no WordPress calls) so it can be unit tested.
 */
final class PaymentVerifier
{
    public const PAID = 'paid';
    public const PENDING = 'pending';
    public const FAILED = 'failed';
    public const MISMATCH = 'mismatch';
    public const UNKNOWN = 'unknown';

    /**
     * @param array<string, mixed> $payload status/webhook payload (status, amount, currency)
     */
    public static function evaluate(array $payload, string $orderTotal, string $orderCurrency): string
    {
        $status = isset($payload['status']) && is_string($payload['status']) ? $payload['status'] : '';
        if ($status === '') {
            return self::UNKNOWN;
        }
        if (OrderStatusMapper::isFailed($status)) {
            return self::FAILED;
        }
        if (!OrderStatusMapper::isPaid($status)) {
            return self::PENDING;
        }
        if (!self::matches($payload, $orderTotal, $orderCurrency)) {
            return self::MISMATCH;
        }

        return self::PAID;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function matches(array $payload, string $orderTotal, string $orderCurrency): bool
    {
        $amount = $payload['amount'] ?? null;
        if (!is_numeric($amount) || !is_numeric($orderTotal)) {
            return false;
        }
        if (abs((float) $amount - (float) $orderTotal) >= 0.005) {
            return false;
        }
        $currency = $payload['currency'] ?? null;
        if (is_string($currency) && $currency !== '' && strtoupper($currency) !== strtoupper($orderCurrency)) {
            return false;
        }

        return true;
    }
}
