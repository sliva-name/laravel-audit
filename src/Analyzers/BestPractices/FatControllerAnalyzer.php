<?php

declare(strict_types=1);

namespace LaravelAudit\Analyzers\BestPractices;

use LaravelAudit\Analysis\AnalysisContext;
use LaravelAudit\Analysis\AnalyzerInterface;
use LaravelAudit\Analysis\Category;
use LaravelAudit\Analysis\Issue;
use LaravelAudit\Analysis\Severity;
use LaravelAudit\Analyzers\BaseAnalyzer;

final class FatControllerAnalyzer extends BaseAnalyzer implements AnalyzerInterface
{
    public function id(): string
    {
        return 'best-practices.fat-controller';
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
            if ($file->lines < 250) {
                continue;
            }

            $issues[] = $this->issue(
                $this->id(),
                $this->category(),
                Severity::Warning,
                'Controller is large',
                "Controller has {$file->lines} lines and may be mixing HTTP orchestration with business logic.",
                $file,
                1,
                'Extract business workflows into actions/services and keep controllers focused on request/response orchestration.',
            );
        }

        return $issues;
    }
}
