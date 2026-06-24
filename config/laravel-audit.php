<?php

declare(strict_types=1);

return [
    'paths' => [
        'app',
        'routes',
        'config',
        'database/migrations',
        'tests',
    ],

    'exclude' => [
        'vendor',
        'storage',
        'bootstrap/cache',
        'node_modules',
    ],

    'tools' => [
        'pint' => [
            'enabled' => true,
            'binary' => env('LARAVEL_AUDIT_PINT_BINARY', 'vendor/bin/pint'),
            'arguments' => ['--test'],
        ],
        'phpstan' => [
            'enabled' => true,
            'binary' => env('LARAVEL_AUDIT_PHPSTAN_BINARY', 'vendor/bin/phpstan'),
            'arguments' => ['analyse', '--error-format=json'],
        ],
    ],

    'reporting' => [
        'default_format' => 'console',
        'fail_on' => 'error',
    ],

    'rules' => [
        'security.raw-sql' => true,
        'security.mass-assignment' => true,
        'security.weak-validation' => true,
        'security.debug-configuration' => true,
        'performance.n-plus-one-candidate' => true,
        'performance.sync-heavy-job' => true,
        'reliability.missing-transaction' => true,
        'reliability.env-access-outside-config' => true,
        'best-practices.missing-form-request' => true,
        'best-practices.fat-controller' => true,
        'code-quality.long-method' => true,
        'code-quality.large-class' => true,
        'code-quality.redundant-boolean-return' => true,
        'code-quality.redundant-null-coalesce' => true,
        'code-quality.redundant-empty-foreach-guard' => true,
        'code-quality.redundant-catch-rethrow' => true,
        'code-quality.redundant-else-after-exit' => true,
        'code-quality.redundant-type-guard' => true,
    ],
];
