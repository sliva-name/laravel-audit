<?php

declare(strict_types=1);

namespace LaravelAudit\Audit;

use Illuminate\Contracts\Foundation\Application;
use Symfony\Component\Process\Process;

final class AuditRunDispatcher
{
    public function __construct(
        private readonly Application $app,
    ) {}

    public function dispatch(string $runUuid): void
    {
        $process = new Process(
            [
                $this->phpBinary(),
                $this->artisanPath(),
                'audit:run-stored',
                $runUuid,
            ],
            $this->app->basePath(),
        );

        $process->start();
    }

    private function phpBinary(): string
    {
        return defined('PHP_BINARY') ? PHP_BINARY : 'php';
    }

    private function artisanPath(): string
    {
        return $this->app->basePath('artisan');
    }
}
