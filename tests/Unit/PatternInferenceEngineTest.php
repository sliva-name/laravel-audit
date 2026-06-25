<?php

declare(strict_types=1);

namespace LaravelAudit\Tests\Unit;

use LaravelAudit\Analysis\Category;
use LaravelAudit\Analysis\Issue;
use LaravelAudit\Analysis\Location;
use LaravelAudit\Analysis\Severity;
use LaravelAudit\Pattern\MethodFeatureExtractor;
use LaravelAudit\Pattern\PatternInferenceEngine;
use LaravelAudit\Pattern\PatternModel;
use LaravelAudit\Project\PhpFile;
use LaravelAudit\Project\ProjectIndex;
use LaravelAudit\Tests\TestCase;
use PhpParser\ParserFactory;

final class PatternInferenceEngineTest extends TestCase
{
    public function test_suggests_strategy_for_switch_dispatch(): void
    {
        $project = new ProjectIndex([
            $this->phpFile(<<<'PHP'
                <?php

                final class PaymentProcessor
                {
                    public function handle(string $type): void
                    {
                        switch ($type) {
                            case 'card':
                                $this->chargeCard();
                                break;
                            case 'paypal':
                                $this->chargePaypal();
                                break;
                            case 'bank':
                                $this->chargeBank();
                                break;
                            case 'wallet':
                                $this->chargeWallet();
                                break;
                            case 'crypto':
                                $this->chargeCrypto();
                                break;
                        }
                    }
                }
                PHP),
        ], []);

        $engine = new PatternInferenceEngine(
            new MethodFeatureExtractor,
            PatternModel::fromPath(__DIR__.'/../../resources/pattern-model.json'),
        );

        $suggestions = $engine->infer($project, [], 0.55, 5);

        self::assertNotSame([], $suggestions);
        self::assertSame('strategy', $suggestions[0]->pattern);
    }

    public function test_boosts_action_when_fat_controller_finding_exists(): void
    {
        $project = new ProjectIndex([
            $this->phpFile(<<<'PHP'
                <?php

                namespace App\Http\Controllers;

                final class OrderController
                {
                    public function store(): void
                    {
                        DB::table('orders')->insert(['total' => 100]);
                        DB::table('order_items')->insert(['sku' => 'abc']);
                        DB::table('inventory')->where('sku', 'abc')->decrement('qty');
                        DB::table('audit_logs')->insert(['event' => 'order.created']);
                        DB::table('customers')->where('id', 1)->update(['last_order_at' => now()]);
                        return;
                    }
                }
                PHP, 'app/Http/Controllers/OrderController.php'),
        ], []);

        $issues = [
            new Issue(
                ruleId: 'best-practices.fat-controller',
                category: Category::BestPractices,
                severity: Severity::Warning,
                title: 'Controller is large',
                message: 'Large controller',
                location: new Location('app/Http/Controllers/OrderController.php', 1),
            ),
        ];

        $engine = new PatternInferenceEngine(
            new MethodFeatureExtractor,
            PatternModel::fromPath(__DIR__.'/../../resources/pattern-model.json'),
        );

        $suggestions = $engine->infer($project, $issues, 0.50, 5);
        $patterns = array_map(fn ($suggestion) => $suggestion->pattern, $suggestions);

        self::assertContains('action', $patterns);
        self::assertContains('best-practices.fat-controller', $suggestions[array_search('action', $patterns, true)]->signals);
    }

    public function test_suggests_dependency_injection_for_service_locator_usage(): void
    {
        $project = new ProjectIndex([
            $this->phpFile(<<<'PHP'
                <?php

                final class Example
                {
                    public function handle(): void
                    {
                        $billing = app(BillingService::class);
                        $reports = resolve(ReportService::class);
                        $sync = new SyncService();
                        $billing->charge();
                        $reports->render();
                        $sync->run();
                    }
                }
                PHP),
        ], []);

        $engine = new PatternInferenceEngine(
            new MethodFeatureExtractor,
            PatternModel::fromPath(__DIR__.'/../../resources/pattern-model.json'),
        );

        $suggestions = $engine->infer($project, [], 0.55, 5);

        self::assertContains('dependency_injection', array_map(
            fn ($suggestion) => $suggestion->pattern,
            $suggestions,
        ));
    }

    private function phpFile(string $contents, string $relativePath = 'app/Example.php'): PhpFile
    {
        $ast = (new ParserFactory)->createForNewestSupportedVersion()->parse($contents) ?? [];

        return new PhpFile(
            path: __DIR__.'/Fixture.php',
            relativePath: $relativePath,
            contents: $contents,
            ast: $ast,
            classes: [],
            methods: [],
            lines: substr_count($contents, PHP_EOL) + 1,
        );
    }
}
