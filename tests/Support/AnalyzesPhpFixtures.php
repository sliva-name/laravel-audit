<?php

declare(strict_types=1);

namespace LaravelAudit\Tests\Support;

use LaravelAudit\Analysis\AnalysisContext;
use LaravelAudit\Analysis\Issue;
use LaravelAudit\Project\PhpFile;
use LaravelAudit\Project\ProjectIndex;
use PhpParser\ParserFactory;

trait AnalyzesPhpFixtures
{
    /**
     * @param  array<string, mixed>  $config
     */
    protected function analysisContext(string $contents, string $relativePath, array $config = []): AnalysisContext
    {
        return new AnalysisContext(
            basePath: __DIR__,
            project: new ProjectIndex([
                $this->phpFixture($contents, $relativePath),
            ], []),
            config: $config,
        );
    }

    protected function phpFixture(string $contents, string $relativePath): PhpFile
    {
        $ast = (new ParserFactory)->createForNewestSupportedVersion()->parse($contents) ?? [];

        return new PhpFile(
            path: __DIR__.'/Fixture.php',
            relativePath: $relativePath,
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
    protected static function assertIssueRule(string $ruleId, array $issues): void
    {
        self::assertContains($ruleId, array_map(
            fn (Issue $issue): string => $issue->ruleId,
            $issues,
        ));
    }

    /**
     * @param  list<Issue>  $issues
     */
    protected static function assertNoIssues(array $issues): void
    {
        self::assertSame([], $issues);
    }
}
