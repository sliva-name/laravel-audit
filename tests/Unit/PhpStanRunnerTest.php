<?php

declare(strict_types=1);

namespace LaravelAudit\Tests\Unit;

use LaravelAudit\Runners\PhpStanRunner;
use LaravelAudit\Tests\TestCase;

final class PhpStanRunnerTest extends TestCase
{
    public function test_uses_generated_config_when_phpstan_config_is_missing(): void
    {
        $basePath = $this->makeProject();

        mkdir($basePath.'/app', 0777, true);
        mkdir($basePath.'/routes', 0777, true);

        $result = (new PhpStanRunner)->run($basePath, [
            'binary' => 'vendor/bin/phpstan',
            'arguments' => ['analyse', '--error-format=json'],
            'paths' => ['app', 'routes', 'missing'],
        ]);

        $output = json_decode($result->output, true);

        self::assertSame('analyse', $output['argv'][0]);
        self::assertSame('--error-format=json', $output['argv'][1]);
        self::assertSame('--no-progress', $output['argv'][2]);
        self::assertSame('--no-ansi', $output['argv'][3]);
        self::assertSame('--memory-limit=1G', $output['argv'][4]);
        self::assertStringStartsWith('--configuration=', $output['argv'][5]);
        self::assertStringContainsString('audit-phpstan.neon', $output['argv'][5]);
    }

    public function test_wraps_existing_project_config_with_writable_cache_paths(): void
    {
        $basePath = $this->makeProject();

        mkdir($basePath.'/app', 0777, true);
        file_put_contents($basePath.'/phpstan.neon', "parameters:\n");

        $result = (new PhpStanRunner)->run($basePath, [
            'binary' => 'vendor/bin/phpstan',
            'arguments' => ['analyse', '--error-format=json'],
            'paths' => ['app'],
        ]);

        $output = json_decode($result->output, true);

        self::assertStringStartsWith('--configuration=', $output['argv'][5]);
        $configPath = substr($output['argv'][5], strlen('--configuration='));
        $configContents = file_get_contents($configPath);

        self::assertIsString($configContents);
        self::assertStringContainsString($basePath.'/phpstan.neon', str_replace('\\', '/', $configContents));
        self::assertStringContainsString('resultCachePath:', $configContents);
    }

    public function test_uses_generated_larastan_config_when_extension_is_available(): void
    {
        $basePath = $this->makeProject(withLarastan: true);

        mkdir($basePath.'/app', 0777, true);
        mkdir($basePath.'/routes', 0777, true);

        $result = (new PhpStanRunner)->run($basePath, [
            'binary' => 'vendor/bin/phpstan',
            'arguments' => ['analyse', '--error-format=json'],
            'paths' => ['app', 'routes'],
            'level' => 5,
        ]);

        $output = json_decode($result->output, true);

        self::assertCount(6, $output['argv']);
        self::assertStringStartsWith('--configuration=', $output['argv'][5]);
        $configPath = substr($output['argv'][5], strlen('--configuration='));
        $configContents = file_get_contents($configPath);

        self::assertIsString($configContents);
        self::assertStringContainsString('vendor/larastan/larastan/extension.neon', $configContents);
        self::assertStringContainsString('level: 5', $configContents);
    }

    public function test_skips_auto_larastan_when_disabled(): void
    {
        $basePath = $this->makeProject(withLarastan: true);

        mkdir($basePath.'/app', 0777, true);

        $result = (new PhpStanRunner)->run($basePath, [
            'binary' => 'vendor/bin/phpstan',
            'arguments' => ['analyse', '--error-format=json'],
            'paths' => ['app'],
            'auto_larastan' => false,
        ]);

        $output = json_decode($result->output, true);

        self::assertStringStartsWith('--configuration=', $output['argv'][5]);
        $configContents = file_get_contents(substr($output['argv'][5], strlen('--configuration=')));

        self::assertIsString($configContents);
        self::assertStringNotContainsString('larastan/larastan/extension.neon', $configContents);
    }

    public function test_maps_json_file_messages_to_audit_issues(): void
    {
        $basePath = $this->makeProject(<<<'PHP'
            #!/usr/bin/env php
            <?php

            echo json_encode([
                'files' => [
                    'app/Foo.php' => [
                        'messages' => [
                            [
                                'message' => 'Access to undefined property.',
                                'line' => 12,
                                'identifier' => 'property.notFound',
                            ],
                        ],
                    ],
                ],
            ]);
            PHP);

        mkdir($basePath.'/app', 0777, true);

        $result = (new PhpStanRunner)->run($basePath, [
            'binary' => 'vendor/bin/phpstan',
            'arguments' => ['analyse', '--error-format=json'],
            'paths' => ['app'],
            'auto_larastan' => false,
        ]);

        self::assertCount(1, $result->issues);
        self::assertSame('property.notFound', $result->issues[0]->ruleId);
        self::assertSame('app/Foo.php', $result->issues[0]->location->file);
        self::assertSame(12, $result->issues[0]->location->line);
    }

    public function test_treats_clean_json_report_as_success_with_no_issues(): void
    {
        $basePath = $this->makeProject(<<<'PHP'
            #!/usr/bin/env php
            <?php

            echo json_encode([
                'totals' => ['errors' => 0, 'file_errors' => 0],
                'files' => [],
            ]);
            PHP);

        mkdir($basePath.'/app', 0777, true);

        $result = (new PhpStanRunner)->run($basePath, [
            'binary' => 'vendor/bin/phpstan',
            'arguments' => ['analyse', '--error-format=json'],
            'paths' => ['app'],
            'auto_larastan' => false,
        ]);

        self::assertSame([], $result->issues);
    }

    public function test_surfaces_missing_binary_as_tooling_issue(): void
    {
        $basePath = sys_get_temp_dir().'/laravel-audit-phpstan-missing-'.bin2hex(random_bytes(6));
        mkdir($basePath.'/app', 0777, true);

        $result = (new PhpStanRunner)->run($basePath, [
            'binary' => 'vendor/bin/phpstan',
            'arguments' => ['analyse', '--error-format=json'],
            'paths' => ['app'],
            'auto_larastan' => false,
        ]);

        self::assertFalse($result->available);
        self::assertSame('tooling.phpstan.runner', $result->issues[0]->ruleId);
    }

    private function makeProject(?string $binaryContents = null, bool $withLarastan = false): string
    {
        $basePath = sys_get_temp_dir().'/laravel-audit-phpstan-runner-'.bin2hex(random_bytes(6));

        mkdir($basePath.'/vendor/bin', 0777, true);
        mkdir($basePath.'/storage/framework/cache', 0777, true);

        if ($withLarastan) {
            mkdir($basePath.'/vendor/larastan/larastan', 0777, true);
            file_put_contents($basePath.'/vendor/larastan/larastan/extension.neon', "parameters:\n");
        }

        $binary = $basePath.'/vendor/bin/phpstan';
        file_put_contents($binary, $binaryContents ?? <<<'PHP'
            #!/usr/bin/env php
            <?php

            echo json_encode([
                'files' => [],
                'argv' => array_slice($argv, 1),
            ]);
            PHP);
        chmod($binary, 0755);

        return $basePath;
    }
}
