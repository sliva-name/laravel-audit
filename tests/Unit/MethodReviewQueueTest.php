<?php

declare(strict_types=1);

namespace LaravelAudit\Tests\Unit;

use LaravelAudit\Pattern\MethodFeatureExtractor;
use LaravelAudit\Pattern\MethodReviewQueue;
use LaravelAudit\Pattern\MethodSnippetExtractor;
use LaravelAudit\Project\PhpFile;
use LaravelAudit\Project\ProjectIndex;
use LaravelAudit\Tests\TestCase;
use PhpParser\ParserFactory;

final class MethodReviewQueueTest extends TestCase
{
    public function test_queues_complex_methods_without_using_pattern_labels(): void
    {
        $project = new ProjectIndex([
            $this->phpFile(<<<'PHP'
                <?php

                final class Example
                {
                    public function handle(string $type): void
                    {
                        switch ($type) {
                            case 'a':
                                echo 1;
                                break;
                            case 'b':
                                echo 2;
                                break;
                            case 'c':
                                echo 3;
                                break;
                        }
                    }
                }
                PHP),
        ], []);

        $candidates = (new MethodReviewQueue(
            new MethodFeatureExtractor,
            new MethodSnippetExtractor,
        ))->candidates($project, [], 5);

        self::assertCount(1, $candidates);
        self::assertSame('handle', $candidates[0]->method);
        self::assertStringContainsString('switch ($type)', $candidates[0]->snippet);
    }

    private function phpFile(string $contents): PhpFile
    {
        $ast = (new ParserFactory)->createForNewestSupportedVersion()->parse($contents) ?? [];

        return new PhpFile(
            path: __DIR__.'/Fixture.php',
            relativePath: 'app/Example.php',
            contents: $contents,
            ast: $ast,
            classes: [],
            methods: [],
            lines: substr_count($contents, PHP_EOL) + 1,
        );
    }
}
