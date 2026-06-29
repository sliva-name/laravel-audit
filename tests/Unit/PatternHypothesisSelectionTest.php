<?php

declare(strict_types=1);

namespace LaravelAudit\Tests\Unit;

use LaravelAudit\Pattern\PatternHypothesisKey;
use LaravelAudit\Pattern\PatternSuggestion;
use PHPUnit\Framework\TestCase;

final class PatternHypothesisSelectionTest extends TestCase
{
    public function test_hypothesis_key_uses_pattern_file_and_method(): void
    {
        $suggestion = new PatternSuggestion(
            pattern: 'action',
            title: 'Action / Use Case',
            description: 'Move orchestration into an action.',
            recommendation: 'Extract an invokable action.',
            confidence: 0.72,
            file: 'app/Http/Controllers/UserController.php',
            line: 12,
            method: 'store',
            class: 'App\\Http\\Controllers\\UserController',
            features: [],
        );

        self::assertSame(
            'action:app/Http/Controllers/UserController.php::store',
            PatternHypothesisKey::for($suggestion),
        );
    }
}
