<?php

declare(strict_types=1);

namespace LaravelAudit\Pattern;

final readonly class MethodFeatures
{
    /**
     * @param  array<string, float>  $values
     */
    public function __construct(
        public string $file,
        public int $line,
        public string $method,
        public string $class,
        public array $values,
    ) {}

    public function scaled(string $feature, float $max): float
    {
        $value = $this->values[$feature] ?? 0.0;

        if ($max <= 0.0) {
            return 0.0;
        }

        return min($value / $max, 1.0);
    }
}
