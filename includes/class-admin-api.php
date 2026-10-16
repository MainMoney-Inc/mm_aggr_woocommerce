<?php

declare(strict_types=1);

use MainMoney\Aggregator\Client;
use MainMoney\WooCommercePlugin\AdminApiHelper;

/** WordPress-aware wrapper around AdminApiHelper for the MainMoney admin pages. */
final class WC_Mm_Aggr_Admin_Api
{
    public static function client(): ?Client
    {
        $options = get_option('woocommerce_mm_aggr_settings', []);

        return AdminApiHelper::clientFromOptions(is_array($options) ? $options : []);
    }

    /**
     * @return array{ok: true, data: array<string, mixed>}|array{ok: false, message: string}
     */
    public static function call(callable $callback): array
    {
        return AdminApiHelper::safeCall($callback, static function (\Throwable $exception): void {
            wc_get_logger()->warning('MainMoney admin API call failed: '.$exception->getMessage(), ['source' => 'mm-aggr']);
        });
    }
}
