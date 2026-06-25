<?php

declare(strict_types=1);

namespace LaravelAudit\Tests\Unit;

use LaravelAudit\Audit\AuditOptions;
use LaravelAudit\Audit\AuditProgressTracker;
use LaravelAudit\Audit\AuditProgressUpdate;
use PHPUnit\Framework\TestCase;

final class AuditProgressTrackerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/laravel-audit-progress-'.uniqid('', true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*.json') ?: [] as $file) {
            unlink($file);
        }

        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }

        parent::tearDown();
    }

    public function test_it_tracks_run_progress_to_completion(): void
    {
        $tracker = new AuditProgressTracker($this->directory);
        $uuid = $tracker->create(new AuditOptions(noTools: true));

        $tracker->markRunning($uuid);
        $tracker->update($uuid, new AuditProgressUpdate('Running analyzer: security.raw-sql', 2, 10));

        $running = $tracker->get($uuid);
        $this->assertSame(20, $running['progress'] ?? null);

        $tracker->complete($uuid, 'report-uuid');

        $run = $tracker->get($uuid);

        $this->assertSame('completed', $run['status'] ?? null);
        $this->assertSame('report-uuid', $run['report_uuid'] ?? null);
        $this->assertSame(100, $run['progress'] ?? null);
        $this->assertContains('Running analyzer: security.raw-sql', $run['log'] ?? []);
    }
}
