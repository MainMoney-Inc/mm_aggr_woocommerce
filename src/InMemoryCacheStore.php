<?php

declare(strict_types=1);

namespace MainMoney\WooCommercePlugin;

/**
 * Process-local store used by the tests.
 */
final class InMemoryCacheStore implements CacheStore
{
    /** @var array<string, array{entry: array{v: mixed}, expires: int}> */
    private array $items = [];

    public function get(string $key): ?array
    {
        $item = $this->items[$key] ?? null;
        if ($item === null || $item['expires'] <= time()) {
            unset($this->items[$key]);

            return null;
        }

        return $item['entry'];
    }

    public function set(string $key, array $entry, int $ttlSeconds): void
    {
        $this->items[$key] = ['entry' => $entry, 'expires' => time() + $ttlSeconds];
    }
}
