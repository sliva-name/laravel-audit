<?php

declare(strict_types=1);

namespace LaravelAudit\Analysis;

final readonly class Issue
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $ruleId,
        public Category $category,
        public Severity $severity,
        public string $title,
        public string $message,
        public Location $location,
        public ?string $recommendation = null,
        public array $metadata = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ruleId' => $this->ruleId,
            'category' => $this->category->value,
            'severity' => $this->severity->value,
            'title' => $this->title,
            'message' => $this->message,
            'location' => $this->location->toArray(),
            'recommendation' => $this->recommendation,
            'metadata' => $this->metadata,
        ];
    }
}
