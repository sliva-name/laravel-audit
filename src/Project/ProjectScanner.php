<?php

declare(strict_types=1);

namespace LaravelAudit\Project;

use Illuminate\Contracts\Foundation\Application;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;

final class ProjectScanner
{
    public function __construct(
        private readonly Application $app,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public function scan(array $config): ProjectIndex
    {
        $basePath = $this->app->basePath();
        $paths = $config['paths'] ?? ['app', 'routes', 'config', 'database/migrations', 'tests'];
        $exclude = $config['exclude'] ?? ['vendor', 'storage', 'bootstrap/cache', 'node_modules'];

        return new ProjectIndex(
            phpFiles: $this->scanPhpFiles($basePath, $paths, $exclude),
            routes: $this->scanRoutes(),
        );
    }

    /**
     * @param  list<string>  $paths
     * @param  list<string>  $exclude
     * @return list<PhpFile>
     */
    private function scanPhpFiles(string $basePath, array $paths, array $exclude): array
    {
        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $files = [];

        foreach ($paths as $path) {
            $absolutePath = $basePath.DIRECTORY_SEPARATOR.$path;

            if (! is_dir($absolutePath)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($absolutePath));

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $relativePath = $this->relativePath($basePath, $file->getPathname());

                if ($this->isExcluded($relativePath, $exclude)) {
                    continue;
                }

                $contents = (string) file_get_contents($file->getPathname());

                try {
                    $ast = $parser->parse($contents) ?? [];
                } catch (Error) {
                    $ast = [];
                }

                $files[] = new PhpFile(
                    path: $file->getPathname(),
                    relativePath: $relativePath,
                    contents: $contents,
                    ast: $ast,
                    classes: $this->classNames($ast),
                    methods: $this->methodNames($ast),
                    lines: substr_count($contents, PHP_EOL) + 1,
                );
            }
        }

        return $files;
    }

    /**
     * @return list<RouteDefinition>
     */
    private function scanRoutes(): array
    {
        try {
            $routes = [];

            foreach ($this->app['router']->getRoutes() as $route) {
                $action = $route->getActionName();

                $routes[] = new RouteDefinition(
                    method: implode('|', $route->methods()),
                    uri: $route->uri(),
                    action: $action,
                    name: $route->getName(),
                );
            }

            return $routes;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param  list<Node>  $ast
     * @return list<string>
     */
    private function classNames(array $ast): array
    {
        $finder = new NodeFinder;

        return array_values(array_filter(array_map(
            fn (Node\Stmt\Class_ $class): ?string => $class->name?->toString(),
            $finder->findInstanceOf($ast, Node\Stmt\Class_::class),
        )));
    }

    /**
     * @param  list<Node>  $ast
     * @return list<string>
     */
    private function methodNames(array $ast): array
    {
        $finder = new NodeFinder;

        return array_map(
            fn (Node\Stmt\ClassMethod $method): string => $method->name->toString(),
            $finder->findInstanceOf($ast, Node\Stmt\ClassMethod::class),
        );
    }

    /**
     * @param  list<string>  $exclude
     */
    private function isExcluded(string $relativePath, array $exclude): bool
    {
        foreach ($exclude as $excludedPath) {
            if (str_starts_with($relativePath, trim($excludedPath, '/\\').'/')) {
                return true;
            }
        }

        return false;
    }

    private function relativePath(string $basePath, string $path): string
    {
        return str_replace('\\', '/', ltrim(str_replace($basePath, '', $path), DIRECTORY_SEPARATOR));
    }
}
