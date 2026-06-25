<?php

declare(strict_types=1);

namespace LaravelAudit\Analyzers\CodeQuality;

use LaravelAudit\Analysis\AnalysisContext;
use LaravelAudit\Analysis\AnalyzerInterface;
use LaravelAudit\Analysis\Category;
use LaravelAudit\Analysis\Issue;
use LaravelAudit\Analysis\NestingDepthCalculator;
use LaravelAudit\Analysis\Severity;
use LaravelAudit\Analyzers\BaseAnalyzer;
use PhpParser\Node;
use PhpParser\NodeFinder;

final class NestingDepthAnalyzer extends BaseAnalyzer implements AnalyzerInterface
{
    private const DEFAULT_THRESHOLD = 4;

    public function __construct(
        private readonly NestingDepthCalculator $nestingDepth = new NestingDepthCalculator,
    ) {}

    public function id(): string
    {
        return 'code-quality.nesting-depth';
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
        $threshold = (int) data_get($context->config, 'thresholds.nesting_depth', self::DEFAULT_THRESHOLD);

        foreach ($context->project->phpFiles as $file) {
            /** @var list<Node\FunctionLike> $functions */
            $functions = $finder->find($file->ast, fn (Node $node): bool => $node instanceof Node\FunctionLike);

            foreach ($functions as $function) {
                if ($function->getStmts() === null) {
                    continue;
                }

                $depth = $this->nestingDepth->maxDepth($function->getStmts());

                if ($depth <= $threshold) {
                    continue;
                }

                $name = $function instanceof Node\Stmt\ClassMethod
                    ? $function->name->toString()
                    : 'function';

                $issues[] = $this->issue(
                    $this->id(),
                    $this->category(),
                    Severity::Warning,
                    'Excessive nesting depth',
                    "Method {$name} reaches a nesting depth of {$depth}, which exceeds the threshold of {$threshold}.",
                    $file,
                    $function->getStartLine(),
                    'Use guard clauses, early returns, or extract methods to flatten nested control flow.',
                );
            }
        }

        return $issues;
    }
}
