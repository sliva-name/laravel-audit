<?php

declare(strict_types=1);

namespace LaravelAudit\Analysis;

enum Severity: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Error = 'error';
    case Critical = 'critical';

    public function rank(): int
    {
        return match ($this) {
            self::Info => 0,
            self::Warning => 1,
            self::Error => 2,
            self::Critical => 3,
        };
    }
}
