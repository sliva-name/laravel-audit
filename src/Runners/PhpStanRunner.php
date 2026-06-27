<?php

declare(strict_types=1);

namespace LaravelAudit\Runners;

use LaravelAudit\Analysis\Category;
use LaravelAudit\Analysis\Issue;
use LaravelAudit\Analysis\Location;
use LaravelAudit\Analysis\Severity;
use Symfony\Component\Process\Process;

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
            $cacheDirectory = $this->configurationFactory->cacheDirectory($basePath);

            if (! $this->binaryAvailable($basePath, $binary)) {
                return new ToolResult('phpstan', false, 127, output: 'PHPStan binary was not found.');
            }

            $process = $this->runProcess($basePath, $binary, $arguments, [
                'TMPDIR' => $cacheDirectory.DIRECTORY_SEPARATOR.'tmp',
            ]);
            $jsonOutput = $this->jsonOutputFromProcess($process);
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
        $arguments = $this->normalizeArguments($config['arguments'] ?? ['analyse', '--error-format=json']);

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
                (int) ($config['level'] ?? PhpStanConfigurationFactory::MAX_LEVEL),
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
     * @param  list<string>  $arguments
     * @return list<string>
     */
    private function normalizeArguments(array $arguments): array
    {
        $hasNoProgress = false;
        $hasMemoryLimit = false;

        foreach ($arguments as $argument) {
            if ($argument === '--no-progress') {
                $hasNoProgress = true;
            }

            if (str_starts_with($argument, '--memory-limit=')) {
                $hasMemoryLimit = true;
            }
        }

        if (! $hasNoProgress) {
            $arguments[] = '--no-progress';
        }

        if (! $hasMemoryLimit) {
            $arguments[] = '--memory-limit=1G';
        }

        return $arguments;
    }

    private function jsonOutputFromProcess(Process $process): string
    {
        $stdout = trim($process->getOutput());

        if ($stdout === '') {
            return '';
        }

        if ($this->decodeJsonOutput($stdout) !== null) {
            return $stdout;
        }

        if (preg_match('/\{\s*"(?:totals|files)"\s*:/s', $stdout, $matches, PREG_OFFSET_CAPTURE) === 1) {
            return substr($stdout, $matches[0][1]);
        }

        return $stdout;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJsonOutput(string $output): ?array
    {
        $decoded = json_decode($output, true);

        return is_array($decoded) ? $decoded : null;
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
        $decoded = $this->decodeJsonOutput($output);

        if (! is_array($decoded)) {
            return $output === '' ? [] : [$this->nonJsonIssue($output)];
        }

        $issues = [];

        if (isset($decoded['files']) && is_array($decoded['files'])) {
            foreach ($decoded['files'] as $file => $details) {
                if (! is_array($details)) {
                    continue;
                }

                foreach (($details['messages'] ?? []) as $message) {
                    if (! is_array($message)) {
                        continue;
                    }

                    $issues[] = new Issue(
                        ruleId: is_string($message['identifier'] ?? null) ? $message['identifier'] : 'tooling.phpstan',
                        category: Category::Tooling,
                        severity: Severity::Error,
                        title: 'PHPStan issue',
                        message: is_string($message['message'] ?? null) ? $message['message'] : 'PHPStan reported an issue.',
                        location: new Location((string) $file, (int) ($message['line'] ?? 1)),
                        recommendation: 'Fix the static analysis error or add a narrow ignore with justification.',
                        metadata: $message,
                    );
                }
            }
        }

        if (isset($decoded['errors']) && is_array($decoded['errors'])) {
            foreach ($decoded['errors'] as $index => $error) {
                if (! is_string($error) || trim($error) === '') {
                    continue;
                }

                [$file, $line] = $this->locationFromRunnerError($error);

                $issues[] = new Issue(
                    ruleId: 'tooling.phpstan.runner',
                    category: Category::Tooling,
                    severity: Severity::Error,
                    title: 'PHPStan runner error',
                    message: $this->summaryFromRunnerError($error),
                    location: new Location($file, $line),
                    recommendation: 'Fix the bootstrap or configuration error, then rerun PHPStan.',
                    metadata: [
                        'error' => $error,
                        'index' => $index,
                    ],
                );
            }
        }

        if ($issues !== [] || $output === '') {
            return $issues;
        }

        return [$this->nonJsonIssue($output)];
    }

    private function nonJsonIssue(string $output): Issue
    {
        return new Issue(
            ruleId: 'tooling.phpstan',
            category: Category::Tooling,
            severity: Severity::Error,
            title: 'PHPStan analysis failed',
            message: 'PHPStan returned a non-JSON response.',
            location: new Location('phpstan.neon'),
            recommendation: 'Run vendor/bin/phpstan analyse and inspect the raw output.',
            metadata: ['output' => $output],
        );
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function locationFromRunnerError(string $error): array
    {
        if (preg_match('/\bat\s+(\S+\.php):(\d+)\b/', $error, $matches) === 1) {
            return [$matches[1], (int) $matches[2]];
        }

        if (preg_match('/\bin\s+(\S+\.php)\s+on line\s+(\d+)/', $error, $matches) === 1) {
            return [$matches[1], (int) $matches[2]];
        }

        return ['phpstan.neon', 1];
    }

    private function summaryFromRunnerError(string $error): string
    {
        if (preg_match('/\n\s*([^\n]+)\n\n/s', $error, $matches) === 1) {
            return trim($matches[1]);
        }

        $firstLine = trim(strtok($error, "\n") ?: $error);

        return strlen($firstLine) > 240 ? substr($firstLine, 0, 237).'...' : $firstLine;
    }
}
