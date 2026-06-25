<?php

declare(strict_types=1);

namespace LaravelAudit\Pattern;

final class PatternAdvisorFactory
{
    public function __construct(
        private readonly HeuristicPatternAdvisor $heuristic,
        private readonly LlmPatternAdvisor $llm,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public function make(array $config, bool $useHeuristic, bool $useLlm): PatternAdvisorInterface
    {
        if ($useHeuristic && $useLlm) {
            return new CompositePatternAdvisor(
                heuristic: $this->heuristic,
                llm: $this->llm,
                includeHeuristic: true,
                includeLlm: true,
            );
        }

        if ($useLlm) {
            return $this->llm;
        }

        return $this->heuristic;
    }
}
