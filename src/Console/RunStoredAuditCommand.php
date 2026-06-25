<?php

declare(strict_types=1);

namespace LaravelAudit\Console;

use Illuminate\Console\Command;
use LaravelAudit\Audit\AuditRunExecutor;

final class RunStoredAuditCommand extends Command
{
    protected $signature = 'audit:run-stored {uuid : The queued audit run UUID}';

    protected $description = 'Execute a queued audit run and persist its report.';

    public function handle(AuditRunExecutor $executor): int
    {
        $uuid = (string) $this->argument('uuid');

        if (! $executor->execute($uuid)) {
            $this->error("Audit run [{$uuid}] failed or was not found.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
