<?php

declare(strict_types=1);

use MainMoney\WooCommercePlugin\CacheStore;

/**
 * WordPress transient backend for the proxy cache, plus the version counter that invalidates it.
 */
final class WC_Mm_Aggr_Cache_Store implements CacheStore
{
    private const VERSION_OPTION = 'wc_mm_aggr_cache_version';

    public function get(string $key): ?array
    {
        $raw = get_transient($key);

        return is_array($raw) && array_key_exists('v', $raw) ? $raw : null;
    }

    public function set(string $key, array $entry, int $ttlSeconds): void
    {
        set_transient($key, $entry, $ttlSeconds);
    }

    public static function version(): int
    {
        return max(1, (int) get_option(self::VERSION_OPTION, 1));
    }

    /** Orphans every cached lookup: call when credentials or merchant configuration change. */
    public static function flush(): void
    {
        update_option(self::VERSION_OPTION, self::version() + 1, false);
    }
}
