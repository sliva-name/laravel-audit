<?php

declare(strict_types=1);

namespace LaravelAudit\Project;

use PhpParser\Node;

final readonly class PhpFile
{
    /**
     * @param  list<Node>  $ast
     * @param  list<string>  $classes
     * @param  list<string>  $methods
     */
    public function __construct(
        public string $path,
        public string $relativePath,
        public string $contents,
        public array $ast,
        public array $classes,
        public array $methods,
        public int $lines,
    ) {}

    public function inDirectory(string $directory): bool
    {
        return str_starts_with(str_replace('\\', '/', $this->relativePath), trim($directory, '/').'/');
    }
}
