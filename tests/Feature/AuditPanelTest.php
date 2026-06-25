<?php

declare(strict_types=1);

namespace LaravelAudit\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use LaravelAudit\Models\AuditReportRecord;
use LaravelAudit\Tests\TestCase;

final class AuditPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_is_accessible(): void
    {
        $this->get('/audit')
            ->assertOk()
            ->assertSee('Overview')
            ->assertSee('All Reports');
    }

    public function test_reports_index_lists_saved_reports(): void
    {
        AuditReportRecord::query()->create([
            'uuid' => '11111111-1111-1111-1111-111111111111',
            'critical_count' => 0,
            'error_count' => 1,
            'warning_count' => 2,
            'info_count' => 0,
            'issues_count' => 3,
            'pattern_count' => 0,
            'duration_seconds' => 1.25,
            'payload' => ['issues' => [], 'patternSuggestions' => []],
            'options' => ['no_tools' => true],
        ]);

        $this->get('/audit/reports')
            ->assertOk()
            ->assertSee('11111111-1111-1111-1111-111111111111');
    }

    public function test_report_show_displays_details(): void
    {
        AuditReportRecord::query()->create([
            'uuid' => '22222222-2222-2222-2222-222222222222',
            'critical_count' => 0,
            'error_count' => 1,
            'warning_count' => 0,
            'info_count' => 0,
            'issues_count' => 1,
            'pattern_count' => 0,
            'duration_seconds' => 0.5,
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
            'options' => ['no_tools' => true],
        ]);

        $this->get('/audit/reports/22222222-2222-2222-2222-222222222222')
            ->assertOk()
            ->assertSee('Raw SQL usage')
            ->assertSee('app/Http/Controllers/Foo.php');
    }

    public function test_run_analysis_stores_report(): void
    {
        $this->post('/audit/reports', [
            'no_tools' => '1',
        ])->assertRedirect();

        $this->assertDatabaseCount('audit_reports', 1);
    }
}
