<?php

declare(strict_types=1);

namespace LaravelAudit\Tests\Unit;

use LaravelAudit\Runners\PintRunner;
use LaravelAudit\Tests\TestCase;

final class PintRunnerTest extends TestCase
{
    public function test_reports_issue_when_pint_binary_is_missing(): void
    {
        $basePath = sys_get_temp_dir().'/laravel-audit-pint-missing-'.bin2hex(random_bytes(6));
        mkdir($basePath, 0777, true);

        $result = (new PintRunner)->run($basePath, [
            'binary' => 'vendor/bin/pint',
        ]);

        self::assertFalse($result->available);
        self::assertCount(1, $result->issues);
        self::assertSame('tooling.pint.runner', $result->issues[0]->ruleId);
    }
}
