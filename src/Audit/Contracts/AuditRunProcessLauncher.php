<?php

declare(strict_types=1);

namespace LaravelAudit\Audit\Contracts;

interface AuditRunProcessLauncher
{
    public function dispatch(string $runUuid): bool;
}
