<?php

declare(strict_types=1);

namespace MainMoney\WooCommercePlugin;

use MainMoney\Aggregator\Client;

final class AdminApiHelper
{
    /**
     * @param array<mixed> $payload
     *
     * @return array{count: int, results: list<array<string, mixed>>}
     */
    public static function normalizeList(array $payload): array
    {
        if (isset($payload['results']) && is_array($payload['results'])) {
            return [
                'count' => (int) ($payload['count'] ?? count($payload['results'])),
                'results' => array_values($payload['results']),
            ];
        }
        if ($payload !== [] && array_is_list($payload)) {
            return ['count' => count($payload), 'results' => $payload];
        }

        return ['count' => 0, 'results' => []];
    }

    /**
     * @param array<mixed> $options the saved woocommerce_mm_aggr_settings option
     */
    public static function clientFromOptions(array $options): ?Client
    {
        $settings = new Settings(
            clientId: (string) ($options['client_id'] ?? ''),
            secret: (string) ($options['secret'] ?? ''),
            test: ($options['test'] ?? 'no') === 'yes',
            baseUri: ($options['base_uri'] ?? '') !== '' ? (string) $options['base_uri'] : null,
            webhookSecret: (string) ($options['webhook_secret'] ?? ''),
        );

        return $settings->isConfigured() ? ClientFactory::fromSettings($settings) : null;
    }

    /**
     * Runs an SDK call; failures are logged and a generic message is returned (never the raw exception).
     *
     * @return array{ok: true, data: array<string, mixed>}|array{ok: false, message: string}
     */
    public static function safeCall(callable $callback, ?callable $onError = null): array
    {
        try {
            $data = $callback();
            if (!is_array($data)) {
                return ['ok' => false, 'message' => 'Unexpected aggregator response'];
            }

            return ['ok' => true, 'data' => $data];
        } catch (\Throwable $exception) {
            if ($onError !== null) {
                $onError($exception);
            }

            return ['ok' => false, 'message' => 'The MainMoney API request failed. Check the connection settings and the mm-aggr log.'];
        }
    }
}
