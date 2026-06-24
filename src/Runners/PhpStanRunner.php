<?php

declare(strict_types=1);

namespace LaravelAudit\Runners;

use LaravelAudit\Analysis\Category;
use LaravelAudit\Analysis\Issue;
use LaravelAudit\Analysis\Location;
use LaravelAudit\Analysis\Severity;

final class PhpStanRunner extends AbstractProcessRunner
{
    /**
     * @param  array{binary?: string, arguments?: list<string>}  $config
     */
    public function run(string $basePath, array $config): ToolResult
    {
        $binary = $config['binary'] ?? 'vendor/bin/phpstan';
        $arguments = $config['arguments'] ?? ['analyse', '--error-format=json'];

        if (! $this->binaryAvailable($basePath, $binary)) {
            return new ToolResult('phpstan', false, 127, output: 'PHPStan binary was not found.');
        }

        $process = $this->runProcess($basePath, $binary, $arguments);
        $output = trim($process->getOutput().PHP_EOL.$process->getErrorOutput());

        return new ToolResult(
            tool: 'phpstan',
            available: true,
            exitCode: $process->getExitCode() ?? 1,
            issues: $this->issuesFromOutput($output),
            output: $output,
        );
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
