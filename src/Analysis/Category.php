<?php

declare(strict_types=1);

namespace LaravelAudit\Analysis;

enum Category: string
{
    case Security = 'security';
    case Performance = 'performance';
    case Reliability = 'reliability';
    case BestPractices = 'best-practices';
    case CodeQuality = 'code-quality';
    case Tooling = 'tooling';
}
