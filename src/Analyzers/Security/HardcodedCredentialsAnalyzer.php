<?php

declare(strict_types=1);

namespace LaravelAudit\Analyzers\Security;

use LaravelAudit\Analysis\AnalysisContext;
use LaravelAudit\Analysis\AnalyzerInterface;
use LaravelAudit\Analysis\Category;
use LaravelAudit\Analysis\Issue;
use LaravelAudit\Analysis\Severity;
use LaravelAudit\Analyzers\BaseAnalyzer;

final class HardcodedCredentialsAnalyzer extends BaseAnalyzer implements AnalyzerInterface
{
    /**
     * @var list<string>
     */
    private const PLACEHOLDER_VALUES = [
        'changeme',
        'change-me',
        'example',
        'password',
        'secret',
        'your-api-key',
        'your-secret',
        'xxx',
        'todo',
    ];

    public function id(): string
    {
        return 'security.hardcoded-credentials';
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
        $pattern = '/\b(?:password|passwd|api[_-]?key|secret(?:[_-]?key)?|access[_-]?token|private[_-]?key|auth[_-]?token)\s*(?:=|=>)\s*[\'"]([^\'"]{4,})[\'"]/i';

        foreach ($context->project->phpFiles as $file) {
            if ($file->inDirectory('tests')) {
                continue;
            }

            foreach ($this->matchingLines($file, $pattern) as $match) {
                if ($this->looksLikePlaceholder($match['text'])) {
                    continue;
                }

                $issues[] = $this->issue(
                    $this->id(),
                    $this->category(),
                    Severity::Critical,
                    'Hardcoded credential candidate',
                    'A credential-like literal was found in source code instead of environment configuration.',
                    $file,
                    $match['line'],
                    'Move secrets to environment variables and read them through config() after defining them in config files.',
                );
            }
        }

        return $issues;
    }

    private function looksLikePlaceholder(string $line): bool
    {
        $normalized = strtolower($line);

        foreach (self::PLACEHOLDER_VALUES as $placeholder) {
            if (str_contains($normalized, "'{$placeholder}'") || str_contains($normalized, "\"{$placeholder}\"")) {
                return true;
            }
        }

        return false;
    }
}
