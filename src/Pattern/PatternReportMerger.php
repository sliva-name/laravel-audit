<?php

declare(strict_types=1);

namespace LaravelAudit\Pattern;

final class PatternReportMerger
{
    /**
     * @param  list<array<string, mixed>>  $existing
     * @param  list<PatternSuggestion>  $confirmed
     * @param  list<string>  $confirmedKeys
     * @return list<array<string, mixed>>
     */
    public static function merge(array $existing, array $confirmed, array $confirmedKeys): array
    {
        $confirmedByKey = [];

        foreach ($confirmed as $suggestion) {
            $confirmedByKey[PatternHypothesisKey::for($suggestion)] = $suggestion;
        }

        $remaining = array_values(array_filter(
            $existing,
            static function (array $item) use ($confirmedByKey): bool {
                if (($item['source'] ?? 'heuristic') !== 'heuristic') {
                    return true;
                }

                return ! isset($confirmedByKey[PatternHypothesisKey::fromArray($item)]);
            },
        ));

        return [
            ...$remaining,
            ...array_map(
                static fn (PatternSuggestion $suggestion): array => $suggestion->toArray(),
                $confirmed,
            ),
        ];
    }
}
