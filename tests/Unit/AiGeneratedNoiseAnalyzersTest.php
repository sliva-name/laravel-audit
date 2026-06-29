<?php

declare(strict_types=1);

namespace LaravelAudit\Tests\Unit;

use LaravelAudit\Analysis\AnalysisContext;
use LaravelAudit\Analysis\Issue;
use LaravelAudit\Analyzers\CodeQuality\RedundantBooleanReturnAnalyzer;
use LaravelAudit\Analyzers\CodeQuality\RedundantCatchRethrowAnalyzer;
use LaravelAudit\Analyzers\CodeQuality\RedundantElseAfterExitAnalyzer;
use LaravelAudit\Analyzers\CodeQuality\RedundantEmptyForeachGuardAnalyzer;
use LaravelAudit\Analyzers\CodeQuality\RedundantNullCoalesceAnalyzer;
use LaravelAudit\Project\PhpFile;
use LaravelAudit\Project\ProjectIndex;
use LaravelAudit\Tests\TestCase;
use PhpParser\ParserFactory;

final class AiGeneratedNoiseAnalyzersTest extends TestCase
{
    public function test_detects_redundant_boolean_return(): void
    {
        $issues = (new RedundantBooleanReturnAnalyzer)->analyze($this->context(<<<'PHP'
            <?php

            final class Example
            {
                public function allowed(bool $active): bool
                {
                    if ($active) {
                        return true;
                    }

                    return false;
                }
            }
            PHP));

        self::assertRuleFound('code-quality.redundant-boolean-return', $issues);
    }

    public function test_detects_redundant_null_coalesce_for_non_nullable_parameter(): void
    {
        $issues = (new RedundantNullCoalesceAnalyzer)->analyze($this->context(<<<'PHP'
            <?php

            final class Example
            {
                public function handle(array $items): array
                {
                    return $items ?? [];
                }
            }
            PHP));

        self::assertRuleFound('code-quality.redundant-null-coalesce', $issues);
    }

    public function test_detects_redundant_empty_guard_before_foreach(): void
    {
        $issues = (new RedundantEmptyForeachGuardAnalyzer)->analyze($this->context(<<<'PHP'
            <?php

            final class Example
            {
                public function handle(array $items): void
                {
                    if (! empty($items)) {
                        foreach ($items as $item) {
                            echo $item;
                        }
                    }
                }
            }
            PHP));

        self::assertRuleFound('code-quality.redundant-empty-foreach-guard', $issues);
    }

    public function test_detects_redundant_catch_and_rethrow(): void
    {
        $issues = (new RedundantCatchRethrowAnalyzer)->analyze($this->context(<<<'PHP'
            <?php

            final class Example
            {
                public function handle(Service $service): void
                {
                    try {
                        $service->run();
                    } catch (Throwable $exception) {
                        throw $exception;
                    }
                }
            }
            PHP));

        self::assertRuleFound('code-quality.redundant-catch-rethrow', $issues);
    }

    public function test_detects_redundant_else_after_exit(): void
    {
        $issues = (new RedundantElseAfterExitAnalyzer)->analyze($this->context(<<<'PHP'
            <?php

            final class Example
            {
                public function label(bool $failed): string
                {
                    if ($failed) {
                        return 'failed';
                    } else {
                        return 'ok';
                    }
                }
            }
            PHP));

        self::assertRuleFound('code-quality.redundant-else-after-exit', $issues);
    }

    public function test_does_not_flag_direct_boolean_return(): void
    {
        $issues = (new RedundantBooleanReturnAnalyzer)->analyze($this->context(<<<'PHP'
            <?php

            final class Example
            {
                public function allowed(bool $active): bool
                {
                    return $active;
                }
            }
            PHP));

        self::assertNoIssues($issues);
    }

    public function test_does_not_flag_null_coalesce_on_nullable_parameter(): void
    {
        $issues = (new RedundantNullCoalesceAnalyzer)->analyze($this->context(<<<'PHP'
            <?php

            final class Example
            {
                public function handle(?array $items): array
                {
                    return $items ?? [];
                }
            }
            PHP));

        self::assertNoIssues($issues);
    }

    public function test_does_not_flag_foreach_without_redundant_empty_guard(): void
    {
        $issues = (new RedundantEmptyForeachGuardAnalyzer)->analyze($this->context(<<<'PHP'
            <?php

            final class Example
            {
                public function handle(array $items): void
                {
                    foreach ($items as $item) {
                        echo $item;
                    }
                }
            }
            PHP));

        self::assertNoIssues($issues);
    }

    public function test_does_not_flag_catch_that_wraps_exception(): void
    {
        $issues = (new RedundantCatchRethrowAnalyzer)->analyze($this->context(<<<'PHP'
            <?php

            final class Example
            {
                public function handle(Service $service): void
                {
                    try {
                        $service->run();
                    } catch (Throwable $exception) {
                        throw new RuntimeException('Service failed', previous: $exception);
                    }
                }
            }
            PHP));

        self::assertNoIssues($issues);
    }

    public function test_does_not_flag_if_without_redundant_else(): void
    {
        $issues = (new RedundantElseAfterExitAnalyzer)->analyze($this->context(<<<'PHP'
            <?php

            final class Example
            {
                public function label(bool $failed): string
                {
                    if ($failed) {
                        return 'failed';
                    }

                    return 'ok';
                }
            }
            PHP));

        self::assertNoIssues($issues);
    }

    /**
     * @param  list<Issue>  $issues
     */
    private static function assertNoIssues(array $issues): void
    {
        self::assertSame([], $issues);
    }

    private function context(string $contents): AnalysisContext
    {
        return new AnalysisContext(
            basePath: __DIR__,
            project: new ProjectIndex([
                $this->phpFile($contents),
            ], []),
            config: [],
        );
    }

    private function phpFile(string $contents): PhpFile
    {
        $ast = (new ParserFactory)->createForNewestSupportedVersion()->parse($contents) ?? [];

        return new PhpFile(
            path: __DIR__.'/Fixture.php',
            relativePath: 'app/Fixture.php',
            contents: $contents,
            ast: $ast,
            classes: [],
            methods: [],
            lines: substr_count($contents, PHP_EOL) + 1,
        );
    }

    /**
     * @param  list<Issue>  $issues
     */
    private static function assertRuleFound(string $ruleId, array $issues): void
    {
        self::assertContains($ruleId, array_map(
            fn ($issue): string => $issue->ruleId,
            $issues,
        ));
    }
}
