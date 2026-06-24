<?php

declare(strict_types=1);

namespace LaravelAudit\Analyzers\BestPractices;

use LaravelAudit\Analysis\AnalysisContext;
use LaravelAudit\Analysis\AnalyzerInterface;
use LaravelAudit\Analysis\Category;
use LaravelAudit\Analysis\Issue;
use LaravelAudit\Analysis\Severity;
use LaravelAudit\Analyzers\BaseAnalyzer;

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

        foreach ($context->project->controllers() as $file) {
            foreach ($this->matchingLines($file, '/->validate\s*\(/') as $match) {
                $issues[] = $this->issue(
                    $this->id(),
                    $this->category(),
                    Severity::Info,
                    'Inline validation in controller',
                    'Validation inside controllers couples HTTP validation details to action orchestration.',
                    $file,
                    $match['line'],
                    'Move reusable validation and authorization to a FormRequest when the rules are non-trivial.',
                );
            }
        }

        return $issues;
    }
}
