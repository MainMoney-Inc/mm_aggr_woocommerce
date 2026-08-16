<?php

declare(strict_types=1);

namespace MainMoney\WooCommercePlugin;

use MainMoney\Aggregator\Client;

final class RefundService
{
    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>|list<mixed>
     */
    public function create(Client $client, array $payload, string $idempotencyKey): array
    {
        return $client->refunds->create($payload, $idempotencyKey);
    }
}
