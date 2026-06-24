<?php

declare(strict_types=1);

namespace LaravelAudit\Console;

use Illuminate\Console\Command;
use LaravelAudit\Analysis\AnalysisContext;
use LaravelAudit\Analysis\AnalyzerRegistry;
use LaravelAudit\Analysis\Severity;
use LaravelAudit\Project\ProjectScanner;
use LaravelAudit\Reporting\AuditReport;
use LaravelAudit\Reporting\ConsoleReporter;
use LaravelAudit\Reporting\JsonReporter;
use LaravelAudit\Reporting\SarifReporter;
use LaravelAudit\Runners\PhpStanRunner;
use LaravelAudit\Runners\PintRunner;

final class AnalyzeCommand extends Command
{
    protected $signature = 'audit:analyze
        {--format=console : Output format: console, json, or sarif}
        {--fail-on= : Minimum severity that should produce a non-zero exit code}
        {--only= : Comma-separated analyzer categories to run}
        {--no-tools : Skip Pint and PHPStan runners}';

    protected $description = 'Analyze a Laravel project with Pint, PHPStan/Larastan, and Laravel-specific audit rules.';

    public function handle(
        ProjectScanner $scanner,
        AnalyzerRegistry $registry,
        PintRunner $pint,
        PhpStanRunner $phpstan,
    ): int {
        $startedAt = microtime(true);
        $config = config('laravel-audit', []);
        $basePath = $this->laravel->basePath();
        $context = new AnalysisContext($basePath, $scanner->scan($config), $config);
        $categories = $this->categories();
        $issues = [];
        $toolResults = [];

        foreach ($registry->enabledFor($context, $categories) as $analyzer) {
            array_push($issues, ...$analyzer->analyze($context));
        }

        if (! $this->option('no-tools')) {
            if ((bool) data_get($config, 'tools.pint.enabled', true)) {
                $toolResults[] = $pint->run($basePath, data_get($config, 'tools.pint', []));
            }

            if ((bool) data_get($config, 'tools.phpstan.enabled', true)) {
                $phpstanConfig = data_get($config, 'tools.phpstan', []);

                if (is_array($phpstanConfig)) {
                    $phpstanConfig['paths'] = $config['paths'] ?? [];
                }

                $toolResults[] = $phpstan->run($basePath, is_array($phpstanConfig) ? $phpstanConfig : []);
            }

            foreach ($toolResults as $toolResult) {
                array_push($issues, ...$toolResult->issues);
            }
        }

        $report = new AuditReport(
            issues: $issues,
            toolResults: $toolResults,
            durationSeconds: microtime(true) - $startedAt,
        );

        $this->renderReport($report);

        return $report->shouldFail($this->failOn($config)) ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function categories(): array
    {
        $only = $this->option('only');

        if (! is_string($only) || trim($only) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $only))));
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function failOn(array $config): Severity
    {
        $value = $this->option('fail-on') ?: data_get($config, 'reporting.fail_on', 'error');

        return Severity::tryFrom((string) $value) ?? Severity::Error;
    }

    private function renderReport(AuditReport $report): void
    {
        if ($this->option('format') === 'json') {
            $this->line((new JsonReporter)->render($report));

            return;
        }

        if ($this->option('format') === 'sarif') {
            $this->line((new SarifReporter)->render($report));

            return;
        }

        (new ConsoleReporter)->render($this, $report);
    }
}
