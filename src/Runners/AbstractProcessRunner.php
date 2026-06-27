<?php

declare(strict_types=1);

namespace LaravelAudit\Runners;

use Symfony\Component\Process\Process;

abstract class AbstractProcessRunner
{
    /**
     * @param  list<string>  $arguments
     * @param  array<string, string>  $environment
     */
    protected function runProcess(string $basePath, string $binary, array $arguments, array $environment = []): Process
    {
        $process = new Process(
            array_merge([$this->resolveBinary($basePath, $binary)], $arguments),
            $basePath,
            $environment !== [] ? $environment : null,
        );
        $process->setTimeout(300);
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
}
