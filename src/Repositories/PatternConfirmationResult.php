<?php

declare(strict_types=1);

namespace LaravelAudit\Repositories;

use LaravelAudit\Models\AuditReportSnapshot;

final readonly class PatternConfirmationResult
{
    public function __construct(
        public AuditReportSnapshot $snapshot,
        public int $requestedCount,
        public int $reviewedCount,
    ) {}
}
