<?php

declare(strict_types=1);

namespace LaravelAudit\Reporting;

use LaravelAudit\Analysis\Issue;
use LaravelAudit\Analysis\Severity;
use LaravelAudit\Pattern\PatternSuggestion;
use LaravelAudit\Runners\ToolResult;

final readonly class AuditReport
{
    /**
     * @param  list<Issue>  $issues
     * @param  list<ToolResult>  $toolResults
     * @param  list<PatternSuggestion>  $patternSuggestions
     */
    public function __construct(
        public array $issues,
        public array $toolResults,
        public float $durationSeconds,
        public array $patternSuggestions = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'summary' => $this->summary(),
            'durationSeconds' => $this->durationSeconds,
            'tools' => array_map(fn (ToolResult $result): array => [
                'tool' => $result->tool,
                'available' => $result->available,
                'exitCode' => $result->exitCode,
            ], $this->toolResults),
            'issues' => array_map(fn (Issue $issue): array => $issue->toArray(), $this->issues),
            'patternSuggestions' => array_map(
                fn (PatternSuggestion $suggestion): array => $suggestion->toArray(),
                $this->patternSuggestions,
            ),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function summary(): array
    {
        $summary = [
            Severity::Critical->value => 0,
            Severity::Error->value => 0,
            Severity::Warning->value => 0,
            Severity::Info->value => 0,
        ];

        foreach ($this->issues as $issue) {
            $summary[$issue->severity->value]++;
        }

        return $summary;
    }

    public function shouldFail(Severity $threshold): bool
    {
        foreach ($this->issues as $issue) {
            if ($issue->severity->rank() >= $threshold->rank()) {
                return true;
            }
        }

        return false;
    }
}
