<?php

declare(strict_types=1);

namespace LaravelAudit\Pattern;

use LaravelAudit\Project\PhpFile;
use PhpParser\Node;
use PhpParser\NodeFinder;

final class MethodSnippetExtractor
{
    public function extract(PhpFile $file, string $methodName, int $lineHint): ?string
    {
        $finder = new NodeFinder;

        /** @var list<Node\Stmt\ClassMethod> $methods */
        $methods = $finder->findInstanceOf($file->ast, Node\Stmt\ClassMethod::class);

        foreach ($methods as $method) {
            if ($method->name->toString() !== $methodName) {
                continue;
            }

            if ($lineHint > 0 && abs($method->getStartLine() - $lineHint) > 5) {
                continue;
            }

            return $this->sliceLines($file->contents, $method->getStartLine(), $method->getEndLine());
        }

        return null;
    }

    private function sliceLines(string $contents, int $startLine, int $endLine): string
    {
        $lines = preg_split('/\R/', $contents) ?: [];

        return trim(implode("\n", array_slice($lines, $startLine - 1, $endLine - $startLine + 1)));
    }
}
