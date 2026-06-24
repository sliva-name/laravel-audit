<?php

declare(strict_types=1);

namespace LaravelAudit\Analysis;

interface AnalyzerInterface
{
    public function id(): string;

    public function category(): Category;

    /**
     * @return list<Issue>
     */
    public function analyze(AnalysisContext $context): array;
}
