<?php

declare(strict_types=1);

namespace LaravelAudit\Tests\Unit\Security;

use LaravelAudit\Analyzers\Security\UnvalidatedMassCreateAnalyzer;
use LaravelAudit\Tests\Support\AnalyzesPhpFixtures;
use LaravelAudit\Tests\TestCase;

final class UnvalidatedMassCreateAnalyzerTest extends TestCase
{
    use AnalyzesPhpFixtures;

    public function test_flags_create_with_request_all(): void
    {
        $issues = (new UnvalidatedMassCreateAnalyzer)->analyze($this->analysisContext(<<<'PHP'
            <?php

            namespace App\Http\Controllers;

            use App\Models\User;
            use Illuminate\Http\Request;

            final class UserController
            {
                public function store(Request $request): void
                {
                    User::create($request->all());
                }
            }
            PHP, 'app/Http/Controllers/UserController.php'));

        self::assertIssueRule('security.unvalidated-mass-create', $issues);
    }

    public function test_does_not_flag_create_with_validated_input(): void
    {
        $issues = (new UnvalidatedMassCreateAnalyzer)->analyze($this->analysisContext(<<<'PHP'
            <?php

            namespace App\Http\Controllers;

            use App\Models\User;
            use Illuminate\Http\Request;

            final class UserController
            {
                public function store(Request $request): User
                {
                    return User::create($request->validated());
                }
            }
            PHP, 'app/Http/Controllers/UserController.php'));

        self::assertNoIssues($issues);
    }

    public function test_does_not_flag_create_after_inline_validation(): void
    {
        $issues = (new UnvalidatedMassCreateAnalyzer)->analyze($this->analysisContext(<<<'PHP'
            <?php

            namespace App\Http\Controllers;

            use App\Models\User;
            use Illuminate\Http\Request;

            final class UserController
            {
                public function store(Request $request): User
                {
                    $data = $request->validate(['name' => 'required']);

                    return User::create($data);
                }
            }
            PHP, 'app/Http/Controllers/UserController.php'));

        self::assertNoIssues($issues);
    }
}
