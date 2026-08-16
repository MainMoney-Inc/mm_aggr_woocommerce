<?php

declare(strict_types=1);

namespace MainMoney\WooCommercePlugin;

use MainMoney\Aggregator\Client;
use MainMoney\Aggregator\Http\HttpClient;

final class ClientFactory
{
    public static function fromSettings(Settings $settings, ?HttpClient $httpClient = null): Client
    {
        return new Client(
            clientId: $settings->clientId,
            secret: $settings->secret,
            baseUri: $settings->baseUri,
            test: $settings->test,
            httpClient: $httpClient,
        );
    }
}
