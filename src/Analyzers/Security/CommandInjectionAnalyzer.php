<?php

declare(strict_types=1);

namespace LaravelAudit\Analyzers\Security;

use LaravelAudit\Analysis\AnalysisContext;
use LaravelAudit\Analysis\AnalyzerInterface;
use LaravelAudit\Analysis\Category;
use LaravelAudit\Analysis\Issue;
use LaravelAudit\Analysis\Severity;
use LaravelAudit\Analyzers\BaseAnalyzer;

final class CommandInjectionAnalyzer extends BaseAnalyzer implements AnalyzerInterface
{
    public function id(): string
    {
        return 'security.command-injection';
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
        $pattern = '/\b(?:exec|shell_exec|system|passthru|proc_open|popen)\s*\(/';

        foreach ($context->project->phpFiles as $file) {
            foreach ($this->matchingLines($file, $pattern) as $match) {
                $issues[] = $this->issue(
                    $this->id(),
                    $this->category(),
                    Severity::Critical,
                    'Shell command execution requires review',
                    'Shell execution functions can enable command injection when user input reaches the command string.',
                    $file,
                    $match['line'],
                    'Avoid shell execution, use Symfony Process with argument arrays, or strictly validate and escape all inputs.',
                );
            }
        }

        return $issues;
    }
}
