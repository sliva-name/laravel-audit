<?php

declare(strict_types=1);

namespace LaravelAudit\Audit;

final class AuditProgressUpdate
{
    public function __construct(
        public readonly string $message,
        public readonly int $currentStep,
        public readonly int $totalSteps,
    ) {}

    public function percent(): int
    {
        if ($this->totalSteps <= 0) {
            return 0;
        }

        return (int) min(100, round(($this->currentStep / $this->totalSteps) * 100));
    }
}
