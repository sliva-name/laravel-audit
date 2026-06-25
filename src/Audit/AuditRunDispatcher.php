<?php

declare(strict_types=1);

namespace LaravelAudit\Audit;

use LaravelAudit\Audit\Contracts\AuditRunProcessLauncher;
use LaravelAudit\Jobs\RunStoredAuditJob;

final class AuditRunDispatcher
{
    public function __construct(
        private readonly AuditRunProcessLauncher $process,
    ) {}

    public function dispatch(string $runUuid): bool
    {
        $runner = (string) config('laravel-audit.dashboard.runner', 'queue');

        if ($runner === 'process') {
            return $this->process->dispatch($runUuid);
        }

        return $this->dispatchQueued($runUuid);
    }

    private function dispatchQueued(string $runUuid): bool
    {
        $pending = RunStoredAuditJob::dispatch($runUuid);

        $connection = config('laravel-audit.dashboard.queue_connection');
        $queue = config('laravel-audit.dashboard.queue');

        if (is_string($connection) && $connection !== '') {
            $pending->onConnection($connection);
        }

        if (is_string($queue) && $queue !== '') {
            $pending->onQueue($queue);
        }

        return true;
    }
}
