<?php

declare(strict_types=1);

namespace LaravelAudit\Tests\Feature;

use LaravelAudit\Tests\TestCase;

final class AnalyzeCommandTest extends TestCase
{
    public function test_analyze_command_runs_without_external_tools(): void
    {
        $this->artisan('audit:analyze', [
            '--no-tools' => true,
            '--format' => 'json',
            '--fail-on' => 'critical',
        ])->assertExitCode(0);
    }
}
