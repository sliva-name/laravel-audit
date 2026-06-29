<?php

declare(strict_types=1);

namespace LaravelAudit\Analyzers\Support;

use LaravelAudit\Project\PhpFile;
use PhpParser\Node;
use PhpParser\NodeFinder;

final class EloquentModelPropertyReader
{
    /**
     * @return array{hasFillable: bool, hasGuarded: bool, hasEmptyGuarded: bool, guardedLine: int|null}
     */
    public function read(PhpFile $file): array
    {
        $result = [
            'hasFillable' => false,
            'hasGuarded' => false,
            'hasEmptyGuarded' => false,
            'guardedLine' => null,
        ];

        $finder = new NodeFinder;

        /** @var list<Node\Stmt\Class_> $classes */
        $classes = $finder->findInstanceOf($file->ast, Node\Stmt\Class_::class);

        foreach ($classes as $class) {
            foreach ($class->getProperties() as $property) {
                $name = $property->props[0]->name->toString();

                if ($name === 'fillable') {
                    $result['hasFillable'] = true;
                }

                if ($name !== 'guarded') {
                    continue;
                }

                $result['hasGuarded'] = true;
                $default = $property->props[0]->default;

                if ($default instanceof Node\Expr\Array_ && $default->items === []) {
                    $result['hasEmptyGuarded'] = true;
                    $result['guardedLine'] = $property->getStartLine();
                }
            }
        }

        return $result;
    }
}
