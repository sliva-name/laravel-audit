<?php

declare(strict_types=1);

namespace LaravelAudit\Analyzers\Security;

use LaravelAudit\Analysis\AnalysisContext;
use LaravelAudit\Analysis\AnalyzerInterface;
use LaravelAudit\Analysis\Category;
use LaravelAudit\Analysis\Issue;
use LaravelAudit\Analysis\Severity;
use LaravelAudit\Analyzers\BaseAnalyzer;

final class EvalUsageAnalyzer extends BaseAnalyzer implements AnalyzerInterface
{
    public function id(): string
    {
        return 'security.eval-usage';
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
        $patterns = [
            '/\beval\s*\(/' => 'eval() allows arbitrary PHP execution.',
            '/\bcreate_function\s*\(/' => 'create_function() executes dynamically generated code.',
            '/preg_replace\s*\([^)]*\/[^\/]*e[^\/]*\/[^)]*\)/' => 'preg_replace() with the /e modifier executes replacement strings as PHP code.',
        ];

        foreach ($context->project->phpFiles as $file) {
            foreach ($patterns as $pattern => $message) {
                foreach ($this->matchingLines($file, $pattern) as $match) {
                    $issues[] = $this->issue(
                        $this->id(),
                        $this->category(),
                        Severity::Critical,
                        'Dynamic code execution detected',
                        $message,
                        $file,
                        $match['line'],
                        'Remove dynamic execution and replace it with explicit, reviewable application logic.',
                    );
                }
            }
        }

        return $issues;
    }
}
