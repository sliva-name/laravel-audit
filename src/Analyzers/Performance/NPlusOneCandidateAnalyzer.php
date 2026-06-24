<?php

declare(strict_types=1);

namespace LaravelAudit\Analyzers\Performance;

use LaravelAudit\Analysis\AnalysisContext;
use LaravelAudit\Analysis\AnalyzerInterface;
use LaravelAudit\Analysis\Category;
use LaravelAudit\Analysis\Issue;
use LaravelAudit\Analysis\Severity;
use LaravelAudit\Analyzers\BaseAnalyzer;

final class NPlusOneCandidateAnalyzer extends BaseAnalyzer implements AnalyzerInterface
{
    public function id(): string
    {
        return 'performance.n-plus-one-candidate';
    }

    public function category(): Category
    {
        return Category::Performance;
    }

    /**
     * @return list<Issue>
     */
    public function analyze(AnalysisContext $context): array
    {
        $issues = [];

        foreach ($context->project->phpFiles as $file) {
            $lines = preg_split('/\R/', $file->contents) ?: [];

            foreach ($lines as $index => $line) {
                if (preg_match('/foreach\s*\(.+\s+as\s+\$\w+/', $line) !== 1) {
                    continue;
                }

                $window = implode("\n", array_slice($lines, $index, 20));

                if (preg_match('/\$\w+->\w+(?!\s*\()/', $window) === 1 && preg_match('/with\(|load\(/', $window) !== 1) {
                    $issues[] = $this->issue(
                        $this->id(),
                        $this->category(),
                        Severity::Warning,
                        'Potential N+1 query in loop',
                        'A model property is accessed inside a loop without visible eager loading nearby.',
                        $file,
                        $index + 1,
                        'Check whether this is an Eloquent relationship and eager load it with with(), load(), or loadMissing().',
                    );
                }
            }
        }

        return $issues;
    }
}
