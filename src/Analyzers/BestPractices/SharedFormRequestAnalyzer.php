<?php

declare(strict_types=1);

namespace LaravelAudit\Analyzers\BestPractices;

use LaravelAudit\Analysis\AnalysisContext;
use LaravelAudit\Analysis\AnalyzerInterface;
use LaravelAudit\Analysis\Category;
use LaravelAudit\Analysis\Issue;
use LaravelAudit\Analysis\Severity;
use LaravelAudit\Analyzers\BaseAnalyzer;
use LaravelAudit\Analyzers\CodeQuality\Support\TypedParameterInspector;
use PhpParser\Node;
use PhpParser\NodeFinder;

final class SharedFormRequestAnalyzer extends BaseAnalyzer implements AnalyzerInterface
{
    public function __construct(
        private readonly TypedParameterInspector $typedParameterInspector = new TypedParameterInspector,
    ) {}

    public function id(): string
    {
        return 'best-practices.shared-form-request';
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

        foreach ($context->project->controllers() as $file) {
            $namespace = $this->namespaceName($file->ast);
            $imports = $this->useImports($file->ast);
            /** @var array<string, array{display: string, usages: list<array{method: string, line: int}>}> $grouped */
            $grouped = [];

            /** @var list<Node\Stmt\ClassMethod> $methods */
            $methods = $finder->findInstanceOf($file->ast, Node\Stmt\ClassMethod::class);

            foreach ($methods as $method) {
                if (! $method->isPublic()) {
                    continue;
                }

                $formRequest = $this->formRequestTypeFromMethod($method, $namespace, $imports);

                if ($formRequest === null) {
                    continue;
                }

                if (! isset($grouped[$formRequest['resolved']])) {
                    $grouped[$formRequest['resolved']] = [
                        'display' => $formRequest['display'],
                        'usages' => [],
                    ];
                }

                $grouped[$formRequest['resolved']]['usages'][] = [
                    'method' => $method->name->toString(),
                    'line' => $method->getStartLine(),
                ];
            }

            foreach ($grouped as $group) {
                $usages = $group['usages'];

                if (count($usages) < 2) {
                    continue;
                }

                $methodNames = array_map(
                    fn (array $usage): string => $usage['method'].'()',
                    $usages,
                );
                sort($methodNames);
                $methodsList = implode(', ', $methodNames);
                $displayName = $group['display'];

                foreach ($usages as $usage) {
                    $issues[] = $this->issue(
                        $this->id(),
                        $this->category(),
                        Severity::Info,
                        'Form Request shared across controller actions',
                        "{$displayName} is type-hinted in {$methodsList}. Different actions usually need different validation rules.",
                        $file,
                        $usage['line'],
                        'Create separate Form Request classes for each action (for example StoreProductoRequest and UpdateProductoRequest).',
                    );
                }
            }
        }

        return $issues;
    }

    /**
     * @param  array<string, string>  $imports
     * @return array{display: string, resolved: string}|null
     */
    private function formRequestTypeFromMethod(
        Node\Stmt\ClassMethod $method,
        ?string $namespace,
        array $imports,
    ): ?array {
        foreach ($method->params as $parameter) {
            $type = $parameter->type;

            if ($type instanceof Node\NullableType) {
                $type = $type->type;
            }

            if (! $type instanceof Node\Name) {
                continue;
            }

            $resolved = $this->resolveClassName($type, $namespace, $imports);

            if ($this->isCustomFormRequest($resolved)) {
                return [
                    'display' => $type->getLast(),
                    'resolved' => $resolved,
                ];
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $imports
     */
    private function resolveClassName(Node\Name $name, ?string $namespace, array $imports): string
    {
        $shortName = strtolower($name->getLast());

        if ($name->isFullyQualified()) {
            return strtolower(ltrim($name->toString(), '\\'));
        }

        if (isset($imports[$shortName])) {
            return $imports[$shortName];
        }

        return $this->typedParameterInspector->resolveClassName($name, $namespace);
    }

    private function isCustomFormRequest(string $resolvedClass): bool
    {
        if ($resolvedClass === 'illuminate\http\request') {
            return false;
        }

        $shortName = substr($resolvedClass, strrpos($resolvedClass, '\\') + 1);

        return $shortName !== 'request' && str_ends_with($shortName, 'request');
    }

    /**
     * @param  list<Node>  $statements
     * @return array<string, string>
     */
    private function useImports(array $statements): array
    {
        $imports = [];

        foreach ($statements as $statement) {
            if ($statement instanceof Node\Stmt\Namespace_) {
                return [
                    ...$imports,
                    ...$this->useImports($statement->stmts),
                ];
            }

            if (! $statement instanceof Node\Stmt\Use_) {
                continue;
            }

            foreach ($statement->uses as $use) {
                $alias = strtolower($use->alias?->toString() ?? $use->name->getLast());
                $imports[$alias] = strtolower($use->name->toString());
            }
        }

        return $imports;
    }

    /**
     * @param  list<Node>  $statements
     */
    private function namespaceName(array $statements): ?string
    {
        foreach ($statements as $statement) {
            if ($statement instanceof Node\Stmt\Namespace_) {
                return $statement->name?->toString();
            }
        }

        return null;
    }
}
