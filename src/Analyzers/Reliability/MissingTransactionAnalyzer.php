<?php

declare(strict_types=1);

namespace LaravelAudit\Analyzers\Reliability;

use LaravelAudit\Analysis\AnalysisContext;
use LaravelAudit\Analysis\AnalyzerInterface;
use LaravelAudit\Analysis\Category;
use LaravelAudit\Analysis\Issue;
use LaravelAudit\Analysis\Severity;
use LaravelAudit\Analyzers\BaseAnalyzer;

final class MissingTransactionAnalyzer extends BaseAnalyzer implements AnalyzerInterface
{
    public function id(): string
    {
        return 'reliability.missing-transaction';
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
        $writePattern = '/(->save\(|->delete\(|::create\(|::update\(|::destroy\()/';

        foreach ($context->project->phpFiles as $file) {
            if (! str_contains($file->relativePath, 'Http/Controllers') && ! str_contains($file->relativePath, 'Actions')) {
                continue;
            }

            if (str_contains($file->contents, 'DB::transaction') || str_contains($file->contents, '->transaction(')) {
                continue;
            }

            $writeCount = preg_match_all($writePattern, $file->contents);

            if ($writeCount >= 2) {
                $issues[] = $this->issue(
                    $this->id(),
                    $this->category(),
                    Severity::Warning,
                    'Multiple writes without visible transaction',
                    'This action appears to perform multiple database writes without wrapping them in a transaction.',
                    $file,
                    1,
                    'Wrap related writes in DB::transaction() so partial persistence does not leave inconsistent state.',
                );
            }
        }

        return $issues;
    }
}
