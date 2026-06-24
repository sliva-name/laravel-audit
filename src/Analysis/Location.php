<?php

declare(strict_types=1);

namespace LaravelAudit\Analysis;

final readonly class Location
{
    public function __construct(
        public string $file,
        public int $line = 1,
        public ?int $column = null,
    ) {}

    /**
     * @return array{file: string, line: int, column?: int}
     */
    public function toArray(): array
    {
        $data = [
            'file' => $this->file,
            'line' => $this->line,
        ];

        if ($this->column !== null) {
            $data['column'] = $this->column;
        }

        return $data;
    }
}
