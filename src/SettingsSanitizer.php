<?php

declare(strict_types=1);

namespace MainMoney\WooCommercePlugin;

/**
 * Turns the raw admin form post into the option array stored by the gateway.
 * Pure (no WordPress calls) so it can be unit tested.
 */
final class SettingsSanitizer
{
    private const SECRET_FIELDS = ['secret', 'webhook_secret'];

    /**
     * @param array<string, mixed> $input    raw (unslashed) form values
     * @param array<string, mixed> $existing currently stored option
     *
     * @return array<string, string>
     */
    public static function sanitize(array $input, array $existing): array
    {
        $out = [];
        foreach (['title', 'client_id', 'base_uri'] as $field) {
            $out[$field] = self::text($input[$field] ?? '');
        }
        if ($out['title'] === '') {
            $out['title'] = 'MainMoney';
        }
        $out['base_uri'] = filter_var($out['base_uri'], FILTER_VALIDATE_URL) !== false ? $out['base_uri'] : '';
        foreach (['enabled', 'test'] as $field) {
            $out[$field] = !empty($input[$field]) ? 'yes' : 'no';
        }
        foreach (self::SECRET_FIELDS as $field) {
            $posted = self::text($input[$field] ?? '');
            $current = $existing[$field] ?? '';
            $out[$field] = $posted !== '' ? $posted : (is_string($current) ? $current : '');
        }

        return $out;
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim(strip_tags((string) $value)) : '';
    }
}
