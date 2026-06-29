<?php

declare(strict_types=1);

namespace LaravelAudit\Tests\Unit;

use LaravelAudit\Analysis\Category;
use LaravelAudit\Analysis\Issue;
use LaravelAudit\Analysis\Location;
use LaravelAudit\Analysis\Severity;
use LaravelAudit\Reporting\AuditReport;
use LaravelAudit\Reporting\JsonReporter;
use LaravelAudit\Tests\TestCase;

final class AuditReportTest extends TestCase
{
    public function test_should_fail_when_issue_meets_threshold(): void
    {
        $report = new AuditReport([
            new Issue(
                ruleId: 'security.raw-sql',
                category: Category::Security,
                severity: Severity::Critical,
                title: 'Raw SQL',
                message: 'Raw SQL found.',
                location: new Location('app/Foo.php'),
            ),
        ], [], 0.1);

        self::assertTrue($report->shouldFail(Severity::Error));
        self::assertTrue($report->shouldFail(Severity::Critical));
    }

    public function test_should_not_fail_when_issues_are_below_threshold(): void
    {
        $report = new AuditReport([
            new Issue(
                ruleId: 'code-quality.long-method',
                category: Category::CodeQuality,
                severity: Severity::Warning,
                title: 'Long method',
                message: 'Method is long.',
                location: new Location('app/Foo.php'),
            ),
        ], [], 0.1);

        self::assertFalse($report->shouldFail(Severity::Error));
    }

    public function test_summary_counts_issues_by_severity(): void
    {
        $report = new AuditReport([
            $this->issue(Severity::Critical),
            $this->issue(Severity::Warning),
            $this->issue(Severity::Warning),
        ], [], 0.2);

        self::assertSame([
            'critical' => 1,
            'error' => 0,
            'warning' => 2,
            'info' => 0,
        ], $report->summary());
    }

    public function test_json_reporter_serializes_report_payload(): void
    {
        $report = new AuditReport([
            new Issue(
                ruleId: 'security.eval-usage',
                category: Category::Security,
                severity: Severity::Critical,
                title: 'Eval usage',
                message: 'Avoid eval().',
                location: new Location('app/Evil.php', 3),
            ),
        ], [], 0.5);

        $payload = json_decode((new JsonReporter)->render($report), true);

        self::assertIsArray($payload);
        self::assertSame('security.eval-usage', $payload['issues'][0]['ruleId'] ?? null);
        self::assertSame(0.5, $payload['durationSeconds'] ?? null);
    }

    private function issue(Severity $severity): Issue
    {
        return new Issue(
            ruleId: 'fixture.rule',
            category: Category::CodeQuality,
            severity: $severity,
            title: 'Fixture',
            message: 'Fixture issue.',
            location: new Location('app/Foo.php'),
        );
    }
}
