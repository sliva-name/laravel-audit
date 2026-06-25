<?php

declare(strict_types=1);

namespace LaravelAudit\Pattern;

final class PatternModel
{
    /**
     * @param  array<string, mixed>  $definition
     */
    public function __construct(
        private readonly array $definition,
    ) {}

    public static function fromPath(string $path): self
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new \RuntimeException("Unable to load pattern model from {$path}.");
        }

        $definition = json_decode($contents, true);

        if (! is_array($definition)) {
            throw new \RuntimeException("Pattern model at {$path} is not valid JSON.");
        }

        return new self($definition);
    }

    /**
     * @param  list<string>  $ruleIds
     * @return list<PatternSuggestion>
     */
    public function score(MethodFeatures $features, array $ruleIds = []): array
    {
        $patterns = $this->definition['patterns'] ?? [];

        if (! is_array($patterns)) {
            return [];
        }

        $suggestions = [];

        foreach ($patterns as $patternId => $pattern) {
            if (! is_string($patternId) || ! is_array($pattern)) {
                continue;
            }

            $confidence = $this->confidence($features, $patternId, $pattern, $ruleIds);

            if ($confidence <= 0.0) {
                continue;
            }

            $suggestions[] = new PatternSuggestion(
                pattern: $patternId,
                title: (string) ($pattern['title'] ?? $patternId),
                description: (string) ($pattern['description'] ?? ''),
                recommendation: (string) ($pattern['recommendation'] ?? ''),
                confidence: $confidence,
                file: $features->file,
                line: $features->line,
                method: $features->method,
                class: $features->class,
                features: $features->values,
            );
        }

        usort(
            $suggestions,
            fn (PatternSuggestion $left, PatternSuggestion $right): int => $right->confidence <=> $left->confidence,
        );

        return $suggestions;
    }

    /**
     * @param  array<string, mixed>  $pattern
     * @param  list<string>  $ruleIds
     */
    private function confidence(MethodFeatures $features, string $patternId, array $pattern, array $ruleIds): float
    {
        $score = (float) ($pattern['bias'] ?? 0.0);
        $weights = $pattern['weights'] ?? [];

        if (is_array($weights)) {
            foreach ($weights as $feature => $weight) {
                if (! is_string($feature)) {
                    continue;
                }

                $max = (float) data_get($this->definition, "feature_scaling.{$feature}.max", 1.0);
                $score += (float) $weight * $features->scaled($feature, $max);
            }
        }

        foreach ($ruleIds as $ruleId) {
            $boost = data_get($this->definition, "finding_boosts.{$ruleId}.{$patternId}");

            if (is_numeric($boost)) {
                $score += (float) $boost;
            }
        }

        return $this->sigmoid($score);
    }

    private function sigmoid(float $score): float
    {
        return 1.0 / (1.0 + exp(-$score));
    }
}
