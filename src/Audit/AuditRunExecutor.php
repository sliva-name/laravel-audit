<?php

declare(strict_types=1);

namespace LaravelAudit\Audit;

use LaravelAudit\Repositories\AuditReportRepository;
use Throwable;

final class AuditRunExecutor
{
    public function __construct(
        private readonly AuditProgressTracker $tracker,
        private readonly AuditEngine $engine,
        private readonly AuditReportRepository $reports,
    ) {}

    public function execute(string $uuid): bool
    {
        $run = $this->tracker->get($uuid);

        if ($run === null) {
            return false;
        }

        $status = (string) ($run['status'] ?? 'queued');

        if ($status === 'completed') {
            return true;
        }

        if ($status === 'running') {
            return false;
        }

        if (! in_array($status, ['queued', 'failed'], true)) {
            return false;
        }

        if (! $this->tracker->tryClaim($uuid)) {
            $run = $this->tracker->get($uuid);

            return $run !== null && ($run['status'] ?? '') === 'completed';
        }

        $options = $this->tracker->optionsFromRun($uuid);

        if ($options === null) {
            $this->tracker->fail($uuid, 'Audit run options are missing.');

            return false;
        }

        @set_time_limit(0);

        try {
            $report = $this->engine->run(
                $options,
                fn ($update) => $this->tracker->update($uuid, $update),
            );

            $snapshot = $this->reports->store($report, $options);
            $this->tracker->complete($uuid, $snapshot->uuid);
        } catch (Throwable $exception) {
            $this->tracker->fail($uuid, $exception->getMessage());

            return false;
        }

        return true;
    }
}
