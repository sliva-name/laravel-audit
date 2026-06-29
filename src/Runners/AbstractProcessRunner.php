<?php

declare(strict_types=1);

namespace LaravelAudit\Runners;

use LaravelAudit\Analysis\Category;
use LaravelAudit\Analysis\Issue;
use LaravelAudit\Analysis\Location;
use LaravelAudit\Analysis\Severity;
use Symfony\Component\Process\Process;

abstract class AbstractProcessRunner
{
    /**
     * @param  list<string>  $arguments
     * @param  array<string, string>  $environment
     */
    protected function runProcess(
        string $basePath,
        string $binary,
        array $arguments,
        array $environment = [],
        ?int $timeout = null,
    ): Process {
        $process = new Process(
            array_merge([$this->resolveBinary($basePath, $binary)], $arguments),
            $basePath,
            $environment !== [] ? $environment : null,
        );
        $process->setTimeout($timeout ?? 1800);
        $process->run();

        return $process;
    }

    protected function binaryAvailable(string $basePath, string $binary): bool
    {
        return is_file($this->resolveBinary($basePath, $binary));
    }

    private function resolveBinary(string $basePath, string $binary): string
    {
        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $binary);

        if (str_starts_with($normalized, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:\\\\/', $normalized) === 1) {
            return $normalized;
        }

        $candidate = $basePath.DIRECTORY_SEPARATOR.$normalized;

        if (is_file($candidate)) {
            return $candidate;
        }

        if (PHP_OS_FAMILY === 'Windows' && is_file($candidate.'.bat')) {
            return $candidate.'.bat';
        }

        return $candidate;
    }

    protected function unavailableToolIssue(string $tool, string $binary): Issue
    {
        return new Issue(
            ruleId: 'tooling.'.$tool.'.runner',
            category: Category::Tooling,
            severity: Severity::Error,
            title: ucfirst($tool).' is not available',
            message: ucfirst($tool).' binary was not found at '.$binary.'.',
            location: new Location('composer.json', 1),
            recommendation: 'Install the tool with Composer dev dependencies or disable it in laravel-audit config.',
        );
    }

    protected function timedOutToolIssue(string $tool, int $timeout): Issue
    {
        return new Issue(
            ruleId: 'tooling.'.$tool.'.runner',
            category: Category::Tooling,
            severity: Severity::Error,
            title: ucfirst($tool).' timed out',
            message: ucfirst($tool).' exceeded the configured timeout of '.$timeout.' seconds.',
            location: new Location('composer.json', 1),
            recommendation: 'Increase LARAVEL_AUDIT_TOOL_TIMEOUT or reduce the analyzed paths.',
        );
    }
}
