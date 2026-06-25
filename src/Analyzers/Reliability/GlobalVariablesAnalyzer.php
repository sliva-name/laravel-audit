<?php

declare(strict_types=1);

namespace LaravelAudit\Analyzers\Reliability;

use LaravelAudit\Analysis\AnalysisContext;
use LaravelAudit\Analysis\AnalyzerInterface;
use LaravelAudit\Analysis\Category;
use LaravelAudit\Analysis\Issue;
use LaravelAudit\Analysis\Severity;
use LaravelAudit\Analyzers\BaseAnalyzer;

final class GlobalVariablesAnalyzer extends BaseAnalyzer implements AnalyzerInterface
{
    public function id(): string
    {
        return 'reliability.global-variables';
    }

    public function category(): Category
    {
        return Category::Reliability;
    }

    /**
     * @return list<Issue>
     */
    public function analyze(AnalysisContext $context): array
    {
        $issues = [];
        $pattern = '/\$_((?:GET|POST|REQUEST|COOKIE|SERVER|FILES))\b/';

        foreach ($context->project->phpFiles as $file) {
            foreach ($this->matchingLines($file, $pattern) as $match) {
                $issues[] = $this->issue(
                    $this->id(),
                    $this->category(),
                    Severity::Warning,
                    'Superglobal access detected',
                    'Direct superglobal access bypasses Laravel request abstraction and complicates testing.',
                    $file,
                    $match['line'],
                    'Use Illuminate\Http\Request, request(), or typed route parameters instead of superglobals.',
                );
            }
        }

        return $issues;
    }
}
