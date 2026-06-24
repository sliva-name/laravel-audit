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

final class RedundantBooleanReturnAnalyzer extends BaseAnalyzer implements AnalyzerInterface
{
    public function id(): string
    {
        return 'code-quality.redundant-boolean-return';
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

        foreach ($statements as $index => $statement) {
            if ($statement instanceof Node\Stmt\Return_ && $this->isBooleanTernary($statement->expr)) {
                $issues[] = $this->issue(
                    $this->id(),
                    $this->category(),
                    Severity::Info,
                    'Redundant boolean ternary',
                    'Return statement uses a ternary that only maps a condition to true or false.',
                    $file,
                    $statement->getStartLine(),
                    'Return the condition directly, or negate it when the branches are reversed.',
                );
            }

            if ($statement instanceof Node\Stmt\If_) {
                $issues = [
                    ...$issues,
                    ...$this->ifBooleanReturnIssues($file, $statement, $statements[$index + 1] ?? null),
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

    /**
     * @return list<Issue>
     */
    private function ifBooleanReturnIssues(PhpFile $file, Node\Stmt\If_ $if, ?Node $nextStatement): array
    {
        if ($if->elseifs !== []) {
            return [];
        }

        $truthyReturn = $this->singleBooleanReturn($if->stmts);

        if ($truthyReturn === null) {
            return [];
        }

        $elseReturn = $if->else === null ? null : $this->singleBooleanReturn($if->else->stmts);
        $nextReturn = $nextStatement instanceof Node\Stmt\Return_ ? $this->booleanValue($nextStatement->expr) : null;

        if ($elseReturn !== null && $elseReturn !== $truthyReturn) {
            return [$this->booleanReturnIssue($file, $if)];
        }

        if ($if->else === null && $nextReturn !== null && $nextReturn !== $truthyReturn) {
            return [$this->booleanReturnIssue($file, $if)];
        }

        return [];
    }

    /**
     * @param  list<Node>  $statements
     */
    private function singleBooleanReturn(array $statements): ?bool
    {
        if (count($statements) !== 1 || ! $statements[0] instanceof Node\Stmt\Return_) {
            return null;
        }

        return $this->booleanValue($statements[0]->expr);
    }

    private function booleanReturnIssue(PhpFile $file, Node\Stmt\If_ $if): Issue
    {
        return $this->issue(
            $this->id(),
            $this->category(),
            Severity::Info,
            'Redundant boolean return',
            'Conditional returns only true or false from the condition result.',
            $file,
            $if->getStartLine(),
            'Return the condition directly, or negate it when the branches are reversed.',
        );
    }

    private function isBooleanTernary(?Node\Expr $expression): bool
    {
        if (! $expression instanceof Node\Expr\Ternary || $expression->if === null) {
            return false;
        }

        $ifValue = $this->booleanValue($expression->if);
        $elseValue = $this->booleanValue($expression->else);

        return $ifValue !== null && $elseValue !== null && $ifValue !== $elseValue;
    }

    private function booleanValue(?Node\Expr $expression): ?bool
    {
        if (! $expression instanceof Node\Expr\ConstFetch) {
            return null;
        }

        return match (strtolower($expression->name->toString())) {
            'true' => true,
            'false' => false,
            default => null,
        };
    }
}
