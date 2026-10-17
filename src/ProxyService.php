<?php

declare(strict_types=1);

namespace MainMoney\WooCommercePlugin;

use MainMoney\Aggregator\Client;
use MainMoney\Aggregator\Exception\ApiException;
use MainMoney\Aggregator\Exception\AggregatorException;

final class ProxyService
{
    public function __construct(private readonly Client $client, private readonly ?ProxyCache $cache = null)
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
            return $this->cached($route, $query, fn (): array => $this->asArray($this->client->countries->list()));
        }
        if ($method === 'GET' && $route === 'providers') {
            return $this->cached($route, $query, fn (): array => $this->asArray($this->client->providers->list($this->scalarQuery($query))));
        }
        if ($method === 'GET' && $route === 'match-provider') {
            $account = $this->stringQuery($query, 'account_number');
            $lookup = $this->boolQuery($query, 'get_lookup');

            return $this->cached($route, $query, fn (): array => $this->asArray($this->client->customers->matchProvider($account, $lookup)));
        }
        if ($method === 'GET' && $route === 'amount-limits') {
            return $this->cached($route, $query, fn (): array => $this->asArray($this->client->amountLimits->list($this->scalarQuery($query))));
        }
        if ($method === 'POST' && $route === 'fees/simulate') {
            return $this->asArray($this->client->fees->simulate($this->pinnedCurrency($this->withoutBrowserFeeFields($body), $session)));
        }
        if ($method === 'GET' && $route === 'checkout-preferences') {
            return $this->cached($route, $query, fn (): array => $this->asArray($this->client->checkoutPreferences->get()));
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
     * @param array<string, mixed> $query
     * @param callable(): (array<string, mixed>|list<mixed>) $loader
     *
     * @return array<string, mixed>|list<mixed>
     */
    private function cached(string $route, array $query, callable $loader): array
    {
        if ($this->cache === null) {
            return $loader();
        }
        $payload = $this->cache->remember($route, $query, $loader);

        return is_array($payload) ? $payload : $loader();
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>|list<mixed>
     */
    private function createDeposit(array $body, CheckoutSession $session): array
    {
        $body = $this->withoutBrowserFeeFields($body);
        if ($session->lockAmount && $session->amount !== null) {
            $body['amount'] = $session->amount;
        }
        if ($session->currency !== null) {
            // The order currency is the cart's: the browser may only choose how the customer pays (payment_currency).
            $body['currency'] = $session->currency;
        }
        $body['reference'] = $session->reference;

        return $this->client->deposits->create($body, self::idempotencyKey($session));
    }

    /**
     * One key per checkout session (and so per page load or retry): a refreshed page or a retry starts a
     * new session, and within a session the same body replays the first answer instead of paying twice.
     */
    public static function idempotencyKey(CheckoutSession $session): string
    {
        return 'mm-'.substr(hash('sha256', $session->token), 0, 32);
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    private function pinnedCurrency(array $body, CheckoutSession $session): array
    {
        if ($session->currency !== null) {
            $body['currency'] = $session->currency;
        }

        return $body;
    }

    /**
     * Fees are merchant-controlled: the browser may not choose the merchant fee or who bears the fees.
     *
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    private function withoutBrowserFeeFields(array $body): array
    {
        unset($body['fees'], $body['fee_bearer']);

        return $body;
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
