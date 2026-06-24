<?php

declare(strict_types=1);

namespace LaravelAudit\Analysis;

use LaravelAudit\Project\ProjectIndex;

final readonly class AnalysisContext
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        public string $basePath,
        public ProjectIndex $project,
        public array $config,
    ) {}

    public function ruleEnabled(string $ruleId): bool
    {
        $rules = $this->config['rules'] ?? [];

        if (array_key_exists($ruleId, $rules)) {
            return (bool) $rules[$ruleId];
        }

        return (bool) data_get($this->config, "rules.{$ruleId}", true);
    }
}
