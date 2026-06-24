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

final class RedundantTypeGuardAnalyzer extends BaseAnalyzer implements AnalyzerInterface
{
    public function id(): string
    {
        return 'code-quality.redundant-type-guard';
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
     * @param  array<string, true>  $arrayFallbackVariables
     * @return list<Issue>
     */
    private function analyzeStatements(PhpFile $file, array $statements, array $arrayFallbackVariables = []): array
    {
        $issues = [];

        foreach ($statements as $statement) {
            if ($statement instanceof Node\Stmt\Expression && $statement->expr instanceof Node\Expr\Assign) {
                $this->trackAssignment($statement->expr, $arrayFallbackVariables);
            }

            if ($statement instanceof Node\Stmt\If_) {
                foreach ($this->redundantGuardVariables($statement->cond, $arrayFallbackVariables) as $variable) {
                    $issues[] = $this->issue(
                        $this->id(),
                        $this->category(),
                        Severity::Info,
                        'Redundant type guard candidate',
                        "Variable \${$variable} is assigned with an array fallback and then guarded with is_array() before array_key_exists().",
                        $file,
                        $statement->getStartLine(),
                        'Remove the type guard when the surrounding contract already guarantees an array.',
                    );
                }

                $issues = [
                    ...$issues,
                    ...$this->analyzeStatements($file, $statement->stmts, $arrayFallbackVariables),
                ];

                foreach ($statement->elseifs as $elseif) {
                    $issues = [
                        ...$issues,
                        ...$this->analyzeStatements($file, $elseif->stmts, $arrayFallbackVariables),
                    ];
                }

                if ($statement->else !== null) {
                    $issues = [
                        ...$issues,
                        ...$this->analyzeStatements($file, $statement->else->stmts, $arrayFallbackVariables),
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
     * @param  array<string, true>  $arrayFallbackVariables
     */
    private function trackAssignment(Node\Expr\Assign $assign, array &$arrayFallbackVariables): void
    {
        if (! $assign->var instanceof Node\Expr\Variable || ! is_string($assign->var->name)) {
            return;
        }

        if ($this->hasArrayFallback($assign->expr)) {
            $arrayFallbackVariables[$assign->var->name] = true;

            return;
        }

        unset($arrayFallbackVariables[$assign->var->name]);
    }

    private function hasArrayFallback(Node\Expr $expression): bool
    {
        return $expression instanceof Node\Expr\Array_
            || ($expression instanceof Node\Expr\BinaryOp\Coalesce && $expression->right instanceof Node\Expr\Array_);
    }

    /**
     * @param  array<string, true>  $arrayFallbackVariables
     * @return list<string>
     */
    private function redundantGuardVariables(Node\Expr $condition, array $arrayFallbackVariables): array
    {
        $expressions = $this->flattenBooleanAnd($condition);
        $guardedVariables = [];
        $keyCheckedVariables = [];

        foreach ($expressions as $expression) {
            if ($expression instanceof Node\Expr\FuncCall && $this->isFunctionCallNamed($expression, 'is_array')) {
                $variable = $this->firstArgumentVariableName($expression);

                if ($variable !== null && isset($arrayFallbackVariables[$variable])) {
                    $guardedVariables[$variable] = true;
                }
            }

            if ($expression instanceof Node\Expr\FuncCall && $this->isFunctionCallNamed($expression, 'array_key_exists')) {
                $variable = $this->argumentVariableName($expression, 1);

                if ($variable !== null) {
                    $keyCheckedVariables[$variable] = true;
                }
            }
        }

        return array_values(array_intersect(array_keys($guardedVariables), array_keys($keyCheckedVariables)));
    }

    /**
     * @return list<Node\Expr>
     */
    private function flattenBooleanAnd(Node\Expr $expression): array
    {
        if ($expression instanceof Node\Expr\BinaryOp\BooleanAnd || $expression instanceof Node\Expr\BinaryOp\LogicalAnd) {
            return [
                ...$this->flattenBooleanAnd($expression->left),
                ...$this->flattenBooleanAnd($expression->right),
            ];
        }

        return [$expression];
    }

    private function isFunctionCallNamed(Node\Expr\FuncCall $expression, string $name): bool
    {
        return $expression->name instanceof Node\Name
            && strtolower($expression->name->toString()) === $name;
    }

    private function firstArgumentVariableName(Node\Expr\FuncCall $call): ?string
    {
        return $this->argumentVariableName($call, 0);
    }

    private function argumentVariableName(Node\Expr\FuncCall $call, int $index): ?string
    {
        $argument = $call->args[$index]->value ?? null;

        if (! $argument instanceof Node\Expr\Variable || ! is_string($argument->name)) {
            return null;
        }

        return $argument->name;
    }
}
