<?php

declare(strict_types=1);

namespace LaravelAudit\Tests\Unit;

use LaravelAudit\Audit\AuditOptions;
use LaravelAudit\Reporting\AuditReport;
use LaravelAudit\Repositories\FileAuditReportStore;
use PHPUnit\Framework\TestCase;

final class FileAuditReportStoreTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/laravel-audit-store-'.uniqid('', true);
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

    public function test_it_persists_and_reads_reports_without_database(): void
    {
        $store = new FileAuditReportStore($this->directory);

        $snapshot = $store->store(
            new AuditReport(issues: [], toolResults: [], durationSeconds: 1.5),
            new AuditOptions(noTools: true, patterns: true),
        );

        $this->assertFileExists($this->directory.'/'.$snapshot->uuid.'.json');
        $this->assertSame($snapshot->uuid, $store->findByUuid($snapshot->uuid)?->uuid);
        $this->assertCount(1, $store->latest());
    }
}
