<?php

declare(strict_types=1);

namespace LaravelAudit\Tests\Unit\BestPractices;

use LaravelAudit\Analyzers\BestPractices\SharedFormRequestAnalyzer;
use LaravelAudit\Tests\Support\AnalyzesPhpFixtures;
use LaravelAudit\Tests\TestCase;

final class SharedFormRequestAnalyzerTest extends TestCase
{
    use AnalyzesPhpFixtures;

    public function test_flags_same_form_request_on_store_and_update(): void
    {
        $issues = (new SharedFormRequestAnalyzer)->analyze($this->analysisContext(<<<'PHP'
            <?php

            namespace App\Http\Controllers\Admin;

            use App\Http\Requests\StoreProductoRequest;
            use App\Models\Producto;

            final class ProductoController
            {
                public function store(StoreProductoRequest $request): void
                {
                }

                public function update(StoreProductoRequest $request, Producto $producto): void
                {
                }
            }
            PHP, 'app/Http/Controllers/Admin/ProductoController.php'));

        self::assertIssueRule('best-practices.shared-form-request', $issues);
        self::assertCount(2, $issues);
        self::assertStringContainsString('store()', $issues[0]->message);
        self::assertStringContainsString('update()', $issues[0]->message);
        self::assertStringContainsString('StoreProductoRequest', $issues[0]->message);
    }

    public function test_does_not_flag_separate_form_requests_per_action(): void
    {
        $issues = (new SharedFormRequestAnalyzer)->analyze($this->analysisContext(<<<'PHP'
            <?php

            namespace App\Http\Controllers\Admin;

            use App\Http\Requests\StoreProductoRequest;
            use App\Http\Requests\UpdateProductoRequest;
            use App\Models\Producto;

            final class ProductoController
            {
                public function store(StoreProductoRequest $request): void
                {
                }

                public function update(UpdateProductoRequest $request, Producto $producto): void
                {
                }
            }
            PHP, 'app/Http/Controllers/Admin/ProductoController.php'));

        self::assertNoIssues($issues);
    }

    public function test_does_not_flag_illuminate_http_request(): void
    {
        $issues = (new SharedFormRequestAnalyzer)->analyze($this->analysisContext(<<<'PHP'
            <?php

            namespace App\Http\Controllers;

            use Illuminate\Http\Request;

            final class CarritoController
            {
                public function store(Request $request): void
                {
                }

                public function update(Request $request, string $key): void
                {
                }
            }
            PHP, 'app/Http/Controllers/CarritoController.php'));

        self::assertNoIssues($issues);
    }

    public function test_does_not_flag_single_method_usage(): void
    {
        $issues = (new SharedFormRequestAnalyzer)->analyze($this->analysisContext(<<<'PHP'
            <?php

            namespace App\Http\Controllers;

            use App\Http\Requests\CheckoutRequest;

            final class CheckoutController
            {
                public function store(CheckoutRequest $request): void
                {
                }
            }
            PHP, 'app/Http/Controllers/CheckoutController.php'));

        self::assertNoIssues($issues);
    }

    public function test_does_not_flag_same_form_request_across_different_controllers(): void
    {
        $issues = (new SharedFormRequestAnalyzer)->analyze($this->analysisContext(<<<'PHP'
            <?php

            namespace App\Http\Controllers\Admin;

            use App\Http\Requests\StoreProductoRequest;

            final class ProductoController
            {
                public function store(StoreProductoRequest $request): void
                {
                }
            }
            PHP, 'app/Http/Controllers/Admin/ProductoController.php'));

        $issues = [
            ...$issues,
            ...(new SharedFormRequestAnalyzer)->analyze($this->analysisContext(<<<'PHP'
                <?php

                namespace App\Http\Controllers\Api;

                use App\Http\Requests\StoreProductoRequest;

                final class ProductoController
                {
                    public function store(StoreProductoRequest $request): void
                    {
                    }
                }
                PHP, 'app/Http/Controllers/Api/ProductoController.php')),
        ];

        self::assertNoIssues($issues);
    }
}
