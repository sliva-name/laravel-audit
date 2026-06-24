<?php

declare(strict_types=1);

namespace LaravelAudit\Runners;

use LaravelAudit\Analysis\Category;
use LaravelAudit\Analysis\Issue;
use LaravelAudit\Analysis\Location;
use LaravelAudit\Analysis\Severity;

final class PintRunner extends AbstractProcessRunner
{
    /**
     * @param  array{binary?: string, arguments?: list<string>}  $config
     */
    public function run(string $basePath, array $config): ToolResult
    {
        $binary = $config['binary'] ?? 'vendor/bin/pint';
        $arguments = $config['arguments'] ?? ['--test'];

        if (! $this->binaryAvailable($basePath, $binary)) {
            return new ToolResult('pint', false, 127, output: 'Pint binary was not found.');
        }

        $process = $this->runProcess($basePath, $binary, $arguments);
        $output = trim($process->getOutput().PHP_EOL.$process->getErrorOutput());
        $issues = [];

        if (! $process->isSuccessful()) {
            $issues[] = new Issue(
                ruleId: 'tooling.pint',
                category: Category::Tooling,
                severity: Severity::Warning,
                title: 'Pint style violations found',
                message: 'Laravel Pint reported code style differences.',
                location: new Location('composer.json'),
                recommendation: 'Run vendor/bin/pint locally and commit the resulting formatting changes.',
                metadata: ['output' => $output],
            );
        }

        return new ToolResult('pint', true, $process->getExitCode() ?? 1, $issues, $output);
    }
}
