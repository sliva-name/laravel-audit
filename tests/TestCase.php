<?php

declare(strict_types=1);

namespace LaravelAudit\Tests;

use Illuminate\Foundation\Application;
use LaravelAudit\AuditServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            AuditServiceProvider::class,
        ];
    }
}
