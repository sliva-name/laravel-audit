<?php

declare(strict_types=1);

namespace LaravelAudit\Tests\Unit\BestPractices;

use LaravelAudit\Analyzers\BestPractices\MissingFormRequestAnalyzer;
use LaravelAudit\Tests\Support\AnalyzesPhpFixtures;
use LaravelAudit\Tests\TestCase;

final class MissingFormRequestAnalyzerTest extends TestCase
{
    use AnalyzesPhpFixtures;

    public function test_flags_inline_request_validation(): void
    {
        $issues = (new MissingFormRequestAnalyzer)->analyze($this->analysisContext(<<<'PHP'
            <?php

            namespace App\Http\Controllers;

            use Illuminate\Http\Request;

            final class PasswordController
            {
                public function update(Request $request): void
                {
                    $request->validate(['password' => 'required']);
                }
            }
            PHP, 'app/Http/Controllers/PasswordController.php'));

        self::assertIssueRule('best-practices.missing-form-request', $issues);
    }

    public function test_does_not_flag_typed_form_request_parameter(): void
    {
        $issues = (new MissingFormRequestAnalyzer)->analyze($this->analysisContext(<<<'PHP'
            <?php

            namespace App\Http\Controllers\Admin;

            use App\Http\Requests\StoreProductoRequest;

            final class ProductoController
            {
                public function update(StoreProductoRequest $request): void
                {
                    $request->validated();
                }
            }
            PHP, 'app/Http/Controllers/Admin/ProductoController.php'));

        self::assertNoIssues($issues);
    }

    public function test_does_not_flag_auth_guard_validate(): void
    {
        $issues = (new MissingFormRequestAnalyzer)->analyze($this->analysisContext(<<<'PHP'
            <?php

            namespace App\Http\Controllers\Auth;

            use Illuminate\Http\Request;
            use Illuminate\Support\Facades\Auth;

            final class ConfirmablePasswordController
            {
                public function store(Request $request): void
                {
                    Auth::guard('web')->validate([
                        'email' => $request->user()->email,
                        'password' => $request->password,
                    ]);
                }
            }
            PHP, 'app/Http/Controllers/Auth/ConfirmablePasswordController.php'));

        self::assertNoIssues($issues);
    }
}
