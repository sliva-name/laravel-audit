<?php

declare(strict_types=1);

namespace LaravelAudit\Project;

final readonly class RouteDefinition
{
    public function __construct(
        public string $method,
        public string $uri,
        public string $action,
        public ?string $name = null,
        public ?string $file = null,
        public ?int $line = null,
    ) {}
}
