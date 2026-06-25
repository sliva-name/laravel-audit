<?php

declare(strict_types=1);

namespace LaravelAudit\Models;

use Illuminate\Support\Carbon;

final readonly class AuditReportSnapshot
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>|null  $options
     */
    public function __construct(
        public string $uuid,
        public int $critical_count,
        public int $error_count,
        public int $warning_count,
        public int $info_count,
        public int $issues_count,
        public int $pattern_count,
        public float $duration_seconds,
        public array $payload,
        public ?array $options,
        public ?Carbon $created_at,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $createdAt = $data['created_at'] ?? null;

        return new self(
            uuid: (string) $data['uuid'],
            critical_count: (int) ($data['critical_count'] ?? 0),
            error_count: (int) ($data['error_count'] ?? 0),
            warning_count: (int) ($data['warning_count'] ?? 0),
            info_count: (int) ($data['info_count'] ?? 0),
            issues_count: (int) ($data['issues_count'] ?? 0),
            pattern_count: (int) ($data['pattern_count'] ?? 0),
            duration_seconds: (float) ($data['duration_seconds'] ?? 0),
            payload: is_array($data['payload'] ?? null) ? $data['payload'] : [],
            options: is_array($data['options'] ?? null) ? $data['options'] : null,
            created_at: is_string($createdAt) ? Carbon::parse($createdAt) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'critical_count' => $this->critical_count,
            'error_count' => $this->error_count,
            'warning_count' => $this->warning_count,
            'info_count' => $this->info_count,
            'issues_count' => $this->issues_count,
            'pattern_count' => $this->pattern_count,
            'duration_seconds' => $this->duration_seconds,
            'payload' => $this->payload,
            'options' => $this->options,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
