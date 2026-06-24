<?php

declare(strict_types=1);

namespace LaravelAudit\Tests\Unit;

use LaravelAudit\Analysis\AnalysisContext;
use LaravelAudit\Analyzers\CodeQuality\RedundantTypeGuardAnalyzer;
use LaravelAudit\Project\PhpFile;
use LaravelAudit\Project\ProjectIndex;
use LaravelAudit\Tests\TestCase;
use PhpParser\ParserFactory;

final class RedundantTypeGuardAnalyzerTest extends TestCase
{
    public function test_detects_redundant_array_guard_after_array_fallback(): void
    {
        $context = new AnalysisContext(
            basePath: __DIR__,
            project: new ProjectIndex([
                $this->phpFile(<<<'PHP'
                    <?php

                    final class Context
                    {
                        public function ruleEnabled(string $ruleId): bool
                        {
                            $rules = $this->config['rules'] ?? [];

                            if (is_array($rules) && array_key_exists($ruleId, $rules)) {
                                return (bool) $rules[$ruleId];
                            }

                            return true;
                        }
                    }
                    PHP),
            ], []),
            config: [],
        );

        $issues = (new RedundantTypeGuardAnalyzer)->analyze($context);

        self::assertCount(1, $issues);
        self::assertSame('code-quality.redundant-type-guard', $issues[0]->ruleId);
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
}
