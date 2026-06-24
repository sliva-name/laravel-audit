<?php

declare(strict_types=1);

namespace LaravelAudit\Tests\Unit;

use LaravelAudit\Analysis\AnalysisContext;
use LaravelAudit\Analysis\AnalyzerRegistry;
use LaravelAudit\Analyzers\Security\RawSqlAnalyzer;
use LaravelAudit\Project\ProjectIndex;
use LaravelAudit\Tests\TestCase;

final class AnalyzerRegistryTest extends TestCase
{
    public function test_filters_disabled_rules(): void
    {
        $registry = new AnalyzerRegistry([
            new RawSqlAnalyzer,
        ]);

        $context = new AnalysisContext(
            basePath: __DIR__,
            project: new ProjectIndex([], []),
            config: ['rules' => ['security.raw-sql' => false]],
        );

        self::assertSame([], $registry->enabledFor($context));
    }
}
