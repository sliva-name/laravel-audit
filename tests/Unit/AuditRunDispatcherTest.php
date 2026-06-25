<?php

declare(strict_types=1);

namespace LaravelAudit\Tests\Unit;

use Illuminate\Support\Facades\Queue;
use LaravelAudit\Audit\AuditRunDispatcher;
use LaravelAudit\Audit\Contracts\AuditRunProcessLauncher;
use LaravelAudit\Jobs\RunStoredAuditJob;
use LaravelAudit\Tests\TestCase;

final class AuditRunDispatcherTest extends TestCase
{
    public function test_it_dispatches_queue_job_by_default(): void
    {
        Queue::fake();

        $this->app['config']->set('laravel-audit.dashboard.runner', 'queue');

        $this->assertTrue($this->app->make(AuditRunDispatcher::class)->dispatch('run-uuid'));

        Queue::assertPushed(RunStoredAuditJob::class, fn (RunStoredAuditJob $job): bool => $job->runUuid === 'run-uuid');
    }

    public function test_it_can_use_process_runner(): void
    {
        $this->app->instance(AuditRunProcessLauncher::class, new class implements AuditRunProcessLauncher
        {
            public function dispatch(string $runUuid): bool
            {
                return $runUuid === 'run-uuid';
            }
        });
        $this->app['config']->set('laravel-audit.dashboard.runner', 'process');

        $this->assertTrue($this->app->make(AuditRunDispatcher::class)->dispatch('run-uuid'));
    }
}
