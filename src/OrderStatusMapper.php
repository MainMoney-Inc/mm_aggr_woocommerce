<?php

declare(strict_types=1);

namespace MainMoney\WooCommercePlugin;

final class OrderStatusMapper
{
    public static function isPaid(string $status): bool
    {
        return strtoupper($status) === 'SUCCESS';
    }

    public static function isFailed(string $status): bool
    {
        $normalized = strtoupper($status);

        return in_array($normalized, ['FAILED', 'CANCELLED', 'CANCELED', 'EXPIRED'], true);
    }

    public static function wcStatus(string $status): string
    {
        if (self::isPaid($status)) {
            return 'processing';
        }
        if (self::isFailed($status)) {
            return 'failed';
        }

        return 'on-hold';
    }
}
