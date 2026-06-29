<?php

declare(strict_types=1);

namespace LaravelAudit\Analyzers\Security;

use LaravelAudit\Analysis\AnalysisContext;
use LaravelAudit\Analysis\AnalyzerInterface;
use LaravelAudit\Analysis\Category;
use LaravelAudit\Analysis\Issue;
use LaravelAudit\Analysis\Severity;
use LaravelAudit\Analyzers\BaseAnalyzer;
use PhpParser\Node;
use PhpParser\NodeFinder;

final class UnvalidatedMassCreateAnalyzer extends BaseAnalyzer implements AnalyzerInterface
{
    /**
     * @var list<string>
     */
    private const MASS_WRITE_METHODS = ['create', 'update', 'fill', 'forceFill', 'forceCreate'];

    public function id(): string
    {
        return 'security.unvalidated-mass-create';
    }

    public function category(): Category
    {
        return Category::Security;
    }

    /**
     * @return list<Issue>
     */
    public function analyze(AnalysisContext $context): array
    {
        $issues = [];
        $finder = new NodeFinder;

        foreach ($context->project->controllers() as $file) {
            /** @var list<Node\Stmt\ClassMethod> $methods */
            $methods = $finder->findInstanceOf($file->ast, Node\Stmt\ClassMethod::class);

            foreach ($methods as $method) {
                if ($method->getStmts() === null || $this->methodUsesValidatedInput($method)) {
                    continue;
                }

                foreach ($this->massAssignmentCalls($finder, $method->getStmts()) as $call) {
                    if (! $this->usesRequestAll($call)) {
                        continue;
                    }

                    $issues[] = $this->issue(
                        $this->id(),
                        $this->category(),
                        Severity::Critical,
                        'Unvalidated mass assignment from request',
                        'Request input is passed wholesale into a mass-assignment write without visible validation in this method.',
                        $file,
                        $call->getStartLine(),
                        'Validate input with a FormRequest or $request->validated() before calling create(), update(), or fill().',
                    );
                }
            }
        }

        return $issues;
    }

    private function methodUsesValidatedInput(Node\Stmt\ClassMethod $method): bool
    {
        foreach ($method->params as $parameter) {
            $type = $parameter->type;

            if ($type instanceof Node\Name) {
                $shortName = $type->getLast();

                if ($shortName !== 'Request' && str_ends_with($shortName, 'Request')) {
                    return true;
                }
            }
        }

        $statements = $method->getStmts() ?? [];
        $finder = new NodeFinder;

        foreach ($finder->findInstanceOf($statements, Node\Expr\MethodCall::class) as $call) {
            if ($call->name instanceof Node\Identifier && in_array(strtolower($call->name->toString()), ['validate', 'validated'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<Node\Stmt>  $statements
     * @return list<Node\Expr\CallLike>
     */
    private function massAssignmentCalls(NodeFinder $finder, array $statements): array
    {
        $calls = [];

        foreach ($finder->findInstanceOf($statements, Node\Expr\StaticCall::class) as $call) {
            if ($call->name instanceof Node\Identifier && in_array(strtolower($call->name->toString()), self::MASS_WRITE_METHODS, true)) {
                $calls[] = $call;
            }
        }

        foreach ($finder->findInstanceOf($statements, Node\Expr\MethodCall::class) as $call) {
            if ($call->name instanceof Node\Identifier && in_array(strtolower($call->name->toString()), self::MASS_WRITE_METHODS, true)) {
                $calls[] = $call;
            }
        }

        return $calls;
    }

    private function usesRequestAll(Node\Expr\CallLike $call): bool
    {
        foreach ($call->getArgs() as $argument) {
            if ($this->isRequestAllExpression($argument->value)) {
                return true;
            }
        }

        return false;
    }

    private function isRequestAllExpression(Node\Expr $expression): bool
    {
        return $expression instanceof Node\Expr\MethodCall
            && $expression->name instanceof Node\Identifier
            && strtolower($expression->name->toString()) === 'all';
    }
}
