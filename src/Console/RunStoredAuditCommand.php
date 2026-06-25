<?php

declare(strict_types=1);

namespace LaravelAudit\Console;

use Illuminate\Console\Command;
use LaravelAudit\Audit\AuditEngine;
use LaravelAudit\Audit\AuditProgressTracker;
use LaravelAudit\Repositories\AuditReportRepository;
use Throwable;

final class RunStoredAuditCommand extends Command
{
    protected $signature = 'audit:run-stored {uuid : The queued audit run UUID}';

    protected $description = 'Execute a queued audit run and persist its report.';

    public function handle(
        AuditProgressTracker $tracker,
        AuditEngine $engine,
        AuditReportRepository $reports,
    ): int {
        $uuid = (string) $this->argument('uuid');
        $options = $tracker->optionsFromRun($uuid);

        if ($options === null) {
            $this->error("Audit run [{$uuid}] was not found.");

            return self::FAILURE;
        }

        $tracker->markRunning($uuid);

        try {
            $report = $engine->run(
                $options,
                fn ($update) => $tracker->update($uuid, $update),
            );

            $snapshot = $reports->store($report, $options);
            $tracker->complete($uuid, $snapshot->uuid);
        } catch (Throwable $exception) {
            $tracker->fail($uuid, $exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
