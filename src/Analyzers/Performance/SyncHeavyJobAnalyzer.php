<?php

declare(strict_types=1);

namespace LaravelAudit\Analyzers\Performance;

use LaravelAudit\Analysis\AnalysisContext;
use LaravelAudit\Analysis\AnalyzerInterface;
use LaravelAudit\Analysis\Category;
use LaravelAudit\Analysis\Issue;
use LaravelAudit\Analysis\Severity;
use LaravelAudit\Analyzers\BaseAnalyzer;

final class SyncHeavyJobAnalyzer extends BaseAnalyzer implements AnalyzerInterface
{
    public function id(): string
    {
        return 'performance.sync-heavy-job';
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

        foreach ($context->project->jobs() as $file) {
            if (str_contains($file->contents, 'ShouldQueue')) {
                continue;
            }

            foreach (['Http::', 'Mail::', 'Storage::', 'Excel::', 'Process::', 'sleep('] as $heavyCall) {
                if (! str_contains($file->contents, $heavyCall)) {
                    continue;
                }

                $issues[] = $this->issue(
                    $this->id(),
                    $this->category(),
                    Severity::Warning,
                    'Job performs heavy work synchronously',
                    "This job references {$heavyCall} but does not implement ShouldQueue.",
                    $file,
                    1,
                    'Implement ShouldQueue for I/O-heavy work and configure a production queue driver.',
                );

                break;
            }
        }

        return $issues;
    }
}
