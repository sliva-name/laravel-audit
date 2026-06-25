<?php

declare(strict_types=1);

namespace LaravelAudit\Audit;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final class AuditProgressTracker
{
    public function __construct(
        private readonly string $directory,
    ) {}

    public function create(AuditOptions $options): string
    {
        $this->ensureDirectory();

        $uuid = (string) Str::uuid();
        $this->write($uuid, [
            'uuid' => $uuid,
            'status' => 'queued',
            'progress' => 0,
            'current_step' => 0,
            'total_steps' => 0,
            'message' => 'Queued',
            'log' => ['Audit queued.'],
            'report_uuid' => null,
            'error' => null,
            'options' => $options->toArray(),
            'created_at' => Carbon::now()->toIso8601String(),
            'updated_at' => Carbon::now()->toIso8601String(),
        ]);

        return $uuid;
    }

    public function markRunning(string $uuid): void
    {
        $this->patch($uuid, [
            'status' => 'running',
            'message' => 'Starting audit...',
        ], 'Starting audit...');
    }

    public function update(string $uuid, AuditProgressUpdate $update): void
    {
        $this->patch($uuid, [
            'status' => 'running',
            'progress' => $update->percent(),
            'current_step' => $update->currentStep,
            'total_steps' => $update->totalSteps,
            'message' => $update->message,
        ], $update->message);
    }

    public function complete(string $uuid, string $reportUuid): void
    {
        $this->patch($uuid, [
            'status' => 'completed',
            'progress' => 100,
            'message' => 'Audit completed.',
            'report_uuid' => $reportUuid,
        ], 'Audit completed.');
    }

    public function fail(string $uuid, string $error): void
    {
        $this->patch($uuid, [
            'status' => 'failed',
            'message' => 'Audit failed.',
            'error' => $error,
        ], 'Audit failed: '.$error);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $uuid): ?array
    {
        $path = $this->pathFor($uuid);

        if (! is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return null;
        }

        /** @var array<string, mixed>|null $data */
        $data = json_decode($contents, true);

        return is_array($data) ? $data : null;
    }

    public function optionsFromRun(string $uuid): ?AuditOptions
    {
        $run = $this->get($uuid);
        $options = $run['options'] ?? null;

        if (! is_array($options)) {
            return null;
        }

        return AuditOptions::fromArray($options);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function patch(string $uuid, array $changes, ?string $logLine = null): void
    {
        $current = $this->get($uuid) ?? ['uuid' => $uuid, 'log' => []];
        $log = is_array($current['log'] ?? null) ? $current['log'] : [];

        if ($logLine !== null && ($log === [] || $log[array_key_last($log)] !== $logLine)) {
            $log[] = $logLine;
        }

        $this->write($uuid, [
            ...$current,
            ...$changes,
            'log' => $log,
            'updated_at' => Carbon::now()->toIso8601String(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function write(string $uuid, array $data): void
    {
        $this->ensureDirectory();

        $path = $this->pathFor($uuid);
        $written = file_put_contents($path, json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

        if ($written === false) {
            throw new \RuntimeException("Unable to write audit run state to [{$path}].");
        }
    }

    private function pathFor(string $uuid): string
    {
        return $this->directory.'/'.str_replace(['/', '\\', "\0"], '', $uuid).'.json';
    }

    private function ensureDirectory(): void
    {
        if (is_dir($this->directory)) {
            return;
        }

        if (! mkdir($this->directory, 0755, true) && ! is_dir($this->directory)) {
            throw new \RuntimeException("Unable to create audit run directory [{$this->directory}].");
        }
    }
}
