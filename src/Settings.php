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

    public function isConfigured(): bool
    {
        return $this->clientId !== '' && $this->secret !== '';
    }
}
