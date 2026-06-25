<?php

declare(strict_types=1);

namespace LaravelAudit\Repositories;

use LaravelAudit\Audit\AuditOptions;
use LaravelAudit\Models\AuditReportSnapshot;
use LaravelAudit\Reporting\AuditReport;
use LaravelAudit\Repositories\Contracts\AuditReportStore;

final class AuditReportRepository
{
    public function __construct(
        private readonly AuditReportStore $store,
    ) {}

    public function store(AuditReport $report, AuditOptions $options): AuditReportSnapshot
    {
        return $this->store->store($report, $options);
    }

    /**
     * @return list<AuditReportSnapshot>
     */
    public function latest(int $limit = 50): array
    {
        return $this->store->latest($limit);
    }

    public function findByUuid(string $uuid): ?AuditReportSnapshot
    {
        return $this->store->findByUuid($uuid);
    }
}
