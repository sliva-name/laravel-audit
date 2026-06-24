<?php

declare(strict_types=1);

namespace LaravelAudit\Analyzers\CodeQuality;

use LaravelAudit\Analysis\AnalysisContext;
use LaravelAudit\Analysis\AnalyzerInterface;
use LaravelAudit\Analysis\Category;
use LaravelAudit\Analysis\Issue;
use LaravelAudit\Analysis\Severity;
use LaravelAudit\Analyzers\BaseAnalyzer;
use PhpParser\Node;
use PhpParser\NodeFinder;

final class RedundantCatchRethrowAnalyzer extends BaseAnalyzer implements AnalyzerInterface
{
    public function id(): string
    {
        return 'code-quality.redundant-catch-rethrow';
    }

    public function category(): Category
    {
        return Category::CodeQuality;
    }

    /**
     * @return list<Issue>
     */
    public function analyze(AnalysisContext $context): array
    {
        $issues = [];
        $finder = new NodeFinder;

        foreach ($context->project->phpFiles as $file) {
            /** @var list<Node\Stmt\TryCatch> $tryCatches */
            $tryCatches = $finder->findInstanceOf($file->ast, Node\Stmt\TryCatch::class);

            foreach ($tryCatches as $tryCatch) {
                foreach ($tryCatch->catches as $catch) {
                    if (! $this->catchOnlyRethrowsSameVariable($catch)) {
                        continue;
                    }

                    $issues[] = $this->issue(
                        $this->id(),
                        $this->category(),
                        Severity::Info,
                        'Redundant catch and rethrow',
                        'Catch block only rethrows the same exception without adding context, logging, mapping, or cleanup.',
                        $file,
                        $catch->getStartLine(),
                        'Remove the catch block unless it adds useful behavior before rethrowing.',
                    );
                }
            }
        }

        return $issues;
    }

    private function catchOnlyRethrowsSameVariable(Node\Stmt\Catch_ $catch): bool
    {
        if (! $catch->var instanceof Node\Expr\Variable || ! is_string($catch->var->name)) {
            return false;
        }

        if (count($catch->stmts) !== 1 || ! $catch->stmts[0] instanceof Node\Stmt\Expression) {
            return false;
        }

        $throw = $catch->stmts[0]->expr;

        if (! $throw instanceof Node\Expr\Throw_) {
            return false;
        }

        return $throw->expr instanceof Node\Expr\Variable && $throw->expr->name === $catch->var->name;
    }
}
