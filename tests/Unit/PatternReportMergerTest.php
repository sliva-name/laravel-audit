<?php

declare(strict_types=1);

namespace LaravelAudit\Tests\Unit;

use LaravelAudit\Pattern\PatternReportMerger;
use LaravelAudit\Pattern\PatternSuggestion;
use PHPUnit\Framework\TestCase;

final class PatternReportMergerTest extends TestCase
{
    public function test_replaces_heuristic_only_when_a_reviewed_result_is_provided(): void
    {
        $reviewedKey = 'action:app/Http/Controllers/UserController.php::store';
        $pendingKey = 'repository:app/Services/UserService.php::create';

        $merged = PatternReportMerger::merge(
            $this->existingSuggestions($reviewedKey, $pendingKey),
            [
                $this->confirmedSuggestion($reviewedKey),
            ],
            [$reviewedKey, $pendingKey],
        );

        self::assertCount(2, $merged);
        self::assertSame('heuristic', $merged[0]['source'] ?? null);
        self::assertSame('repository', $merged[0]['pattern'] ?? null);
        self::assertSame('confirmed', $merged[1]['source'] ?? null);
        self::assertSame('action', $merged[1]['pattern'] ?? null);
    }

    public function test_leaves_unselected_heuristics_unchanged(): void
    {
        $selectedKey = 'action:app/Http/Controllers/UserController.php::store';
        $otherKey = 'repository:app/Services/UserService.php::create';

        $merged = PatternReportMerger::merge(
            $this->existingSuggestions($selectedKey, $otherKey),
            [
                $this->confirmedSuggestion($selectedKey),
            ],
            [$selectedKey],
        );

        self::assertCount(2, $merged);
        self::assertSame('heuristic', $merged[0]['source'] ?? null);
        self::assertSame($otherKey, $merged[0]['hypothesisKey'] ?? null);
        self::assertSame('confirmed', $merged[1]['source'] ?? null);
    }

    public function test_preserves_previously_reviewed_suggestions(): void
    {
        $merged = PatternReportMerger::merge(
            [
                [
                    'hypothesisKey' => 'action:app/Http/Controllers/UserController.php::store',
                    'pattern' => 'action',
                    'source' => 'confirmed',
                    'title' => 'Already reviewed',
                ],
            ],
            [],
            [],
        );

        self::assertCount(1, $merged);
        self::assertSame('confirmed', $merged[0]['source'] ?? null);
    }

    public function test_replaces_heuristic_with_refuted_review_result(): void
    {
        $key = 'repository:database/migrations/2024_01_01_000000_update_tags.php::up';

        $merged = PatternReportMerger::merge(
            [
                [
                    'hypothesisKey' => $key,
                    'pattern' => 'repository',
                    'source' => 'heuristic',
                    'title' => 'Repository',
                ],
            ],
            [
                new PatternSuggestion(
                    pattern: 'repository',
                    title: 'Repository',
                    description: 'This is migration logic, not a repository.',
                    recommendation: 'Keep the logic in the migration.',
                    confidence: 0.68,
                    file: 'database/migrations/2024_01_01_000000_update_tags.php',
                    line: 10,
                    method: 'up',
                    class: 'anonymous',
                    features: [],
                    llmRationale: 'Uses Schema::table and DB::table.',
                    source: 'refuted',
                    hypothesisKey: $key,
                ),
            ],
            [$key],
        );

        self::assertCount(1, $merged);
        self::assertSame('refuted', $merged[0]['source'] ?? null);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function existingSuggestions(string $firstKey, string $secondKey): array
    {
        return [
            [
                'hypothesisKey' => $firstKey,
                'pattern' => 'action',
                'source' => 'heuristic',
                'title' => 'Action / Use Case',
            ],
            [
                'hypothesisKey' => $secondKey,
                'pattern' => 'repository',
                'source' => 'heuristic',
                'title' => 'Repository',
            ],
        ];
    }

    private function confirmedSuggestion(string $key): PatternSuggestion
    {
        return new PatternSuggestion(
            pattern: 'action',
            title: 'Action / Use Case',
            description: 'Confirmed by LLM.',
            recommendation: 'Extract action class.',
            confidence: 0.91,
            file: 'app/Http/Controllers/UserController.php',
            line: 12,
            method: 'store',
            class: 'App\\Http\\Controllers\\UserController',
            features: [],
            llmRationale: 'Controller orchestrates validation and persistence.',
            source: 'confirmed',
            hypothesisKey: $key,
        );
    }
}
