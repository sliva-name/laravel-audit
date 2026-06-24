<?php

declare(strict_types=1);

namespace LaravelAudit\Analyzers\Security;

use LaravelAudit\Analysis\AnalysisContext;
use LaravelAudit\Analysis\AnalyzerInterface;
use LaravelAudit\Analysis\Category;
use LaravelAudit\Analysis\Issue;
use LaravelAudit\Analysis\Severity;
use LaravelAudit\Analyzers\BaseAnalyzer;

final class DebugConfigurationAnalyzer extends BaseAnalyzer implements AnalyzerInterface
{
    public function id(): string
    {
        return 'security.debug-configuration';
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
            if ($file->relativePath !== 'config/app.php') {
                continue;
            }

            foreach ($this->matchingLines($file, "/'debug'\\s*=>\\s*(true|env\\(\\s*['\"]APP_DEBUG['\"]\\s*,\\s*true\\s*\\))/") as $match) {
                $issues[] = $this->issue(
                    $this->id(),
                    $this->category(),
                    Severity::Critical,
                    'Debug mode may default to enabled',
                    'Production debug output can leak secrets, stack traces, and internal paths.',
                    $file,
                    $match['line'],
                    'Use env(\'APP_DEBUG\', false) and enforce APP_DEBUG=false in production environments.',
                );
            }
        }

        return $issues;
    }
}
