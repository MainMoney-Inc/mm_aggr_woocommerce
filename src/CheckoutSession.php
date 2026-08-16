<?php

declare(strict_types=1);

namespace MainMoney\WooCommercePlugin;

final class CheckoutSession
{
    public function __construct(
        public readonly string $token,
        public readonly string $reference,
        public readonly ?string $amount,
        public readonly ?string $currency,
        public readonly bool $lockAmount,
        public readonly int $expiresAt,
    ) {
    }

    public static function create(
        ?string $amount = null,
        ?string $currency = null,
        ?string $reference = null,
        bool $lockAmount = false,
        int $ttlSeconds = 1800,
    ): self {
        $trimmedAmount = $amount !== null ? trim($amount) : '';
        $trimmedCurrency = $currency !== null ? trim($currency) : '';
        $trimmedReference = $reference !== null ? trim($reference) : '';

        return new self(
            token: bin2hex(random_bytes(32)),
            reference: $trimmedReference !== '' ? $trimmedReference : 'WP-'.bin2hex(random_bytes(8)),
            amount: $trimmedAmount !== '' ? $trimmedAmount : null,
            currency: $trimmedCurrency !== '' ? $trimmedCurrency : null,
            lockAmount: $lockAmount,
            expiresAt: time() + $ttlSeconds,
        );
    }

    public function isExpired(int $now = 0): bool
    {
        $timestamp = $now > 0 ? $now : time();

        return $timestamp >= $this->expiresAt;
    }
}
