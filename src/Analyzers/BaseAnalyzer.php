<?php

declare(strict_types=1);

namespace LaravelAudit\Analyzers;

use LaravelAudit\Analysis\Category;
use LaravelAudit\Analysis\Issue;
use LaravelAudit\Analysis\Location;
use LaravelAudit\Analysis\Severity;
use LaravelAudit\Project\PhpFile;

abstract class BaseAnalyzer
{
    protected function issue(
        string $ruleId,
        Category $category,
        Severity $severity,
        string $title,
        string $message,
        PhpFile|string $file,
        int $line = 1,
        ?string $recommendation = null,
    ): Issue {
        return new Issue(
            ruleId: $ruleId,
            category: $category,
            severity: $severity,
            title: $title,
            message: $message,
            location: new Location($file instanceof PhpFile ? $file->relativePath : $file, $line),
            recommendation: $recommendation,
        );
    }

    /**
     * @return list<array{line: int, text: string}>
     */
    protected function matchingLines(PhpFile $file, string $pattern): array
    {
        $matches = [];

        foreach (preg_split('/\R/', $file->contents) ?: [] as $index => $line) {
            if (preg_match($pattern, $line) === 1) {
                $matches[] = ['line' => $index + 1, 'text' => trim($line)];
            }
        }

        return $matches;
    }
}
