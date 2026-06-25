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

final class LogicInRoutesAnalyzer extends BaseAnalyzer implements AnalyzerInterface
{
    private const MIN_CLOSURE_LINES = 15;

    public function id(): string
    {
        return 'best-practices.logic-in-routes';
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
            if (! $file->inDirectory('routes')) {
                continue;
            }

            /** @var list<Node\Expr\Closure> $closures */
            $closures = $finder->findInstanceOf($file->ast, Node\Expr\Closure::class);

            foreach ($closures as $closure) {
                $length = $closure->getEndLine() - $closure->getStartLine() + 1;

                if ($length < self::MIN_CLOSURE_LINES) {
                    continue;
                }

                $issues[] = $this->issue(
                    $this->id(),
                    $this->category(),
                    Severity::Warning,
                    'Business logic in route closure',
                    "Route closure spans {$length} lines and likely mixes routing with application logic.",
                    $file,
                    $closure->getStartLine(),
                    'Move the workflow into a controller action or invokable class to preserve MVC separation.',
                );
            }
        }

        return $issues;
    }
}
