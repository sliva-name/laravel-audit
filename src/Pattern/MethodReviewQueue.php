<?php

declare(strict_types=1);

namespace LaravelAudit\Pattern;

use LaravelAudit\Analysis\Issue;
use LaravelAudit\Project\ProjectIndex;

final class MethodReviewQueue
{
    public function __construct(
        private readonly MethodFeatureExtractor $featureExtractor,
        private readonly MethodSnippetExtractor $snippetExtractor,
    ) {}

    /**
     * @param  list<Issue>  $issues
     * @return list<MethodReviewCandidate>
     */
    public function candidates(ProjectIndex $project, array $issues, int $limit): array
    {
        $candidates = [];
        $seen = [];

        foreach ($project->phpFiles as $file) {
            foreach ($this->featureExtractor->extract($file) as $features) {
                $key = $features->file.'::'.$features->method;

                if (isset($seen[$key])) {
                    continue;
                }

                $findings = $this->staticFindings($issues, $features);
                $score = $this->reviewScore($features, $findings);

                if ($score <= 0.0) {
                    continue;
                }

                $snippet = $this->snippetExtractor->extract($file, $features->method, $features->line);

                if ($snippet === null || trim($snippet) === '') {
                    continue;
                }

                $seen[$key] = true;
                $candidates[] = new MethodReviewCandidate(
                    file: $features->file,
                    line: $features->line,
                    class: $features->class,
                    method: $features->method,
                    snippet: $snippet,
                    staticFindings: $findings,
                    reviewScore: $score,
                );
            }
        }

        usort(
            $candidates,
            fn (MethodReviewCandidate $left, MethodReviewCandidate $right): int => $right->reviewScore <=> $left->reviewScore,
        );

        return array_slice($candidates, 0, $limit);
    }

    /**
     * @param  list<Issue>  $issues
     * @return list<string>
     */
    private function staticFindings(array $issues, MethodFeatures $features): array
    {
        $findings = [];

        foreach ($issues as $issue) {
            if ($issue->location->file !== $features->file) {
                continue;
            }

            if (abs($issue->location->line - $features->line) > 25) {
                continue;
            }

            $findings[] = $issue->ruleId.': '.$issue->title;
        }

        return array_values(array_unique($findings));
    }

    /**
     * @param  list<string>  $findings
     */
    private function reviewScore(MethodFeatures $features, array $findings): float
    {
        $score = 0.0;
        $values = $features->values;

        $score += min(($values['lines'] ?? 0.0) / 40.0, 1.0) * 0.35;
        $score += min(($values['nesting_depth'] ?? 0.0) / 5.0, 1.0) * 0.25;
        $score += min(($values['switch_branches'] ?? 0.0) / 4.0, 1.0) * 0.20;
        $score += min(count($findings) / 3.0, 1.0) * 0.20;

        if ($score < 0.25 && $findings === []) {
            return 0.0;
        }

        return $score;
    }
}
