<?php

declare(strict_types=1);

use MainMoney\WooCommercePlugin\Settings;

final class WC_Mm_Aggr_Settings
{
    public static function fromGateway(WC_Payment_Gateway $gateway): Settings
    {
        $baseUri = trim((string) $gateway->get_option('base_uri', ''));

        return new Settings(
            clientId: (string) $gateway->get_option('client_id', ''),
            secret: (string) $gateway->get_option('secret', ''),
            test: $gateway->get_option('test', 'no') === 'yes',
            baseUri: $baseUri !== '' ? $baseUri : null,
            webhookSecret: (string) $gateway->get_option('webhook_secret', ''),
        );
    }
}
