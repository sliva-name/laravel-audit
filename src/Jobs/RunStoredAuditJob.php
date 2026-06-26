<?php

declare(strict_types=1);

namespace LaravelAudit\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use LaravelAudit\Audit\AuditProgressTracker;
use LaravelAudit\Audit\AuditRunExecutor;
use LaravelAudit\Audit\AuditRunJobTimeout;
use RuntimeException;
use Throwable;

final class RunStoredAuditJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout;

    public function __construct(
        public readonly string $runUuid,
    ) {
        $options = app(AuditProgressTracker::class)->optionsFromRun($runUuid);

        $this->timeout = AuditRunJobTimeout::forOptions($options);
    }

    public function handle(AuditRunExecutor $executor): void
    {
        if (! $executor->execute($this->runUuid)) {
            throw new RuntimeException('Audit run failed or was already processed.');
        }
    }

    public function failed(?Throwable $exception): void
    {
        app(AuditProgressTracker::class)->fail(
            $this->runUuid,
            $exception?->getMessage() ?? 'Queue worker failed to run audit.',
        );
    }
}
