<?php

declare(strict_types=1);

namespace LaravelAudit\Analyzers\Security;

use LaravelAudit\Analysis\AnalysisContext;
use LaravelAudit\Analysis\AnalyzerInterface;
use LaravelAudit\Analysis\Category;
use LaravelAudit\Analysis\Issue;
use LaravelAudit\Analysis\Severity;
use LaravelAudit\Analyzers\BaseAnalyzer;

final class UnguardedModelAnalyzer extends BaseAnalyzer implements AnalyzerInterface
{
    public function id(): string
    {
        return 'security.unguarded-model';
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

        foreach ($context->project->phpFiles as $file) {
            foreach ($this->matchingLines($file, '/::unguard\s*\(/') as $match) {
                $issues[] = $this->issue(
                    $this->id(),
                    $this->category(),
                    Severity::Critical,
                    'Mass assignment protection disabled',
                    'Model::unguard() disables Eloquent mass-assignment protection for all attributes.',
                    $file,
                    $match['line'],
                    'Use explicit $fillable lists or narrowly scoped reguard() calls instead of global unguard().',
                );
            }
        }

        return $issues;
    }
}
