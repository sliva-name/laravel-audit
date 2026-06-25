<?php

declare(strict_types=1);

namespace LaravelAudit\Repositories;

use Illuminate\Support\Str;
use LaravelAudit\Audit\AuditOptions;
use LaravelAudit\Models\AuditReportRecord;
use LaravelAudit\Reporting\AuditReport;

final class AuditReportRepository
{
    public function store(AuditReport $report, AuditOptions $options): AuditReportRecord
    {
        $summary = $report->summary();

        return AuditReportRecord::query()->create([
            'uuid' => (string) Str::uuid(),
            'critical_count' => $summary['critical'] ?? 0,
            'error_count' => $summary['error'] ?? 0,
            'warning_count' => $summary['warning'] ?? 0,
            'info_count' => $summary['info'] ?? 0,
            'issues_count' => count($report->issues),
            'pattern_count' => count($report->patternSuggestions),
            'duration_seconds' => $report->durationSeconds,
            'payload' => $report->toArray(),
            'options' => $options->toArray(),
        ]);
    }

    /**
     * @return list<AuditReportRecord>
     */
    public function latest(int $limit = 50): array
    {
        /** @var list<AuditReportRecord> $reports */
        $reports = AuditReportRecord::query()
            ->latest()
            ->limit($limit)
            ->get()
            ->all();

        return $reports;
    }

    public function findByUuid(string $uuid): ?AuditReportRecord
    {
        return AuditReportRecord::query()
            ->where('uuid', $uuid)
            ->first();
    }
}
