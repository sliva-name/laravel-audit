<?php

declare(strict_types=1);

namespace LaravelAudit\Reporting;

final class SarifReporter
{
    public function render(AuditReport $report): string
    {
        $rules = [];
        $results = [];

        foreach ($report->issues as $issue) {
            $rules[$issue->ruleId] = [
                'id' => $issue->ruleId,
                'name' => $issue->title,
                'shortDescription' => ['text' => $issue->title],
                'help' => ['text' => $issue->recommendation ?? $issue->message],
            ];

            $results[] = [
                'ruleId' => $issue->ruleId,
                'level' => $this->level($issue->severity->value),
                'message' => ['text' => $issue->message],
                'locations' => [[
                    'physicalLocation' => [
                        'artifactLocation' => ['uri' => $issue->location->file],
                        'region' => ['startLine' => $issue->location->line],
                    ],
                ]],
            ];
        }

        return json_encode([
            'version' => '2.1.0',
            '$schema' => 'https://json.schemastore.org/sarif-2.1.0.json',
            'runs' => [[
                'tool' => [
                    'driver' => [
                        'name' => 'Laravel Audit',
                        'informationUri' => 'https://docs.shieldci.com/',
                        'rules' => array_values($rules),
                    ],
                ],
                'results' => $results,
            ]],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    private function level(string $severity): string
    {
        return match ($severity) {
            'critical', 'error' => 'error',
            'warning' => 'warning',
            default => 'note',
        };
    }
}
