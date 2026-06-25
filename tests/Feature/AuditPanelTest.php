<?php

declare(strict_types=1);

namespace LaravelAudit\Tests\Feature;

use LaravelAudit\Audit\AuditOptions;
use LaravelAudit\Reporting\AuditReport;
use LaravelAudit\Repositories\FileAuditReportStore;
use LaravelAudit\Tests\TestCase;

final class AuditPanelTest extends TestCase
{
    private string $reportsDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reportsDirectory = sys_get_temp_dir().'/laravel-audit-panel-'.uniqid('', true);
        $this->app['config']->set('laravel-audit.dashboard.storage', 'file');
        $this->app['config']->set('laravel-audit.dashboard.storage_path', $this->reportsDirectory);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->reportsDirectory.'/*.json') ?: [] as $file) {
            unlink($file);
        }

        if (is_dir($this->reportsDirectory)) {
            rmdir($this->reportsDirectory);
        }

        parent::tearDown();
    }

    public function test_dashboard_is_accessible(): void
    {
        $this->get('/audit')
            ->assertOk()
            ->assertSee('Overview')
            ->assertSee('All Reports');
    }

    public function test_reports_index_lists_saved_reports(): void
    {
        $store = new FileAuditReportStore($this->reportsDirectory);
        $snapshot = $store->store(new AuditReport(issues: [], toolResults: [], durationSeconds: 1.25), new AuditOptions(noTools: true));

        $this->get('/audit/reports')
            ->assertOk()
            ->assertSee($snapshot->uuid);
    }

    public function test_report_show_displays_details(): void
    {
        $store = new FileAuditReportStore($this->reportsDirectory);
        $snapshot = $store->store(new AuditReport(
            issues: [],
            toolResults: [],
            durationSeconds: 0.5,
        ), new AuditOptions(noTools: true));

        file_put_contents(
            $this->reportsDirectory.'/'.$snapshot->uuid.'.json',
            json_encode([
                ...$snapshot->toArray(),
                'payload' => [
                    'issues' => [[
                        'ruleId' => 'security.raw-sql',
                        'severity' => 'error',
                        'title' => 'Raw SQL usage',
                        'message' => 'Avoid raw SQL in controllers.',
                        'location' => ['file' => 'app/Http/Controllers/Foo.php', 'line' => 10],
                        'recommendation' => 'Use Eloquent.',
                    ]],
                    'patternSuggestions' => [],
                ],
            ], JSON_THROW_ON_ERROR),
        );

        $this->get('/audit/reports/'.$snapshot->uuid)
            ->assertOk()
            ->assertSee('Raw SQL usage')
            ->assertSee('app/Http/Controllers/Foo.php');
    }

    public function test_run_analysis_stores_report(): void
    {
        $this->post('/audit/reports', [
            'no_tools' => '1',
        ])->assertRedirect();

        $this->assertCount(1, glob($this->reportsDirectory.'/*.json') ?: []);
    }
}
