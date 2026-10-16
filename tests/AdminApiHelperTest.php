<?php

declare(strict_types=1);

namespace MainMoney\WooCommercePlugin\Tests;

use MainMoney\WooCommercePlugin\AdminApiHelper;
use PHPUnit\Framework\TestCase;

final class AdminApiHelperTest extends TestCase
{
    public function testNormalizeListReadsPaginatedPayload(): void
    {
        $list = AdminApiHelper::normalizeList(['count' => 7, 'results' => [['id' => 1]]]);
        self::assertSame(7, $list['count']);
        self::assertSame([['id' => 1]], $list['results']);
    }

    public function testNormalizeListAcceptsBareList(): void
    {
        $list = AdminApiHelper::normalizeList([['id' => 1], ['id' => 2]]);
        self::assertSame(2, $list['count']);
    }

    public function testNormalizeListHandlesEmptyPayload(): void
    {
        self::assertSame(['count' => 0, 'results' => []], AdminApiHelper::normalizeList([]));
    }

    public function testSafeCallDoesNotLeakExceptionMessage(): void
    {
        $result = AdminApiHelper::safeCall(static function (): array {
            throw new \RuntimeException('secret-internal-detail');
        });
        self::assertFalse($result['ok']);
        self::assertStringNotContainsString('secret-internal-detail', $result['message']);
    }
}
