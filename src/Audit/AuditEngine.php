<?php

declare(strict_types=1);

namespace LaravelAudit\Audit;

use Illuminate\Contracts\Foundation\Application;
use LaravelAudit\Analysis\AnalysisContext;
use LaravelAudit\Analysis\AnalyzerRegistry;
use LaravelAudit\Pattern\PatternAdvisorFactory;
use LaravelAudit\Project\ProjectScanner;
use LaravelAudit\Reporting\AuditReport;
use LaravelAudit\Runners\PhpStanRunner;
use LaravelAudit\Runners\PintRunner;

final class AuditEngine
{
    public function __construct(
        private readonly Application $app,
        private readonly ProjectScanner $scanner,
        private readonly AnalyzerRegistry $registry,
        private readonly PintRunner $pint,
        private readonly PhpStanRunner $phpstan,
        private readonly PatternAdvisorFactory $patternAdvisorFactory,
    ) {}

    public function run(?AuditOptions $options = null): AuditReport
    {
        $options ??= new AuditOptions;
        $startedAt = microtime(true);
        $config = config('laravel-audit', []);
        $basePath = $this->app->basePath();
        $context = new AnalysisContext($basePath, $this->scanner->scan($config), $config);
        $issues = [];
        $toolResults = [];

        foreach ($this->registry->enabledFor($context, $options->categories) as $analyzer) {
            array_push($issues, ...$analyzer->analyze($context));
        }

        if (! $options->noTools) {
            if ((bool) data_get($config, 'tools.pint.enabled', true)) {
                $toolResults[] = $this->pint->run($basePath, data_get($config, 'tools.pint', []));
            }

            if ((bool) data_get($config, 'tools.phpstan.enabled', true)) {
                $phpstanConfig = data_get($config, 'tools.phpstan', []);

                if (is_array($phpstanConfig)) {
                    $phpstanConfig['paths'] = $config['paths'] ?? [];
                }

                $toolResults[] = $this->phpstan->run($basePath, is_array($phpstanConfig) ? $phpstanConfig : []);
            }

            foreach ($toolResults as $toolResult) {
                array_push($issues, ...$toolResult->issues);
            }
        }

        $patternSuggestions = [];

        if ($this->shouldInferPatterns($config, $options)) {
            $useLlm = $options->llm || (bool) data_get($config, 'patterns.llm.enabled', false);
            $useHeuristic = $options->patterns || (bool) data_get($config, 'patterns.enabled', false);
            $patternAdvisor = $this->patternAdvisorFactory->make($config, $useHeuristic, $useLlm);
            $patternSuggestions = $patternAdvisor->suggest($context->project, $issues);
        }

        return new AuditReport(
            issues: $issues,
            toolResults: $toolResults,
            durationSeconds: microtime(true) - $startedAt,
            patternSuggestions: $patternSuggestions,
        );
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function shouldInferPatterns(array $config, AuditOptions $options): bool
    {
        if ($options->patterns || $options->llm) {
            return true;
        }

        return (bool) data_get($config, 'patterns.enabled', false)
            || (bool) data_get($config, 'patterns.llm.enabled', false);
    }
}
