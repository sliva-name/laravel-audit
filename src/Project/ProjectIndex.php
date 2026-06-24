<?php

declare(strict_types=1);

namespace LaravelAudit\Project;

final readonly class ProjectIndex
{
    /**
     * @param  list<PhpFile>  $phpFiles
     * @param  list<RouteDefinition>  $routes
     */
    public function __construct(
        public array $phpFiles,
        public array $routes,
    ) {}

    /**
     * @return list<PhpFile>
     */
    public function controllers(): array
    {
        return array_values(array_filter(
            $this->phpFiles,
            fn (PhpFile $file): bool => str_contains($file->relativePath, 'Http/Controllers')
                || str_contains($file->relativePath, 'Http\\Controllers'),
        ));
    }

    /**
     * @return list<PhpFile>
     */
    public function models(): array
    {
        return array_values(array_filter(
            $this->phpFiles,
            fn (PhpFile $file): bool => str_contains($file->relativePath, 'Models/')
                || str_contains($file->relativePath, 'Models\\'),
        ));
    }

    /**
     * @return list<PhpFile>
     */
    public function formRequests(): array
    {
        return array_values(array_filter(
            $this->phpFiles,
            fn (PhpFile $file): bool => str_contains($file->relativePath, 'Http/Requests')
                || str_contains($file->relativePath, 'Http\\Requests'),
        ));
    }

    /**
     * @return list<PhpFile>
     */
    public function jobs(): array
    {
        return array_values(array_filter(
            $this->phpFiles,
            fn (PhpFile $file): bool => str_contains($file->relativePath, 'Jobs/')
                || str_contains($file->relativePath, 'Jobs\\'),
        ));
    }
}
