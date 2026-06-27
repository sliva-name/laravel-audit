<?php

declare(strict_types=1);

namespace LaravelAudit\Tests\Unit;

use LaravelAudit\Runners\PhpStanConfigurationFactory;
use LaravelAudit\Tests\TestCase;

final class PhpStanConfigurationFactoryTest extends TestCase
{
    public function test_detects_larastan_extension_in_project(): void
    {
        $basePath = $this->makeProject(withLarastan: true);

        $factory = new PhpStanConfigurationFactory;

        self::assertNotNull($factory->larastanExtensionPath($basePath));
        self::assertNull($factory->projectConfigPath($basePath));
    }

    public function test_creates_audit_config_with_absolute_paths(): void
    {
        $basePath = $this->makeProject(withLarastan: true);
        mkdir($basePath.'/app', 0777, true);
        mkdir($basePath.'/routes', 0777, true);
        mkdir($basePath.'/storage/framework/cache', 0777, true);

        $factory = new PhpStanConfigurationFactory;
        $configPath = $factory->createAuditConfig($basePath, ['app', 'routes', 'missing'], 5, true);

        try {
            $contents = file_get_contents($configPath);

            self::assertIsString($contents);
            self::assertStringContainsString('vendor/larastan/larastan/extension.neon', $contents);
            self::assertStringContainsString('level: 5', $contents);
            self::assertStringContainsString('tmpDir:', $contents);
            self::assertStringContainsString('resultCachePath:', $contents);
            self::assertStringContainsString('resultCache.php', $contents);
            self::assertStringContainsString('audit-phpstan.neon', $configPath);
            self::assertStringContainsString($basePath.'/app', str_replace('\\', '/', $contents));
            self::assertStringContainsString($basePath.'/routes', str_replace('\\', '/', $contents));
            self::assertStringNotContainsString('missing', $contents);
        } finally {
            @unlink($configPath);
        }
    }

    private function makeProject(bool $withLarastan = false): string
    {
        $basePath = sys_get_temp_dir().'/laravel-audit-phpstan-config-'.bin2hex(random_bytes(6));

        mkdir($basePath, 0777, true);

        if ($withLarastan) {
            mkdir($basePath.'/vendor/larastan/larastan', 0777, true);
            file_put_contents($basePath.'/vendor/larastan/larastan/extension.neon', "parameters:\n");
        }

        return $basePath;
    }
}
