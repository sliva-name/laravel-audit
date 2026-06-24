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

final class RedundantElseAfterExitAnalyzer extends BaseAnalyzer implements AnalyzerInterface
{
    public function id(): string
    {
        return 'code-quality.redundant-else-after-exit';
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
                if ($statement->else !== null && $this->statementsEndExecution($statement->stmts)) {
                    $issues[] = $this->issue(
                        $this->id(),
                        $this->category(),
                        Severity::Info,
                        'Redundant else after terminating branch',
                        'If branch already terminates execution, so the else block can usually be flattened.',
                        $file,
                        $statement->else->getStartLine(),
                        'Move the else body after the if block to reduce nesting.',
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

    /**
     * @param  list<Node>  $statements
     */
    private function statementsEndExecution(array $statements): bool
    {
        $last = $statements[array_key_last($statements)] ?? null;

        return $last instanceof Node\Stmt\Return_
            || ($last instanceof Node\Stmt\Expression && $last->expr instanceof Node\Expr\Throw_)
            || $last instanceof Node\Stmt\Break_
            || $last instanceof Node\Stmt\Continue_;
    }
}
