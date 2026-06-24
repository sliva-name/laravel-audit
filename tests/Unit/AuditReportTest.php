<?php

declare(strict_types=1);

namespace LaravelAudit\Tests\Unit;

use LaravelAudit\Analysis\Category;
use LaravelAudit\Analysis\Issue;
use LaravelAudit\Analysis\Location;
use LaravelAudit\Analysis\Severity;
use LaravelAudit\Reporting\AuditReport;
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
}
