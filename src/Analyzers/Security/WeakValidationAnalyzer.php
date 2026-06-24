<?php

declare(strict_types=1);

namespace LaravelAudit\Analyzers\Security;

use LaravelAudit\Analysis\AnalysisContext;
use LaravelAudit\Analysis\AnalyzerInterface;
use LaravelAudit\Analysis\Category;
use LaravelAudit\Analysis\Issue;
use LaravelAudit\Analysis\Severity;
use LaravelAudit\Analyzers\BaseAnalyzer;

final class WeakValidationAnalyzer extends BaseAnalyzer implements AnalyzerInterface
{
    public function id(): string
    {
        return 'security.weak-validation';
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

        foreach ($context->project->controllers() as $file) {
            foreach ($this->matchingLines($file, '/->validate\(\s*\[\s*\]\s*\)/') as $match) {
                $issues[] = $this->issue(
                    $this->id(),
                    $this->category(),
                    Severity::Error,
                    'Empty validation rules',
                    'A validate call with an empty rule set gives a false sense of protection.',
                    $file,
                    $match['line'],
                    'Move validation to a FormRequest and define explicit constraints for every accepted field.',
                );
            }

            foreach ($this->matchingLines($file, '/request\(\)->all\(\)|->all\(\)/') as $match) {
                if (str_contains($file->contents, 'validate(') || str_contains($file->contents, 'FormRequest')) {
                    continue;
                }

                $issues[] = $this->issue(
                    $this->id(),
                    $this->category(),
                    Severity::Warning,
                    'Unvalidated request payload candidate',
                    'Using all request input without visible validation can accidentally persist unsafe fields.',
                    $file,
                    $match['line'],
                    'Use $request->validated() from a FormRequest or validate only the fields the action accepts.',
                );
            }
        }

        return $issues;
    }
}
