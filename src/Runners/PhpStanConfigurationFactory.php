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

    public function cacheDirectory(string $basePath): string
    {
        $storageDirectory = $basePath.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'framework'
            .DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR.'laravel-audit-phpstan';

        $directory = $this->ensureWritableDirectory($storageDirectory)
            ?? $this->ensureWritableDirectory(sys_get_temp_dir().DIRECTORY_SEPARATOR.'laravel-audit-phpstan-'.sha1($basePath));

        if ($directory === null) {
            throw new \RuntimeException('Unable to create a writable PHPStan cache directory.');
        }

        $tmpDirectory = $directory.DIRECTORY_SEPARATOR.'tmp';

        if (! is_dir($tmpDirectory)) {
            mkdir($tmpDirectory, 0775, true);
        }

        return $directory;
    }

    /**
     * @param  list<string>  $paths
     */
    public function createAuditConfig(string $basePath, array $paths, int $level, bool $includeLarastan): string
    {
        $existingPaths = $this->existingProjectPaths($basePath, $paths);

        if ($existingPaths === []) {
            throw new \InvalidArgumentException('No analysable paths exist for the generated PHPStan configuration.');
        }

        if ($includeLarastan && $this->larastanExtensionPath($basePath) === null) {
            throw new \InvalidArgumentException('Larastan extension was not found in the project.');
        }

        $cacheDirectory = $this->cacheDirectory($basePath);
        $projectConfig = $this->projectConfigPath($basePath);
        $includes = [];

        if ($projectConfig !== null) {
            $includes[] = $this->neonPath($projectConfig);
        }

        if ($includeLarastan) {
            $includes[] = $this->neonPath((string) $this->larastanExtensionPath($basePath));
        }

        $lines = [];

        if ($includes !== []) {
            $lines[] = 'includes:';

            foreach ($includes as $include) {
                $lines[] = '    - '.$include;
            }

            $lines[] = '';
        }

        $lines[] = 'parameters:';
        $lines[] = '    tmpDir: '.$this->neonPath($cacheDirectory.DIRECTORY_SEPARATOR.'tmp');
        $lines[] = '    resultCachePath: '.$this->neonPath($cacheDirectory.DIRECTORY_SEPARATOR.'resultCache.php');

        if ($projectConfig === null) {
            $lines[] = '    level: '.$level;
            $lines[] = '    paths:';

            foreach ($existingPaths as $path) {
                $lines[] = '        - '.$this->neonPath($basePath.DIRECTORY_SEPARATOR.$path);
            }
        }

        $configPath = $cacheDirectory.DIRECTORY_SEPARATOR.'audit-phpstan.neon';
        file_put_contents($configPath, implode(PHP_EOL, $lines).PHP_EOL);

        return $configPath;
    }

    /**
     * @deprecated Use createAuditConfig() instead.
     *
     * @param  list<string>  $paths
     */
    public function createLarastanConfig(string $basePath, array $paths, int $level): string
    {
        return $this->createAuditConfig($basePath, $paths, $level, true);
    }

    private function ensureWritableDirectory(string $directory): ?string
    {
        if (is_dir($directory)) {
            return is_writable($directory) ? $directory : null;
        }

        if (! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            return null;
        }

        return is_writable($directory) ? $directory : null;
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
