<?php

declare(strict_types=1);

namespace LaravelAudit\Tests\Unit;

use LaravelAudit\Pattern\JsonHttpClient;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class JsonHttpClientTest extends TestCase
{
    public function test_rejects_non_success_status_codes(): void
    {
        $client = new JsonHttpClient;
        $method = new ReflectionMethod(JsonHttpClient::class, 'responseSuccessful');
        $method->setAccessible(true);

        self::assertFalse($method->invoke($client, ['HTTP/1.1 500 Internal Server Error']));
        self::assertFalse($method->invoke($client, ['HTTP/1.1 401 Unauthorized']));
        self::assertTrue($method->invoke($client, ['HTTP/1.1 200 OK']));
    }
}
