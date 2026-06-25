<?php

declare(strict_types=1);

namespace LaravelAudit\Reporting;

use Illuminate\Console\Command;
use LaravelAudit\Analysis\Severity;

final class ConsoleReporter
{
    public function render(Command $command, AuditReport $report): void
    {
        $summary = $report->summary();

        $command->info('Laravel Audit completed in '.number_format($report->durationSeconds, 2).'s');
        $command->line(sprintf(
            'Critical: %d | Error: %d | Warning: %d | Info: %d',
            $summary[Severity::Critical->value],
            $summary[Severity::Error->value],
            $summary[Severity::Warning->value],
            $summary[Severity::Info->value],
        ));

        foreach ($report->toolResults as $toolResult) {
            if (! $toolResult->available) {
                $command->warn("Tool {$toolResult->tool} is not available: {$toolResult->output}");
            }
        }

        if ($report->issues === []) {
            $command->info('No issues found.');

            return;
        }

        foreach ($report->issues as $issue) {
            $command->newLine();
            $command->line(strtoupper($issue->severity->value).' ['.$issue->ruleId.'] '.$issue->title);
            $command->line($issue->location->file.':'.$issue->location->line);
            $command->line($issue->message);

            if ($issue->recommendation !== null) {
                $command->comment('Fix: '.$issue->recommendation);
            }
        }

        if ($report->patternSuggestions === []) {
            return;
        }

        $command->newLine();
        $command->info('Pattern suggestions');

        foreach ($report->patternSuggestions as $suggestion) {
            $command->newLine();
            $command->line(sprintf(
                '%s [%s] %s (%.0f%%)',
                strtoupper($suggestion->source),
                $suggestion->pattern,
                $suggestion->title,
                $suggestion->confidence * 100,
            ));
            $command->line("{$suggestion->file}:{$suggestion->line} {$suggestion->class}::{$suggestion->method}()");
            $command->line($suggestion->description);
            $command->comment('Refactor: '.$suggestion->recommendation);

            if ($suggestion->signals !== []) {
                $command->line('Signals: '.implode(', ', $suggestion->signals));
            }

            if ($suggestion->llmRationale !== null) {
                $command->line('LLM: '.$suggestion->llmRationale);
            }
        }
    }
}
