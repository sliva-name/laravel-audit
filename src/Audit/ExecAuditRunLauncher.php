<?php

declare(strict_types=1);

namespace LaravelAudit\Audit;

use Illuminate\Contracts\Foundation\Application;
use LaravelAudit\Audit\Contracts\AuditRunProcessLauncher;

final class ExecAuditRunLauncher implements AuditRunProcessLauncher
{
    public function __construct(
        private readonly Application $app,
    ) {}

    public function dispatch(string $runUuid): bool
    {
        $basePath = $this->app->basePath();
        $php = $this->phpBinary();
        $artisan = $this->artisanPath();
        $logFile = $this->logFile($runUuid);

        if (PHP_OS_FAMILY === 'Windows') {
            return $this->dispatchOnWindows($php, $artisan, $runUuid, $basePath, $logFile);
        }

        return $this->dispatchOnUnix($php, $artisan, $runUuid, $basePath, $logFile);
    }

    private function dispatchOnUnix(
        string $php,
        string $artisan,
        string $runUuid,
        string $basePath,
        string $logFile,
    ): bool {
        $this->ensureLogDirectory($logFile);

        $command = sprintf(
            'cd %s && nohup %s %s audit:run-stored %s >> %s 2>&1 &',
            escapeshellarg($basePath),
            escapeshellarg($php),
            escapeshellarg($artisan),
            escapeshellarg($runUuid),
            escapeshellarg($logFile),
        );

        if (! function_exists('exec')) {
            return false;
        }

        exec($command, $output, $exitCode);

        return $exitCode === 0;
    }

    private function dispatchOnWindows(
        string $php,
        string $artisan,
        string $runUuid,
        string $basePath,
        string $logFile,
    ): bool {
        $this->ensureLogDirectory($logFile);

        $command = sprintf(
            'start /B "" %s %s audit:run-stored %s >> %s 2>&1',
            escapeshellarg($php),
            escapeshellarg($artisan),
            escapeshellarg($runUuid),
            escapeshellarg($logFile),
        );

        if (! function_exists('popen')) {
            return false;
        }

        $handle = popen('cd /d '.escapeshellarg($basePath).' && '.$command, 'r');

        if ($handle === false) {
            return false;
        }

        pclose($handle);

        return true;
    }

    private function phpBinary(): string
    {
        return defined('PHP_BINARY') ? PHP_BINARY : 'php';
    }

    private function artisanPath(): string
    {
        return $this->app->basePath('artisan');
    }

    private function logFile(string $runUuid): string
    {
        $safeUuid = str_replace(['/', '\\', ':'], '-', $runUuid);

        return $this->app->storagePath('logs/laravel-audit-'.$safeUuid.'.log');
    }

    private function ensureLogDirectory(string $logFile): void
    {
        $directory = dirname($logFile);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }
}
