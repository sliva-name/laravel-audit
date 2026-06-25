<?php

declare(strict_types=1);

namespace LaravelAudit\Pattern;

final readonly class MethodReviewCandidate
{
    /**
     * @param  list<string>  $staticFindings
     */
    public function __construct(
        public string $file,
        public int $line,
        public string $class,
        public string $method,
        public string $snippet,
        public array $staticFindings,
        public float $reviewScore,
    ) {}
}
