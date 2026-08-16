<?php

declare(strict_types=1);

namespace MainMoney\WooCommercePlugin;

use MainMoney\Aggregator\Client;
use MainMoney\Aggregator\Exception\AggregatorException;

final class WebhookService
{
    /**
     * @return array<string, mixed>
     */
    public function verifyAndDecode(Client $client, string $rawBody, string $signature, string $secret): array
    {
        $client->webhooks->verifyOrFail($rawBody, $signature, $secret);
        try {
            $decoded = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new AggregatorException('Webhook body must be JSON', 0, $exception);
        }
        if (!is_array($decoded)) {
            throw new AggregatorException('Webhook body must be a JSON object');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
