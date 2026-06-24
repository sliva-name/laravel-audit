<?php

declare(strict_types=1);

namespace LaravelAudit\Runners;

use LaravelAudit\Analysis\Issue;

final readonly class ToolResult
{
    /**
     * @param  list<Issue>  $issues
     */
    public function __construct(
        public string $tool,
        public bool $available,
        public int $exitCode,
        public array $issues = [],
        public string $output = '',
    ) {}
}
