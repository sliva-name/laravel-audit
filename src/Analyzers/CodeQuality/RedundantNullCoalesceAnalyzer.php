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

final class RedundantNullCoalesceAnalyzer extends BaseAnalyzer implements AnalyzerInterface
{
    public function id(): string
    {
        return 'code-quality.redundant-null-coalesce';
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
            /** @var list<Node\FunctionLike> $functions */
            $functions = $finder->find($file->ast, fn (Node $node): bool => $node instanceof Node\FunctionLike);

            foreach ($functions as $function) {
                $parameterNames = $this->nonNullableParameterNames($function);

                if ($parameterNames === [] || $function->getStmts() === null) {
                    continue;
                }

                /** @var list<Node\Expr\BinaryOp\Coalesce> $coalesces */
                $coalesces = $finder->findInstanceOf($function->getStmts(), Node\Expr\BinaryOp\Coalesce::class);

                foreach ($coalesces as $coalesce) {
                    $variable = $this->variableName($coalesce->left);

                    if ($variable === null || ! isset($parameterNames[$variable])) {
                        continue;
                    }

                    $issues[] = $this->issue(
                        $this->id(),
                        $this->category(),
                        Severity::Info,
                        'Redundant null coalesce candidate',
                        "Parameter \${$variable} is non-nullable, but is still guarded with a null coalesce fallback.",
                        $file,
                        $coalesce->getStartLine(),
                        'Remove the fallback or make the parameter nullable if null is part of the contract.',
                    );
                }
            }
        }

        return $issues;
    }

    /**
     * @return array<string, true>
     */
    private function nonNullableParameterNames(Node\FunctionLike $function): array
    {
        $parameters = [];

        foreach ($function->getParams() as $parameter) {
            if (! $parameter->var instanceof Node\Expr\Variable || ! is_string($parameter->var->name)) {
                continue;
            }

            if ($parameter->type === null || $this->typeAllowsNull($parameter->type)) {
                continue;
            }

            $parameters[$parameter->var->name] = true;
        }

        return $parameters;
    }

    private function typeAllowsNull(Node $type): bool
    {
        if ($type instanceof Node\NullableType) {
            return true;
        }

        if ($type instanceof Node\UnionType) {
            foreach ($type->types as $unionType) {
                if ($this->typeName($unionType) === 'null') {
                    return true;
                }
            }
        }

        return $this->typeName($type) === 'mixed';
    }

    private function typeName(Node $type): ?string
    {
        if (! $type instanceof Node\Name && ! $type instanceof Node\Identifier) {
            return null;
        }

        return strtolower($type->toString());
    }

    private function variableName(Node\Expr $expression): ?string
    {
        if (! $expression instanceof Node\Expr\Variable || ! is_string($expression->name)) {
            return null;
        }

        return $expression->name;
    }
}
