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

final class MissingFormRequestAnalyzer extends BaseAnalyzer implements AnalyzerInterface
{
    public function id(): string
    {
        return 'best-practices.missing-form-request';
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
            /** @var list<Node\Stmt\ClassMethod> $methods */
            $methods = $finder->findInstanceOf($file->ast, Node\Stmt\ClassMethod::class);

            foreach ($methods as $method) {
                if ($method->getStmts() === null || $this->hasTypedFormRequestParam($method)) {
                    continue;
                }

                foreach ($this->inlineRequestValidateCalls($finder, $method->getStmts()) as $call) {
                    $issues[] = $this->issue(
                        $this->id(),
                        $this->category(),
                        Severity::Info,
                        'Inline validation in controller',
                        'Validation inside controllers couples HTTP validation details to action orchestration.',
                        $file,
                        $call->getStartLine(),
                        'Move reusable validation and authorization to a FormRequest when the rules are non-trivial.',
                    );
                }
            }
        }

        return $issues;
    }

    private function hasTypedFormRequestParam(Node\Stmt\ClassMethod $method): bool
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

        return false;
    }

    /**
     * @param  list<Node\Stmt>  $statements
     * @return list<Node\Expr\MethodCall>
     */
    private function inlineRequestValidateCalls(NodeFinder $finder, array $statements): array
    {
        $calls = [];

        foreach ($finder->findInstanceOf($statements, Node\Expr\MethodCall::class) as $call) {
            if (! $call->name instanceof Node\Identifier || strtolower($call->name->toString()) !== 'validate') {
                continue;
            }

            if ($this->isRequestValidateReceiver($call->var)) {
                $calls[] = $call;
            }
        }

        return $calls;
    }

    private function isRequestValidateReceiver(Node\Expr $receiver): bool
    {
        if ($receiver instanceof Node\Expr\Variable
            && is_string($receiver->name)
            && strtolower($receiver->name) === 'request') {
            return true;
        }

        return $receiver instanceof Node\Expr\FuncCall
            && $receiver->name instanceof Node\Name
            && strtolower($receiver->name->toString()) === 'request';
    }
}
