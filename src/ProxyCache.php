<?php

declare(strict_types=1);

namespace MainMoney\WooCommercePlugin;

/**
 * Caches the read-only merchant lookups the checkout widget repeats on every page load.
 *
 * Entries are keyed by merchant credentials plus a version counter: bumping the version (settings saved,
 * "Clear cache" button) orphans every entry at once, and the TTLs renew the rest periodically.
 */
final class ProxyCache
{
    public const DAY = 86400;

    /** Seconds each cacheable route is kept; routes not listed (fees, deposits, status) are never cached. */
    private const TTL = [
        'countries' => self::DAY,
        'providers' => self::DAY,
        'checkout-preferences' => self::DAY,
        'amount-limits' => 21600,
        'match-provider' => 600,
    ];

    public function __construct(
        private readonly CacheStore $store,
        private readonly string $scope,
        private readonly int $version = 1,
    ) {
    }

    /**
     * @param array<string, mixed> $query
     * @param callable(): mixed $loader
     */
    public function remember(string $route, array $query, callable $loader): mixed
    {
        $ttl = self::TTL[$route] ?? null;
        if ($ttl === null || !$this->isCacheable($route, $query)) {
            return $loader();
        }
        $key = $this->key($route, $query);
        $hit = $this->store->get($key);
        if ($hit !== null) {
            return $hit['v'];
        }
        $value = $loader();
        $this->store->set($key, ['v' => $value], $ttl);

        return $value;
    }

    /**
     * A match-provider answer with `get_lookup` carries the account holder's name: never keep that.
     *
     * @param array<string, mixed> $query
     */
    private function isCacheable(string $route, array $query): bool
    {
        if ($route !== 'match-provider') {
            return true;
        }
        $lookup = $query['get_lookup'] ?? false;

        return !($lookup === true || $lookup === 'true' || $lookup === '1' || $lookup === 1);
    }

    /**
     * @param array<string, mixed> $query
     */
    private function key(string $route, array $query): string
    {
        ksort($query);

        return 'mm_pc_'.substr(hash('sha256', $this->scope.'|'.$this->version.'|'.$route.'|'.json_encode($query)), 0, 40);
    }
}
