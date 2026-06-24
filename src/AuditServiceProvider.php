<?php

declare(strict_types=1);

namespace LaravelAudit;

use Illuminate\Support\ServiceProvider;
use LaravelAudit\Analysis\AnalyzerRegistry;
use LaravelAudit\Analyzers\BestPractices\FatControllerAnalyzer;
use LaravelAudit\Analyzers\BestPractices\MissingFormRequestAnalyzer;
use LaravelAudit\Analyzers\CodeQuality\LargeClassAnalyzer;
use LaravelAudit\Analyzers\CodeQuality\LongMethodAnalyzer;
use LaravelAudit\Analyzers\CodeQuality\RedundantBooleanReturnAnalyzer;
use LaravelAudit\Analyzers\CodeQuality\RedundantCatchRethrowAnalyzer;
use LaravelAudit\Analyzers\CodeQuality\RedundantElseAfterExitAnalyzer;
use LaravelAudit\Analyzers\CodeQuality\RedundantEmptyForeachGuardAnalyzer;
use LaravelAudit\Analyzers\CodeQuality\RedundantNullCoalesceAnalyzer;
use LaravelAudit\Analyzers\CodeQuality\RedundantTypeGuardAnalyzer;
use LaravelAudit\Analyzers\Performance\NPlusOneCandidateAnalyzer;
use LaravelAudit\Analyzers\Performance\SyncHeavyJobAnalyzer;
use LaravelAudit\Analyzers\Reliability\EnvAccessOutsideConfigAnalyzer;
use LaravelAudit\Analyzers\Reliability\MissingTransactionAnalyzer;
use LaravelAudit\Analyzers\Security\DebugConfigurationAnalyzer;
use LaravelAudit\Analyzers\Security\MassAssignmentAnalyzer;
use LaravelAudit\Analyzers\Security\RawSqlAnalyzer;
use LaravelAudit\Analyzers\Security\WeakValidationAnalyzer;
use LaravelAudit\Console\AnalyzeCommand;
use LaravelAudit\Project\ProjectScanner;
use LaravelAudit\Runners\PhpStanConfigurationFactory;
use LaravelAudit\Runners\PhpStanRunner;
use LaravelAudit\Runners\PintRunner;

final class AuditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/laravel-audit.php', 'laravel-audit');

        $this->app->singleton(ProjectScanner::class);
        $this->app->singleton(PintRunner::class);
        $this->app->singleton(PhpStanConfigurationFactory::class);
        $this->app->singleton(PhpStanRunner::class);

        $this->app->singleton(AnalyzerRegistry::class, function (): AnalyzerRegistry {
            return new AnalyzerRegistry([
                new RawSqlAnalyzer,
                new MassAssignmentAnalyzer,
                new WeakValidationAnalyzer,
                new DebugConfigurationAnalyzer,
                new NPlusOneCandidateAnalyzer,
                new SyncHeavyJobAnalyzer,
                new MissingTransactionAnalyzer,
                new EnvAccessOutsideConfigAnalyzer,
                new MissingFormRequestAnalyzer,
                new FatControllerAnalyzer,
                new LongMethodAnalyzer,
                new LargeClassAnalyzer,
                new RedundantBooleanReturnAnalyzer,
                new RedundantNullCoalesceAnalyzer,
                new RedundantEmptyForeachGuardAnalyzer,
                new RedundantCatchRethrowAnalyzer,
                new RedundantElseAfterExitAnalyzer,
                new RedundantTypeGuardAnalyzer,
            ]);
        });
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/laravel-audit.php' => config_path('laravel-audit.php'),
        ], 'laravel-audit-config');

        $this->commands([
            AnalyzeCommand::class,
        ]);
    }
}
