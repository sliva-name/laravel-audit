<?php

declare(strict_types=1);

namespace LaravelAudit\Tests\Unit\BestPractices;

use LaravelAudit\Analyzers\BestPractices\DebugStatementAnalyzer;
use LaravelAudit\Tests\Support\AnalyzesPhpFixtures;
use LaravelAudit\Tests\TestCase;

final class DebugStatementAnalyzerTest extends TestCase
{
    use AnalyzesPhpFixtures;

    public function test_flags_dd_in_application_code(): void
    {
        $issues = (new DebugStatementAnalyzer)->analyze($this->analysisContext(<<<'PHP'
            <?php

            final class Example
            {
                public function handle(): void
                {
                    dd('debug');
                }
            }
            PHP, 'app/Example.php'));

        self::assertIssueRule('best-practices.debug-statement', $issues);
    }

    public function test_ignores_dd_in_tests(): void
    {
        $issues = (new DebugStatementAnalyzer)->analyze($this->analysisContext(<<<'PHP'
            <?php

            final class ExampleTest
            {
                public function test_debugging(): void
                {
                    dd('debug');
                }
            }
            PHP, 'tests/Feature/ExampleTest.php'));

        self::assertNoIssues($issues);
    }
}
