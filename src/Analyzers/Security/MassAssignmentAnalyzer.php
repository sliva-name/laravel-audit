<?php

declare(strict_types=1);

namespace LaravelAudit\Analyzers\Security;

use LaravelAudit\Analysis\AnalysisContext;
use LaravelAudit\Analysis\AnalyzerInterface;
use LaravelAudit\Analysis\Category;
use LaravelAudit\Analysis\Issue;
use LaravelAudit\Analysis\Severity;
use LaravelAudit\Analyzers\BaseAnalyzer;

final class MassAssignmentAnalyzer extends BaseAnalyzer implements AnalyzerInterface
{
    public function id(): string
    {
        return 'security.mass-assignment';
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

        foreach ($context->project->models() as $file) {
            if (! str_contains($file->contents, '$fillable') && ! str_contains($file->contents, '$guarded')) {
                $issues[] = $this->issue(
                    $this->id(),
                    $this->category(),
                    Severity::Error,
                    'Model has no mass assignment policy',
                    'Eloquent models should explicitly define fillable or guarded attributes.',
                    $file,
                    1,
                    'Add a narrow $fillable list, or set $guarded intentionally with a comment when the model is not mass-assigned.',
                );
            }

            foreach ($this->matchingLines($file, '/protected\s+\$guarded\s*=\s*\[\s*\]/') as $match) {
                $issues[] = $this->issue(
                    $this->id(),
                    $this->category(),
                    Severity::Warning,
                    'Model allows all mass assignment',
                    'An empty $guarded array permits mass assignment for every column.',
                    $file,
                    $match['line'],
                    'Prefer an explicit $fillable list for request-driven writes.',
                );
            }
        }

        return $issues;
    }
}
