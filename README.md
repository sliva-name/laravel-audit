# Laravel Audit

Extensible Laravel audit package inspired by Laravel-focused tools such as ShieldCI. It combines Laravel-specific analyzers with optional Pint and PHPStan/Larastan runners.

## Installation

```bash
composer require laravel-audit/package --dev
```

Publish the configuration when you need to tune paths, tools, or rules:

```bash
php artisan vendor:publish --tag=laravel-audit-config
```

## Usage

```bash
php artisan audit:analyze
php artisan audit:analyze --format=json
php artisan audit:analyze --format=sarif --fail-on=warning
php artisan audit:analyze --only=security,performance
php artisan audit:analyze --no-tools
```

The command returns a non-zero exit code when an issue meets the configured `fail_on` threshold.

When Larastan is installed in the target project and no `phpstan.neon` / `phpstan.neon.dist` exists, the PHPStan runner automatically generates a temporary Larastan configuration using `laravel-audit.paths` and `tools.phpstan.level`. Disable this with `tools.phpstan.auto_larastan => false` or `LARAVEL_AUDIT_PHPSTAN_AUTO_LARASTAN=false`.

## Built-In Categories

- `security`: raw SQL, mass assignment, weak validation, debug defaults.
- `performance`: N+1 candidates and synchronous heavy jobs.
- `reliability`: missing transaction candidates and `env()` outside config.
- `best-practices`: inline validation and large controllers.
- `code-quality`: long methods, large classes, redundant guards, boolean returns, null coalesce fallbacks, empty foreach guards, catch/rethrow blocks, and else-after-exit nesting.
- `tooling`: Pint and PHPStan/Larastan findings.

## Adding Analyzers

Implement `LaravelAudit\Analysis\AnalyzerInterface`, return normalized `Issue` objects, and register the analyzer in `AuditServiceProvider` or a consuming app service provider.

Analyzers should report evidence and recommendations. If a rule cannot prove a defect statically, phrase the issue as a candidate or risk.
