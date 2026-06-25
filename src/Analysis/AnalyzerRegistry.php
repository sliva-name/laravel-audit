<?php

declare(strict_types=1);

namespace LaravelAudit\Analysis;

use InvalidArgumentException;

final class AnalyzerRegistry
{
    /** @var array<string, AnalyzerInterface> */
    private array $analyzers = [];

    /**
     * @param  iterable<AnalyzerInterface>  $analyzers
     */
    public function __construct(iterable $analyzers = [])
    {
        foreach ($analyzers as $analyzer) {
            $this->add($analyzer);
        }
    }

    public function add(AnalyzerInterface $analyzer): void
    {
        if (isset($this->analyzers[$analyzer->id()])) {
            throw new InvalidArgumentException("Analyzer [{$analyzer->id()}] is already registered.");
        }

        $this->analyzers[$analyzer->id()] = $analyzer;
    }

    /**
     * @param  list<string>  $categories
     * @return list<AnalyzerInterface>
     */
    public function enabledFor(AnalysisContext $context, array $categories = []): array
    {
        return array_values(array_filter(
            $this->analyzers,
            static fn (AnalyzerInterface $analyzer): bool => $context->ruleEnabled($analyzer->id())
                && ($categories === [] || in_array($analyzer->category()->value, $categories, true)),
        ));
    }

    /**
     * @return list<AnalyzerInterface>
     */
    public function all(): array
    {
        return array_values($this->analyzers);
    }
}
