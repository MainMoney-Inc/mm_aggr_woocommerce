<?php

declare(strict_types=1);

namespace MainMoney\WooCommercePlugin;

/**
 * Minimal key/value store behind {@see ProxyCache}; the plugin backs it with WordPress transients.
 */
interface CacheStore
{
    /**
     * @return array{v: mixed}|null the wrapped value, or null on a miss
     */
    public function get(string $key): ?array;

    /**
     * @param array{v: mixed} $entry
     */
    public function set(string $key, array $entry, int $ttlSeconds): void;
}
