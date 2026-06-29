<?php

declare(strict_types=1);

namespace LaravelAudit\Tests\Unit;

use LaravelAudit\Pattern\MethodFeatures;
use LaravelAudit\Pattern\PatternModel;
use LaravelAudit\Pattern\PatternSuggestion;
use LaravelAudit\Tests\TestCase;

final class PatternModelTest extends TestCase
{
    public function test_scores_guard_clauses_higher_for_deep_nesting(): void
    {
        $model = PatternModel::fromPath(__DIR__.'/../../resources/pattern-model.json');

        $deep = $model->score($this->features(['nesting_depth' => 6.0, 'return_statements' => 2.0]));
        $flat = $model->score($this->features(['nesting_depth' => 1.0, 'return_statements' => 1.0]));

        $deepGuard = $this->confidenceFor($deep, 'guard_clauses');
        $flatGuard = $this->confidenceFor($flat, 'guard_clauses');

        self::assertGreaterThan($flatGuard, $deepGuard);
    }

    public function test_scores_enum_pattern_for_magic_string_comparisons(): void
    {
        $model = PatternModel::fromPath(__DIR__.'/../../resources/pattern-model.json');

        $withStrings = $model->score($this->features([
            'magic_string_comparisons' => 6.0,
            'switch_branches' => 4.0,
        ]));
        $withoutStrings = $model->score($this->features([
            'magic_string_comparisons' => 0.0,
            'switch_branches' => 0.0,
        ]));

        self::assertGreaterThan(
            $this->confidenceFor($withoutStrings, 'enum'),
            $this->confidenceFor($withStrings, 'enum'),
        );
    }

    /**
     * @param  array<string, float>  $values
     */
    private function features(array $values): MethodFeatures
    {
        return new MethodFeatures(
            file: 'app/Example.php',
            line: 10,
            method: 'handle',
            class: 'Example',
            values: $values,
        );
    }

    /**
     * @param  list<PatternSuggestion>  $suggestions
     */
    private function confidenceFor(array $suggestions, string $pattern): float
    {
        foreach ($suggestions as $suggestion) {
            if ($suggestion->pattern === $pattern) {
                return $suggestion->confidence;
            }
        }

        return 0.0;
    }
}
