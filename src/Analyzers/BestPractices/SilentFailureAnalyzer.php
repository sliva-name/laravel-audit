<?php

declare(strict_types=1);

namespace LaravelAudit\Analyzers\BestPractices;

use LaravelAudit\Analysis\AnalysisContext;
use LaravelAudit\Analysis\AnalyzerInterface;
use LaravelAudit\Analysis\Category;
use LaravelAudit\Analysis\Issue;
use LaravelAudit\Analysis\Severity;
use LaravelAudit\Analyzers\BaseAnalyzer;
use PhpParser\Node;
use PhpParser\NodeFinder;

final class SilentFailureAnalyzer extends BaseAnalyzer implements AnalyzerInterface
{
    public function id(): string
    {
        return 'best-practices.silent-failure';
    }

    public function category(): Category
    {
        return Category::BestPractices;
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
                    if (! $this->catchIsEmpty($catch)) {
                        continue;
                    }

                    $issues[] = $this->issue(
                        $this->id(),
                        $this->category(),
                        Severity::Warning,
                        'Silent failure in catch block',
                        'Catch block swallows exceptions without logging, mapping, rethrowing, or recovery.',
                        $file,
                        $catch->getStartLine(),
                        'Log the exception, rethrow a domain-specific error, or handle the failure explicitly.',
                    );
                }
            }
        }

        return $issues;
    }

    private function catchIsEmpty(Node\Stmt\Catch_ $catch): bool
    {
        return $catch->stmts === [];
    }
}
