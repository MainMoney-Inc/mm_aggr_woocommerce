<?php

declare(strict_types=1);

namespace MainMoney\WooCommercePlugin;

use MainMoney\Aggregator\Client;
use MainMoney\Aggregator\Exception\ApiException;
use MainMoney\Aggregator\Exception\AggregatorException;

final class ProxyService
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     *
     * @return array{status: int, body: mixed}
     */
    public function handle(string $method, string $route, array $query, array $body, CheckoutSession $session): array
    {
        try {
            $payload = $this->dispatch(strtoupper($method), trim($route, '/'), $query, $body, $session);

            return ['status' => 200, 'body' => $payload];
        } catch (ApiException $exception) {
            $status = $exception->getStatusCode() >= 400 ? $exception->getStatusCode() : 400;

            return [
                'status' => $status,
                'body' => [
                    'message' => $exception->getMessage(),
                    'errors' => $exception->getErrors(),
                ],
            ];
        } catch (AggregatorException $exception) {
            return [
                'status' => 400,
                'body' => ['message' => $exception->getMessage()],
            ];
        }
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>|list<mixed>
     */
    private function dispatch(string $method, string $route, array $query, array $body, CheckoutSession $session): array
    {
        if ($method === 'GET' && $route === 'countries') {
            return $this->asArray($this->client->countries->list());
        }
        if ($method === 'GET' && $route === 'providers') {
            return $this->asArray($this->client->providers->list($this->scalarQuery($query)));
        }
        if ($method === 'GET' && $route === 'match-provider') {
            $account = $this->stringQuery($query, 'account_number');
            $lookup = $this->boolQuery($query, 'get_lookup');

            return $this->asArray($this->client->customers->matchProvider($account, $lookup));
        }
        if ($method === 'GET' && $route === 'amount-limits') {
            return $this->asArray($this->client->amountLimits->list($this->scalarQuery($query)));
        }
        if ($method === 'POST' && $route === 'fees/simulate') {
            return $this->asArray($this->client->fees->simulate($body));
        }
        if ($method === 'GET' && $route === 'checkout-preferences') {
            return $this->asArray($this->client->checkoutPreferences->get());
        }
        if ($method === 'POST' && $route === 'deposits') {
            return $this->asArray($this->createDeposit($body, $session));
        }
        if ($method === 'GET' && $route === 'status') {
            $reference = $this->stringQuery($query, 'reference');
            if ($reference === '') {
                $reference = $session->reference;
            }
            $operation = $this->stringQuery($query, 'operation');
            if ($operation === '') {
                $operation = 'deposit';
            }

            return $this->asArray($this->client->status->check($operation, $reference));
        }

        throw new AggregatorException('Unknown merchant backend path');
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>|list<mixed>
     */
    private function createDeposit(array $body, CheckoutSession $session): array
    {
        if ($session->lockAmount && $session->amount !== null) {
            $body['amount'] = $session->amount;
        }
        $body['reference'] = $session->reference;

        return $this->client->deposits->create($body, $session->reference);
    }

    /**
     * @param array<string, mixed>|list<mixed> $payload
     *
     * @return array<string, mixed>|list<mixed>
     */
    private function asArray(array $payload): array
    {
        return $payload;
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array<string, scalar|null>
     */
    private function scalarQuery(array $query): array
    {
        $filtered = [];
        foreach ($query as $name => $value) {
            if (is_scalar($value) || $value === null) {
                $filtered[$name] = $value;
            }
        }

        return $filtered;
    }

    /**
     * @param array<string, mixed> $query
     */
    private function stringQuery(array $query, string $name): string
    {
        $value = $query[$name] ?? '';

        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * @param array<string, mixed> $query
     */
    private function boolQuery(array $query, string $name): bool
    {
        $value = $query[$name] ?? false;

        return $value === true || $value === 'true' || $value === '1' || $value === 1;
    }
}
