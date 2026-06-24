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
    }
}
