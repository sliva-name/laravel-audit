<?php

declare(strict_types=1);

namespace LaravelAudit\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use LaravelAudit\Tests\TestCase;

final class AnalyzeCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $modelsPath = $this->app->basePath().'/app/Models';

        if (! is_dir($modelsPath)) {
            mkdir($modelsPath, 0777, true);
        }

        file_put_contents($modelsPath.'/AnalyzeCommandFixture.php', <<<'PHP'
            <?php

            namespace App\Models;

            use Illuminate\Database\Eloquent\Model;

            final class AnalyzeCommandFixture extends Model
            {
            }
            PHP);
    }

    public function test_analyze_command_runs_without_external_tools(): void
    {
        $this->artisan('audit:analyze', [
            '--no-tools' => true,
            '--format' => 'json',
            '--fail-on' => 'critical',
        ])->assertExitCode(0);
    }

    public function test_analyze_command_can_limit_output_to_one_category(): void
    {
        $exitCode = Artisan::call('audit:analyze', [
            '--no-tools' => true,
            '--only' => 'security',
            '--format' => 'json',
            '--fail-on' => 'critical',
        ]);

        self::assertSame(0, $exitCode);

        /** @var array<string, mixed> $payload */
        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $ruleIds = array_column($payload['issues'] ?? [], 'ruleId');

        self::assertContains('security.mass-assignment', $ruleIds);
        self::assertTrue(collect($ruleIds)->every(
            fn (string $ruleId): bool => str_starts_with($ruleId, 'security.'),
        ));
    }

    public function test_analyze_command_excludes_other_categories_when_only_is_set(): void
    {
        Artisan::call('audit:analyze', [
            '--no-tools' => true,
            '--only' => 'performance',
            '--format' => 'json',
            '--fail-on' => 'critical',
        ]);

        /** @var array<string, mixed> $payload */
        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $ruleIds = array_column($payload['issues'] ?? [], 'ruleId');

        self::assertNotContains('security.mass-assignment', $ruleIds);
    }
}
