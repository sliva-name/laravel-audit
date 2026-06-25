<?php

declare(strict_types=1);

namespace LaravelAudit\Analysis;

use PhpParser\Node;

final class NestingDepthCalculator
{
    /**
     * @param  list<Node>  $statements
     */
    public function maxDepth(array $statements, int $currentDepth = 0): int
    {
        $maxDepth = $currentDepth;

        foreach ($statements as $statement) {
            $maxDepth = max($maxDepth, $this->statementDepth($statement, $currentDepth));
        }

        return $maxDepth;
    }

    private function statementDepth(Node $statement, int $currentDepth): int
    {
        if (! $this->increasesNesting($statement)) {
            return $currentDepth;
        }

        $nestedDepth = $currentDepth + 1;
        $maxDepth = $nestedDepth;

        foreach ($this->nestedStatements($statement) as $nested) {
            $maxDepth = max($maxDepth, $this->maxDepth($nested, $nestedDepth));
        }

        return $maxDepth;
    }

    private function increasesNesting(Node $statement): bool
    {
        return $statement instanceof Node\Stmt\If_
            || $statement instanceof Node\Stmt\Foreach_
            || $statement instanceof Node\Stmt\For_
            || $statement instanceof Node\Stmt\While_
            || $statement instanceof Node\Stmt\Do_
            || $statement instanceof Node\Stmt\Switch_
            || $statement instanceof Node\Stmt\TryCatch;
    }

    /**
     * @return list<list<Node>>
     */
    private function nestedStatements(Node $statement): array
    {
        if ($statement instanceof Node\Stmt\If_) {
            $blocks = [$statement->stmts ?? []];

            foreach ($statement->elseifs as $elseif) {
                $blocks[] = $elseif->stmts;
            }

            if ($statement->else !== null) {
                $blocks[] = $statement->else->stmts;
            }

            return $blocks;
        }

        if ($statement instanceof Node\Stmt\Foreach_
            || $statement instanceof Node\Stmt\For_
            || $statement instanceof Node\Stmt\While_
            || $statement instanceof Node\Stmt\Do_) {
            return [$statement->stmts ?? []];
        }

        if ($statement instanceof Node\Stmt\Switch_) {
            return array_map(
                fn (Node\Stmt\Case_ $case): array => $case->stmts,
                $statement->cases,
            );
        }

        if ($statement instanceof Node\Stmt\TryCatch) {
            $blocks = [$statement->stmts];

            foreach ($statement->catches as $catch) {
                $blocks[] = $catch->stmts;
            }

            if ($statement->finally !== null) {
                $blocks[] = $statement->finally->stmts;
            }

            return $blocks;
        }

        return [];
    }
}
