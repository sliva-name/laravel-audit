<?php

declare(strict_types=1);

namespace LaravelAudit\Repositories\Contracts;

use LaravelAudit\Audit\AuditOptions;
use LaravelAudit\Models\AuditReportSnapshot;
use LaravelAudit\Reporting\AuditReport;

interface AuditReportStore
{
    public function store(AuditReport $report, AuditOptions $options): AuditReportSnapshot;

    /**
     * @return list<AuditReportSnapshot>
     */
    public function latest(int $limit = 50): array;

    public function findByUuid(string $uuid): ?AuditReportSnapshot;
}
