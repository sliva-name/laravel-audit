<?php

declare(strict_types=1);

namespace LaravelAudit\Pattern;

use LaravelAudit\Analysis\Issue;
use LaravelAudit\Project\ProjectIndex;

final class PatternInferenceEngine
{
    public function __construct(
        private readonly MethodFeatureExtractor $featureExtractor,
        private readonly PatternModel $model,
    ) {}

    /**
     * @param  list<Issue>  $issues
     * @return list<PatternSuggestion>
     */
    public function infer(ProjectIndex $project, array $issues, float $minConfidence, int $limit = 20): array
    {
        $suggestions = [];

        foreach ($project->phpFiles as $file) {
            foreach ($this->featureExtractor->extract($file) as $features) {
                $ruleIds = $this->matchingRuleIds($issues, $features);

                foreach ($this->model->score($features, $ruleIds) as $suggestion) {
                    if ($suggestion->confidence < $minConfidence) {
                        continue;
                    }

                    $suggestions[] = $this->withSignals($suggestion, $ruleIds);
                }
            }
        }

        usort(
            $suggestions,
            fn (PatternSuggestion $left, PatternSuggestion $right): int => $right->confidence <=> $left->confidence,
        );

        return array_slice($suggestions, 0, $limit);
    }

    /**
     * @param  list<Issue>  $issues
     * @return list<string>
     */
    private function matchingRuleIds(array $issues, MethodFeatures $features): array
    {
        $ruleIds = [];

        foreach ($issues as $issue) {
            if ($issue->location->file !== $features->file) {
                continue;
            }

            if (abs($issue->location->line - $features->line) > 25) {
                continue;
            }

            $ruleIds[] = $issue->ruleId;
        }

        return array_values(array_unique($ruleIds));
    }

    /**
     * @param  list<string>  $ruleIds
     */
    private function withSignals(PatternSuggestion $suggestion, array $ruleIds): PatternSuggestion
    {
        if ($ruleIds === []) {
            return $suggestion;
        }

        return new PatternSuggestion(
            pattern: $suggestion->pattern,
            title: $suggestion->title,
            description: $suggestion->description,
            recommendation: $suggestion->recommendation,
            confidence: $suggestion->confidence,
            file: $suggestion->file,
            line: $suggestion->line,
            method: $suggestion->method,
            class: $suggestion->class,
            features: $suggestion->features,
            signals: $ruleIds,
            llmRationale: $suggestion->llmRationale,
        );
    }
}
