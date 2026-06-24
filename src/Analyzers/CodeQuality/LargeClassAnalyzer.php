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

final class LargeClassAnalyzer extends BaseAnalyzer implements AnalyzerInterface
{
    public function id(): string
    {
        return 'code-quality.large-class';
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
            /** @var list<Node\Stmt\Class_> $classes */
            $classes = $finder->findInstanceOf($file->ast, Node\Stmt\Class_::class);

            foreach ($classes as $class) {
                $length = $class->getEndLine() - $class->getStartLine() + 1;

                if ($length < 300) {
                    continue;
                }

                $issues[] = $this->issue(
                    $this->id(),
                    $this->category(),
                    Severity::Warning,
                    'Class is large',
                    sprintf('Class %s has %d lines.', $class->name?->toString() ?? 'anonymous', $length),
                    $file,
                    $class->getStartLine(),
                    'Split unrelated responsibilities into smaller collaborators that match Laravel boundaries.',
                );
            }
        }

        return $issues;
    }
}
