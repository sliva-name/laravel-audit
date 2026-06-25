<?php

declare(strict_types=1);

namespace LaravelAudit\Tests\Unit;

use LaravelAudit\Pattern\MethodSnippetExtractor;
use LaravelAudit\Project\PhpFile;
use LaravelAudit\Tests\TestCase;
use PhpParser\ParserFactory;

final class MethodSnippetExtractorTest extends TestCase
{
    public function test_extracts_method_source_by_name(): void
    {
        $contents = <<<'PHP'
            <?php

            final class Example
            {
                public function handle(): void
                {
                    echo 'hello';
                }
            }
            PHP;

        $file = $this->phpFile($contents);
        $snippet = (new MethodSnippetExtractor)->extract($file, 'handle', 5);

        self::assertNotNull($snippet);
        self::assertStringContainsString("echo 'hello';", $snippet);
        self::assertStringContainsString('public function handle(): void', $snippet);
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
