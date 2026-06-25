<?php

declare(strict_types=1);

namespace LaravelAudit\Audit;

use LaravelAudit\Analysis\Severity;

final readonly class AuditOptions
{
    /**
     * @param  list<string>  $categories
     */
    public function __construct(
        public array $categories = [],
        public bool $noTools = false,
        public bool $patterns = false,
        public bool $llm = false,
        public Severity $failOn = Severity::Error,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'categories' => $this->categories,
            'no_tools' => $this->noTools,
            'patterns' => $this->patterns,
            'llm' => $this->llm,
            'fail_on' => $this->failOn->value,
        ];
    }
}
