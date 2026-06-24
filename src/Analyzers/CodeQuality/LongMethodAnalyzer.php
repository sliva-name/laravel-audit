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

final class LongMethodAnalyzer extends BaseAnalyzer implements AnalyzerInterface
{
    public function id(): string
    {
        return 'code-quality.long-method';
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
            /** @var list<Node\Stmt\ClassMethod> $methods */
            $methods = $finder->findInstanceOf($file->ast, Node\Stmt\ClassMethod::class);

            foreach ($methods as $method) {
                $length = $method->getEndLine() - $method->getStartLine() + 1;

                if ($length < 60) {
                    continue;
                }

                $issues[] = $this->issue(
                    $this->id(),
                    $this->category(),
                    Severity::Warning,
                    'Method is long',
                    "Method {$method->name->toString()} has {$length} lines.",
                    $file,
                    $method->getStartLine(),
                    'Extract cohesive private methods, actions, query objects, or domain services when the method has multiple responsibilities.',
                );
            }
        }

        return $issues;
    }
}
