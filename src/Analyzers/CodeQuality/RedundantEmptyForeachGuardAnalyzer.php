<?php

declare(strict_types=1);

namespace LaravelAudit\Analyzers\CodeQuality;

use LaravelAudit\Analysis\AnalysisContext;
use LaravelAudit\Analysis\AnalyzerInterface;
use LaravelAudit\Analysis\Category;
use LaravelAudit\Analysis\Issue;
use LaravelAudit\Analysis\Severity;
use LaravelAudit\Analyzers\BaseAnalyzer;
use LaravelAudit\Project\PhpFile;
use PhpParser\Node;

final class RedundantEmptyForeachGuardAnalyzer extends BaseAnalyzer implements AnalyzerInterface
{
    public function id(): string
    {
        return 'code-quality.redundant-empty-foreach-guard';
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

        foreach ($context->project->phpFiles as $file) {
            $issues = [
                ...$issues,
                ...$this->analyzeStatements($file, $file->ast),
            ];
        }

        return $issues;
    }

    /**
     * @param  list<Node>  $statements
     * @return list<Issue>
     */
    private function analyzeStatements(PhpFile $file, array $statements): array
    {
        $issues = [];

        foreach ($statements as $statement) {
            if ($statement instanceof Node\Stmt\If_) {
                $guardedVariable = $this->notEmptyVariable($statement->cond);
                $foreachVariable = $this->singleForeachVariable($statement->stmts);

                if ($guardedVariable !== null && $guardedVariable === $foreachVariable) {
                    $issues[] = $this->issue(
                        $this->id(),
                        $this->category(),
                        Severity::Info,
                        'Redundant empty guard before foreach',
                        "Variable \${$guardedVariable} is checked with ! empty() before a foreach over the same variable.",
                        $file,
                        $statement->getStartLine(),
                        'Remove the guard when the loop body can simply run zero times for an empty array or iterable.',
                    );
                }

                $issues = [
                    ...$issues,
                    ...$this->analyzeStatements($file, $statement->stmts),
                ];

                foreach ($statement->elseifs as $elseif) {
                    $issues = [
                        ...$issues,
                        ...$this->analyzeStatements($file, $elseif->stmts),
                    ];
                }

                if ($statement->else !== null) {
                    $issues = [
                        ...$issues,
                        ...$this->analyzeStatements($file, $statement->else->stmts),
                    ];
                }
            }

            if ($statement instanceof Node\Stmt\ClassLike) {
                $issues = [
                    ...$issues,
                    ...$this->analyzeStatements($file, $statement->stmts ?? []),
                ];
            }

            if ($statement instanceof Node\Stmt\ClassMethod || $statement instanceof Node\Stmt\Function_) {
                $issues = [
                    ...$issues,
                    ...$this->analyzeStatements($file, $statement->stmts ?? []),
                ];
            }
        }

        return $issues;
    }

    private function notEmptyVariable(Node\Expr $expression): ?string
    {
        if (! $expression instanceof Node\Expr\BooleanNot) {
            return null;
        }

        $inner = $expression->expr;

        if (! $inner instanceof Node\Expr\Empty_) {
            return null;
        }

        return $this->variableName($inner->expr);
    }

    /**
     * @param  list<Node>  $statements
     */
    private function singleForeachVariable(array $statements): ?string
    {
        if (count($statements) !== 1 || ! $statements[0] instanceof Node\Stmt\Foreach_) {
            return null;
        }

        return $this->variableName($statements[0]->expr);
    }

    private function variableName(Node\Expr $expression): ?string
    {
        if (! $expression instanceof Node\Expr\Variable || ! is_string($expression->name)) {
            return null;
        }

        return $expression->name;
    }
}
