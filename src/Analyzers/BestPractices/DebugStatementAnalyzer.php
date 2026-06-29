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

final class DebugStatementAnalyzer extends BaseAnalyzer implements AnalyzerInterface
{
    /**
     * @var list<string>
     */
    private const DEBUG_FUNCTIONS = ['dd', 'dump', 'ray', 'var_dump', 'print_r'];

    public function id(): string
    {
        return 'best-practices.debug-statement';
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
            if ($file->inDirectory('tests')) {
                continue;
            }

            foreach ($finder->findInstanceOf($file->ast, Node\Expr\FuncCall::class) as $call) {
                if (! $call->name instanceof Node\Name) {
                    continue;
                }

                $function = strtolower($call->name->toString());

                if (! in_array($function, self::DEBUG_FUNCTIONS, true)) {
                    continue;
                }

                $issues[] = $this->issue(
                    $this->id(),
                    $this->category(),
                    Severity::Error,
                    'Debug statement left in application code',
                    "Call to {$function}() should not ship in production paths.",
                    $file,
                    $call->getStartLine(),
                    'Remove debugging helpers or guard them behind local-only tooling before deployment.',
                );
            }
        }

        return $issues;
    }
}
