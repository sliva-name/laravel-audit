<?php

declare(strict_types=1);

namespace LaravelAudit\Runners;

final class PhpStanConfigurationFactory
{
    public const MAX_LEVEL = 10;

    public function larastanExtensionPath(string $basePath): ?string
    {
        $path = $basePath.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'larastan'.DIRECTORY_SEPARATOR.'larastan'.DIRECTORY_SEPARATOR.'extension.neon';

        return is_file($path) ? $path : null;
    }

    public function projectConfigPath(string $basePath): ?string
    {
        foreach (['phpstan.neon', 'phpstan.neon.dist'] as $file) {
            $path = $basePath.DIRECTORY_SEPARATOR.$file;

            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $paths
     */
    public function createLarastanConfig(string $basePath, array $paths, int $level): string
    {
        $extension = $this->larastanExtensionPath($basePath);

        if ($extension === null) {
            throw new \InvalidArgumentException('Larastan extension was not found in the project.');
        }

        $existingPaths = $this->existingProjectPaths($basePath, $paths);

        if ($existingPaths === []) {
            throw new \InvalidArgumentException('No analysable paths exist for the generated Larastan configuration.');
        }

        $lines = [
            'includes:',
            '    - '.$this->neonPath($extension),
            '',
            'parameters:',
            '    level: '.$level,
            '    paths:',
        ];

        foreach ($existingPaths as $path) {
            $lines[] = '        - '.$this->neonPath($basePath.DIRECTORY_SEPARATOR.$path);
        }

        $configPath = tempnam(sys_get_temp_dir(), 'laravel-audit-phpstan-').'.neon';
        file_put_contents($configPath, implode(PHP_EOL, $lines).PHP_EOL);

        return $configPath;
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

    private function neonPath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}
