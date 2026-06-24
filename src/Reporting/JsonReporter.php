<?php

declare(strict_types=1);

namespace LaravelAudit\Reporting;

final class JsonReporter
{
    public function render(AuditReport $report): string
    {
        return json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
    }
}
