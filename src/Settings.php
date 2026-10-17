<?php

declare(strict_types=1);

namespace MainMoney\WooCommercePlugin;

final class Settings
{
    public function __construct(
        public readonly string $clientId,
        public readonly string $secret,
        public readonly bool $test,
        public readonly ?string $baseUri,
        public readonly string $webhookSecret,
    ) {
    }

    /** Identifies the merchant account behind the credentials; cache entries are never shared across accounts. */
    public function cacheScope(): string
    {
        return hash('sha256', $this->clientId.'|'.($this->baseUri ?? '').'|'.($this->test ? 'test' : 'live'));
    }

    public function isConfigured(): bool
    {
        return $this->clientId !== '' && $this->secret !== '';
    }
}
