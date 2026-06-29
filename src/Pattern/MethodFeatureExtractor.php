<?php

declare(strict_types=1);

namespace LaravelAudit\Pattern;

use LaravelAudit\Analysis\NestingDepthCalculator;
use LaravelAudit\Project\PhpFile;
use PhpParser\Node;
use PhpParser\NodeFinder;

final class MethodFeatureExtractor
{
    public function __construct(
        private readonly NestingDepthCalculator $nestingDepth = new NestingDepthCalculator,
    ) {}

    /**
     * @return list<MethodFeatures>
     */
    public function extract(PhpFile $file): array
    {
        $finder = new NodeFinder;
        $features = [];
        $className = $this->className($file);

        /** @var list<Node\Stmt\ClassMethod> $methods */
        $methods = $finder->findInstanceOf($file->ast, Node\Stmt\ClassMethod::class);

        foreach ($methods as $method) {
            if ($method->getStmts() === null) {
                continue;
            }

            $features[] = new MethodFeatures(
                file: $file->relativePath,
                line: $method->getStartLine(),
                method: $method->name->toString(),
                class: $className,
                values: $this->values($file, $method),
            );
        }

        return $features;
    }

    private function className(PhpFile $file): string
    {
        $finder = new NodeFinder;
        /** @var list<Node\Stmt\Class_> $classes */
        $classes = $finder->findInstanceOf($file->ast, Node\Stmt\Class_::class);

        if ($classes === []) {
            return 'global';
        }

        return $classes[0]->name?->toString() ?? 'anonymous';
    }

    /**
     * @return array<string, float>
     */
    private function values(PhpFile $file, Node\Stmt\ClassMethod $method): array
    {
        $statements = $method->getStmts() ?? [];
        $finder = new NodeFinder;

        return [
            'lines' => (float) ($method->getEndLine() - $method->getStartLine() + 1),
            'nesting_depth' => (float) $this->nestingDepth->maxDepth($statements),
            'switch_branches' => (float) $this->maxSwitchBranches($finder, $statements),
            'elseif_chain' => (float) $this->maxElseIfChain($finder, $statements),
            'instanceof_checks' => (float) count($finder->findInstanceOf($statements, Node\Expr\Instanceof_::class)),
            'app_resolver_calls' => (float) $this->countAppResolverCalls($finder, $statements),
            'manual_instantiations' => (float) count($finder->findInstanceOf($statements, Node\Expr\New_::class)),
            'db_calls' => (float) $this->countDbCalls($finder, $statements),
            'validate_calls' => (float) $this->countValidateCalls($finder, $statements),
            'parameter_count' => (float) count($method->params),
            'return_statements' => (float) count($finder->findInstanceOf($statements, Node\Stmt\Return_::class)),
            'try_catch_blocks' => (float) count($finder->findInstanceOf($statements, Node\Stmt\TryCatch::class)),
            'foreach_loops' => (float) count($finder->findInstanceOf($statements, Node\Stmt\Foreach_::class)),
            'is_controller_method' => $this->isControllerMethod($file) ? 1.0 : 0.0,
            'authorize_calls' => (float) $this->countAuthorizeCalls($finder, $statements),
            'pipeline_usages' => (float) $this->countPipelineUsages($finder, $statements),
            'magic_string_comparisons' => (float) $this->countMagicStringComparisons($finder, $statements),
            'direct_model_returns' => (float) $this->countDirectModelReturns($finder, $statements),
            'resource_wrapped_returns' => (float) $this->countResourceWrappedReturns($finder, $statements),
        ];
    }

    /**
     * @param  list<Node\Stmt>  $statements
     */
    private function countAppResolverCalls(NodeFinder $finder, array $statements): int
    {
        $count = 0;

        foreach ($finder->findInstanceOf($statements, Node\Expr\FuncCall::class) as $call) {
            if ($call->name instanceof Node\Name && in_array(strtolower($call->name->toString()), ['app', 'resolve'], true)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param  list<Node\Stmt>  $statements
     */
    private function countDbCalls(NodeFinder $finder, array $statements): int
    {
        $count = 0;

        foreach ($finder->findInstanceOf($statements, Node\Expr\StaticCall::class) as $call) {
            if ($call->class instanceof Node\Name && strtoupper($call->class->toString()) === 'DB') {
                $count++;
            }
        }

        foreach ($finder->findInstanceOf($statements, Node\Expr\MethodCall::class) as $call) {
            if ($call->name instanceof Node\Identifier && in_array(strtolower($call->name->toString()), [
                'where', 'join', 'select', 'insert', 'update', 'delete', 'create', 'first', 'get',
            ], true)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param  list<Node\Stmt>  $statements
     */
    private function countValidateCalls(NodeFinder $finder, array $statements): int
    {
        $count = 0;

        foreach ($finder->findInstanceOf($statements, Node\Expr\MethodCall::class) as $call) {
            if ($call->name instanceof Node\Identifier && strtolower($call->name->toString()) === 'validate') {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param  list<Node\Stmt>  $statements
     */
    private function maxSwitchBranches(NodeFinder $finder, array $statements): int
    {
        $max = 0;

        foreach ($finder->findInstanceOf($statements, Node\Stmt\Switch_::class) as $switch) {
            $max = max($max, count($switch->cases));
        }

        return $max;
    }

    /**
     * @param  list<Node\Stmt>  $statements
     */
    private function maxElseIfChain(NodeFinder $finder, array $statements): int
    {
        $max = 0;

        foreach ($finder->findInstanceOf($statements, Node\Stmt\If_::class) as $if) {
            $max = max($max, count($if->elseifs) + ($if->else !== null ? 1 : 0));
        }

        return $max;
    }

    private function isControllerMethod(PhpFile $file): bool
    {
        return str_contains($file->relativePath, 'Http/Controllers')
            || str_contains($file->relativePath, 'Http\\Controllers');
    }

    /**
     * @param  list<Node\Stmt>  $statements
     */
    private function countAuthorizeCalls(NodeFinder $finder, array $statements): int
    {
        $count = 0;

        foreach ($finder->findInstanceOf($statements, Node\Expr\MethodCall::class) as $call) {
            if ($call->name instanceof Node\Identifier && strtolower($call->name->toString()) === 'authorize') {
                $count++;
            }
        }

        foreach ($finder->findInstanceOf($statements, Node\Expr\StaticCall::class) as $call) {
            if ($call->class instanceof Node\Name
                && strtoupper($call->class->toString()) === 'GATE'
                && $call->name instanceof Node\Identifier
                && in_array(strtolower($call->name->toString()), ['authorize', 'allows', 'denies'], true)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param  list<Node\Stmt>  $statements
     */
    private function countPipelineUsages(NodeFinder $finder, array $statements): int
    {
        $count = 0;

        foreach ($finder->findInstanceOf($statements, Node\Expr\StaticCall::class) as $call) {
            if ($call->class instanceof Node\Name
                && str_contains(strtolower($call->class->toString()), 'pipeline')
                && $call->name instanceof Node\Identifier
                && strtolower($call->name->toString()) === 'send') {
                $count++;
            }
        }

        foreach ($finder->findInstanceOf($statements, Node\Expr\MethodCall::class) as $call) {
            if ($call->name instanceof Node\Identifier && in_array(strtolower($call->name->toString()), ['through', 'pipe', 'then'], true)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param  list<Node\Stmt>  $statements
     */
    private function countMagicStringComparisons(NodeFinder $finder, array $statements): int
    {
        $count = 0;

        foreach ($finder->findInstanceOf($statements, Node\Expr\BinaryOp::class) as $comparison) {
            if (! $comparison instanceof Node\Expr\BinaryOp\Equal && ! $comparison instanceof Node\Expr\BinaryOp\Identical) {
                continue;
            }

            if ($comparison->left instanceof Node\Scalar\String_ || $comparison->right instanceof Node\Scalar\String_) {
                $count++;
            }
        }

        foreach ($finder->findInstanceOf($statements, Node\Stmt\Switch_::class) as $switch) {
            foreach ($switch->cases as $case) {
                if ($case->cond instanceof Node\Scalar\String_) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * @param  list<Node\Stmt>  $statements
     */
    private function countDirectModelReturns(NodeFinder $finder, array $statements): int
    {
        $count = 0;

        foreach ($finder->findInstanceOf($statements, Node\Stmt\Return_::class) as $return) {
            if ($return->expr === null) {
                continue;
            }

            if ($return->expr instanceof Node\Expr\Variable || $return->expr instanceof Node\Expr\StaticCall || $return->expr instanceof Node\Expr\MethodCall) {
                if (! $this->isResourceExpression($return->expr)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * @param  list<Node\Stmt>  $statements
     */
    private function countResourceWrappedReturns(NodeFinder $finder, array $statements): int
    {
        $count = 0;

        foreach ($finder->findInstanceOf($statements, Node\Stmt\Return_::class) as $return) {
            if ($return->expr !== null && $this->isResourceExpression($return->expr)) {
                $count++;
            }
        }

        return $count;
    }

    private function isResourceExpression(Node\Expr $expression): bool
    {
        if ($expression instanceof Node\Expr\StaticCall
            && $expression->name instanceof Node\Identifier
            && in_array(strtolower($expression->name->toString()), ['collection', 'make'], true)) {
            return true;
        }

        if ($expression instanceof Node\Expr\New_
            && $expression->class instanceof Node\Name
            && str_contains($expression->class->toString(), 'Resource')) {
            return true;
        }

        return $expression instanceof Node\Expr\FuncCall
            && $expression->name instanceof Node\Name
            && strtolower($expression->name->toString()) === 'response';
    }
}
