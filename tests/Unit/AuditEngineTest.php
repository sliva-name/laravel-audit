<?php

declare(strict_types=1);

namespace LaravelAudit\Tests\Unit;

use LaravelAudit\Analysis\Category;
use LaravelAudit\Audit\AuditEngine;
use LaravelAudit\Audit\AuditOptions;
use LaravelAudit\Audit\AuditProgressUpdate;
use LaravelAudit\Tests\TestCase;

final class AuditEngineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedSecurityFixture();
    }

    public function test_run_collects_security_findings_when_tools_are_disabled(): void
    {
        $report = $this->app->make(AuditEngine::class)->run(new AuditOptions(
            categories: [Category::Security->value],
            noTools: true,
        ));

        self::assertNotSame([], $report->issues);
        self::assertContains(
            'security.mass-assignment',
            array_map(fn ($issue) => $issue->ruleId, $report->issues),
        );
        self::assertSame([], $report->toolResults);
    }

    public function test_run_honors_category_filters(): void
    {
        $report = $this->app->make(AuditEngine::class)->run(new AuditOptions(
            categories: [Category::Performance->value],
            noTools: true,
        ));

        self::assertSame([], $report->issues);
    }

    public function test_run_emits_progress_updates_through_the_audit_pipeline(): void
    {
        $updates = [];

        $this->app->make(AuditEngine::class)->run(
            new AuditOptions(categories: [Category::Security->value], noTools: true),
            function (AuditProgressUpdate $update) use (&$updates): void {
                $updates[] = $update->message;
            },
        );

        self::assertContains('Scanning project files', $updates);
        self::assertTrue(
            collect($updates)->contains(fn (string $message): bool => str_starts_with($message, 'Running analyzer: ')),
        );
        self::assertContains('Finalizing report', $updates);
    }

    public function test_run_skips_disabled_analyzers(): void
    {
        $this->app['config']->set('laravel-audit.rules', [
            'security.mass-assignment' => false,
        ]);

        $this->seedSecurityFixture();

        $report = $this->app->make(AuditEngine::class)->run(new AuditOptions(
            categories: [Category::Security->value],
            noTools: true,
        ));

        self::assertNotContains(
            'security.mass-assignment',
            array_map(fn ($issue) => $issue->ruleId, $report->issues),
        );
    }

    private function seedSecurityFixture(): void
    {
        $modelsPath = $this->app->basePath().'/app/Models';

        if (! is_dir($modelsPath)) {
            mkdir($modelsPath, 0777, true);
        }

        file_put_contents($modelsPath.'/AuditFixture.php', <<<'PHP'
            <?php

            namespace App\Models;

            use Illuminate\Database\Eloquent\Model;

            final class AuditFixture extends Model
            {
            }
            PHP);
    }
}
