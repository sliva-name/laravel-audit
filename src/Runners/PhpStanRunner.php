<?php

declare(strict_types=1);

namespace LaravelAudit\Runners;

use LaravelAudit\Analysis\Category;
use LaravelAudit\Analysis\Issue;
use LaravelAudit\Analysis\Location;
use LaravelAudit\Analysis\Severity;

final class PhpStanRunner extends AbstractProcessRunner
{
    public function __construct(
        private readonly PhpStanConfigurationFactory $configurationFactory = new PhpStanConfigurationFactory,
    ) {}

    /**
     * @param  array{binary?: string, arguments?: list<string>, paths?: list<string>, auto_larastan?: bool, level?: int}  $config
     */
    public function run(string $basePath, array $config): ToolResult
    {
        $binary = $config['binary'] ?? 'vendor/bin/phpstan';
        /** @var list<string> $temporaryConfigs */
        $temporaryConfigs = [];

        try {
            $arguments = $this->arguments($basePath, $config, $temporaryConfigs);

            if (! $this->binaryAvailable($basePath, $binary)) {
                return new ToolResult('phpstan', false, 127, output: 'PHPStan binary was not found.');
            }

            $process = $this->runProcess($basePath, $binary, $arguments);
            $jsonOutput = trim($process->getOutput());
            $output = trim($jsonOutput.PHP_EOL.$process->getErrorOutput());

            return new ToolResult(
                tool: 'phpstan',
                available: true,
                exitCode: $process->getExitCode() ?? 1,
                issues: $this->issuesFromOutput($jsonOutput !== '' ? $jsonOutput : $output),
                output: $output,
            );
        } finally {
            foreach ($temporaryConfigs as $configPath) {
                @unlink($configPath);
            }
        }
    }

    /**
     * @param  array{arguments?: list<string>, paths?: list<string>, auto_larastan?: bool, level?: int}  $config
     * @param  list<string>  $temporaryConfigs
     * @return list<string>
     */
    private function arguments(string $basePath, array $config, array &$temporaryConfigs): array
    {
        $arguments = $config['arguments'] ?? ['analyse', '--error-format=json'];

        if ($this->configurationFactory->projectConfigPath($basePath) !== null) {
            return $arguments;
        }

        if ($this->hasExplicitConfiguration($arguments) || $this->hasExplicitAnalysisPath($arguments)) {
            return $arguments;
        }

        if ($this->shouldUseLarastan($basePath, $config)) {
            $configPath = $this->configurationFactory->createLarastanConfig(
                $basePath,
                $config['paths'] ?? [],
                (int) ($config['level'] ?? 5),
            );
            $temporaryConfigs[] = $configPath;

            return [
                ...$arguments,
                '--configuration='.$configPath,
            ];
        }

        return [
            ...$arguments,
            ...$this->existingProjectPaths($basePath, $config['paths'] ?? []),
        ];
    }

    /**
     * @param  array{auto_larastan?: bool}  $config
     */
    private function shouldUseLarastan(string $basePath, array $config): bool
    {
        if (($config['auto_larastan'] ?? true) === false) {
            return false;
        }

        return $this->configurationFactory->larastanExtensionPath($basePath) !== null;
    }

    /**
     * @param  list<string>  $arguments
     */
    private function hasExplicitConfiguration(array $arguments): bool
    {
        foreach ($arguments as $argument) {
            if ($argument === '-c' || $argument === '--configuration' || str_starts_with($argument, '--configuration=')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $arguments
     */
    private function hasExplicitAnalysisPath(array $arguments): bool
    {
        $analyseSeen = false;

        foreach ($arguments as $argument) {
            if ($argument === 'analyse' || $argument === 'analyze') {
                $analyseSeen = true;

                continue;
            }

            if (! $analyseSeen || str_starts_with($argument, '-')) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    private function existingProjectPaths(string $basePath, array $paths): array
    {
        return array_values(array_filter(
            $paths,
            fn (string $path): bool => file_exists($basePath.DIRECTORY_SEPARATOR.$path),
        ));
    }

    /**
     * @return list<Issue>
     */
    private function issuesFromOutput(string $output): array
    {
        $decoded = json_decode($output, true);

        if (! is_array($decoded) || ! isset($decoded['files']) || ! is_array($decoded['files'])) {
            return $output === '' ? [] : [
                new Issue(
                    ruleId: 'tooling.phpstan',
                    category: Category::Tooling,
                    severity: Severity::Error,
                    title: 'PHPStan analysis failed',
                    message: 'PHPStan returned a non-JSON response.',
                    location: new Location('phpstan.neon'),
                    recommendation: 'Run vendor/bin/phpstan analyse and inspect the raw output.',
                    metadata: ['output' => $output],
                ),
            ];
        }

        $issues = [];

        foreach ($decoded['files'] as $file => $details) {
            foreach (($details['messages'] ?? []) as $message) {
                $issues[] = new Issue(
                    ruleId: $message['identifier'] ?? 'tooling.phpstan',
                    category: Category::Tooling,
                    severity: Severity::Error,
                    title: 'PHPStan issue',
                    message: $message['message'] ?? 'PHPStan reported an issue.',
                    location: new Location((string) $file, (int) ($message['line'] ?? 1)),
                    recommendation: 'Fix the static analysis error or add a narrow ignore with justification.',
                    metadata: $message,
                );
            }
        }

        return $issues;
    }
}
